<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Analytics;
use App\Models\ChatMessage;
use App\Models\ContactRequest;
use App\Models\Page;
use App\Models\Post;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Maintenance\CacheCleanupService;
use App\Services\Maintenance\DatabaseBackupService;
use App\Services\Maintenance\MaintenanceLogService;
use Throwable;

final class DatabaseMaintenanceService
{
    public const CONFIRMATION_PHRASE = 'СБРОСИТЬ FIREBALL';

    private const SAFE_ACTIONS = [
        'clear_cache',
        'clear_temp_files',
        'clear_analytics',
        'clear_logs',
    ];

    private const DANGEROUS_ACTIONS = [
        'recreate_system_seeds',
        'recreate_roles',
        'delete_demo_content',
        'full_reset',
    ];

    private CacheCleanupService $cacheCleanup;
    private DatabaseBackupService $backupService;
    private MaintenanceLogService $logService;

    public function __construct()
    {
        // Service extraction keeps this class as the legacy facade for controllers and updater code.
        $this->cacheCleanup = new CacheCleanupService();
        $this->backupService = new DatabaseBackupService();
        $this->logService = new MaintenanceLogService();
    }

    public function actions(): array
    {
        return [
            'safe' => self::SAFE_ACTIONS,
            'dangerous' => self::DANGEROUS_ACTIONS,
        ];
    }

    public function isSafeAction(string $action): bool
    {
        return in_array($action, self::SAFE_ACTIONS, true);
    }

    public function isDangerousAction(string $action): bool
    {
        return in_array($action, self::DANGEROUS_ACTIONS, true);
    }

    public function run(string $action, array $user, string $ip): array
    {
        $this->ensureLogTable();
        $backupPath = null;

        try {
            if (!$this->isSafeAction($action) && !$this->isDangerousAction($action)) {
                throw new \InvalidArgumentException('Unsupported maintenance action.');
            }

            if ($this->isDangerousAction($action)) {
                $backupPath = $this->createBackup();
                if ($backupPath === '') {
                    throw new \RuntimeException('Database backup was not created.');
                }
            }

            $result = match ($action) {
                'clear_cache' => $this->clearCache(),
                'clear_temp_files' => $this->clearTempFiles(),
                'clear_analytics' => $this->clearAnalytics(),
                'clear_logs' => $this->clearLogs(),
                'recreate_system_seeds' => $this->recreateSystemSeeds(),
                'recreate_roles' => $this->recreateRolesAndPermissions(),
                'delete_demo_content' => $this->deleteDemoContent(),
                'full_reset' => $this->fullResetCms($user),
                default => ['message' => 'Unsupported action.'],
            };

            $this->writeLog($user, $ip, $action, 'success', null, $backupPath);

            return [
                'status' => 'success',
                'message' => (string)($result['message'] ?? 'Maintenance action completed.'),
                'backup_path' => $backupPath,
                'result' => $result,
            ];
        } catch (Throwable $exception) {
            $this->writeLog($user, $ip, $action, 'error', $exception->getMessage(), $backupPath);

            return [
                'status' => 'error',
                'message' => $exception->getMessage(),
                'backup_path' => $backupPath,
            ];
        }
    }

    public function getPaginatedLogs(int $perPage = 20): array
    {
        return $this->logService->getPaginatedLogs($perPage);
    }

    public function clearMaintenanceLogs(): int
    {
        return $this->logService->clearMaintenanceLogs();
    }

    public function ensureLogTable(): void
    {
        $this->logService->ensureLogTable();
    }

    public function createBackup(): string
    {
        return $this->backupService->createBackup();
    }

    private function clearCache(): array
    {
        return $this->cacheCleanup->clearCache();
    }

    private function clearTempFiles(): array
    {
        return $this->cacheCleanup->clearTempFiles();
    }

    private function clearAnalytics(): array
    {
        (new AnalyticsService())->resetAll();

        return ['message' => 'Analytics cleared.'];
    }

    private function clearLogs(): array
    {
        return $this->cacheCleanup->clearLogs();
    }

    private function recreateSystemSeeds(): array
    {
        $now = date('Y-m-d H:i:s');
        $this->ensureCoreSchemas();
        $this->recreateRolesAndPermissions();
        $this->upsertDefaultSettings('FIREBALL CMS', 'FIREBALL CMS system seed.', $now);
        $this->resetMetrics($now);

        return ['message' => 'System seeds recreated.'];
    }

    private function recreateRolesAndPermissions(): array
    {
        $now = date('Y-m-d H:i:s');
        $this->ensureCoreSchemas();

        db()->query("DELETE FROM user_roles WHERE slug IN ('creator', 'admin', 'moderator', 'user')");
        db()->query(
            'INSERT INTO user_roles (id, name, slug, is_system, created_at)
             VALUES
                (?, ?, ?, ?, ?),
                (?, ?, ?, ?, ?),
                (?, ?, ?, ?, ?),
                (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE name = VALUES(name), slug = VALUES(slug), is_system = VALUES(is_system)',
            [
                1, 'Creator', 'creator', 1, $now,
                2, 'Admin', 'admin', 1, $now,
                3, 'Moderator', 'moderator', 1, $now,
                4, 'User', 'user', 1, $now,
            ]
        );

        return ['message' => 'Roles and permissions recreated.'];
    }

    private function deleteDemoContent(): array
    {
        $deletedPosts = 0;
        $deletedCategories = 0;

        if ($this->tableExists('posts')) {
            db()->query("DELETE FROM posts WHERE slug IN ('demo-post') OR title LIKE ?", ['%Демо-запись FIREBALL CMS%']);
            $deletedPosts = db()->rowCount();
        }

        if ($this->tableExists('post_categories')) {
            db()->query(
                "DELETE FROM post_categories
                 WHERE slug IN ('moscow')
                   AND NOT EXISTS (
                       SELECT 1 FROM posts WHERE posts.category_id = post_categories.id LIMIT 1
                   )"
            );
            $deletedCategories = db()->rowCount();
        }

        if ($this->tableExists('products')) {
            db()->query("DELETE FROM products WHERE slug LIKE 'demo-%' OR title LIKE 'Demo %'");
        }

        Post::clearPublicCache();

        return [
            'message' => 'Demo content deleted.',
            'deleted_posts' => $deletedPosts,
            'deleted_categories' => $deletedCategories,
        ];
    }

    private function fullResetCms(array $actor): array
    {
        $now = date('Y-m-d H:i:s');
        $this->ensureCoreSchemas();
        $creatorId = (int)($actor['id'] ?? 0);
        $creator = $creatorId > 0
            ? db()->query("SELECT * FROM users WHERE id = ? AND role = 'creator' LIMIT 1", [$creatorId])->getOne()
            : false;
        if (!$creator) {
            $creator = db()->query("SELECT * FROM users WHERE role = 'creator' ORDER BY id ASC LIMIT 1")->getOne();
        }
        if (!$creator) {
            throw new \RuntimeException('Current Creator account could not be preserved.');
        }

        $this->truncateTables([
            'chat_audit_logs',
            'chat_messages',
            'contact_requests',
            'mail_logs',
            'password_resets',
            'two_factor_recovery_tokens',
            'security_logs',
            'posts',
            'post_categories',
            'pages',
            'products',
            'categories',
            'analytics_visits',
            'site_metrics',
            'database_maintenance_logs',
            'cms_update_logs',
            'update_migrations',
        ]);
        db()->query('DELETE FROM users WHERE id <> ?', [(int)$creator['id']]);
        db()->query("UPDATE users SET role = 'creator' WHERE id = ?", [(int)$creator['id']]);
        db()->query('TRUNCATE TABLE user_roles');
        db()->query('TRUNCATE TABLE site_settings');
        $this->recreateRolesAndPermissions();
        $this->upsertDefaultSettings('FIREBALL CMS', 'Clean FIREBALL CMS installation after reset.', $now);
        $this->resetMetrics($now);
        PostImageService::clearGeneratedCache();
        Post::clearPublicCache();
        Page::clearPublicCache();

        return [
            'message' => 'CMS reset completed. Current Creator account was preserved.',
            'account' => [
                'id' => (int)$creator['id'],
                'login' => (string)($creator['login'] ?? ''),
                'email' => (string)($creator['email'] ?? ''),
            ],
        ];
    }

    private function ensureCoreSchemas(): void
    {
        $this->ensureShopTables();
        (new User())->ensureUsersTableExists();
        (new SiteSetting())->ensureTableExists();
        (new ContactRequest())->ensureTableExists();
        (new ChatMessage())->ensureTableExists();
        (new Admin())->ensureSchema();
        (new Analytics())->getStats();
        $this->ensureLogTable();
    }

    private function ensureShopTables(): void
    {
        db()->query(
            'CREATE TABLE IF NOT EXISTS categories (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(255) DEFAULT NULL,
                parent_id INT(10) UNSIGNED NOT NULL DEFAULT 0,
                image VARCHAR(255) DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY slug (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        db()->query(
            'CREATE TABLE IF NOT EXISTS products (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(255) DEFAULT NULL,
                category_id INT(10) UNSIGNED NOT NULL,
                price INT(10) UNSIGNED NOT NULL,
                old_price INT(10) UNSIGNED NOT NULL DEFAULT 0,
                excerpt VARCHAR(255) DEFAULT NULL,
                content TEXT NOT NULL,
                image VARCHAR(255) DEFAULT NULL,
                gallery TEXT DEFAULT NULL,
                is_sale TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
                in_stock TINYINT(3) UNSIGNED NOT NULL DEFAULT 1,
                PRIMARY KEY (id),
                UNIQUE KEY slug (slug),
                KEY category_id (category_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function upsertDefaultSettings(string $title, string $description, string $now): void
    {
        $settings = [
            'site_title' => $title,
            'site_description' => $description,
            'seo_home_title' => $title,
            'seo_default_title_suffix' => ' | FIREBALL CMS',
            'seo_meta_description' => $description,
            'seo_meta_keywords' => 'fireball cms',
            'seo_meta_author' => 'FIREBALL CMS',
            'seo_robots' => 'index,follow',
            'seo_og_image' => '',
            'seo_twitter_card' => 'summary_large_image',
            'cookie_enabled' => '0',
            'cookie_message' => 'Мы используем файлы cookie для корректной работы сайта. Продолжая пользоваться сайтом, вы соглашаетесь с их использованием.',
            'cookie_button_text' => 'Принять',
            'cookie_policy_page_id' => '0',
            'cookie_policy_use_on_registration' => '0',
            'cookie_position' => 'bottom_right',
            'cookie_style' => 'card',
            'cookie_expiration_days' => '365',
            'cookie_consent_categories' => '["necessary"]',
            'updater_github_repository' => '',
            'updater_github_branch' => 'main',
            'updater_github_token' => '',
            'update_channel' => 'stable',
            'updater_last_check_payload' => '',
            'updater_last_checked_at' => '',
            'updater_last_updated_at' => '',
            'updater_rollback_commit' => '',
        ];

        foreach ($settings as $key => $value) {
            db()->query(
                'INSERT INTO site_settings (setting_key, setting_value, updated_at)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)',
                [$key, $value, $now]
            );
        }

        cache()->remove('site_settings:all');
    }

    private function resetMetrics(string $now): void
    {
        if (!$this->tableExists('site_metrics')) {
            return;
        }

        db()->query("DELETE FROM site_metrics WHERE metric_key IN ('site_visits', 'page_views')");
        db()->query(
            'INSERT INTO site_metrics (metric_key, metric_value, updated_at)
             VALUES (?, ?, ?), (?, ?, ?)',
            ['site_visits', 0, $now, 'page_views', 0, $now]
        );
    }

    private function truncateTables(array $tables): void
    {
        db()->query('SET FOREIGN_KEY_CHECKS = 0');
        try {
            foreach ($tables as $table) {
                if ($this->tableExists($table)) {
                    db()->query('TRUNCATE TABLE ' . $this->quoteIdentifier($table));
                }
            }
        } finally {
            db()->query('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    private function tableExists(string $table): bool
    {
        return (int)db()->query(
            'SELECT COUNT(*)
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?',
            [$table]
        )->getColumn() > 0;
    }

    private function writeLog(array $user, string $ip, string $action, string $result, ?string $error, ?string $backupPath): void
    {
        try {
            $this->logService->writeLog($user, $ip, $action, $result, $error, $backupPath);
        } catch (Throwable $exception) {
            log_error_details('Database maintenance log write failed', [
                'Action' => $action,
                'Result' => $result,
            ], $exception);
        }
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $identifier)) {
            throw new \InvalidArgumentException('Unsafe database identifier.');
        }

        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
