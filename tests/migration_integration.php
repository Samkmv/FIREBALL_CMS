<?php

declare(strict_types=1);

// Explicit integration test: only a randomly named disposable database is modified.
if (PHP_SAPI !== 'cli' || !in_array('--disposable-database', $argv, true)) {
    exit("Usage: php tests/migration_integration.php --disposable-database\n");
}
$temporary = sys_get_temp_dir() . '/fireball-migrations-' . bin2hex(random_bytes(6));
define('CACHE', $temporary . '/cache');
define('STORAGE', $temporary . '/storage');
define('ERROR_LOGS', $temporary . '/error.log');
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
    $provider = new class implements App\Search\Contracts\SearchProviderInterface {
        public string $mode = 'ok';
        public function getDocuments(): iterable {
            if ($this->mode === 'empty') return;
            if ($this->mode === 'malformed') { yield 'bad'; return; }
            yield new App\Search\SearchDocument(type:'fixture', entityId:$this->mode, title:'Replacement ' . $this->mode);
            if ($this->mode === 'throw') throw new RuntimeException('Provider unavailable after first document');
            if ($this->mode === 'database') db()->query('SELECT * FROM deliberately_missing_fixture_table');
        }
        public function getDocument(int|string $entityId): ?App\Search\SearchDocument { return null; }
        public function canAccess(App\Search\SearchDocument $document, array $context = []): bool { return true; }
    };
    $registry = new App\Search\SearchRegistry();
    $registry->registerProvider('fixture', $provider);
    $indexer = new App\Search\SearchIndexer($registry);
    $assert($indexer->reindexProvider('fixture') === 1, 'Successful staging publishes documents');
    $snapshot = static fn() => [
        db()->query("SELECT * FROM search_index WHERE provider = 'fixture'")->get(),
        db()->query("SELECT * FROM search_index_state WHERE provider = 'fixture'")->get(),
        db()->query("SELECT t.* FROM search_index_tokens t JOIN search_index i ON i.id=t.search_index_id WHERE i.provider='fixture'")->get(),
    ];
    $before = $snapshot();
    $pdo->exec("CREATE TRIGGER reject_fixture BEFORE INSERT ON search_index FOR EACH ROW BEGIN IF NEW.provider = 'fixture' AND NEW.entity_id = 'writefail' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Fixture write failure'; END IF; END");
    foreach (['throw', 'malformed', 'empty', 'database', 'writefail'] as $mode) {
        $provider->mode = $mode;
        $failed = false;
        try { $indexer->reindexProvider('fixture'); } catch (Throwable) { $failed = true; }
        $assert($failed && $snapshot() === $before, 'Failure preserves documents, tokens and state: ' . $mode);
    }
    $provider->mode = 'new';
    $assert($indexer->reindexProvider('fixture') === 1 && $snapshot()[0][0]['entity_id'] === 'new', 'Nonempty rebuild replaces old set');
    $provider->mode = 'empty';
    $assert($indexer->reindexProvider('fixture', true) === 0 && $snapshot()[0] === [], 'Explicit authoritative empty clears provider');
    $assert((int)$snapshot()[1][0]['document_count'] === 0, 'Successful empty rebuild publishes state');
    echo "Migration integration: {$checks} checks passed on disposable database.\n";
} finally {
    $app->session->close();
    if ($created) $pdo->exec('DROP DATABASE `' . $schema . '`');
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temporary, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $entry) $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    rmdir($temporary);
}
