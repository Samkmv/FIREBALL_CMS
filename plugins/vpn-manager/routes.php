<?php

use Fireball\VpnManager\Services\SubscriptionConfigService;

/** @var \FBL\Router $router */

$router->get('/plugins/vpn-manager/assets/(?P<file>[a-z0-9._-]+)', static function (): never {
    $file = (string)get_route_param('file');
    if (!in_array($file, ['vpn-manager.css', 'vpn-manager.js'], true)) {
        abort();
    }

    $path = __DIR__ . '/assets/' . $file;
    $real = realpath($path);
    $base = realpath(__DIR__ . '/assets');
    if ($real === false || $base === false || !str_starts_with($real, rtrim($base, '/') . '/')) {
        abort();
    }

    header('Content-Type: ' . (str_ends_with($file, '.css') ? 'text/css' : 'application/javascript') . '; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
    readfile($real);
    exit;
});

$router->get('/my-vpn', static function (): string {
    $user = get_user();
    $userId = is_array($user) ? (int)($user['id'] ?? 0) : 0;
    $repo = FireballPluginVpnManager::repository();

    return plugin_view('vpn-manager', 'my-vpn', FireballPluginVpnManager::viewData('dashboard', [
        'title' => FireballPluginVpnManager::t('vpn_manager_my_vpn_title'),
        'subscriptions' => $repo->mySubscriptions($userId),
    ]));
})->middleware(['auth']);

$router->get('/vpn/subscription/(?P<token>[a-zA-Z0-9._-]+)/?', static function (): string {
    $token = (string)get_route_param('token');
    $repo = FireballPluginVpnManager::repository();
    $subscription = $repo->subscriptionByToken($token);
    if (!$subscription) {
        response()->setResponseCode(404);

        return FireballPluginVpnManager::t('vpn_manager_subscription_endpoint_not_found');
    }

    $payload = (new SubscriptionConfigService($repo))->encodedPayload($subscription);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Subscription-Userinfo: upload=0; download=' . (int)($subscription['traffic_used_bytes'] ?? 0) . '; total=' . (int)($subscription['traffic_limit_bytes'] ?? 0) . '; expire=' . (strtotime((string)($subscription['expires_at'] ?? '')) ?: 0));

    if ($payload === '') {
        return FireballPluginVpnManager::t('vpn_manager_subscription_endpoint_not_ready');
    }

    return $payload;
});
