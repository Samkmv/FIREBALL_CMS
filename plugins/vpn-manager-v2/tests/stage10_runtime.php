<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/config/config.php';
require ROOT . '/vendor/autoload.php';
require ROOT . '/helpers/helpers.php';

$app = new FBL\Application();
require CONFIG . '/routes.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$expected = [
    'GET /profile/vpn-v2',
    'GET /profile/vpn-v2/instructions/(?P<platform>ios|android|windows|macos)/?',
    'GET /profile/vpn-v2/(?P<id>\d+)/?',
];
$found = [];
$routeKeys = [];
$profilePaths = [];
foreach ($app->router->getRoutes() as $route) {
    $path = (string)($route['path'] ?? '');
    if (str_starts_with($path, '/profile/vpn')) {
        $profilePaths[$path] = true;
    }
    foreach ((array)($route['method'] ?? []) as $method) {
        $key = strtoupper((string)$method) . ' ' . $path;
        $routeKeys[$key] = ($routeKeys[$key] ?? 0) + 1;
        if (!in_array($key, $expected, true)) {
            continue;
        }
        $assert(($route['middleware'] ?? []) === ['auth'], $key . ' must be authenticated.');
        $found[$key] = ($found[$key] ?? 0) + 1;
    }
}
foreach ($expected as $key) {
    $assert(($found[$key] ?? 0) === 1, $key . ' is missing or duplicated.');
}
foreach ($routeKeys as $key => $count) {
    if (str_contains($key, '/profile/vpn-v2') || str_contains($key, '/vpn-v2/')) {
        $assert($count === 1, 'Duplicate VPN V2 route: ' . $key);
    }
}
$assert(isset($profilePaths['/profile/vpn-v2']), 'VPN V2 profile route is not registered.');
$oldRouteSource = (string)file_get_contents(ROOT . '/plugins/vpn-manager/routes.php');
$assert(str_contains($oldRouteSource, "'/profile/vpn'") && !str_contains($oldRouteSource, "'/profile/vpn-v2'"),
    'VPN V2 profile route conflicts with the old plugin source.');

$coreProfile = (string)file_get_contents(ROOT . '/app/Views/themes/default/auth/profile.php');
$assert(str_contains($coreProfile, "apply_filters('profile_menu'")
    && !str_contains($coreProfile, '/profile/vpn-v2'),
    'The profile integration must use Plugin API without a hard-coded core link.');

$plugin = db()->query('SELECT slug, version, status FROM plugins WHERE slug = ? LIMIT 1', ['vpn-manager-v2'])->getOne();
$assert(is_array($plugin) && $plugin['version'] === '0.10.0' && $plugin['status'] === 'active',
    'Plugin metadata is not at active stage 10.');
$migrationFiles = array_map('basename', glob(dirname(__DIR__) . '/migrations/*.sql') ?: []);
sort($migrationFiles);
$assert($migrationFiles === [
    '001_create_vpn_manager_v2_tables.sql',
    '002_add_server_auth_type.sql',
    '003_add_subscription_internal_comment.sql',
], 'Stage 10 must not add a database migration.');
$appliedMigrations = db()->query(
    'SELECT migration FROM plugin_migrations WHERE plugin_slug = ? ORDER BY migration',
    ['vpn-manager-v2']
)->get() ?: [];
$assert(array_column($appliedMigrations, 'migration') === $migrationFiles,
    'VPN Manager V2 migration state is inconsistent.');
$tableCount = (int)db()->query(
    "SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'vpn_v2_%'"
)->getColumn();
$assert($tableCount === 7, 'VPN Manager V2 table set changed during stage 10.');

$subscriber = db()->query('SELECT user_id FROM vpn_v2_subscriptions ORDER BY id DESC LIMIT 1')->getOne();
$assert(is_array($subscriber), 'A live V2 subscriber is required for runtime profile verification.');
$items = apply_filters('profile_menu', [], ['id' => (int)$subscriber['user_id']]);
$vpnItems = array_values(array_filter($items, static fn(array $item): bool => ($item['key'] ?? '') === 'vpn-v2'));
$assert(count($vpnItems) === 1 && str_contains((string)$vpnItems[0]['href'], '/profile/vpn-v2'),
    'My VPN profile hook is not registered.');
$assert(apply_filters('profile_menu', [], ['id' => PHP_INT_MAX]) === [],
    'My VPN profile menu is visible without a physical subscription.');

$data = (new Fireball\VpnManagerV2\Services\ProfileVpnService())->dashboard((int)$subscriber['user_id']);
$html = plugin_view('vpn-manager-v2', 'public/my-vpn', FireballPluginVpnManagerV2::publicViewData(array_merge($data, [
    'title' => FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_title'),
    'subtitle' => FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_subtitle'),
])), false);
$assert(str_contains($html, 'data-vpn-v2-profile') && str_contains($html, 'data-vpn-v2-instructions'),
    'My VPN profile view failed to render.');
$assert(!str_contains($html, 'panel_url') && !str_contains($html, 'remote_inbound_id')
    && !str_contains($html, 'client_uuid') && !str_contains($html, 'settings_json'),
    'Technical fields leaked into rendered profile HTML.');

echo json_encode([
    'status' => 'ok',
    'routes' => array_keys($found),
    'plugin' => $plugin,
    'auth' => true,
    'profile_menu_hook' => true,
    'core_profile_hook' => true,
    'old_profile_route_conflicts' => 0,
    'migration_count' => count($migrationFiles),
    'table_count' => $tableCount,
    'safe_profile_render' => true,
], JSON_UNESCAPED_SLASHES), PHP_EOL;
