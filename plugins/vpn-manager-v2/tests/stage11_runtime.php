<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/config/config.php';
require ROOT . '/vendor/autoload.php';
require ROOT . '/helpers/helpers.php';

$app = new FBL\Application();
require CONFIG . '/routes.php';

use Fireball\VpnManagerV2\Repositories\SettingsRepository;
use Fireball\VpnManagerV2\Services\CountryFlagService;
use Fireball\VpnManagerV2\Services\SettingsService;
use Fireball\VpnManagerV2\Support\Permissions;
use Fireball\VpnManagerV2\Validators\SettingsValidator;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$expected = [
    'GET /admin/plugins/vpn-manager-v2/settings',
    'POST /admin/plugins/vpn-manager-v2/settings',
];
$found = [];
foreach ($app->router->getRoutes() as $route) {
    $path = (string)($route['path'] ?? '');
    foreach ((array)($route['method'] ?? []) as $method) {
        $key = strtoupper((string)$method) . ' ' . $path;
        if (!in_array($key, $expected, true)) {
            continue;
        }
        $assert(($route['middleware'] ?? []) === ['auth', 'admin'], $key . ' is missing auth/admin middleware.');
        if (str_starts_with($key, 'POST ')) {
            $assert(($route['needCSRFToken'] ?? false) === true, $key . ' is missing CSRF protection.');
        }
        $found[$key] = ($found[$key] ?? 0) + 1;
    }
}
foreach ($expected as $key) {
    $assert(($found[$key] ?? 0) === 1, $key . ' is missing or duplicated.');
}

$plugin = db()->query(
    'SELECT slug, version, status FROM plugins WHERE slug = ? LIMIT 1',
    ['vpn-manager-v2']
)->getOne();
$assert(is_array($plugin) && $plugin['version'] === '0.11.0' && $plugin['status'] === 'active',
    'Plugin metadata is not at active stage 11.');

$assert(isset(FireballPluginVpnManagerV2::permissions()[Permissions::MANAGE_SETTINGS]),
    'Settings permission is not registered.');
$assert(Permissions::allows(Permissions::MANAGE_SETTINGS, ['role' => 'admin'])
    && !Permissions::allows(Permissions::MANAGE_SETTINGS, ['role' => 'user']),
    'Settings permission guard failed.');

$settingsRepository = new SettingsRepository();
$settingsRepository->assertStorageReady();
$settings = (new SettingsService($settingsRepository))->current();
$assert(count($settings) === count(SettingsService::defaults())
    && count(array_intersect_key($settingsRepository->stored(), SettingsService::defaults())) === count(SettingsService::defaults()),
    'Persistent settings were not installed in plugin_settings.');

$index = db()->query("SHOW INDEX FROM plugin_settings WHERE Key_name = 'plugin_setting'")->get() ?: [];
$assert(array_column($index, 'Column_name') === ['plugin_slug', 'setting_key'],
    'CMS plugin_settings unique index is invalid.');

$migrationFiles = array_map('basename', glob(dirname(__DIR__) . '/migrations/*.sql') ?: []);
sort($migrationFiles);
$appliedMigrations = db()->query(
    'SELECT migration FROM plugin_migrations WHERE plugin_slug = ? ORDER BY migration',
    ['vpn-manager-v2']
)->get() ?: [];
$assert(count($migrationFiles) === 3 && array_column($appliedMigrations, 'migration') === $migrationFiles,
    'Stage 11 unexpectedly changed plugin migrations.');
$tableCount = (int)db()->query(
    "SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'vpn_v2_%'"
)->getColumn();
$assert($tableCount === 7, 'Stage 11 unexpectedly changed the V2 table set.');

$requiredCaseSensitiveFiles = [
    dirname(__DIR__) . '/src/Controllers/Admin/SettingsController.php',
    dirname(__DIR__) . '/src/Repositories/SettingsRepository.php',
    dirname(__DIR__) . '/src/Services/SettingsService.php',
    dirname(__DIR__) . '/src/Services/CountryFlagService.php',
    dirname(__DIR__) . '/src/Services/VpnServerNameRenderer.php',
    dirname(__DIR__) . '/src/Validators/SettingsValidator.php',
    dirname(__DIR__) . '/views/admin/settings.php',
];
foreach ($requiredCaseSensitiveFiles as $file) {
    $assert(is_file($file) && realpath($file) === $file, 'Case-sensitive file path mismatch: ' . basename($file));
}

$html = plugin_view('vpn-manager-v2', 'admin/settings', FireballPluginVpnManagerV2::viewData('settings', [
    'title' => FireballPluginVpnManagerV2::t('vpn_manager_v2_settings_title'),
    'subtitle' => FireballPluginVpnManagerV2::t('vpn_manager_v2_settings_subtitle'),
    'settings' => $settings,
    'templateVariables' => SettingsValidator::TEMPLATE_VARIABLES,
]), false);
$assert(str_contains($html, 'data-vpn-v2-settings-form')
    && str_contains($html, 'name="needCSRFToken"')
    && str_contains($html, 'name="service_name"')
    && str_contains($html, 'name="global_show_flags"')
    && str_contains($html, '{country_code}')
    && !str_contains(strtolower($html), 'cdn.'),
    'Settings page is incomplete, lacks CSRF, or references a CDN.');

$controllerSource = (string)file_get_contents(dirname(__DIR__) . '/src/Controllers/Admin/SettingsController.php');
$repositorySource = (string)file_get_contents(dirname(__DIR__) . '/src/Repositories/SettingsRepository.php');
$assert(str_contains($controllerSource, 'Permissions::authorize(Permissions::MANAGE_SETTINGS)')
    && str_contains($repositorySource, 'INSERT INTO plugin_settings')
    && str_contains($repositorySource, 'ON DUPLICATE KEY UPDATE'),
    'Permission or standard CMS settings storage integration is missing.');
$assert((new CountryFlagService())->emoji('US') === '🇺🇸'
    && (new CountryFlagService())->emoji('ZZ') === '', 'CountryFlagService runtime validation failed.');

echo json_encode([
    'status' => 'ok',
    'routes' => array_keys($found),
    'plugin' => $plugin,
    'permission' => Permissions::MANAGE_SETTINGS,
    'csrf' => true,
    'settings_rows' => count(SettingsService::defaults()),
    'read_repository' => true,
    'cms_storage' => 'plugin_settings',
    'migration_count' => count($migrationFiles),
    'table_count' => $tableCount,
    'linux_case_sensitive_paths' => true,
    'local_assets_only' => true,
], JSON_UNESCAPED_SLASHES), PHP_EOL;
