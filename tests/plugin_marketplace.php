<?php
declare(strict_types=1);

// Run with an isolated MySQL socket in tests/.tmp/plugin-marketplace-check-*/db.sock.
// Example: php -n tests/plugin_marketplace.php tests/.tmp/plugin-marketplace-check-XXXXXX
$project = dirname(__DIR__);
$scratch = realpath($argv[1] ?? '') ?: '';
if (!str_starts_with($scratch, __DIR__ . '/.tmp/plugin-marketplace-check-') || !is_file($scratch . '/db.pid')) {
    throw new RuntimeException('Pass the owned, running plugin marketplace test database directory.');
}
require $project . '/vendor/autoload.php';
$siteDirectory = 'site-' . bin2hex(random_bytes(6));
foreach (['plugins', 'storage', 'cache', 'config', 'public', 'sessions'] as $directory) {
    $directory = $siteDirectory . '/' . $directory;
    if (!is_dir($scratch . '/' . $directory)) mkdir($scratch . '/' . $directory, 0700, true);
}
foreach ([
    'ROOT' => $scratch . '/' . $siteDirectory, 'STORAGE' => $scratch . '/' . $siteDirectory . '/storage', 'CACHE' => $scratch . '/' . $siteDirectory . '/cache',
    'PLUGINS' => $scratch . '/' . $siteDirectory . '/plugins', 'CONFIG' => $scratch . '/' . $siteDirectory . '/config', 'WWW' => $scratch . '/' . $siteDirectory . '/public',
    'APP' => $project . '/app', 'DEFAULT_LOCALE' => 'ru', 'DEBUG' => 0, 'MULTILANGS' => 0,
    'LANGS' => ['ru' => [], 'en' => [], 'de' => [], 'zh-cn' => []],
] as $key => $value) define($key, $value);
ini_set('session.save_path', ROOT . '/sessions');
file_put_contents(CONFIG . '/version.php', '<?php return ["version" => "9.0.0"];');
FBL\Language::$lang_data = require APP . '/Languages/ru.php';
$appFixture = new class {
    public $db = null;
    public function get(string $key): mixed { return $key === 'lang' ? ['code' => 'ru'] : null; }
};
$cacheFixture = new FBL\Cache();
$hooksFixture = new FBL\Plugins\HookManager();
$requestFixture = new FBL\Request('/admin/plugins/catalog');
function app() { return $GLOBALS['appFixture']; }
function cache() { return $GLOBALS['cacheFixture']; }
function db() { return $GLOBALS['databaseFixture']; }
function plugin_manager() { return $GLOBALS['manager']; }
function request() { return $GLOBALS['requestFixture']; }
function add_filter(string $hook, callable $callback, int $priority = 10): void { $GLOBALS['hooksFixture']->addFilter($hook, $callback, $priority); }
function base_url(string $path): string { return $path; }
function asset_versioned_url(string $url, string $path): string { return $url; }
function return_translation(string $key): string { return FBL\Language::$lang_data[$key] ?? $key; }
function print_translation(string $key): string { return return_translation($key); }
function log_error_details(string $message, array $context = [], ?Throwable $error = null): void {}
function htmlSC(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function base_href(string $path): string { return $path; }
function get_csrf_field(): string { return '<input type="hidden" name="csrf" value="fixture-token">'; }
function view(?string $template = null, array $data = []) {
    if ($template !== null) {
        $GLOBALS['rendered_view'] = ['template' => $template, 'data' => $data];
        return '';
    }
    return new class {
        public function renderPartial(string $name, array $data = []): string {
            if ($name === 'admin/shell_close') return '</main>';
            return '<main><h1>' . htmlSC($data['title']) . '</h1><p>' . htmlSC($data['subtitle']) . '</p>' . $data['actions'];
        }
    };
}
$pdo = new PDO('mysql:unix_socket=' . $scratch . '/db.sock;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec('CREATE DATABASE fbl_plugin_marketplace CHARACTER SET utf8mb4');
$pdo->exec('USE fbl_plugin_marketplace');
$databaseFixture = new FBL\Database($pdo);
$manager = new FBL\Plugins\PluginManager(PLUGINS);
App\Services\SchemaMigration::run(fn() => (new ReflectionMethod($manager, 'ensureSchema'))->invoke($manager));

final class MarketplaceSettings extends App\Models\SiteSetting
{
    public function all(): array { return ['updater_github_token' => 'fixture-invalid-token']; }
}
final class MarketplaceProbe extends App\Services\PluginUpdateService
{
    public array $packages = [];
    public array $requests = [];
    public array $downloads = [];
    public bool $offline = false;
    public string $archiveMode = '';
    public string $commit;
    public function __construct(FBL\Plugins\PluginManager $manager) {
        parent::__construct($manager, PLUGINS, STORAGE . '/plugin-updates');
        $this->siteSettings = new MarketplaceSettings();
        $this->commit = str_repeat('a', 40);
    }
    protected function httpGet(string $url, array $headers = []): array {
        $this->requests[] = ['url' => $url, 'headers' => $headers];
        if ($this->offline) throw new RuntimeException('Authorization: Bearer fixture-invalid-token');
        if (str_contains($url, '.git/info/refs?')) return ['status_code' => 200, 'body' => $this->commit . " refs/heads/main\0\n"];
        if (str_contains($url, '/contents/plugins?ref=' . $this->commit)) {
            $entries = array_map(static fn(string $slug): array => ['name' => $slug, 'type' => 'dir'], array_keys($this->packages));
            $entries[] = ['name' => '../escape', 'type' => 'dir'];
            $entries[] = ['name' => 'README.md', 'type' => 'file'];
            $entries[] = ['name' => 'without-manifest', 'type' => 'dir'];
            return ['status_code' => 200, 'body' => json_encode($entries, JSON_THROW_ON_ERROR)];
        }
        if (str_starts_with($url, 'https://raw.githubusercontent.com/Samkmv/FIREBALL_CMS/' . $this->commit . '/plugins/')) {
            $slug = basename(dirname((string)parse_url($url, PHP_URL_PATH)));
            if (!isset($this->packages[$slug])) return ['status_code' => 404, 'body' => 'Missing'];
            return ['status_code' => 200, 'body' => $this->packages[$slug]['plugin.json']];
        }
        throw new RuntimeException('Unexpected fixture HTTP request: ' . $url);
    }
    protected function downloadPluginArchive(array $config, string $commit, string $destination): void {
        $this->downloads[] = ['slug' => basename($config['path']), 'commit' => $commit];
        if ($commit !== $this->commit) throw new RuntimeException('Archive must use the pinned metadata revision');
        $zip = new ZipArchive();
        $zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $slug = basename($config['path']);
        foreach ($this->packages[$slug] as $name => $content) {
            if ($this->archiveMode === 'mismatch' && $name === 'plugin.json') $content = str_replace('1.0.0', '0.1.0', $content);
            if ($this->archiveMode === 'syntax' && $name === 'Plugin.php') $content = '<?php bad @ syntax';
            $zip->addFromString('repository/plugins/' . $slug . '/' . $name, $content);
        }
        if ($this->archiveMode === 'traversal') $zip->addFromString('../outside.php', '<?php echo "escape";');
        if ($this->archiveMode === 'symlink') {
            $zip->addFromString('repository/plugins/' . $slug . '/linked.php', '../../outside');
            $zip->setExternalAttributesName('repository/plugins/' . $slug . '/linked.php', ZipArchive::OPSYS_UNIX, 0120777 << 16);
        }
        $zip->close();
    }
}
function marketplacePackage(string $slug, array $extra = []): array {
    $identifier = str_replace('-', '_', $slug);
    $class = 'MarketplaceFixture_' . $identifier;
    $metadata = array_merge([
        'slug' => $slug, 'name' => 'Fixture ' . $slug, 'version' => '1.0.0', 'class' => $class,
        'name_i18n' => ['ru' => 'Плагин ' . $slug], 'description' => '<script>unsafe</script>',
        'author' => 'Fixture', 'requires' => ['cms' => '>=8.0.0', 'php' => '>=8.2'],
    ], $extra);
    return [
        'plugin.json' => json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        'Plugin.php' => '<?php class ' . $class . ' implements \\FBL\\Plugins\\PluginInterface {
            public function install(): void { if (is_file(ROOT . "/fail-install")) throw new \\RuntimeException("Fixture install failure"); $GLOBALS["fixture_installs"] = ($GLOBALS["fixture_installs"] ?? 0) + 1; }
            public function activate(): void { $GLOBALS["fixture_activations"] = true; }
            public function deactivate(): void {}
            public function uninstall(): void {}
            public function boot(): void { $GLOBALS["fixture_boots"] = true; }
        }',
        'migrations/001_create_fixture.sql' => 'CREATE TABLE marketplace_' . $identifier . ' (id INT PRIMARY KEY);',
        'routes.php' => '<?php $router->get("/marketplace-fixture-' . $slug . '", static fn() => "Fixture page");',
        'assets/fixture.js' => 'window.marketplaceFixture = true;',
    ];
}
$checks = [];
function marketplaceCheck(string $name, bool $condition): void {
    if (!$condition) throw new RuntimeException('FAILED: ' . $name);
    $GLOBALS['checks'][] = $name;
}
function marketplaceFailure(callable $operation): string {
    try { $operation(); } catch (Throwable $error) { return $error->getMessage(); }
    throw new RuntimeException('Expected an operation to fail');
}
function marketplaceItem(array $catalog, string $slug): array {
    foreach ($catalog['plugins'] as $item) if ($item['slug'] === $slug) return $item;
    throw new RuntimeException('Catalog item missing: ' . $slug);
}
function marketplaceLocal(string $slug, array $files): void {
    foreach ($files as $name => $contents) {
        $file = PLUGINS . '/' . $slug . '/' . $name;
        if (!is_dir(dirname($file))) mkdir(dirname($file), 0700, true);
        file_put_contents($file, $contents);
    }
}
function marketplaceRender(array $catalog): string {
    ob_start();
    require dirname(__DIR__) . '/app/Views/themes/default/admin/plugin_catalog.php';
    return (string)ob_get_clean();
}
try {
    $probe = new MarketplaceProbe($manager);
    $probe->packages['new-plugin'] = marketplacePackage('new-plugin', ['category' => 'media', 'description' => 'Shared audio collection', 'author' => 'Example Studio']);
    $probe->packages['future-php'] = marketplacePackage('future-php', ['category' => 'network', 'requires' => ['php' => '>=99.0']]);
    $catalog = $probe->marketplace($manager->all());
    marketplaceCheck('New remote plugins appear without local directories', count($catalog['plugins']) === 2 && !is_dir(PLUGINS . '/new-plugin'));
    marketplaceCheck('Remote names use the current UI language', marketplaceItem($catalog, 'new-plugin')['name'] === 'Плагин new-plugin');
    marketplaceCheck('Incompatible PHP is visible before installation', marketplaceItem($catalog, 'future-php')['incompatible'] !== '');
    marketplaceCheck('Catalog reads do not install or activate code', $manager->all() === [] && empty($GLOBALS['fixture_boots']));
    marketplaceCheck('Public discovery ignores an invalid configured token', !array_filter($probe->requests, static fn(array $request): bool => (bool)array_filter($request['headers'], static fn(string $header): bool => str_starts_with($header, 'Authorization:'))));
    marketplaceCheck('Manifest categories and category counts are available', marketplaceItem($catalog, 'new-plugin')['category'] === 'media' && $catalog['categories']['media']['count'] === 1 && $catalog['categories']['network']['count'] === 1 && $catalog['total'] === 2);
    foreach (['commerce' => 'commerce', 'subscriptions' => 'commerce', 'toy-car-rental' => 'commerce', 'reel-player' => 'media', 'camera-manager' => 'media', 'calendar' => 'productivity', 'vpn-manager-v2' => 'network'] as $slug => $category) {
        marketplaceCheck('Legacy plugin category: ' . $slug, App\Services\PluginCatalogCategories::resolve(['slug' => $slug]) === $category);
    }
    marketplaceCheck('Unknown categories fall back safely', App\Services\PluginCatalogCategories::resolve(['slug' => 'custom', 'category' => '<script>']) === 'other');
    marketplaceCheck('Explicit category overrides the legacy mapping', App\Services\PluginCatalogCategories::resolve(['slug' => 'calendar', 'category' => 'media']) === 'media');
    marketplaceCheck('Category filtering excludes unrelated plugins', array_column($probe->marketplace([], false, 'media')['plugins'], 'slug') === ['new-plugin']);
    marketplaceCheck('Localized search ignores case and surrounding spaces', array_column($probe->marketplace([], false, '', '  ПЛАГИН NEW-PLUGIN  ')['plugins'], 'slug') === ['new-plugin']);
    foreach (['NEW-PLUGIN', 'audio', 'studio'] as $query) {
        marketplaceCheck('Search matches plugin identifier, description and author: ' . $query, array_column($probe->marketplace([], false, '', $query)['plugins'], 'slug') === ['new-plugin']);
    }
    $filtered = $probe->marketplace([], false, 'media', 'future-php');
    marketplaceCheck('Search combines with category filtering and updates counts', $filtered['plugins'] === [] && $filtered['total'] === 1 && $filtered['categories']['network']['count'] === 1 && $filtered['categories']['media']['count'] === 0);
    marketplaceCheck('Unknown filter categories reset to all categories', count($probe->marketplace([], false, '../unknown')['plugins']) === 2);
    marketplaceCheck('Search length is bounded', mb_strlen($probe->marketplace([], false, '', str_repeat('я', 250))['query']) === 200);
    $requestFixture->get = ['category' => 'media', 'q' => 'audio'];
    (new App\Controllers\PluginController())->marketplace();
    marketplaceCheck('Catalog controller applies search and category parameters', array_column($GLOBALS['rendered_view']['data']['catalog']['plugins'], 'slug') === ['new-plugin']);
    $requestFixture->get = ['category' => ['media'], 'q' => ['invalid']];
    (new App\Controllers\PluginController())->marketplace();
    marketplaceCheck('Array-shaped query parameters are handled safely', $GLOBALS['rendered_view']['data']['catalog']['category'] === '' && $GLOBALS['rendered_view']['data']['catalog']['query'] === '');
    $requestFixture->get = [];
    $requestFixture->post = ['category' => 'media', 'q' => 'audio & video'];
    $returnPath = new ReflectionMethod(App\Controllers\PluginController::class, 'catalogReturnPath');
    marketplaceCheck('Refresh and failed installation preserve filters in an encoded local URL', $returnPath->invoke(new App\Controllers\PluginController()) === '/admin/plugins/catalog?category=media&q=audio%20%26%20video');
    $requestFixture->post = ['category' => ['media'], 'q' => ['invalid']];
    marketplaceCheck('Malformed return parameters cannot change the catalog destination', $returnPath->invoke(new App\Controllers\PluginController()) === '/admin/plugins/catalog');
    $requestFixture->post = [];
    $filteredHtml = marketplaceRender($probe->marketplace([], false, 'media', 'audio'));
    marketplaceCheck('Category links preserve the search query', str_contains($filteredHtml, 'category=network&amp;q=audio'));
    marketplaceCheck('Search form and refresh retain the selected category', str_contains($filteredHtml, 'name="category" value="media"') && str_contains($filteredHtml, 'role="search"') && str_contains($filteredHtml, 'value="audio"'));
    marketplaceCheck('Selected categories and card labels are visible', str_contains($filteredHtml, 'aria-current="page"') && str_contains($filteredHtml, 'Медиа и камеры'));
    $noResultsHtml = marketplaceRender($probe->marketplace([], false, 'media', '\"><script>bad</script>'));
    marketplaceCheck('Empty search results provide an explanation and escape the query', str_contains($noResultsHtml, return_translation('admin_plugin_catalog_no_results')) && str_contains($noResultsHtml, '&lt;script&gt;bad&lt;/script&gt;') && !str_contains($noResultsHtml, '<script>bad</script>'));
    $requestCount = count($probe->requests);
    $probe->marketplace($manager->all());
    marketplaceCheck('Catalog cache prevents repeated network calls', count($probe->requests) === $requestCount);
    $probe->offline = true;
    $offline = $probe->marketplace($manager->all(), true);
    marketplaceCheck('Network failure preserves the last successful catalog', count($offline['plugins']) === 2 && $offline['error'] !== '');
    marketplaceCheck('Catalog errors hide credentials', !str_contains($offline['error'], 'fixture-invalid-token'));
    $requestCount = count($probe->requests);
    $probe->marketplace([]);
    marketplaceCheck('Failed discovery is throttled', count($probe->requests) === $requestCount);
    $probe->offline = false;
    $probe->packages['added-later'] = marketplacePackage('added-later');
    $catalog = $probe->marketplace([], true);
    marketplaceCheck('Refresh discovers plugins added after the initial visit', count($catalog['plugins']) === 3 && $catalog['error'] === '');
    marketplaceCheck('Incompatible packages cannot be installed', marketplaceFailure(fn() => $probe->installFromMarketplace('future-php')) !== '' && !file_exists(PLUGINS . '/future-php'));
    $probe->installFromMarketplace('new-plugin');
    $local = $manager->all();
    marketplaceCheck('Install downloads the pinned package and applies migrations', $probe->downloads[0]['commit'] === $probe->commit && db()->query('SELECT COUNT(*) FROM plugin_migrations WHERE plugin_slug = ?', ['new-plugin'])->getColumn() === 1);
    marketplaceCheck('Installed plugin stays inactive until explicitly activated', $local[0]['installed'] && $local[0]['status'] === 'inactive' && empty($GLOBALS['fixture_boots']));
    $receipt = (new App\Services\ManagedPluginInstallations(ROOT))->receipt('new-plugin');
    marketplaceCheck('Installation receipt records the official repository and revision', $receipt['origin']['repository'] === 'Samkmv/FIREBALL_CMS' && $receipt['origin']['commit'] === $probe->commit);
    marketplaceCheck('Receipt matches all installed files', (new App\Services\ManagedPluginInstallations(ROOT))->isVerified('new-plugin'));
    $downloads = count($probe->downloads);
    marketplaceCheck('Repeated installation is rejected without another download', marketplaceFailure(fn() => $probe->installFromMarketplace('new-plugin')) !== '' && count($probe->downloads) === $downloads);
    $catalog = $probe->marketplace($manager->all());
    marketplaceCheck('Installation immediately removes the plugin from the cached catalog', !in_array('new-plugin', array_column($catalog['plugins'], 'slug'), true));
    marketplaceCheck('Installed plugins are also excluded from search and category counts', $probe->marketplace($manager->all(), false, 'media', 'new-plugin')['total'] === 0);
    $manager->activate('new-plugin');
    $router = new FBL\Router(new FBL\Request('/'), new FBL\Response());
    (new FBL\Plugins\PluginManager(PLUGINS))->bootActivePlugins($router);
    marketplaceCheck('Activation exposes the plugin runtime and public route', !empty($GLOBALS['fixture_boots']) && in_array('/marketplace-fixture-new-plugin', array_column($router->getRoutes(), 'path'), true));
    marketplaceCheck('Active plugins remain in the installed list and stay out of the catalog', marketplaceItem(['plugins' => $manager->all()], 'new-plugin')['status'] === 'active'
        && !in_array('new-plugin', array_column($probe->marketplace($manager->all())['plugins'], 'slug'), true));
    marketplaceLocal('manual-plugin', marketplacePackage('manual-plugin', ['category' => 'commerce']));
    $probe->offline = true;
    marketplaceCheck('Uninstalled local plugins remain available in the catalog offline', marketplaceItem($probe->marketplace($manager->all()), 'manual-plugin')['local']);
    marketplaceCheck('Local plugin metadata participates in search and categories offline', array_column($probe->marketplace($manager->all(), false, 'commerce', 'manual-plugin')['plugins'], 'slug') === ['manual-plugin']);
    (new App\Controllers\PluginController())->index();
    marketplaceCheck('Plugins page excludes uninstalled local packages', array_column($GLOBALS['rendered_view']['data']['plugins'], 'slug') === ['new-plugin']);
    $probe->installFromMarketplace('manual-plugin');
    marketplaceCheck('Manually added plugins install without network access', count($probe->downloads) === $downloads && marketplaceItem(['plugins' => $manager->all()], 'manual-plugin')['installed']);
    (new App\Controllers\PluginController())->index();
    $installedSlugs = array_column($GLOBALS['rendered_view']['data']['plugins'], 'slug');
    marketplaceCheck('Plugins page includes both active and inactive installed plugins', $installedSlugs === ['manual-plugin', 'new-plugin']);
    marketplaceCheck('Inactive installed plugins are hidden from the catalog', !in_array('manual-plugin', array_column($probe->marketplace($manager->all())['plugins'], 'slug'), true));
    $probe->offline = false;
    foreach (['mismatch', 'syntax', 'traversal', 'symlink'] as $mode) {
        $slug = 'reject-' . $mode;
        $probe->packages[$slug] = marketplacePackage($slug);
        $probe->archiveMode = $mode;
        marketplaceCheck('Unsafe package rejected: ' . $mode, marketplaceFailure(fn() => $probe->installFromMarketplace($slug)) !== '' && !file_exists(PLUGINS . '/' . $slug));
        marketplaceCheck('Rejected package leaves no staging/work directory: ' . $mode, (glob(PLUGINS . '/.*.install-*') ?: []) === [] && (glob(STORAGE . '/plugin-updates/work/*') ?: []) === []);
    }
    $probe->archiveMode = '';
    $probe->packages['failed-hook'] = marketplacePackage('failed-hook');
    file_put_contents(ROOT . '/fail-install', 'fixture');
    marketplaceCheck('Migration hook failure is reported', str_contains(marketplaceFailure(fn() => $probe->installFromMarketplace('failed-hook')), 'Fixture install failure'));
    marketplaceCheck('Failed installation does not leave a successful database row after MySQL DDL', db()->query('SELECT COUNT(*) FROM plugins WHERE slug = ?', ['failed-hook'])->getColumn() === 0);
    marketplaceCheck('Validated files and migration progress remain available for retry', is_file(PLUGINS . '/failed-hook/Plugin.php') && db()->query('SELECT COUNT(*) FROM plugin_migrations WHERE plugin_slug = ?', ['failed-hook'])->getColumn() === 1);
    unlink(ROOT . '/fail-install');
    $downloads = count($probe->downloads);
    $probe->installFromMarketplace('failed-hook');
    marketplaceCheck('Retry succeeds without re-downloading or reapplying completed migrations', count($probe->downloads) === $downloads && marketplaceItem(['plugins' => $manager->all()], 'failed-hook')['installed']);
    foreach (['../escape', '', 'new-plugin/other', "new-plugin\n"] as $slug) {
        marketplaceCheck('Invalid slug rejected: ' . json_encode($slug), marketplaceFailure(fn() => $probe->installFromMarketplace($slug)) !== '');
    }
    file_put_contents(PLUGINS . '/existing-file', 'must remain intact');
    marketplaceCheck('Existing local files cannot be overwritten', marketplaceFailure(fn() => $probe->installFromMarketplace('existing-file')) !== '' && file_get_contents(PLUGINS . '/existing-file') === 'must remain intact');
    symlink(ROOT . '/missing-target', PLUGINS . '/existing-link');
    marketplaceCheck('Dangling symlinks cannot be replaced', marketplaceFailure(fn() => $probe->installFromMarketplace('existing-link')) !== '' && is_link(PLUGINS . '/existing-link'));
    unlink(PLUGINS . '/existing-link');
    $catalog = $probe->marketplace($manager->all());
    $html = marketplaceRender($catalog);
    marketplaceCheck('Catalog uses the requested Russian title', str_contains($html, 'Каталог плагинов') && !str_contains($html, '>Marketplace<'));
    marketplaceCheck('Remote descriptions are escaped', str_contains($html, '&lt;script&gt;unsafe&lt;/script&gt;') && !str_contains($html, '<script>unsafe</script>'));
    marketplaceCheck('Catalog forms include CSRF fields and installation actions', str_contains($html, 'fixture-token') && str_contains($html, '/admin/plugins/catalog/install'));
    marketplaceCheck('Catalog renders no installed plugin cards', !str_contains($html, 'id="plugin-new-plugin"') && !str_contains($html, 'id="plugin-manual-plugin"') && !str_contains($html, 'id="plugin-failed-hook"'));
    marketplaceCheck('Catalog still has no GitHub link', !str_contains($html, 'href="https://github.com/'));
    $app = new class(new FBL\Router(new FBL\Request('/'), new FBL\Response())) {
        public function __construct(public FBL\Router $router) {}
        public function isInstalled(): bool { return false; }
    };
    require $project . '/config/routes.php';
    $catalogRoutes = array_filter($app->router->getRoutes(), static fn(array $route): bool => str_starts_with($route['path'], '/admin/plugins/catalog'));
    marketplaceCheck('Catalog page, refresh and installation routes are registered', count($catalogRoutes) === 3);
    foreach ($catalogRoutes as $route) {
        marketplaceCheck('Catalog route requires authentication and admin rights: ' . $route['path'], $route['middleware'] === ['auth', 'admin'] && $route['needCSRFToken'] === true);
    }
    $lock = FBL\UpdateLock::acquire(STORAGE, 0);
    $lock->release();
    marketplaceCheck('All installation paths release the update lock', true);
    foreach (['ru', 'en', 'de', 'zh-cn'] as $locale) {
        $translations = require APP . '/Languages/' . $locale . '.php';
        marketplaceCheck('Catalog translations complete: ' . $locale, count(array_filter(array_keys($translations), static fn(string $key): bool => str_starts_with($key, 'admin_plugin_marketplace_'))) === 16);
        marketplaceCheck('Category and search translations complete: ' . $locale, count(array_filter(array_keys($translations), static fn(string $key): bool => str_starts_with($key, 'admin_plugin_catalog_') || str_starts_with($key, 'admin_plugin_category_'))) === 12);
    }
    $artifacts = __DIR__ . '/artifacts/plugin-catalog';
    if (!is_dir($artifacts)) mkdir($artifacts, 0700, true);
    file_put_contents($artifacts . '/checks.json', json_encode(['passed' => count($checks), 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    echo 'PASS ' . count($checks) . " plugin catalog integration checks\n";
} finally {
    $pdo->exec('DROP DATABASE fbl_plugin_marketplace');
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(ROOT, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        if ($file->isDir() && !$file->isLink()) rmdir($file->getPathname());
        else unlink($file->getPathname());
    }
    rmdir(ROOT);
}
