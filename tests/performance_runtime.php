<?php

declare(strict_types=1);

$root = dirname(__DIR__);
define('CACHE', sys_get_temp_dir() . '/fireball-cache-test-' . bin2hex(random_bytes(5)));
define('STORAGE', CACHE . '/storage');
mkdir(STORAGE, 0700, true);
require $root . '/config/config.php';
require $root . '/vendor/autoload.php';
require HELPERS . '/helpers.php';
putenv('FIREBALL_PERFORMANCE_DEBUG=1');
FBL\PerformanceProfiler::start();
$app = new FBL\Application(false);
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) throw new RuntimeException($message);
};
try {
    $cache = $app->cache;
    foreach ([null, false, 0, '', ['nested'=>['a'=>1]]] as $index => $value) {
        $cache->set('value' . $index, $value, 20);
        $assert($cache->get('value' . $index, 'miss') === $value, 'Falsy values preserve their type');
    }
    $cache->set('shared', ['a'=>1]);
    $result = $cache->get('shared');
    $result['a'] = 99;
    $assert((new FBL\Cache())->get('shared') === ['a'=>1], 'Reads cannot mutate shared values');
    $reads = FBL\PerformanceProfiler::snapshot()['counts']['cache_file_reads'] ?? 0;
    for ($i = 0; $i < 10; $i++) $cache->get('shared');
    $assert((FBL\PerformanceProfiler::snapshot()['counts']['cache_file_reads'] ?? 0) === $reads, 'L1 eliminates repeat file reads');
    $cache->remove('shared');
    $assert((new FBL\Cache())->get('shared', 'missing') === 'missing', 'Invalidation reaches other cache instances');
    file_put_contents(CACHE . '/' . md5('expired') . '.txt', serialize(['data'=>'old', 'end_time'=>time()-1]));
    $assert($cache->get('expired', 'missing') === 'missing', 'Expired file misses');
    file_put_contents(CACHE . '/' . md5('corrupt') . '.txt', 'bad payload');
    $assert($cache->get('corrupt', 'missing') === 'missing', 'Corrupt file misses');
    $cache->set('default-ttl', 'valid', 0);
    $assert($cache->get('default-ttl') === 'valid', 'Legacy nonpositive TTL fallback preserved');
    $cache->set('short', 'short', 1);
    $memory = new ReflectionProperty(FBL\Cache::class, 'memory');
    $entries = $memory->getValue();
    $entries[md5('short')] = serialize(['data'=>'short', 'end_time'=>time()-1]);
    $memory->setValue(null, $entries);
    $assert($cache->get('short', 'missing') === 'missing', 'L1 honors TTL');

    $assets = App\Services\FrontendAssets::requirements('<article>Text only</article>');
    $assert($assets === [], 'Text pages have no optional libraries');
    $assets = App\Services\FrontendAssets::requirements('<video></video><pre><code>hi</code></pre><select data-select></select><div class="swiper"></div>');
    $assert(isset($assets['player'], $assets['highlight'], $assets['choices'], $assets['swiper']), 'Legacy markup detects required libraries');
    $assert(isset(App\Services\FrontendAssets::requirements('', ['player'])['player']), 'Explicit asset requirements supported');

    $assert(!isset(App\Services\FrontendAssets::requirements('<video data-player-native data-home-hero-video></video>')['player']), 'Native background video skips player libraries');
    $assert(isset(App\Services\FrontendAssets::requirements('<video data-player-native></video><audio controls></audio>')['player']), 'Other media still requests player libraries');
    $assert(isset(App\Services\FrontendAssets::requirements('<video data-player-native></video>', ['player'])['player']), 'Explicit player overrides native detection');

    $file = CACHE . '/asset.js';
    file_put_contents($file, 'first');
    $version1 = FBL\AssetManifest::version($file);
    file_put_contents($file, 'second');
    $assert(FBL\AssetManifest::version($file) === $version1, 'Production versions use manifest');
    FBL\AssetManifest::invalidate();
    $assert(FBL\AssetManifest::version($file) !== $version1, 'Asset edit invalidation refreshes version');

    foreach ([App\Models\User::class=>'ensureUsersTableExists', App\Models\Page::class=>'ensureSchema', App\Models\Post::class=>'ensureSchema', App\Models\SiteSetting::class=>'ensureTableExists', App\Models\Admin::class=>'ensureSchema', App\Repositories\AnalyticsRepository::class=>'ensureSchema', App\Services\PwaService::class=>'ensureTables', App\Services\NotificationService::class=>'ensureTables', FBL\Plugins\PluginManager::class=>'ensureSchema'] as $class=>$method) {
        // No database is available: any runtime SQL would fail this test.
        (new ReflectionMethod($class, $method))->invoke(new $class());
        $assert(true, $class . ' performs no runtime DDL');
    }
    $assert(!App\Services\SchemaMigration::isRunning(), 'Migration scope defaults off');
    try { App\Services\SchemaMigration::run(static function () { throw new RuntimeException('test'); }); } catch (RuntimeException) {}
    $assert(!App\Services\SchemaMigration::isRunning(), 'Migration scope restored after failure');

    require ROOT . '/plugins/vpn-manager-v2/Plugin.php';
    (new FireballPluginVpnManagerV2())->boot();
    $assert(true, 'VPN boot registers hooks without database or schema access');
    $assert((new FireballPluginVpnManagerV2()) instanceof FBL\Plugins\PluginMigrationInterface, 'VPN retains an explicit schema upgrade path');
    $pluginDirectory = CACHE . '/test-plugin';
    mkdir($pluginDirectory);
    file_put_contents($pluginDirectory . '/Plugin.php', '<?php');
    $migrationPlugin = new class implements FBL\Plugins\PluginInterface, FBL\Plugins\PluginMigrationInterface {
        public static bool $migrated = false;
        public function install(): void {}
        public function uninstall(): void {}
        public function activate(): void {}
        public function deactivate(): void {}
        public function boot(): void { throw new RuntimeException('Maintenance must not boot plugins.'); }
        public function migrateSchema(): void {
            if (!App\Services\SchemaMigration::isRunning()) throw new RuntimeException('Missing migration scope');
            self::$migrated = true;
        }
    };
    (new ReflectionMethod(FBL\Plugins\PluginManager::class, 'runMigrations'))->invoke(
        new FBL\Plugins\PluginManager(), ['path'=>realpath($pluginDirectory), 'slug'=>'test-plugin', 'class'=>$migrationPlugin::class]
    );
    $assert($migrationPlugin::$migrated, 'Custom migration runner owns upgrades, even without SQL directory');
    $assert(!App\Services\SchemaMigration::isRunning(), 'Plugin migration scope is restored');

    $searchDatabase = new class extends FBL\Database {
        public array $queries = [];
        public function __construct() {}
        public function query(string $query, array $params = []): static {
            $this->queries[] = [$query, $params];
            return $this;
        }
        public function get(): false|array { return []; }
        public function getColumn(): mixed { throw new RuntimeException('Search must not inspect index age or initiate a rebuild'); }
    };
    $app->db = $searchDatabase;
    $registry = new App\Search\SearchRegistry();
    $registry->registerProvider('pages', App\Search\Providers\PageSearchProvider::class);
    $indexer = new App\Search\SearchIndexer($registry);
    $result = (new App\Search\SearchEngine($registry, $indexer, new App\Search\SearchConfig([])))->search('Техническая');
    $assert($result['total'] === 0 && count($searchDatabase->queries) === 1, 'Even an empty index performs a bounded read-only search');
    $assert(str_contains($searchDatabase->queries[0][0], 'SELECT DISTINCT search_index_id'), 'Search reads tokens without maintenance queries');
    $searchDatabase->queries = [];
    (new ReflectionMethod(App\Search\SearchIndexer::class, 'replaceTokens'))->invoke($indexer, 7, ['title'=>implode(' ', array_map(static fn($i) => 'word'.$i, range(1, 601)))]);
    $assert(count($searchDatabase->queries) === 4, '601 tokens use three insert batches and one delete');
    $assert(count($searchDatabase->queries[1][1]) === 750 && count($searchDatabase->queries[3][1]) === 303, 'Token batches preserve all bound values');
    $app->db = null;

    $router = new FBL\Router(new FBL\Request('/'), new FBL\Response());
    foreach (['/', '/contacts', '/post/(?P<slug>[a-z0-9-]+)', '/sw.js'] as $path) $router->get($path, static fn() => 'ok');
    $routes = $router->getRoutes();
    $assert(preg_match($routes[0]['pattern'], '/') === 1, 'Root route pattern');
    $assert(preg_match($routes[1]['pattern'], '/de/contacts') === (MULTILANGS ? 1 : 0), 'Locale route syntax');
    $assert(preg_match($routes[2]['pattern'], '/post/test-slug', $matches) === 1 && $matches['slug'] === 'test-slug', 'Named dynamic parameters');
    $cache->clear();
    $assert($cache->get('value1', 'missing') === 'missing', 'Clear invalidates L1');
    echo "Performance runtime: {$checks} checks passed.\n";
} finally {
    $app->session->close();
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(CACHE, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $entry) $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    rmdir(CACHE);
}
