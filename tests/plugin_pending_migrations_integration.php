<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/config.php';
require ROOT . '/vendor/autoload.php';
require ROOT . '/helpers/helpers.php';

$app = new FBL\Application();

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$slug = 'pending-migration-fixture';
$table = 'pending_migration_fixture';
$root = sys_get_temp_dir() . '/fireball-plugin-migration-' . bin2hex(random_bytes(6));
$pluginRoot = $root . '/' . $slug;
$migrationRoot = $pluginRoot . '/migrations';
$now = date('Y-m-d H:i:s');

$removeTree = static function (string $path) use (&$removeTree): void {
    if (!is_dir($path)) {
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $item = $path . '/' . $entry;
        if (is_dir($item) && !is_link($item)) {
            $removeTree($item);
        } else {
            @unlink($item);
        }
    }
    @rmdir($path);
};

try {
    foreach (db()->query("SELECT slug, name, version, description, author FROM plugins WHERE status = 'active'")->get() ?: [] as $active) {
        $activeSlug = (string)$active['slug'];
        if ($activeSlug === $slug) {
            continue;
        }
        $activeRoot = $root . '/' . $activeSlug;
        if (!mkdir($activeRoot, 0700, true) && !is_dir($activeRoot)) {
            throw new RuntimeException('Could not create active plugin stub.');
        }
        file_put_contents($activeRoot . '/plugin.json', json_encode([
            'name' => (string)$active['name'],
            'slug' => $activeSlug,
            'version' => (string)$active['version'],
            'description' => (string)($active['description'] ?? ''),
            'author' => (string)($active['author'] ?? ''),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $class = 'FireballPlugin' . str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $activeSlug)));
        file_put_contents($activeRoot . '/Plugin.php', "<?php\n\nuse FBL\\Plugins\\PluginInterface;\n\nfinal class {$class} implements PluginInterface\n{\n    public function install(): void {}\n    public function uninstall(): void {}\n    public function activate(): void {}\n    public function deactivate(): void {}\n    public function boot(): void {}\n}\n");
    }

    if (!mkdir($migrationRoot, 0700, true) && !is_dir($migrationRoot)) {
        throw new RuntimeException('Could not create plugin fixture directory.');
    }
    file_put_contents($pluginRoot . '/plugin.json', json_encode([
        'name' => 'Pending migration fixture',
        'slug' => $slug,
        'version' => '2.0.0',
        'type' => 'system',
        'description' => 'Migration lifecycle fixture',
        'author' => 'Tests',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    file_put_contents($pluginRoot . '/Plugin.php', <<<'PHP'
<?php

use FBL\Plugins\PluginInterface;

final class FireballPluginPendingMigrationFixture implements PluginInterface
{
    public function install(): void {}
    public function uninstall(): void {}
    public function activate(): void {}
    public function deactivate(): void {}
    public function boot(): void {}
}
PHP);
    file_put_contents(
        $migrationRoot . '/001_create_fixture.sql',
        "CREATE TABLE IF NOT EXISTS {$table} (id INT UNSIGNED NOT NULL AUTO_INCREMENT, PRIMARY KEY (id)) ENGINE=InnoDB"
    );

    db()->query("DROP TABLE IF EXISTS {$table}");
    db()->query('DELETE FROM plugin_migrations WHERE plugin_slug = ?', [$slug]);
    db()->query('DELETE FROM plugins WHERE slug = ?', [$slug]);
    db()->query(
        "INSERT INTO plugins
            (slug, name, version, description, author, status, installed_at, activated_at, updated_at)
         VALUES (?, ?, ?, ?, ?, 'active', ?, ?, ?)",
        [$slug, 'Old fixture', '1.0.0', '', '', $now, $now, $now]
    );

    $manager = new FBL\Plugins\PluginManager($root);
    $manager->bootActivePlugins($app->router);

    $tableExists = (bool)db()->query(
        'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1',
        [$table]
    )->getOne();
    $migrationCount = (int)db()->query(
        'SELECT COUNT(*) FROM plugin_migrations WHERE plugin_slug = ?',
        [$slug]
    )->getColumn();
    $plugin = db()->query('SELECT version, status FROM plugins WHERE slug = ? LIMIT 1', [$slug])->getOne();
    $assert($tableExists, 'Pending migration was not executed before plugin boot.');
    $assert($migrationCount === 1, 'Pending migration journal was not updated exactly once.');
    $assert(is_array($plugin) && $plugin['version'] === '2.0.0' && $plugin['status'] === 'active',
        'Installed plugin metadata or active status was not preserved.');

    $secondManager = new FBL\Plugins\PluginManager($root);
    $secondManager->bootActivePlugins($app->router);
    $assert((int)db()->query(
        'SELECT COUNT(*) FROM plugin_migrations WHERE plugin_slug = ?',
        [$slug]
    )->getColumn() === 1, 'An applied migration was executed or journaled twice.');

    echo json_encode([
        'status' => 'ok',
        'pending_migration_applied' => true,
        'active_status_preserved' => true,
        'second_boot_idempotent' => true,
    ], JSON_UNESCAPED_SLASHES), PHP_EOL;
} finally {
    db()->query("DROP TABLE IF EXISTS {$table}");
    db()->query('DELETE FROM plugin_migrations WHERE plugin_slug = ?', [$slug]);
    db()->query('DELETE FROM plugins WHERE slug = ?', [$slug]);
    $removeTree($root);
}
