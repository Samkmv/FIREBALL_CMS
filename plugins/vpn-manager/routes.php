<?php

/** @var \FBL\Router $router */

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
    response()->setResponseCode(501);

    return 'VPN subscription delivery is prepared for the next integration stage.';
})->middleware(['auth']);
