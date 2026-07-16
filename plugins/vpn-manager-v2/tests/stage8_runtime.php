<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/config/config.php';
require ROOT . '/vendor/autoload.php';
require ROOT . '/helpers/helpers.php';

$_SERVER['REQUEST_URI'] = '/admin/plugins/vpn-manager-v2/subscriptions?page=4&search=vpn&status=active&sort=expires_at&direction=desc';
$app = new FBL\Application();
require CONFIG . '/routes.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$expected = [
    'POST /admin/plugins/vpn-manager-v2/subscriptions/(?P<id>\d+)/suspend/?',
    'POST /admin/plugins/vpn-manager-v2/subscriptions/(?P<id>\d+)/delete/?',
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
        $assert(($route['needCSRFToken'] ?? false) === true, $key . ' is not CSRF protected.');
        $found[$key] = ($found[$key] ?? 0) + 1;
    }
}
foreach ($expected as $key) {
    $assert(($found[$key] ?? 0) === 1, $key . ' is missing or duplicated.');
}

$plugin = db()->query('SELECT slug, version, status FROM plugins WHERE slug = ? LIMIT 1', ['vpn-manager-v2'])->getOne();
$assert(is_array($plugin) && $plugin['version'] === '0.8.0' && $plugin['status'] === 'active',
    'Plugin metadata is not at active stage 8.');
$assert(isset(FireballPluginVpnManagerV2::permissions()[Fireball\VpnManagerV2\Support\Permissions::MANAGE_SUBSCRIPTIONS]),
    'Subscription permission is missing.');
$assert(Fireball\VpnManagerV2\Support\Permissions::allows(
    Fireball\VpnManagerV2\Support\Permissions::MANAGE_SUBSCRIPTIONS,
    ['role' => 'admin']
), 'Admin permission guard failed.');
$assert(!Fireball\VpnManagerV2\Support\Permissions::allows(
    Fireball\VpnManagerV2\Support\Permissions::MANAGE_SUBSCRIPTIONS,
    ['role' => 'user']
), 'User passed the destructive permission guard.');

$state = Fireball\VpnManagerV2\Support\AdminTableState::sanitize(
    'page=4&search=vpn&status=active&sort=expires_at&direction=desc&filters%5Bserver%5D=1&return_url=https://evil.invalid'
);
$assert(str_contains($state, 'page=4') && str_contains($state, 'search=vpn')
    && str_contains($state, 'filters%5Bserver%5D=1') && !str_contains($state, 'return_url'),
    'Table return state is not preserved safely.');

$html = (new Fireball\VpnManagerV2\Controllers\Admin\SubscriptionController())->index();
$assert(str_contains($html, 'admin-table-component__table'), 'Universal subscription table failed.');
$assert(str_contains($html, '/suspend') && str_contains($html, '/delete')
    && str_contains($html, 'data-admin-delete-form'), 'Suspend/delete table actions are missing.');
$assert(str_contains($html, 'name="return_query"') && str_contains($html, 'page=4')
    && str_contains($html, 'search%3Dvpn'), 'Subscription table forms lost the current list state.');
$assert(FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_subscription_delete_failed')
    === 'Не удалось полностью удалить подписку. Проверьте подключения и журнал.'
    || FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_subscription_delete_failed') !== '',
    'Delete failure flash translation is missing.');

echo json_encode([
    'status' => 'ok',
    'routes' => array_keys($found),
    'plugin' => $plugin,
    'csrf' => true,
    'admin_middleware' => true,
    'permission_guard' => true,
    'return_state' => true,
    'universal_table_actions' => true,
], JSON_UNESCAPED_SLASHES), PHP_EOL;
