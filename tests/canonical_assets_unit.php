<?php
declare(strict_types=1);
namespace FBL {
    function app(): object { return new class { public mixed $db = null; public function get(string $key): mixed { return ['code' => 'en']; } }; }
}
namespace App\Components {
    function uri_without_lang(): string { return '/posts/test'; }
    function site_setting(string $key, string $default): string { return $default; }
}
namespace {
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/helpers/helpers.php';
define('ROOT', dirname(__DIR__)); define('WWW', ROOT . '/public'); define('STORAGE', sys_get_temp_dir() . '/fireball-origin-fixture-absent');
define('PATH', 'https://cms.example/cms'); define('SITE_NAME', 'FIREBALL CMS');
define('LANGS', ['ru' => ['code' => 'ru', 'base' => 1], 'en' => ['code' => 'en', 'base' => 0]]);
define('DEFAULT_LOCALE', 'ru'); define('DEBUG', 0);
$_SERVER['HTTP_HOST'] = 'www.untrusted.example'; $_SERVER['REQUEST_URI'] = '/cms/en/posts/test';
$settings = new class extends App\Models\SiteSetting {
    public function get(string $key, string $default = ''): string { return ['site_favicon' => '/assets/default/icons/fireball-cms.svg', 'site_title' => 'Site'][$key] ?? $default; }
};
$service = new App\Services\PwaService($settings);
$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void { $checks++; if (!$ok) throw new RuntimeException($label); };
foreach (['/assets/default/css/theme.min.css', '/assets/default/js/pwa.js', '/assets/default/fonts/inter-variable-latin.woff2', '/manifest.webmanifest', '/service-worker.js', '/api/analytics/track'] as $path) {
    $check(base_url($path) === PATH . $path, 'Same configured origin ' . $path);
}
$manifest = $service->manifest('/cms/en/posts/test?q=1');
$check($manifest['start_url'] === PATH . '/en/posts/test?q=1' && $manifest['scope'] === PATH . '/', 'Manifest subdirectory/locale without duplication');
$check(parse_url($manifest['icons'][0]['src'], PHP_URL_HOST) === 'cms.example', 'Generated icon origin');
$script = $service->serviceWorkerScript();
preg_match('/const FIREBALL_PWA = (\{.*?\});/s', $script, $match);
$config = json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
foreach (['offlineUrl', 'homeUrl', 'icon', 'badge', 'statusUrl'] as $key) $check(str_starts_with($config[$key], PATH . '/'), 'SW ' . $key);
$check($config['basePath'] === '/cms', 'SW installation scope');
$analytics = (new App\Components\AnalyticsTracker())->render();
$check(str_contains($analytics, PATH . '/en/api/analytics/track') && !str_contains($analytics, 'untrusted.example'), 'Localized analytics endpoint remains same-origin');
echo "$checks canonical asset checks passed\n";
}
