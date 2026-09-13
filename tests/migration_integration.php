<?php

declare(strict_types=1);

// Explicit integration test: only a randomly named disposable database is modified.
if (PHP_SAPI !== 'cli' || !in_array('--disposable-database', $argv, true)) {
    exit("Usage: php tests/migration_integration.php --disposable-database\n");
}
$temporary = sys_get_temp_dir() . '/fireball-migrations-' . bin2hex(random_bytes(6));
define('CACHE', $temporary . '/cache');
define('STORAGE', $temporary . '/storage');
mkdir(CACHE, 0700, true);
mkdir(STORAGE, 0700, true);
require dirname(__DIR__) . '/config/config.php';
require ROOT . '/vendor/autoload.php';
require HELPERS . '/helpers.php';
$schema = 'fireball_test_' . bin2hex(random_bytes(8));
$dsn = 'mysql:host=' . DB_SETTINGS['host'] . ';charset=utf8mb4';
if (!empty(DB_SETTINGS['port'])) $dsn .= ';port=' . (int)DB_SETTINGS['port'];
$pdo = new PDO($dsn, DB_SETTINGS['username'], DB_SETTINGS['password'], DB_SETTINGS['options']);
$app = new FBL\Application(false);
$created = false;
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) throw new RuntimeException($message);
};
try {
    $pdo->exec('CREATE DATABASE `' . $schema . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $created = true;
    $pdo->exec('USE `' . $schema . '`');
    $app->db = new FBL\Database($pdo);
    $runner = new App\Services\MigrationRunner();
    $first = $runner->run();
    $assert(count($first) > 0, 'Empty database is upgraded by explicit migrations');
    $assert($runner->run() === [], 'Repeated migration is idempotent');
    $assert(App\Services\SchemaManifest::hasTable('search_index_tokens'), 'Search schema is present');
    $assert(in_array('session_version', App\Services\SchemaManifest::columns('users'), true), 'Existing security columns are retained');
    $app->plugins = new FBL\Plugins\PluginManager();
    $app->plugins->install('vpn-manager-v2');
    $assert(App\Services\SchemaManifest::hasTable('vpn_v2_subscription_nodes'), 'VPN migrations work without HTTP boot');
    $before = (int)db()->query("SELECT COUNT(*) FROM plugin_migrations WHERE plugin_slug = 'vpn-manager-v2'")->getColumn();
    $app->plugins->completeUpdate('vpn-manager-v2');
    $after = (int)db()->query("SELECT COUNT(*) FROM plugin_migrations WHERE plugin_slug = 'vpn-manager-v2'")->getColumn();
    $assert($before > 0 && $before === $after, 'Explicit plugin update preserves the migration journal');
    $result = App\Services\SearchMaintenance::rebuild($app->db);
    $assert(array_keys($result) === ['pages', 'posts', 'products'], 'Installation can build all core search providers');
    $app->set('search.registry', new App\Search\SearchRegistry());
    search_registry()->registerProvider('pages', App\Search\Providers\PageSearchProvider::class);
    $page = new App\Search\SearchDocument(type:'page', entityId:991, title:'Тест 1234', content:'Проверка индекса', url:'/test', module:'pages');
    search_indexer()->save('pages', $page);
    $app->theme = new FBL\ThemeManager();
    $engine = new App\Search\SearchEngine(search_registry(), search_indexer(), new App\Search\SearchConfig([]));
    $assert($engine->search('1234')['total'] === 1, 'Batched tokens remain searchable');
    search_indexer()->removeEntity('pages', 991);
    $assert($engine->search('1234')['total'] === 0, 'Entity removal invalidates search immediately');
    $pages = new App\Models\Page();
    $data = ['title'=>'Проверкауникальная', 'slug'=>'performance-fixture', 'content'=>'', 'is_published'=>1, 'show_in_header'=>1];
    $id = $pages->createPage($data);
    $assert($engine->search('Проверкауникальная')['total'] === 1, 'Creating content updates search without a full rebuild');
    $navigation = (new App\Services\PublicLayoutContext())->navigation();
    $assert(count($navigation['headerPageLinks']) === 1, 'New page reaches cached navigation');
    $data['title'] = 'Обновлениеуникальное';
    $pages->updatePage($id, $data);
    $assert($engine->search('Проверкауникальная')['total'] === 0 && $engine->search('Обновлениеуникальное')['total'] === 1, 'Editing content replaces indexed terms immediately');
    $pages->deletePage($id);
    $assert($engine->search('Обновлениеуникальное')['total'] === 0, 'Deleting content removes indexed results');
    $assert((new App\Services\PublicLayoutContext())->navigation()['headerPageLinks'] === [], 'Deleting content invalidates navigation');
    $settings = new App\Models\SiteSetting();
    $settings->setMany(['site_title'=>'Integration setting']);
    $assert((new App\Models\SiteSetting())->get('site_title') === 'Integration setting', 'Settings writes invalidate all request instances');
    echo "Migration integration: {$checks} checks passed on disposable database.\n";
} finally {
    $app->session->close();
    if ($created) $pdo->exec('DROP DATABASE `' . $schema . '`');
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temporary, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $entry) $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    rmdir($temporary);
}
