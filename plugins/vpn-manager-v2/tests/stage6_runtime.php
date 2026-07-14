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

$routeKeys = [];
$publicRoutes = [];
foreach ($app->router->getRoutes() as $route) {
    $path = (string)($route['path'] ?? '');
    foreach ((array)($route['method'] ?? []) as $method) {
        $key = strtoupper((string)$method) . ' ' . $path;
        $routeKeys[$key] = ($routeKeys[$key] ?? 0) + 1;
    }
    if (str_starts_with($path, '/vpn-v2/subscription/')) {
        $publicRoutes[] = $route;
    }
}
$assert(count($publicRoutes) === 1, 'The VPN V2 public subscription route is missing or duplicated.');
$publicRoute = $publicRoutes[0];
$assert(($publicRoute['method'] ?? []) === ['GET'], 'The subscription endpoint is not GET-only.');
$assert(($publicRoute['middleware'] ?? []) === [], 'The public subscription endpoint has middleware.');
$assert(($publicRoute['needCSRFToken'] ?? true) === false, 'The public GET endpoint was not explicitly marked CSRF-free.');
foreach ($routeKeys as $key => $count) {
    if (str_contains($key, '/vpn-v2/subscription/')) {
        $assert($count === 1, 'Duplicate VPN V2 subscription route.');
    }
}
$oldRoutes = array_filter(
    $app->router->getRoutes(),
    static fn(array $route): bool => str_starts_with((string)($route['path'] ?? ''), '/vpn/subscription/')
        || str_starts_with((string)($route['path'] ?? ''), '/plugins/vpn-manager/subscription/')
);
foreach ($oldRoutes as $oldRoute) {
    $assert((string)$oldRoute['path'] !== (string)$publicRoute['path'], 'VPN V2 conflicts with the old VPN endpoint.');
}

$plugin = db()->query(
    'SELECT slug, version, status FROM plugins WHERE slug = ? LIMIT 1',
    ['vpn-manager-v2']
)->getOne();
$assert(is_array($plugin) && $plugin['version'] === '0.6.0' && $plugin['status'] === 'active', 'Plugin metadata is not at active stage 6.');

$token = str_repeat('a', 64);
$qr = (new Fireball\VpnManagerV2\Services\QrCodeService())->renderForToken($token);
$url = (new Fireball\VpnManagerV2\Services\VpnSubscriptionUrlService())->forToken($token);
$assert(str_contains($url, '/vpn-v2/subscription/') && str_contains($qr, '<svg'), 'Public URL or local QR is unavailable.');
$assert(!str_contains(strtolower($qr), 'cdn') && !str_contains(strtolower($qr), 'qrserver'), 'QR references an external service.');

$invalid = (new Fireball\VpnManagerV2\Services\VpnSubscriptionEndpointService())->respond(str_repeat('f', 64));
$assert($invalid->status === 404, 'Unknown token does not return 404.');
$assert(($invalid->headers['Content-Type'] ?? '') === 'text/plain; charset=utf-8', 'Content-Type is missing.');
$assert(($invalid->headers['Cache-Control'] ?? '') === 'private, no-cache, must-revalidate', 'Cache-Control is invalid.');

$subscriptionsHtml = (new Fireball\VpnManagerV2\Controllers\Admin\SubscriptionController())->index();
$assert(str_contains($subscriptionsHtml, 'admin-table-component__table'), 'The universal subscription table no longer renders.');
$assert(isset(FireballPluginVpnManagerV2::permissions()[Fireball\VpnManagerV2\Support\Permissions::MANAGE_SUBSCRIPTIONS]), 'Subscription permission is missing.');

echo json_encode([
    'status' => 'ok',
    'public_route' => [
        'method' => 'GET',
        'path' => (string)$publicRoute['path'],
        'middleware' => [],
    ],
    'plugin' => $plugin,
    'old_route_conflicts' => 0,
    'content_type' => true,
    'cache_control' => true,
    'local_qr' => true,
    'universal_table' => true,
    'permission' => Fireball\VpnManagerV2\Support\Permissions::MANAGE_SUBSCRIPTIONS,
], JSON_UNESCAPED_SLASHES), PHP_EOL;
