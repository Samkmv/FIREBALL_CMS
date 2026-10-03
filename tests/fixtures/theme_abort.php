<?php
namespace FBL {
    class Application { public static $app; public $theme; public $response; public function get($key) { return ['code' => 'ru']; } }
}
namespace {
$repository = dirname(__DIR__, 2);
$fixture = realpath(sys_get_temp_dir()) . '/fireball-abort-' . bin2hex(random_bytes(5));
mkdir($fixture, 0755, true);
define('ROOT', $fixture);
define('PATH', 'https://example.test');
define('VIEWS', $fixture . '/views');
require $repository . '/core/PerformanceProfiler.php';
require $repository . '/app/Services/Themes/ThemeAssets.php';
require $repository . '/core/ThemeManager.php';
require $repository . '/helpers/helpers.php';
class AbortThemes extends \FBL\ThemeManager {
    public function select() { $this->activeTheme = $this->getTheme('default'); }
    protected function standardizeTemplateData(string $template, array $data): array { return $data; }
    protected function injectCmsComponents(string $html): string { return $html; }
}
function writeFixture($path, $text) { if (!is_dir(dirname($path))) mkdir(dirname($path), 0755, true); file_put_contents($path, $text); }
$case = $argv[1];
writeFixture($fixture . '/themes/default/theme.json', '{"slug":"default"}');
writeFixture($fixture . '/themes/default/templates/layout.php', '<html><?= $this->content ?></html>');
writeFixture($fixture . '/themes/default/templates/404.php', '<?php response()->setResponseCode(200); ?>THEMED 404');
writeFixture(VIEWS . '/themes/default/errors/404.php', 'SYSTEM 404');
if ($case === 'missing') unlink($fixture . '/themes/default/templates/404.php');
if ($case === 'broken') writeFixture($fixture . '/themes/default/templates/404.php', '<?php echo "LEAK"; throw new RuntimeException("broken");');
if ($case === 'layout') writeFixture($fixture . '/themes/default/templates/layout.php', '<?php echo "LEAK"; throw new RuntimeException("broken layout");');
if ($case === 'missing-layout') unlink($fixture . '/themes/default/templates/layout.php');
if ($case === 'partial') {
    writeFixture($fixture . '/themes/default/templates/404.php', '<?php echo $this->partial("broken");');
    writeFixture($fixture . '/themes/default/partials/broken.php', '<?php echo "LEAK"; throw new RuntimeException("partial");');
}
if ($case === 'no-default') unlink($fixture . '/themes/default/theme.json');
if ($case === 'no-system') { unlink($fixture . '/themes/default/templates/404.php'); unlink(VIEWS . '/themes/default/errors/404.php'); }
require $repository . '/core/Response.php';
$response = new \FBL\Response();
$application = new \FBL\Application();
\FBL\Application::$app = $application;
$application->response = $response;
$application->theme = new AbortThemes($fixture . '/themes');
$application->theme->select();
register_shutdown_function(function () use ($fixture, $response) {
    echo '|STATUS:' . http_response_code();
    $remove = function ($path) use (&$remove) { if (is_file($path)) { unlink($path); return; } foreach (scandir($path) as $name) if ($name !== '.' && $name !== '..') $remove($path . '/' . $name); rmdir($path); };
    $remove($fixture);
});
abort('Example', 404);

}
