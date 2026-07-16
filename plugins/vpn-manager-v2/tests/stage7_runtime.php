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
    'GET /admin/plugins/vpn-manager-v2/subscriptions/edit/(?P<id>\d+)/?',
    'POST /admin/plugins/vpn-manager-v2/subscriptions/edit/(?P<id>\d+)/?',
    'GET /admin/plugins/vpn-manager-v2/connections/(?P<id>\d+)/edit/?',
    'POST /admin/plugins/vpn-manager-v2/connections/(?P<id>\d+)/edit/?',
    'POST /admin/plugins/vpn-manager-v2/connections/(?P<id>\d+)/sync/?',
];
$found = [];
foreach ($app->router->getRoutes() as $route) {
    $path = (string)($route['path'] ?? '');
    foreach ((array)($route['method'] ?? []) as $method) {
        $key = strtoupper((string)$method) . ' ' . $path;
        if (!in_array($key, $expected, true)) {
            continue;
        }
        $assert(($route['middleware'] ?? []) === ['auth', 'admin'], $key . ' is missing admin middleware.');
        if (str_starts_with($key, 'POST ')) {
            $assert(($route['needCSRFToken'] ?? false) === true, $key . ' is not CSRF protected.');
        }
        $found[$key] = ($found[$key] ?? 0) + 1;
    }
}
foreach ($expected as $key) {
    $assert(($found[$key] ?? 0) === 1, $key . ' is missing or duplicated.');
}

$plugin = db()->query('SELECT slug, version, status FROM plugins WHERE slug = ? LIMIT 1', ['vpn-manager-v2'])->getOne();
$assert(is_array($plugin) && $plugin['version'] === '0.7.0' && $plugin['status'] === 'active', 'Plugin metadata is not at active stage 7.');
$column = db()->query(
    "SELECT COLUMN_NAME FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_subscriptions'
       AND COLUMN_NAME = 'internal_comment' LIMIT 1"
)->getColumn();
$assert($column === 'internal_comment', 'Stage 7 internal_comment migration is missing.');
$migration = db()->query(
    'SELECT migration FROM plugin_migrations WHERE plugin_slug = ? AND migration = ? LIMIT 1',
    ['vpn-manager-v2', '003_add_subscription_internal_comment.sql']
)->getColumn();
$assert($migration === '003_add_subscription_internal_comment.sql', 'Stage 7 migration journal entry is missing.');

$subscriptionsHtml = (new Fireball\VpnManagerV2\Controllers\Admin\SubscriptionController())->index();
$connectionsHtml = (new Fireball\VpnManagerV2\Controllers\Admin\ConnectionController())->index();
$assert(str_contains($subscriptionsHtml, 'admin-table-component__table'), 'Universal subscription table failed.');
$assert(str_contains($connectionsHtml, 'admin-table-component__table'), 'Universal connection table failed.');
$assert(isset(FireballPluginVpnManagerV2::permissions()[Fireball\VpnManagerV2\Support\Permissions::MANAGE_SUBSCRIPTIONS]),
    'Subscription permission is missing.');

echo json_encode([
    'status' => 'ok',
    'routes' => array_keys($found),
    'plugin' => $plugin,
    'migration' => $migration,
    'csrf' => true,
    'admin_middleware' => true,
    'universal_tables' => true,
    'permission' => Fireball\VpnManagerV2\Support\Permissions::MANAGE_SUBSCRIPTIONS,
], JSON_UNESCAPED_SLASHES), PHP_EOL;
