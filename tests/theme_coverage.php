<?php
/** Standalone filesystem regressions. No database, accounts or installed themes are changed. */
$repository = dirname(__DIR__);
$fixture = realpath(sys_get_temp_dir()) . '/fireball-theme-' . bin2hex(random_bytes(6));
mkdir($fixture, 0755, true);
define('ROOT', $fixture);
define('STORAGE', $fixture . '/storage');
define('WWW', $fixture . '/public');
define('SITE_NAME', 'Test');
require $repository . '/core/PerformanceProfiler.php';
require $repository . '/core/AssetManifest.php';
require $repository . '/app/Services/Themes/ThemeAssets.php';
require $repository . '/core/ThemeManager.php';
require $repository . '/core/Theme.php';
require $repository . '/core/View.php';
require $repository . '/core/Plugins/PluginManager.php';
define('VIEWS', $fixture . '/views');
define('LAYOUT', 'default');
function theme() { return $GLOBALS['manager']; }
function app() { return $GLOBALS['application']; }
function uri_without_lang() { return $GLOBALS['routePath'] ?? '/example'; }
function renderAnalyticsTracker() { return ''; }
function renderCookieConsent() { return ''; }
function base_url($path = '') { return 'https://example.test' . $path; }
function base_href($path = '') { return base_url($path); }
function htmlSC($value) { return htmlspecialchars((string)$value); }
function return_translation($key) { return $key; }
function check_admin() { return $GLOBALS['admin'] ?? true; }
function expect($condition, $message) { if (!$condition) throw new RuntimeException($message); }
function put($path, $content) { if (!is_dir(dirname($path))) mkdir(dirname($path), 0755, true); file_put_contents($path, $content); }
function removeTree($path) { if (is_link($path) || is_file($path)) { unlink($path); return; } foreach (scandir($path) ?: [] as $name) if ($name !== '.' && $name !== '..') removeTree($path . '/' . $name); rmdir($path); }
class TestThemes extends \FBL\ThemeManager {
    public function select($slug) { $this->invalidateRuntime(); $this->activeTheme = $this->getTheme($slug); }
    protected function standardizeTemplateData(string $template, array $data): array { return $data; }
    protected function injectCmsComponents(string $html): string { return $html; }
    protected function logThemeAction(string $event, array $context = []): void {}
}
try {
    $shippingTheme = new TestThemes($repository . '/themes');
    $shippingTheme->select('default');
    expect(!array_filter($shippingTheme->diagnostics('default')['coverage'], fn($entry) => $entry['missing']), 'Shipped default has complete public coverage');
    foreach (['js/password-field.js', 'css/profile.css', 'js/app-viewport.js', 'js/chat-viewport.js'] as $dependency) {
        expect(is_file($repository . '/themes/default/assets/' . $dependency), 'Public dependency present: ' . $dependency);
    }
    $manager = new TestThemes($fixture . '/themes');
    $old = $manager->createTheme(['slug' => 'classic', 'name' => 'Classic']);
    expect(is_file($old['path'] . '/templates/posts.php'), 'Generator creates posts.php');
    expect(str_contains(file_get_contents($old['path'] . '/templates/posts.php'), 'foreach'), 'Generated listing is substantive');
    $placeholderHash = hash_file('sha256', $old['path'] . '/preview.png');
    expect(getimagesize($old['path'] . '/preview.png')[0] === 800, 'Generator creates a visible neutral placeholder');
    rename($fixture . '/themes/classic', $fixture . '/themes/default');
    put($fixture . '/themes/default/preview.png', 'existing default screenshot');
    put($fixture . '/themes/default/theme.json', json_encode(['slug' => 'default', 'name' => 'Default']));
    $old = $manager->createTheme(['slug' => 'classic', 'name' => 'Classic']);
    expect(hash_file('sha256', $old['path'] . '/preview.png') === $placeholderHash, 'New theme preview does not copy the default screenshot');
    expect(file_get_contents($fixture . '/themes/default/preview.png') === 'existing default screenshot', 'Existing preview remains unchanged');
    unlink($old['path'] . '/templates/posts.php');
    expect($manager->validateThemeStructure('classic'), 'Older theme without posts/new public templates remains valid');
    $manager->select('classic');
    put($old['path'] . '/templates/layout.php', '<html><?= $this->content ?></html>');
    $listing = $manager->render('posts', ['posts' => [['title' => '<Example>', 'slug' => 'example', 'excerpt' => 'Summary']], 'pagination' => '<nav>Next</nav>']);
    expect(str_contains($listing, '&lt;Example&gt;') && str_contains($listing, '/posts/example') && str_contains($listing, '<nav>Next</nav>'), 'Generated posts lists escaped titles, links and pagination');
    put($fixture . '/themes/default/templates/layout.php', '<html><?= $this->partial("header") ?><?= $this->content ?></html>');
    put($fixture . '/themes/default/partials/header.php', 'DEFAULT HEADER');
    unlink($old['path'] . '/templates/layout.php');
    unlink($old['path'] . '/partials/header.php');
    foreach ($manager->publicTemplates() as $name) {
        if (is_file($old['path'] . '/templates/' . $name . '.php')) unlink($old['path'] . '/templates/' . $name . '.php');
        put($fixture . '/themes/default/templates/' . $name . '.php', 'DEFAULT ' . $name);
        expect(str_contains($manager->render($name), 'DEFAULT ' . $name) || is_file($old['path'] . '/templates/' . $name . '.php'), 'Public template resolves: ' . $name);
        put($old['path'] . '/templates/' . $name . '.php', 'OVERRIDE ' . $name);
        expect(str_contains($manager->render($name), 'OVERRIDE ' . $name), 'Public template override: ' . $name);
    }
    put($old['path'] . '/templates/layout.php', '<custom><?= $this->partial("header") ?><?= $this->content ?></custom>');
    expect(str_contains($manager->render('contacts'), '<custom>DEFAULT HEADEROVERRIDE contacts'), 'Active layout with default partial works independently');
    unlink($old['path'] . '/templates/contacts.php');
    expect(str_contains($manager->render('contacts'), '<custom>DEFAULT HEADERDEFAULT contacts'), 'Default template with active layout');
    $diagnostics = $manager->diagnostics('classic');
    expect($diagnostics['active_theme'] === 'classic' && $diagnostics['coverage']['templates/contacts.php']['source'] === 'default', 'Diagnostics reports actual template fallback');
    expect(!$diagnostics['coverage']['templates/contacts.php']['present'], 'Diagnostics reports missing selected template');
    unlink($fixture . '/themes/default/templates/contacts.php');
    expect($manager->diagnostics('classic')['coverage']['templates/contacts.php']['missing'], 'Diagnostics detects missing after fallback');
    try { $manager->render('contacts'); throw new LogicException('Expected missing template exception'); } catch (RuntimeException $e) { expect(!$e instanceof LogicException, 'Missing render throws instead of abort recursion'); }
    $GLOBALS['admin'] = false;
    try { $manager->diagnostics(); throw new LogicException('Unauthorized diagnostics succeeded'); } catch (RuntimeException $e) { expect(!$e instanceof LogicException, 'Diagnostics is restricted'); }
    $GLOBALS['admin'] = true;
    put($old['path'] . '/templates/broken.php', '<?php echo "LEAK"; throw new RuntimeException("broken");');
    $level = ob_get_level();
    $manager->content = 'previous';
    try { $manager->render('broken'); } catch (RuntimeException) {}
    expect(ob_get_level() === $level && $manager->content === 'previous', 'Broken template cleans buffers and content');
    put($old['path'] . '/templates/broken.php', '<?php invalid syntax');
    try { $manager->render('broken'); } catch (ParseError) {}
    expect(ob_get_level() === $level, 'Syntax error cleans buffers');
    put($fixture . '/outside.php', 'SECRET');
    symlink($fixture . '/outside.php', $old['path'] . '/templates/escape.php');
    expect($manager->resolveFile('templates', 'escape') === null, 'Template symlink escape rejected');
    expect($manager->resolveFile('templates', 'nested/..') === null, 'Trailing traversal rejected');
    expect($manager->resolveFile('templates', '../outside') === null, 'Template traversal rejected');
    rename($old['path'] . '/partials', $old['path'] . '/partials-saved');
    mkdir($fixture . '/outside-partials');
    put($fixture . '/outside-partials/header.php', 'SECRET');
    symlink($fixture . '/outside-partials', $old['path'] . '/partials');
    expect($manager->partial('header') === 'DEFAULT HEADER', 'Partial directory symlink escape falls back');
    $pluginFile = $fixture . '/plugins/example/views/public.php';
    put($pluginFile, '<p>PLUGIN <?= htmlSC($label) ?></p>');
    expect(str_contains($manager->renderPlugin('example', 'public', ['label' => '<safe>'], $pluginFile), 'PLUGIN &lt;safe&gt;'), 'Plugin original content uses theme layout');
    put($old['path'] . '/templates/plugins/example/public.php', 'PLUGIN OVERRIDE');
    expect(str_contains($manager->renderPlugin('example', 'public', [], $pluginFile), 'PLUGIN OVERRIDE'), 'Plugin public page override');
    put($fixture . '/plugins/example/plugin.json', '{"slug":"example","name":"Example","version":"1.0"}');
    put(VIEWS . '/layouts/default.php', '<legacy><?= $this->content ?></legacy>');
    $GLOBALS['application'] = (object)['view' => new \FBL\View('default')];
    $plugins = new \FBL\Plugins\PluginManager($fixture . '/plugins');
    expect(str_contains($plugins->renderView('example', 'public'), '<custom>'), 'plugin_view delegate uses theme for public pages');
    $GLOBALS['routePath'] = '/admin/example';
    expect(str_contains($plugins->renderView('example', 'public', ['label' => 'Admin']), '<legacy><p>PLUGIN Admin'), 'Plugin admin view keeps legacy layout');
    expect(!str_contains($plugins->renderView('example', 'public', ['label' => 'Fragment'], false), '<legacy>'), 'Plugin fragments remain layout-free');
    $GLOBALS['routePath'] = '/example';
    expect(str_contains($plugins->renderView('example', 'public', ['label' => 'Admin'], true), 'PLUGIN OVERRIDE'), 'Administrator visiting public route still uses theme');
    $assets = new \App\Services\Themes\ThemeAssets();
    $default = $manager->getTheme('default');
    put($default['path'] . '/assets/css/test.css', 'body{}');
    expect(str_contains($assets->asset('css/test.css', $old, $default), '/themes/default/'), 'Asset fallback URL');
    expect($assets->assetPath('css/test.css', $old, $default) === realpath($default['path'] . '/assets/css/test.css'), 'Asset fallback physical file');
    expect($assets->asset('css/..', $old) === '', 'Trailing asset traversal rejected');
    expect($assets->asset('../outside.php', $old, $default) === '' && $assets->asset('%2e%2e/outside.php', $old) === '', 'Asset traversal rejected');
    symlink($fixture . '/outside.php', $old['path'] . '/assets/css/leak.css');
    expect($assets->asset('css/leak.css', $old, $default) === '' && $assets->assetPath('css/leak.css', $old, $default) === '', 'Asset file symlink never leaks URL or hash path');
    put($old['path'] . '/assets/css/swapped.css', 'safe');
    expect($assets->assetPath('css/swapped.css', $old) !== '', 'Valid asset before swap');
    unlink($old['path'] . '/assets/css/swapped.css');
    symlink($fixture . '/outside.php', $old['path'] . '/assets/css/swapped.css');
    clearstatcache();
    expect($assets->assetPath('css/swapped.css', $old) === '' && $assets->asset('css/swapped.css', $old) === '', 'Asset lookup rejects a symlink introduced after a previous lookup');
    symlink($old['path'] . '/templates/home.php', $old['path'] . '/assets/css/private.css');
    expect($assets->asset('css/private.css', $old) === '', 'Assets cannot link into private templates in the same theme');
    mkdir($fixture . '/outside-assets');
    put($fixture . '/outside-assets/leak.css', 'SECRET');
    symlink($fixture . '/outside-assets', $old['path'] . '/assets/external');
    expect($assets->asset('external/leak.css', $old) === '' && $assets->asset('external/missing.css', $old) === '', 'Asset parent symlink escape rejected');
    \FBL\AssetManifest::rebuild([$default['path'] . '/assets']);
    $url = \FBL\AssetManifest::url($assets->asset('css/test.css', $old, $default), $assets->assetPath('css/test.css', $old, $default));
    expect(str_contains($url, '?v=' . substr(hash('sha256', 'body{}'), 0, 20)), 'Versioned assets use resolved fallback file');
    foreach (['valid', 'missing', 'broken', 'partial', 'layout', 'missing-layout', 'no-default', 'no-system'] as $case) {
        $process = proc_open([PHP_BINARY, __DIR__ . '/fixtures/theme_abort.php', $case], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $output = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        expect(proc_close($process) === 0 && str_ends_with($output, '|STATUS:404'), '404 preserved for ' . $case . ': ' . $errors);
        expect(!str_contains($output, 'LEAK'), '404 has no partial output: ' . $case);
    }
    echo "Theme coverage regressions passed.\n";
} finally { removeTree($fixture); }
