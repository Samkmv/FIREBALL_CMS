<?php

if (PHP_VERSION_ID < 80200) {
    http_response_code(500);
    exit('Requires PHP version 8.2 or higher.');
}

require_once __DIR__ . '/../config/config.php';

if (!is_dir(dirname(ERROR_LOGS))) {
    @mkdir(dirname(ERROR_LOGS), 0755, true);
}

ini_set('log_errors', '1');
ini_set('error_log', ERROR_LOGS);
error_reporting(E_ALL);

$vendorAutoload = ROOT . '/vendor/autoload.php';
if (is_file($vendorAutoload)) {
    require_once $vendorAutoload;
}

require_once HELPERS . '/helpers.php';

register_shutdown_function('log_last_php_error');

$app = new \FBL\Application();
require_once PLUGINS . '/vpn-manager/Plugin.php';

$token = trim((string)($_GET['token'] ?? ''));
$format = strtolower(trim((string)($_GET['format'] ?? 'plain')));
$debug = !empty($_GET['debug']);
$repo = FireballPluginVpnManager::repository();

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
header('X-VPN-Manager-Endpoint: file');

if ($token === '') {
    http_response_code(404);
    exit($debug ? "VPN Manager endpoint OK\nerror=missing_token\n" : '');
}

$subscription = $repo->subscriptionByToken($token);
if (!$subscription) {
    $repo->logEvent('subscription_endpoint_invalid_token', 'VPN subscription file endpoint was opened with an invalid token.', [
        'preview' => substr($token, 0, 12),
    ]);
    http_response_code(404);
    if ($debug) {
        $count = (int)db()->query('SELECT COUNT(*) FROM vpn_subscriptions')->getColumn();
        exit("VPN Manager endpoint OK\nerror=token_not_found\ntoken_preview=" . substr($token, 0, 12) . "\nsubscriptions_count={$count}\n");
    }

    exit('');
}

header('Subscription-Userinfo: upload=0; download=' . (int)($subscription['traffic_used_bytes'] ?? 0) . '; total=' . (int)($subscription['traffic_limit_bytes'] ?? 0) . '; expire=' . (strtotime((string)($subscription['expires_at'] ?? '')) ?: 0));

if ((string)($subscription['status'] ?? '') !== 'active') {
    $repo->logEvent('subscription_endpoint_blocked', 'VPN subscription file endpoint did not return configs because subscription is not active.', [
        'status' => (string)($subscription['status'] ?? ''),
    ], (int)($subscription['user_id'] ?? 0), (int)$subscription['id']);
    exit($debug ? "VPN Manager endpoint OK\nerror=subscription_not_active\nstatus=" . (string)($subscription['status'] ?? '') . "\nsubscription_id=" . (int)$subscription['id'] . "\n" : '');
}

$configService = new \Fireball\VpnManager\Services\SubscriptionConfigService($repo);
$uris = $configService->uris($subscription, true);
if (!$uris) {
    $repo->logEvent('subscription_endpoint_empty', 'VPN subscription file endpoint has no active configs to return.', [], (int)($subscription['user_id'] ?? 0), (int)$subscription['id']);
    exit($debug ? "VPN Manager endpoint OK\nerror=no_server_links\nsubscription_id=" . (int)$subscription['id'] . "\n" : '');
}

$plainPayload = implode("\n", $uris) . "\n";
$repo->logEvent('subscription_endpoint_payload_ready', 'VPN subscription file endpoint returned configs.', [
    'format' => $format === 'base64' ? 'base64' : 'plain',
    'links_count' => count($uris),
], (int)($subscription['user_id'] ?? 0), (int)$subscription['id']);

if ($debug) {
    exit("VPN Manager endpoint OK\nsubscription_id=" . (int)$subscription['id'] . "\nlinks_count=" . count($uris) . "\nformat=" . ($format === 'base64' ? 'base64' : 'plain') . "\nfirst_link=" . $uris[0] . "\n");
}

exit($format === 'base64' ? base64_encode($plainPayload) : $plainPayload);
