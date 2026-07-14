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

$stage5Routes = [];
$routeKeys = [];
foreach ($app->router->getRoutes() as $route) {
    $path = (string)($route['path'] ?? '');
    foreach ((array)($route['method'] ?? []) as $method) {
        $key = strtoupper((string)$method) . ' ' . $path;
        $routeKeys[$key] = ($routeKeys[$key] ?? 0) + 1;
    }
    if (!str_starts_with($path, '/admin/plugins/vpn-manager-v2/subscriptions')
        && !str_starts_with($path, '/admin/plugins/vpn-manager-v2/connections')) {
        continue;
    }

    $assert(($route['middleware'] ?? []) === ['auth', 'admin'], 'A stage 5 route is missing auth/admin middleware.');
    if (in_array('POST', (array)($route['method'] ?? []), true)) {
        $assert(($route['needCSRFToken'] ?? false) === true, 'A stage 5 POST route is missing CSRF protection.');
    }
    $stage5Routes[] = ['method' => implode('|', (array)$route['method']), 'path' => $path];
}
$assert(count($stage5Routes) === 7, 'The stage 5 route catalog is incomplete.');
foreach ($routeKeys as $key => $count) {
    if (str_contains($key, '/vpn-manager-v2/')) {
        $assert($count === 1, 'Duplicate V2 route: ' . $key);
    }
}

$permissions = FireballPluginVpnManagerV2::permissions();
$assert(isset($permissions[Fireball\VpnManagerV2\Support\Permissions::MANAGE_SUBSCRIPTIONS]), 'Subscription permission is missing.');
$plugin = db()->query('SELECT slug, version, status FROM plugins WHERE slug = ? LIMIT 1', ['vpn-manager-v2'])->getOne();
$assert(is_array($plugin) && $plugin['version'] === '0.5.0' && $plugin['status'] === 'active', 'Plugin metadata is not at active stage 5.');

$subscriptionController = new Fireball\VpnManagerV2\Controllers\Admin\SubscriptionController();
$connectionController = new Fireball\VpnManagerV2\Controllers\Admin\ConnectionController();
$subscriptionsHtml = $subscriptionController->index();
$formHtml = $subscriptionController->create();
$connectionsHtml = $connectionController->index();
$assert(str_contains($subscriptionsHtml, 'admin-table-component__table'), 'Subscription list does not use the universal table.');
$assert(str_contains($connectionsHtml, 'admin-table-component__table'), 'Connection list does not use the universal table.');
$assert(str_contains($formHtml, 'name="needCSRFToken"'), 'Subscription form is missing CSRF.');
$assert(str_contains($formHtml, 'name="user_id"') && str_contains($formHtml, 'name="plan_id"'), 'Subscription form fields are incomplete.');
$assert(!str_contains($formHtml, 'name="subscription_token"'), 'The form exposes subscription token input.');
$assert(!str_contains($formHtml, 'cdn.'), 'A stage 5 page references a CDN.');

$publicStage6Routes = array_filter(
    $app->router->getRoutes(),
    static fn(array $route): bool => str_starts_with((string)($route['path'] ?? ''), '/vpn-manager-v2/sub/')
        || str_starts_with((string)($route['path'] ?? ''), '/vpn-manager-v2/s/')
);
$assert($publicStage6Routes === [], 'A public subscription endpoint was registered before stage 6.');

echo json_encode([
    'status' => 'ok',
    'routes' => $stage5Routes,
    'permission' => Fireball\VpnManagerV2\Support\Permissions::MANAGE_SUBSCRIPTIONS,
    'plugin' => $plugin,
    'universal_tables' => 2,
    'csrf_protected' => true,
    'public_subscription_routes' => 0,
], JSON_UNESCAPED_SLASHES), PHP_EOL;
