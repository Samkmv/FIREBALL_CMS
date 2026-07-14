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

$planRoutes = [];
$routeKeys = [];
foreach ($app->router->getRoutes() as $route) {
    $path = (string)($route['path'] ?? '');
    foreach ((array)($route['method'] ?? []) as $method) {
        $key = strtoupper((string)$method) . ' ' . $path;
        $routeKeys[$key] = ($routeKeys[$key] ?? 0) + 1;
    }

    if (!str_starts_with($path, '/admin/plugins/vpn-manager-v2/plans')) {
        continue;
    }

    $assert(($route['middleware'] ?? []) === ['auth', 'admin'], 'A plan route is missing auth/admin middleware.');
    if (in_array('POST', (array)($route['method'] ?? []), true)) {
        $assert(($route['needCSRFToken'] ?? false) === true, 'A plan POST route is missing CSRF protection.');
    }
    $planRoutes[] = [
        'method' => implode('|', (array)$route['method']),
        'path' => $path,
    ];
}

$assert(count($planRoutes) === 6, 'The plan route catalog is incomplete.');
foreach ($routeKeys as $key => $count) {
    if (str_contains($key, '/vpn-manager-v2/')) {
        $assert($count === 1, 'Duplicate V2 route: ' . $key);
    }
}

$permissions = FireballPluginVpnManagerV2::permissions();
$assert(
    isset($permissions[Fireball\VpnManagerV2\Support\Permissions::MANAGE_PLANS]),
    'The plan permission is not registered.'
);

$plugin = db()->query(
    'SELECT slug, version, status FROM plugins WHERE slug = ? LIMIT 1',
    [FireballPluginVpnManagerV2::SLUG]
)->getOne();
$assert(is_array($plugin) && $plugin['status'] === 'active', 'VPN Manager V2 is not active.');
$assert((string)$plugin['version'] === '0.4.0', 'VPN Manager V2 database metadata is not at stage 4.');

$index = db()->query(
    'SHOW INDEX FROM vpn_v2_plan_nodes WHERE Key_name = ?',
    ['uq_vpn_v2_plan_nodes_target']
)->get() ?: [];
$assert(array_column($index, 'Column_name') === ['plan_id', 'server_id', 'inbound_id'], 'Plan node unique index is invalid.');

$foreignKeys = db()->query(
    "SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
     FROM information_schema.KEY_COLUMN_USAGE
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'vpn_v2_plan_nodes'
       AND REFERENCED_TABLE_NAME IS NOT NULL"
)->get() ?: [];
$foreignKeyMap = [];
foreach ($foreignKeys as $foreignKey) {
    $foreignKeyMap[(string)$foreignKey['COLUMN_NAME']] = (string)$foreignKey['REFERENCED_TABLE_NAME'];
}
$assert(($foreignKeyMap['plan_id'] ?? null) === 'vpn_v2_plans', 'plan_id foreign key is missing.');
$assert(($foreignKeyMap['server_id'] ?? null) === 'vpn_v2_servers', 'server_id foreign key is missing.');
$assert(($foreignKeyMap['inbound_id'] ?? null) === 'vpn_v2_inbounds', 'inbound_id foreign key is missing.');

$controller = new Fireball\VpnManagerV2\Controllers\Admin\PlanController();
$listHtml = $controller->index();
$formHtml = $controller->create();
$assert(str_contains($listHtml, 'admin-table-component__table'), 'The universal CMS table was not rendered.');
$assert(str_contains($listHtml, '/admin/plugins/vpn-manager-v2/plans/create'), 'The create-plan link is missing.');
$assert(str_contains($formHtml, 'data-vpn-v2-plan-nodes'), 'The plan node editor was not rendered.');
$assert(str_contains($formHtml, 'name="needCSRFToken"'), 'The plan form is missing a CSRF field.');
$assert(str_contains($formHtml, '/plugins/vpn-manager-v2/assets/vpn-manager-v2.js?v='), 'The local plan asset was not attached.');
$assert(!str_contains($formHtml, 'cdn.'), 'The plan form unexpectedly references a CDN.');

$subscriptionRoutes = array_filter(
    $app->router->getRoutes(),
    static fn(array $route): bool => str_contains((string)($route['path'] ?? ''), '/vpn-manager-v2/subscriptions')
);
$assert($subscriptionRoutes === [], 'Stage 5 subscription routes were registered early.');

echo json_encode([
    'status' => 'ok',
    'plan_routes' => $planRoutes,
    'permission' => Fireball\VpnManagerV2\Support\Permissions::MANAGE_PLANS,
    'plugin' => $plugin,
    'foreign_keys' => $foreignKeyMap,
    'universal_table_rendered' => true,
    'csrf_protected' => true,
    'local_asset_attached' => true,
    'stage5_routes' => 0,
], JSON_UNESCAPED_SLASHES), PHP_EOL;
