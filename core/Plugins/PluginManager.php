<?php

namespace FBL\Plugins;

use App\Services\SqlFileRunner;
use FBL\Language;
use FBL\Router;
use Throwable;

final class PluginManager
{
    private string $pluginsPath;
    private bool $schemaReady = false;
    private bool $booted = false;
    private array $loadErrors = [];
    private ?string $bootingPluginSlug = null;

    public function __construct(?string $pluginsPath = null)
    {
        $this->pluginsPath = $pluginsPath ?: (defined('PLUGINS') ? PLUGINS : ROOT . '/plugins');
    }

    public function bootActivePlugins(Router $router): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;
        $this->ensureSchema();

        foreach ($this->activePluginRows() as $row) {
            $slug = (string)$row['slug'];
            $path = $this->pluginPath($slug);
            if ($path === null) {
                $this->recordError($slug, 'Plugin directory is missing.');
                continue;
            }

            try {
                $metadata = $this->readMetadata($path);
                if ($this->shouldRunPendingMigrations($row, $metadata)
                    && $this->hasPendingMigrations($metadata)) {
                    $this->runMigrations($metadata);
                }
                $this->syncInstalledMetadata($row, $metadata);
                $this->registerPluginLanguage($metadata);
                $plugin = $this->loadPluginInstance($metadata);
                $this->bootPluginInstance($slug, $plugin);
                $this->includePluginRoutes($metadata, $router);
            } catch (Throwable $exception) {
                $this->recordError($slug, 'Plugin boot failed.', $exception);
            }
        }
    }

    public function all(): array
    {
        $this->ensureSchema();

        $items = [];
        foreach ($this->scan() as $slug => $metadata) {
            $row = $this->pluginRow($slug);
            $items[$slug] = [
                'slug' => $slug,
                'path' => $metadata['path'] ?? '',
                'name' => (string)($metadata['name'] ?? $slug),
                'version' => (string)($metadata['version'] ?? ''),
                'description' => (string)($metadata['description'] ?? ''),
                'author' => (string)($metadata['author'] ?? ''),
                'status' => $row['status'] ?? 'not_installed',
                'installed' => $row !== null,
                'valid' => empty($metadata['error']),
                'error' => $metadata['error'] ?? '',
                'load_error' => $this->loadErrors[$slug] ?? '',
                'installed_at' => $row['installed_at'] ?? null,
                'activated_at' => $row['activated_at'] ?? null,
                'deactivated_at' => $row['deactivated_at'] ?? null,
            ];
        }

        $this->pruneMissingInstalledPlugins(array_keys($items));

        ksort($items);

        return array_values($items);
    }

    public function install(string $slug): void
    {
        $this->ensureSchema();
        $metadata = $this->validMetadataBySlug($slug);
        $now = date('Y-m-d H:i:s');

        db()->beginTransaction();
        try {
            db()->query(
                "INSERT INTO plugins (slug, name, version, description, author, status, installed_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, 'inactive', ?, ?)
                 ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    version = VALUES(version),
                    description = VALUES(description),
                    author = VALUES(author),
                    updated_at = VALUES(updated_at)",
                [
                    $metadata['slug'],
                    $metadata['name'],
                    $metadata['version'],
                    $metadata['description'] ?? '',
                    $metadata['author'] ?? '',
                    $now,
                    $now,
                ]
            );

            $this->runMigrations($metadata);
            $this->loadPluginInstance($metadata)->install();

            if (db()->inTransaction()) {
                db()->commit();
            }
        } catch (Throwable $exception) {
            if (db()->inTransaction()) {
                try {
                    db()->rollBack();
                } catch (Throwable) {
                    // MySQL DDL inside plugin migrations may auto-commit the transaction.
                }
            }
            $this->recordError($metadata['slug'], 'Plugin install failed.', $exception);
            throw $exception;
        }
    }

    public function activate(string $slug): void
    {
        $this->ensureSchema();
        $metadata = $this->validMetadataBySlug($slug);
        if ($this->pluginRow($slug) === null) {
            throw new \RuntimeException('Plugin is not installed.');
        }

        try {
            $row = $this->pluginRow($slug);
            if (is_array($row) && $this->shouldRunPendingMigrations($row, $metadata)
                && $this->hasPendingMigrations($metadata)) {
                $this->runMigrations($metadata);
            }
            if (is_array($row)) {
                $this->syncInstalledMetadata($row, $metadata);
            }
            $plugin = $this->loadPluginInstance($metadata);
            $plugin->activate();
            db()->query(
                "UPDATE plugins
                 SET status = 'active', activated_at = ?, deactivated_at = NULL, updated_at = ?
                 WHERE slug = ?",
                [date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $slug]
            );
            $this->bootPluginInstance($slug, $plugin);
            if (function_exists('search_indexer')) {
                foreach (search_registry()->namesByOwner($slug) as $providerName) {
                    search_indexer()->reindexProvider($providerName);
                }
            }
        } catch (Throwable $exception) {
            $this->recordError($slug, 'Plugin activation failed.', $exception);
            throw $exception;
        }
    }

    public function deactivate(string $slug): void
    {
        $this->ensureSchema();
        $metadata = $this->validMetadataBySlug($slug);
        if ($this->pluginRow($slug) === null) {
            throw new \RuntimeException('Plugin is not installed.');
        }

        try {
            $this->loadPluginInstance($metadata)->deactivate();
            db()->query(
                "UPDATE plugins
                 SET status = 'inactive', deactivated_at = ?, updated_at = ?
                 WHERE slug = ?",
                [date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $slug]
            );
            if (function_exists('search_indexer')) {
                search_indexer()->removeOwner($slug);
            }
        } catch (Throwable $exception) {
            $this->recordError($slug, 'Plugin deactivation failed.', $exception);
            throw $exception;
        }
    }

    public function setting(string $pluginSlug, string $key, mixed $default = null): mixed
    {
        $this->ensureSchema();
        $this->assertSlug($pluginSlug);
        $row = db()->query(
            'SELECT setting_value FROM plugin_settings WHERE plugin_slug = ? AND setting_key = ? LIMIT 1',
            [$pluginSlug, $key]
        )->getOne();

        if (!$row) {
            return $default;
        }

        $decoded = json_decode((string)$row['setting_value'], true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : (string)$row['setting_value'];
    }

    public function setSetting(string $pluginSlug, string $key, mixed $value): void
    {
        $this->ensureSchema();
        $this->assertSlug($pluginSlug);
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        db()->query(
            "INSERT INTO plugin_settings (plugin_slug, setting_key, setting_value, updated_at)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)",
            [$pluginSlug, $key, $encoded !== false ? $encoded : 'null', date('Y-m-d H:i:s')]
        );
    }

    public function currentBootingPluginSlug(): ?string
    {
        return $this->bootingPluginSlug;
    }

    private function bootPluginInstance(string $slug, PluginInterface $plugin): void
    {
        $previous = $this->bootingPluginSlug;
        $this->bootingPluginSlug = $slug;
        try {
            $plugin->boot();
        } finally {
            $this->bootingPluginSlug = $previous;
        }
    }

    public function renderView(string $pluginSlug, string $view, array $data = [], bool $layout = true): string
    {
        $metadata = $this->validMetadataBySlug($pluginSlug);
        $safeView = trim(str_replace('\\', '/', $view), '/');
        if ($safeView === '' || str_contains($safeView, '..') || str_starts_with($safeView, '/')) {
            throw new \InvalidArgumentException('Invalid plugin view path.');
        }

        $file = $metadata['path'] . '/views/' . $safeView . '.php';
        $realFile = realpath($file);
        $viewsRoot = realpath($metadata['path'] . '/views');
        if ($realFile === false || $viewsRoot === false || !$this->isInside($realFile, $viewsRoot)) {
            abort('Plugin view not found.', 500);
        }

        extract($data);
        ob_start();
        require $realFile;
        $content = (string)ob_get_clean();

        if (!$layout) {
            return $content;
        }

        $view = app()->view;
        $view->content = $content;
        $layoutFile = VIEWS . '/layouts/' . LAYOUT . '.php';
        if (!is_file($layoutFile)) {
            abort('Not Found layout - ' . $layoutFile, 500);
        }

        $layoutData = array_merge([
            'title' => (string)($data['title'] ?? ($metadata['name'] ?? $pluginSlug)),
        ], $data);

        $renderer = function (string $layoutFile, array $layoutData): string {
            extract($layoutData);
            ob_start();
            require $layoutFile;

            return (string)ob_get_clean();
        };

        $html = $renderer->call($view, $layoutFile, $layoutData);

        return (string)$html;
    }

    public function loadErrors(): array
    {
        return $this->loadErrors;
    }

    private function scan(): array
    {
        $this->ensurePluginsRoot();

        $items = [];
        foreach (scandir($this->pluginsPath) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $this->pluginsPath . '/' . $entry;
            if (!is_dir($path) || is_link($path)) {
                continue;
            }

            try {
                $metadata = $this->readMetadata($path);
                $items[(string)$metadata['slug']] = $metadata;
            } catch (Throwable $exception) {
                $slug = preg_match('/^[a-zA-Z0-9_-]+$/', $entry) ? $entry : 'invalid-' . md5($path);
                $items[$slug] = [
                    'slug' => $slug,
                    'name' => $entry,
                    'version' => '',
                    'description' => '',
                    'author' => '',
                    'path' => $path,
                    'error' => $exception->getMessage(),
                ];
                $this->recordError($slug, 'Plugin metadata error.', $exception);
            }
        }

        return $items;
    }

    private function readMetadata(string $path): array
    {
        $realPath = realpath($path);
        $root = realpath($this->pluginsPath);
        if ($realPath === false || $root === false || !$this->isInside($realPath, $root) || is_link($realPath)) {
            throw new \RuntimeException('Invalid plugin directory.');
        }

        $file = $realPath . '/plugin.json';
        if (!is_file($file)) {
            throw new \RuntimeException('plugin.json is missing.');
        }

        $decoded = json_decode((string)file_get_contents($file), true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('plugin.json is damaged.');
        }

        foreach (['slug', 'name', 'version'] as $field) {
            if (trim((string)($decoded[$field] ?? '')) === '') {
                throw new \RuntimeException($field . ' is required.');
            }
        }

        $decoded['slug'] = trim((string)$decoded['slug']);
        $this->assertSlug($decoded['slug']);

        if ($decoded['slug'] !== basename($realPath)) {
            throw new \RuntimeException('Plugin slug must match directory name.');
        }

        $decoded['name'] = trim((string)$decoded['name']);
        $decoded['version'] = trim((string)$decoded['version']);
        $decoded['description'] = (string)($decoded['description'] ?? '');
        $decoded['author'] = (string)($decoded['author'] ?? '');
        $decoded['path'] = $realPath;

        return $decoded;
    }

    private function validMetadataBySlug(string $slug): array
    {
        $this->assertSlug($slug);
        $path = $this->pluginPath($slug);
        if ($path === null) {
            throw new \RuntimeException('Plugin directory is missing.');
        }

        return $this->readMetadata($path);
    }

    private function loadPluginInstance(array $metadata): PluginInterface
    {
        $file = $metadata['path'] . '/Plugin.php';
        $realFile = realpath($file);
        if ($realFile === false || !$this->isInside($realFile, $metadata['path'])) {
            throw new \RuntimeException('Plugin.php is missing.');
        }

        require_once $realFile;

        $class = (string)($metadata['class'] ?? '');
        if ($class === '') {
            $class = 'FireballPlugin' . str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', (string)$metadata['slug'])));
        }

        if (!class_exists($class)) {
            throw new \RuntimeException('Plugin class not found: ' . $class);
        }

        $plugin = new $class();
        if (!$plugin instanceof PluginInterface) {
            throw new \RuntimeException('Plugin class must implement PluginInterface.');
        }

        return $plugin;
    }

    private function registerPluginLanguage(array $metadata): void
    {
        $langDir = (string)($metadata['path'] ?? '') . '/lang';
        if (!is_dir($langDir)) {
            return;
        }

        Language::registerPluginLanguage((string)$metadata['slug'], $langDir);
    }

    private function includePluginRoutes(array $metadata, Router $router): void
    {
        foreach (['routes.php', 'admin.php'] as $fileName) {
            $file = $metadata['path'] . '/' . $fileName;
            $realFile = realpath($file);
            if ($realFile === false) {
                continue;
            }
            if (!$this->isInside($realFile, $metadata['path'])) {
                throw new \RuntimeException('Plugin route file is outside plugin directory.');
            }

            $plugin = $metadata;
            $pluginSlug = (string)$metadata['slug'];
            require $realFile;
        }
    }

    private function runMigrations(array $metadata): void
    {
        $migrationsPath = $metadata['path'] . '/migrations';
        if (!is_dir($migrationsPath)) {
            return;
        }

        $lockName = 'fblpm:' . substr(hash('sha256', (string)$metadata['slug']), 0, 48);
        $locked = (int)db()->query('SELECT GET_LOCK(?, 15)', [$lockName])->getColumn() === 1;
        if (!$locked) {
            throw new \RuntimeException('Could not acquire plugin migration lock.');
        }

        try {
            $runner = new SqlFileRunner();
            $files = glob($migrationsPath . '/*.sql') ?: [];
            sort($files);

            foreach ($files as $file) {
                $realFile = realpath($file);
                $root = realpath($migrationsPath);
                if ($realFile === false || $root === false || !$this->isInside($realFile, $root)) {
                    continue;
                }

                $migration = basename($realFile);
                $exists = db()->query(
                    'SELECT id FROM plugin_migrations WHERE plugin_slug = ? AND migration = ? LIMIT 1',
                    [$metadata['slug'], $migration]
                )->getOne();
                if ($exists) {
                    continue;
                }

                $sql = (string)file_get_contents($realFile);
                if (trim($sql) !== '') {
                    $runner->executeDatabase($sql);
                }

                db()->query(
                    'INSERT INTO plugin_migrations (plugin_slug, migration, executed_at) VALUES (?, ?, ?)',
                    [$metadata['slug'], $migration, date('Y-m-d H:i:s')]
                );
            }
        } finally {
            try {
                db()->query('SELECT RELEASE_LOCK(?)', [$lockName]);
            } catch (Throwable) {
                // The connection closing also releases a MySQL advisory lock.
            }
        }
    }

    private function hasPendingMigrations(array $metadata): bool
    {
        $migrationsPath = (string)($metadata['path'] ?? '') . '/migrations';
        if (!is_dir($migrationsPath)) {
            return false;
        }
        $files = array_map('basename', glob($migrationsPath . '/*.sql') ?: []);
        if ($files === []) {
            return false;
        }
        $applied = db()->query(
            'SELECT migration FROM plugin_migrations WHERE plugin_slug = ?',
            [$metadata['slug']]
        )->get() ?: [];

        return array_diff($files, array_column($applied, 'migration')) !== [];
    }

    private function shouldRunPendingMigrations(array $row, array $metadata): bool
    {
        if (!empty($metadata['auto_migrate'])) {
            return true;
        }

        return version_compare(
            (string)($metadata['version'] ?? '0.0.0'),
            (string)($row['version'] ?? '0.0.0'),
            '>'
        );
    }

    private function syncInstalledMetadata(array $row, array $metadata): void
    {
        $values = [
            'name' => (string)$metadata['name'],
            'version' => (string)$metadata['version'],
            'description' => (string)($metadata['description'] ?? ''),
            'author' => (string)($metadata['author'] ?? ''),
        ];
        foreach ($values as $column => $value) {
            if ((string)($row[$column] ?? '') !== $value) {
                db()->query(
                    'UPDATE plugins SET name = ?, version = ?, description = ?, author = ?, updated_at = ? WHERE slug = ?',
                    [
                        $values['name'],
                        $values['version'],
                        $values['description'],
                        $values['author'],
                        date('Y-m-d H:i:s'),
                        $metadata['slug'],
                    ]
                );
                break;
            }
        }
    }

    private function ensureSchema(): void
    {
        if ($this->schemaReady) {
            return;
        }

        db()->query(
            "CREATE TABLE IF NOT EXISTS plugins (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                slug VARCHAR(120) NOT NULL,
                name VARCHAR(255) NOT NULL,
                version VARCHAR(50) NOT NULL,
                description TEXT NULL,
                author VARCHAR(255) NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'inactive',
                installed_at DATETIME NULL,
                activated_at DATETIME NULL,
                deactivated_at DATETIME NULL,
                updated_at DATETIME NULL,
                PRIMARY KEY (id),
                UNIQUE KEY slug (slug),
                KEY status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        db()->query(
            "CREATE TABLE IF NOT EXISTS plugin_migrations (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                plugin_slug VARCHAR(120) NOT NULL,
                migration VARCHAR(255) NOT NULL,
                executed_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY plugin_migration (plugin_slug, migration),
                KEY plugin_slug (plugin_slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        db()->query(
            "CREATE TABLE IF NOT EXISTS plugin_settings (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                plugin_slug VARCHAR(120) NOT NULL,
                setting_key VARCHAR(190) NOT NULL,
                setting_value LONGTEXT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY plugin_setting (plugin_slug, setting_key),
                KEY plugin_slug (plugin_slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->schemaReady = true;
    }

    private function installedRows(): array
    {
        return db()->query('SELECT * FROM plugins ORDER BY name ASC')->get() ?: [];
    }

    private function activePluginRows(): array
    {
        return db()->query("SELECT * FROM plugins WHERE status = 'active' ORDER BY name ASC")->get() ?: [];
    }

    private function pruneMissingInstalledPlugins(array $foundSlugs): void
    {
        $found = array_fill_keys($foundSlugs, true);
        foreach ($this->installedRows() as $row) {
            $slug = (string)$row['slug'];
            if (isset($found[$slug])) {
                continue;
            }

            $this->assertSlug($slug);
            db()->query('DELETE FROM plugin_settings WHERE plugin_slug = ?', [$slug]);
            db()->query('DELETE FROM plugin_migrations WHERE plugin_slug = ?', [$slug]);
            db()->query('DELETE FROM plugins WHERE slug = ?', [$slug]);
            log_error_details('Missing plugin database record removed', ['Plugin' => $slug]);
        }
    }

    private function pluginRow(string $slug): ?array
    {
        $this->assertSlug($slug);
        $row = db()->query('SELECT * FROM plugins WHERE slug = ? LIMIT 1', [$slug])->getOne();

        return is_array($row) ? $row : null;
    }

    private function pluginPath(string $slug): ?string
    {
        $this->assertSlug($slug);
        $this->ensurePluginsRoot();
        $path = $this->pluginsPath . '/' . $slug;
        $real = realpath($path);
        $root = realpath($this->pluginsPath);

        if ($real === false || $root === false || !$this->isInside($real, $root) || !is_dir($real) || is_link($real)) {
            return null;
        }

        return $real;
    }

    private function ensurePluginsRoot(): void
    {
        if (!is_dir($this->pluginsPath)) {
            mkdir($this->pluginsPath, 0755, true);
        }
    }

    private function assertSlug(string $slug): void
    {
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $slug)) {
            throw new \InvalidArgumentException('Invalid plugin slug.');
        }
    }

    private function isInside(string $path, string $base): bool
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');
        $base = rtrim(str_replace('\\', '/', $base), '/');

        return $path === $base || str_starts_with($path, $base . '/');
    }

    private function recordError(string $slug, string $message, ?Throwable $exception = null): void
    {
        $this->loadErrors[$slug] = $exception ? $message . ' ' . $exception->getMessage() : $message;
        log_error_details($message, ['Plugin' => $slug], $exception);
    }
}
