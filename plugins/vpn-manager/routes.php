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

$vpnSubscriptionEndpoint = static function (): string {
    $token = (string)get_route_param('token');
    $debug = !empty($_GET['debug']);
    $repo = FireballPluginVpnManager::repository();
    $subscription = $repo->subscriptionByToken($token);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('X-VPN-Manager-Endpoint: route');

    if (!$subscription) {
        $repo->logEvent('subscription_endpoint_invalid_token', 'VPN subscription endpoint was opened with an invalid token.', [
            'preview' => substr($token, 0, 12),
        ]);
        if ($debug) {
            $count = (int)db()->query('SELECT COUNT(*) FROM vpn_subscriptions')->getColumn();
            return "VPN Manager endpoint OK\nerror=token_not_found\ntoken_preview=" . substr($token, 0, 12) . "\nsubscriptions_count={$count}\n";
        }

        return '';
    }

    header('Subscription-Userinfo: upload=0; download=' . (int)($subscription['traffic_used_bytes'] ?? 0) . '; total=' . (int)($subscription['traffic_limit_bytes'] ?? 0) . '; expire=' . (strtotime((string)($subscription['expires_at'] ?? '')) ?: 0));

    $status = (string)($subscription['status'] ?? '');
    if ($status !== 'active') {
        $repo->logEvent('subscription_endpoint_blocked', 'VPN subscription endpoint did not return configs because subscription is not active.', [
            'status' => $status,
        ], (int)($subscription['user_id'] ?? 0), (int)$subscription['id']);

        return $debug
            ? "VPN Manager endpoint OK\nerror=subscription_not_active\nstatus={$status}\nsubscription_id=" . (int)$subscription['id'] . "\n"
            : '';
    }

    $repo->logEvent('subscription_endpoint_opened', 'VPN subscription endpoint opened.', [], (int)($subscription['user_id'] ?? 0), (int)$subscription['id']);
    $configService = new SubscriptionConfigService($repo);
    $uris = $configService->uris($subscription, true);
    if (!$uris) {
        $repo->logEvent('subscription_endpoint_empty', 'VPN subscription endpoint has no active configs to return.', [], (int)($subscription['user_id'] ?? 0), (int)$subscription['id']);

        return $debug ? "VPN Manager endpoint OK\nerror=no_server_links\nsubscription_id=" . (int)$subscription['id'] . "\n" : '';
    }

    $format = strtolower(trim((string)($_GET['format'] ?? 'plain')));
    $plainPayload = implode("\n", $uris) . "\n";
    $repo->logEvent('subscription_endpoint_payload_ready', 'VPN subscription endpoint returned configs.', [
        'format' => $format === 'base64' ? 'base64' : 'plain',
        'links_count' => count($uris),
    ], (int)($subscription['user_id'] ?? 0), (int)$subscription['id']);

    if ($debug) {
        return "VPN Manager endpoint OK\nsubscription_id=" . (int)$subscription['id'] . "\nlinks_count=" . count($uris) . "\nformat=" . ($format === 'base64' ? 'base64' : 'plain') . "\nfirst_link=" . $uris[0] . "\n";
    }

    return $format === 'base64' ? base64_encode($plainPayload) : $plainPayload;
};

$router->get('/plugins/vpn-manager/subscription/(?P<token>[a-zA-Z0-9._-]+)/?', $vpnSubscriptionEndpoint);
$router->get('/vpn/subscription/(?P<token>[a-zA-Z0-9._-]+)/?', $vpnSubscriptionEndpoint);
