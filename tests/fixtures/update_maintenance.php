<?php
declare(strict_types=1);
// Isolated CLI render of the real guard or the normal application's wrapper.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__, 2);
$locale = in_array($argv[1] ?? '', ['ru', 'en', 'de', 'zh-cn'], true) ? $argv[1] : 'ru';
$mode = $argv[2] ?? 'app';
$_SERVER['SCRIPT_NAME'] = '/cms/public/index.php';
$_SERVER['REQUEST_URI'] = '/cms' . ($locale === 'ru' ? '' : '/' . $locale) . '/profile/settings';
if ($mode === 'gate') {
    $fixtureRoot = $argv[3] ?? '';
    if (!is_file($fixtureRoot . '/public/runtime-gate.php')) throw new RuntimeException('Isolated runtime gate missing');
    register_shutdown_function(static function (): void {
        if (http_response_code() !== 503) { fwrite(STDERR, 'Guard did not fail closed with 503'); exit(1); }
    });
    require $fixtureRoot . '/public/runtime-gate.php';
    throw new RuntimeException('Guard must not proceed to application bootstrap');
}
define('SITE_NAME', 'Fixture CMS');
function site_setting(string $key, mixed $default): mixed { return $key === 'site_title' ? 'Fixture CMS' : $default; }
function current_locale(): string { return $GLOBALS['locale']; }
function base_href(string $path): string { return '/cms' . ($GLOBALS['locale'] === 'ru' ? '' : '/' . $GLOBALS['locale']) . $path; }
function return_translation(string $key): string { return $GLOBALS['translations'][$key] ?? $key; }
$translations = require $root . '/app/Languages/' . $locale . '.php';
require $root . '/app/Views/themes/default/errors/update.php';
