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
