<?php

namespace App\Services;

use PDO;
use Throwable;

final class InstallService
{
    public function requirements(): array
    {
        return [
            ['label' => 'PHP >= 8.2', 'ok' => version_compare(PHP_VERSION, '8.2.0', '>=')],
            ['label' => 'PDO', 'ok' => extension_loaded('pdo')],
            ['label' => 'PDO MySQL', 'ok' => extension_loaded('pdo_mysql')],
            ['label' => 'OpenSSL', 'ok' => extension_loaded('openssl')],
            ['label' => 'JSON', 'ok' => extension_loaded('json')],
            ['label' => 'vendor/autoload.php', 'ok' => is_file(ROOT . '/vendor/autoload.php')],
            ['label' => 'config writable', 'ok' => is_writable(CONFIG)],
            ['label' => 'storage writable', 'ok' => $this->ensureWritableDirectory(STORAGE)],
            ['label' => 'uploads writable', 'ok' => $this->ensureWritableDirectory(UPLOADS)],
            ['label' => 'installed.lock writable', 'ok' => is_writable(STORAGE) || $this->ensureWritableDirectory(STORAGE)],
        ];
    }

    public function requirementsPass(): bool
    {
        foreach ($this->requirements() as $requirement) {
            if (empty($requirement['ok'])) {
                return false;
            }
        }

        return true;
    }

    public function testDatabase(array $data): array
    {
        try {
            $pdo = $this->pdo($data);
            $tables = $this->existingTables($pdo);

            return [
                'ok' => true,
                'tables' => $tables,
                'warning' => $tables !== [],
            ];
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'message' => $exception->getMessage(),
                'tables' => [],
                'warning' => false,
            ];
        }
    }

    public function install(array $payload): array
    {
        if (is_file(INSTALLED_LOCK)) {
            return ['ok' => false, 'message' => 'FIREBALL CMS is already installed.'];
        }

        $db = (array)($payload['db'] ?? []);
        $site = (array)($payload['site'] ?? []);
        $admin = (array)($payload['admin'] ?? []);
        $locale = (string)($payload['locale'] ?? DEFAULT_LOCALE);
        $demo = !empty($payload['demo']);
        $now = date('Y-m-d H:i:s');

        $validationError = $this->validateInstallPayload($db, $site, $admin, $locale);
        if ($validationError !== '') {
            return ['ok' => false, 'message' => $validationError];
        }

        try {
            $pdo = $this->pdo($db);
            $tables = $this->existingTables($pdo);
        } catch (Throwable $exception) {
            return ['ok' => false, 'message' => $exception->getMessage()];
        }
        if ($tables !== [] && empty($payload['allow_existing'])) {
            return [
                'ok' => false,
                'message' => 'Database already contains tables. Confirm installation over existing tables.',
                'tables' => $tables,
                'requires_confirmation' => true,
            ];
        }

        $temporaryConfig = CONFIG . '/config.local.php.tmp';
        $finalConfig = CONFIG . '/config.local.php';
        $temporaryLock = INSTALLED_LOCK . '.tmp';
        $configPromoted = false;

        try {
            $this->writeTemporaryLocalConfig($temporaryConfig, $db, $site, $locale);
            $this->runSqlFile($pdo, ROOT . '/database/schema.sql', ['now' => $now]);
            $this->runMigrationFiles($pdo);

            $pdo->beginTransaction();
            $this->runSqlFile($pdo, ROOT . '/database/seed.sql', ['now' => $now]);
            $this->insertSiteSettings($pdo, $site, $locale, $now);
            $this->insertCreator($pdo, $admin, $now);

            if ($demo) {
                $this->runSqlFile($pdo, ROOT . '/database/demo.sql', ['now' => $now]);
            }

            if (!@rename($temporaryConfig, $finalConfig)) {
                throw new \RuntimeException('Unable to activate config.local.php.');
            }
            $configPromoted = true;
            $this->writeInstalledLock($temporaryLock, $site, $admin, $locale);
            $pdo->commit();

            if (!@rename($temporaryLock, INSTALLED_LOCK)) {
                throw new \RuntimeException('Unable to activate installed.lock.');
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (is_file($temporaryConfig)) {
                @unlink($temporaryConfig);
            }
            if (is_file($temporaryLock)) {
                @unlink($temporaryLock);
            }
            if (is_file(INSTALLED_LOCK)) {
                @unlink(INSTALLED_LOCK);
            }
            if ($configPromoted && is_file($finalConfig)) {
                @unlink($finalConfig);
            }
            $this->removeTablesCreatedByAttempt($pdo, $tables);

            return ['ok' => false, 'message' => $exception->getMessage()];
        }

        return [
            'ok' => true,
            'version' => (string)((require CONFIG . '/version.php')['version'] ?? ''),
            'site_url' => (string)($site['url'] ?? ''),
            'login' => (string)($admin['login'] ?? ''),
        ];
    }

    public function defaultSiteUrl(): string
    {
        $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
        if ($host === '') {
            return 'http://localhost';
        }

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        return ($secure ? 'https' : 'http') . '://' . $host;
    }

    private function pdo(array $db): PDO
    {
        $host = trim((string)($db['host'] ?? 'localhost'));
        $database = trim((string)($db['database'] ?? ''));
        $username = trim((string)($db['username'] ?? ''));
        $password = (string)($db['password'] ?? '');
        $port = (int)($db['port'] ?? 3306);
        $charset = trim((string)($db['charset'] ?? 'utf8mb4')) ?: 'utf8mb4';

        if ($database === '') {
            throw new \RuntimeException('Database name is required.');
        }

        $dsn = 'mysql:host=' . $host . ';dbname=' . $database . ';charset=' . $charset;
        if ($port > 0) {
            $dsn .= ';port=' . $port;
        }

        return new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    private function existingTables(PDO $pdo): array
    {
        $rows = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM);

        return array_values(array_map(static fn(array $row): string => (string)($row[0] ?? ''), $rows ?: []));
    }

    private function removeTablesCreatedByAttempt(PDO $pdo, array $tablesBefore): void
    {
        try {
            $createdTables = array_values(array_diff($this->existingTables($pdo), $tablesBefore));
            if ($createdTables === []) {
                return;
            }

            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            try {
                foreach ($createdTables as $table) {
                    $pdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '``', $table) . '`');
                }
            } finally {
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            }
        } catch (Throwable) {
            // Preserve the original installation error; diagnostics can report any remaining tables.
        }
    }

    private function runSqlFile(PDO $pdo, string $path, array $params = []): void
    {
        if (!is_file($path)) {
            throw new \RuntimeException('SQL file not found: ' . basename($path));
        }

        $sql = (string)file_get_contents($path);
        (new SqlFileRunner())->executePdo($pdo, $sql, $params);
    }

    private function runMigrationFiles(PDO $pdo): void
    {
        $runner = new SqlFileRunner();
        foreach (glob(ROOT . '/database/migrations/*.sql') ?: [] as $file) {
            $name = basename($file);
            $check = $pdo->prepare('SELECT COUNT(*) FROM update_migrations WHERE migration = ?');
            $check->execute([$name]);
            if ((int)$check->fetchColumn() > 0) {
                continue;
            }

            $runner->executePdo($pdo, (string)file_get_contents($file));
            $insert = $pdo->prepare('INSERT INTO update_migrations (migration, executed_at) VALUES (?, ?)');
            $insert->execute([$name, date('Y-m-d H:i:s')]);
        }
    }

    private function insertSiteSettings(PDO $pdo, array $site, string $locale, string $now): void
    {
        $settings = [
            'site_title' => (string)($site['name'] ?? 'FIREBALL CMS'),
            'site_description' => '',
            'seo_home_title' => (string)($site['name'] ?? 'FIREBALL CMS'),
            'seo_default_title_suffix' => ' | FIREBALL CMS',
            'seo_meta_description' => '',
            'seo_meta_keywords' => 'fireball cms',
            'seo_meta_author' => (string)($site['name'] ?? 'FIREBALL CMS'),
            'seo_robots' => 'index,follow',
            'seo_og_image' => '',
            'seo_twitter_card' => 'summary_large_image',
            'active_theme' => 'default',
            'homepage_type' => 'default',
            'posts_per_page' => '20',
            'default_locale' => $locale,
            'site_url' => (string)($site['url'] ?? ''),
            'timezone' => (string)($site['timezone'] ?? 'Europe/Moscow'),
            'cms_version' => (string)((require CONFIG . '/version.php')['version'] ?? ''),
            'updater_github_repository' => 'Samkmv/FIREBALL_CMS',
            'updater_github_branch' => 'main',
            'updater_github_token' => '',
            'updater_last_check_payload' => '',
            'updater_last_checked_at' => '',
            'updater_last_updated_at' => '',
        ];

        $stmt = $pdo->prepare(
            'INSERT INTO site_settings (setting_key, setting_value, updated_at)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)'
        );

        foreach ($settings as $key => $value) {
            $stmt->execute([$key, $value, $now]);
        }
    }

    private function insertCreator(PDO $pdo, array $admin, string $now): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO users (name, login, email, password, avatar, role, last_seen_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE name = VALUES(name), password = VALUES(password), role = VALUES(role)'
        );
        $stmt->execute([
            (string)($admin['login'] ?? 'creator'),
            make_slug((string)($admin['login'] ?? 'creator'), 'creator'),
            mb_strtolower(trim((string)($admin['email'] ?? ''))),
            password_hash((string)($admin['password'] ?? ''), PASSWORD_DEFAULT),
            null,
            'creator',
            null,
            $now,
        ]);
    }

    private function writeTemporaryLocalConfig(string $path, array $db, array $site, string $locale): void
    {
        $finalPath = CONFIG . '/config.local.php';
        if (is_file($finalPath) && !is_file(INSTALLED_LOCK)) {
            throw new \RuntimeException('config.local.php already exists. Remove it before reinstalling.');
        }
        if (is_file($path)) {
            @unlink($path);
        }

        $config = [
            'DEBUG' => 0,
            'PATH' => rtrim((string)($site['url'] ?? $this->defaultSiteUrl()), '/'),
            'SITE_NAME' => (string)($site['name'] ?? 'FIREBALL CMS'),
            'APP_TIMEZONE' => (string)($site['timezone'] ?? 'Europe/Moscow'),
            'DEFAULT_LOCALE' => $locale,
            'DB_SETTINGS' => [
                'driver' => 'mysql',
                'host' => (string)($db['host'] ?? 'localhost'),
                'database' => (string)($db['database'] ?? ''),
                'username' => (string)($db['username'] ?? ''),
                'password' => (string)($db['password'] ?? ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'port' => (int)($db['port'] ?? 3306),
                'prefix' => (string)($db['prefix'] ?? ''),
                'options' => [
                    'PDO::ATTR_ERRMODE' => 'PDO::ERRMODE_EXCEPTION',
                    'PDO::ATTR_DEFAULT_FETCH_MODE' => 'PDO::FETCH_ASSOC',
                ],
            ],
        ];

        $php = "<?php\n\nreturn " . $this->exportConfig($config) . ";\n";
        if (file_put_contents($path, $php, LOCK_EX) === false) {
            throw new \RuntimeException('Unable to write temporary config.local.php.');
        }
    }

    private function exportConfig(array $config): string
    {
        $export = var_export($config, true);
        $export = str_replace("'PDO::ATTR_ERRMODE'", 'PDO::ATTR_ERRMODE', $export);
        $export = str_replace("'PDO::ERRMODE_EXCEPTION'", 'PDO::ERRMODE_EXCEPTION', $export);
        $export = str_replace("'PDO::ATTR_DEFAULT_FETCH_MODE'", 'PDO::ATTR_DEFAULT_FETCH_MODE', $export);
        $export = str_replace("'PDO::FETCH_ASSOC'", 'PDO::FETCH_ASSOC', $export);

        return $export;
    }

    private function writeInstalledLock(string $path, array $site, array $admin, string $locale): void
    {
        $this->ensureWritableDirectory(STORAGE);
        $payload = [
            'installed_at' => date('Y-m-d H:i:s'),
            'version' => (string)((require CONFIG . '/version.php')['version'] ?? ''),
            'site_url' => (string)($site['url'] ?? ''),
            'admin_login' => (string)($admin['login'] ?? ''),
            'locale' => $locale,
        ];

        if (is_file($path)) {
            @unlink($path);
        }
        if (file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) {
            throw new \RuntimeException('Unable to create temporary installed.lock.');
        }
    }

    private function validateInstallPayload(array $db, array $site, array $admin, string $locale): string
    {
        if (!array_key_exists($locale, LANGS)) {
            return 'Invalid language.';
        }
        if (trim((string)($db['database'] ?? '')) === '') {
            return 'Database name is required.';
        }
        if (trim((string)($db['prefix'] ?? '')) !== '') {
            return 'Table prefix is reserved for a future database layer update. Leave it empty for this version.';
        }
        if (trim((string)($site['name'] ?? '')) === '') {
            return 'Site name is required.';
        }
        if (filter_var((string)($admin['email'] ?? ''), FILTER_VALIDATE_EMAIL) === false) {
            return 'Admin email is invalid.';
        }
        if (trim((string)($admin['login'] ?? '')) === '') {
            return 'Admin login is required.';
        }
        if ((string)($admin['password'] ?? '') === '' || (string)($admin['password'] ?? '') !== (string)($admin['password_confirmation'] ?? '')) {
            return 'Admin password confirmation does not match.';
        }

        return '';
    }

    private function ensureWritableDirectory(string $path): bool
    {
        if (!is_dir($path) && !@mkdir($path, 0755, true) && !is_dir($path)) {
            return false;
        }

        return is_writable($path);
    }
}
