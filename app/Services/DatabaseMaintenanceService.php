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
use FBL\Pagination;
use PDO;
use Throwable;

final class DatabaseMaintenanceService
{
    public const CONFIRMATION_PHRASE = 'СБРОСИТЬ FIREBALL';

    private const LOG_TABLE = 'database_maintenance_logs';

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
        $this->ensureLogTable();
        $perPage = max(1, min(100, $perPage));
        $total = (int)db()->query(
            'SELECT COUNT(*) FROM ' . self::LOG_TABLE
        )->getColumn();
        $pagination = new Pagination(
            $total,
            $perPage,
            PAGINATION_SETTINGS['midSize'],
            PAGINATION_SETTINGS['maxPages'],
            PAGINATION_SETTINGS['tpl'],
            'logs_page'
        );
        $offset = $pagination->getOffset();

        return [
            'items' => db()->query(
                'SELECT * FROM ' . self::LOG_TABLE . " ORDER BY id DESC LIMIT {$offset}, {$perPage}"
            )->get() ?: [],
            'total' => $total,
            'pagination' => $pagination,
        ];
    }

    public function clearMaintenanceLogs(): int
    {
        $this->ensureLogTable();
        db()->query('DELETE FROM ' . self::LOG_TABLE);

        return db()->rowCount();
    }

    public function ensureLogTable(): void
    {
        db()->query(
            'CREATE TABLE IF NOT EXISTS ' . self::LOG_TABLE . ' (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT(10) UNSIGNED NULL,
                user_name VARCHAR(255) NULL,
                ip_address VARCHAR(64) NULL,
                action VARCHAR(100) NOT NULL,
                result VARCHAR(20) NOT NULL,
                error TEXT NULL,
                backup_path VARCHAR(500) NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY action (action),
                KEY result (result),
                KEY created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function createBackup(): string
    {
        $directory = ROOT . '/storage/backups';
        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            return '';
        }

        $path = $directory . '/db-backup-' . date('Y-m-d-H-i') . '.sql';
        $suffix = 1;
        while (is_file($path)) {
            $path = $directory . '/db-backup-' . date('Y-m-d-H-i') . '-' . $suffix . '.sql';
            $suffix++;
        }

        $sql = $this->buildSqlDump();
        if ($sql === '' || file_put_contents($path, $sql) === false) {
            return '';
        }

        return $path;
    }

    private function buildSqlDump(): string
    {
        $pdo = $this->pdo();
        $database = (string)(DB_SETTINGS['database'] ?? '');
        $tables = $pdo->query('SHOW FULL TABLES WHERE Table_type = ' . $pdo->quote('BASE TABLE'))->fetchAll(PDO::FETCH_NUM);
        if (!is_array($tables)) {
            return '';
        }

        $lines = [
            '-- FIREBALL CMS database backup',
            '-- Created at: ' . date('Y-m-d H:i:s'),
            '-- Database: ' . $database,
            'SET FOREIGN_KEY_CHECKS=0;',
            '',
        ];

        foreach ($tables as $row) {
            $table = (string)($row[0] ?? '');
            if ($table === '') {
                continue;
            }

            $quotedTable = $this->quoteIdentifier($table);
            $create = $pdo->query('SHOW CREATE TABLE ' . $quotedTable)->fetch(PDO::FETCH_ASSOC);
            $createSql = (string)($create['Create Table'] ?? '');

            $lines[] = 'DROP TABLE IF EXISTS ' . $quotedTable . ';';
            $lines[] = $createSql . ';';

            $stmt = $pdo->query('SELECT * FROM ' . $quotedTable);
            while ($record = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $columns = array_map([$this, 'quoteIdentifier'], array_keys($record));
                $values = array_map(
                    static fn($value): string => $value === null ? 'NULL' : $pdo->quote((string)$value),
                    array_values($record)
                );

                $lines[] = 'INSERT INTO ' . $quotedTable . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ');';
            }

            $lines[] = '';
        }

        $lines[] = 'SET FOREIGN_KEY_CHECKS=1;';

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    private function pdo(): PDO
    {
        $dsn = 'mysql:host=' . DB_SETTINGS['host'] . ';dbname=' . DB_SETTINGS['database'] . ';charset=' . DB_SETTINGS['charset'];
        if (!empty(DB_SETTINGS['port'])) {
            $dsn .= ';port=' . (int)DB_SETTINGS['port'];
        }

        return new PDO($dsn, DB_SETTINGS['username'], DB_SETTINGS['password'], DB_SETTINGS['options']);
    }

    private function clearCache(): array
    {
        $deleted = $this->clearDirectoryContents(CACHE);
        $deleted += PostImageService::clearGeneratedCache();
        Post::clearPublicCache();
        Page::clearPublicCache();

        return ['message' => 'Cache cleared.', 'deleted' => $deleted];
    }

    private function clearTempFiles(): array
    {
        $deleted = 0;
        foreach ([ROOT . '/tmp/temp', ROOT . '/tmp/uploads', ROOT . '/storage/temp'] as $directory) {
            $deleted += $this->clearDirectoryContents($directory);
        }

        return ['message' => 'Temporary files cleared.', 'deleted' => $deleted];
    }

    private function clearAnalytics(): array
    {
        (new AnalyticsService())->resetAll();

        return ['message' => 'Analytics cleared.'];
    }

    private function clearLogs(): array
    {
        $deleted = 0;
        foreach ([ROOT . '/tmp/logs', ROOT . '/storage/logs'] as $directory) {
            $deleted += $this->clearDirectoryContents($directory);
        }

        if (is_file(ERROR_LOGS) && file_put_contents(ERROR_LOGS, '') !== false) {
            $deleted++;
        }

        return ['message' => 'System logs cleared.', 'deleted' => $deleted];
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
            'password_resets',
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

    private function clearDirectoryContents(string $directory): int
    {
        if ($directory === '' || !is_dir($directory)) {
            return 0;
        }

        $deleted = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $path = $item->getPathname();
            if ($item->isDir()) {
                if (@rmdir($path)) {
                    $deleted++;
                }
                continue;
            }

            if (@unlink($path)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    private function writeLog(array $user, string $ip, string $action, string $result, ?string $error, ?string $backupPath): void
    {
        try {
            $this->ensureLogTable();
            db()->query(
                'INSERT INTO ' . self::LOG_TABLE . '
                 (user_id, user_name, ip_address, action, result, error, backup_path, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    (int)($user['id'] ?? 0) ?: null,
                    (string)($user['name'] ?? ''),
                    $ip,
                    $action,
                    $result,
                    $error,
                    $backupPath,
                    date('Y-m-d H:i:s'),
                ]
            );
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
