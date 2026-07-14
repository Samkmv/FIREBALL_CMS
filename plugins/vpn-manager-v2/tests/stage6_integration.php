<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/config/config.php';
require ROOT . '/vendor/autoload.php';
require ROOT . '/helpers/helpers.php';

new FBL\Application();
require dirname(__DIR__) . '/Plugin.php';
FBL\Language::registerPluginLanguage('vpn-manager-v2', dirname(__DIR__) . '/lang');

use Fireball\VpnManagerV2\Services\QrCodeService;
use Fireball\VpnManagerV2\Services\VpnFlowResolver;
use Fireball\VpnManagerV2\Services\VpnSubscriptionCache;
use Fireball\VpnManagerV2\Services\VpnSubscriptionEndpointService;
use Fireball\VpnManagerV2\Services\VpnSubscriptionRevisionService;
use Fireball\VpnManagerV2\Support\SubscriptionToken;
use Fireball\VpnManagerV2\Support\Uuid;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$lines = static function (string $body, string $format): array {
    $plain = $format === 'base64' ? base64_decode($body, true) : $body;
    if (!is_string($plain)) {
        return [];
    }

    return array_values(array_filter(array_map('trim', explode("\n", $plain))));
};
$query = static function (string $uri): array {
    parse_str((string)(parse_url($uri, PHP_URL_QUERY) ?? ''), $params);

    return $params;
};

$admin = db()->query("SELECT id FROM users WHERE role IN ('creator', 'admin') ORDER BY id LIMIT 1")->getOne();
$user = db()->query("SELECT id FROM users WHERE role = 'user' ORDER BY id LIMIT 1")->getOne()
    ?: db()->query('SELECT id FROM users ORDER BY id LIMIT 1')->getOne();
$assert(is_array($admin) && is_array($user), 'CMS users are required.');
$adminId = (int)$admin['id'];
$userId = (int)$user['id'];
$suffix = substr(hash('sha256', uniqid('vpn-v2-stage6-', true)), 0, 10);
$now = date('Y-m-d H:i:s');
$future = date('Y-m-d H:i:s', time() + 86400 * 30);
$past = date('Y-m-d H:i:s', time() - 3600);
$serverIds = [];
$inboundIds = [];
$subscriptionIds = [];
$tokens = [];
$planId = 0;
$cache = new VpnSubscriptionCache();

try {
    foreach ([1, 2] as $number) {
        db()->query(
            'INSERT INTO vpn_v2_servers
                (name, code, panel_url, panel_path, auth_type, country_code, country_name, city,
                 show_flag, status, is_enabled, created_at, updated_at)
             VALUES (?, ?, ?, NULL, ?, ?, ?, ?, 1, ?, 1, ?, ?)',
            [
                'Stage 6 Node ' . $number,
                'stage6-' . $number . '-' . $suffix,
                'https://vpn-' . $number . '.stage6.example:2053',
                'token',
                $number === 1 ? 'DE' : 'NL',
                $number === 1 ? 'Germany' : 'Netherlands',
                $number === 1 ? 'Berlin' : 'Amsterdam',
                'online',
                $now,
                $now,
            ]
        );
        $serverIds[$number] = (int)db()->getInsertId();
    }

    $tcpStream = [
        'network' => 'tcp',
        'security' => 'reality',
        'tcpSettings' => ['header' => ['type' => 'none']],
        'realitySettings' => [
            'serverNames' => ['tcp.stage6.example'],
            'shortIds' => ['a1b2c3d4'],
            'settings' => ['publicKey' => 'stage6-public-one', 'fingerprint' => 'chrome', 'spiderX' => '/one'],
        ],
    ];
    $xhttpStream = [
        'network' => 'xhttp',
        'security' => 'reality',
        'xhttpSettings' => [
            'path' => '/stage6-two',
            'host' => 'xhttp.stage6.example',
            'mode' => 'packet-up',
            'xPaddingBytes' => '120-800',
            'noGRPCHeader' => true,
        ],
        'realitySettings' => [
            'serverNames' => ['xhttp-reality.stage6.example'],
            'shortIds' => ['d4c3b2a1'],
            'settings' => ['publicKey' => 'stage6-public-two', 'fingerprint' => 'firefox'],
        ],
    ];
    foreach ([1 => $tcpStream, 2 => $xhttpStream] as $number => $stream) {
        $network = (string)$stream['network'];
        db()->query(
            'INSERT INTO vpn_v2_inbounds
                (server_id, remote_inbound_id, name, remark, protocol, port, network, security,
                 default_flow, settings_json, stream_settings_json, status, is_enabled,
                 synced_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)',
            [
                $serverIds[$number],
                (string)(61000 + $number),
                'Stage 6 ' . strtoupper($network),
                'Node ' . $number,
                'vless',
                440 + $number,
                $network,
                'reality',
                $network === 'tcp' ? VpnFlowResolver::VISION : null,
                '{}',
                json_encode($stream, JSON_UNESCAPED_SLASHES),
                'active',
                $now,
                $now,
                $now,
            ]
        );
        $inboundIds[$number] = (int)db()->getInsertId();
    }

    db()->query(
        'INSERT INTO vpn_v2_plans
            (name, description, duration_days, traffic_limit_bytes, device_limit, is_active, created_at, updated_at)
         VALUES (?, ?, 30, ?, 3, 1, ?, ?)',
        ['Stage 6 ' . $suffix, 'Stage 6 fixture', 50 * (1024 ** 3), $now, $now]
    );
    $planId = (int)db()->getInsertId();

    $createSubscription = static function (string $status, string $expiresAt, array $numbers) use (
        &$subscriptionIds,
        &$tokens,
        $userId,
        $adminId,
        $planId,
        $serverIds,
        $inboundIds,
        $now
    ): int {
        $token = SubscriptionToken::generate();
        db()->query(
            'INSERT INTO vpn_v2_subscriptions
                (user_id, plan_id, status, starts_at, expires_at, traffic_limit_bytes, device_limit,
                 subscription_token, revision, config_updated_at, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, 3, ?, 1, ?, ?, ?, ?)',
            [$userId, $planId, $status, date('Y-m-d H:i:s', time() - 3600), $expiresAt, 50 * (1024 ** 3), $token, $now, $adminId, $now, $now]
        );
        $subscriptionId = (int)db()->getInsertId();
        $subscriptionIds[] = $subscriptionId;
        $tokens[$subscriptionId] = $token;
        foreach ($numbers as $number) {
            $network = $number === 1 ? 'tcp' : 'xhttp';
            db()->query(
                'INSERT INTO vpn_v2_subscription_nodes
                    (subscription_id, server_id, inbound_id, remote_client_id, client_uuid, client_email,
                     client_sub_id, protocol, network, security, flow, status, traffic_limit_bytes,
                     traffic_used_bytes, last_sync_at, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?)',
                [
                    $subscriptionId,
                    $serverIds[$number],
                    $inboundIds[$number],
                    Uuid::v4(),
                    Uuid::v4(),
                    'stage6-u' . $userId . '-s' . $subscriptionId . '-n' . $number,
                    bin2hex(random_bytes(8)),
                    'vless',
                    $network,
                    'reality',
                    $network === 'tcp' ? VpnFlowResolver::VISION : null,
                    'active',
                    50 * (1024 ** 3),
                    $now,
                    $now,
                    $now,
                ]
            );
        }

        return $subscriptionId;
    };

    $singleId = $createSubscription('active', $future, [1]);
    $multiId = $createSubscription('active', $future, [1, 2]);
    $suspendedId = $createSubscription('suspended', $future, [1]);
    $expiredId = $createSubscription('active', $past, [1]);

    $endpoint = new VpnSubscriptionEndpointService();
    $single = $endpoint->respond($tokens[$singleId]);
    $singleLines = $lines($single->body, 'base64');
    $assert($single->status === 200 && count($singleLines) === 1 && $single->configCount === 1, 'Single subscription response is invalid.');
    $singleQuery = $query($singleLines[0]);
    $assert(($singleQuery['security'] ?? '') === 'reality' && ($singleQuery['flow'] ?? '') === VpnFlowResolver::VISION, 'Single Reality URI is invalid.');
    $cachedSingle = $endpoint->respond($tokens[$singleId]);
    $assert($cachedSingle->cacheHit && $cachedSingle->body === $single->body, 'Subscription cache was not used.');

    $plain = $endpoint->respond($tokens[$singleId], 'plain');
    $assert($plain->status === 200 && count($lines($plain->body, 'plain')) === 1 && $plain->headers['ETag'] !== $single->headers['ETag'], 'Plain format or ETag is invalid.');
    $notModified = $endpoint->respond($tokens[$singleId], 'base64', (string)$single->headers['ETag']);
    $assert($notModified->status === 304 && $notModified->body === '', 'ETag did not produce 304.');
    $modifiedSince = $endpoint->respond($tokens[$singleId], 'base64', '', (string)$single->headers['Last-Modified']);
    $assert($modifiedSince->status === 304, 'Last-Modified did not produce 304.');

    $multi = $endpoint->respond($tokens[$multiId]);
    $multiLines = $lines($multi->body, 'base64');
    $assert($multi->status === 200 && count($multiLines) === 2, 'Multi-server subscription is incomplete.');
    $xhttpQuery = $query($multiLines[1]);
    $assert(($xhttpQuery['type'] ?? '') === 'xhttp'
        && ($xhttpQuery['path'] ?? '') === '/stage6-two'
        && !array_key_exists('flow', $xhttpQuery), 'XHTTP config is invalid.');

    $assert($endpoint->respond(str_repeat('f', 64))->status === 404, 'Invalid token was accepted.');
    $assert($endpoint->respond($tokens[$suspendedId])->status === 403, 'Suspended subscription was returned.');
    $assert($endpoint->respond($tokens[$expiredId])->status === 410, 'Expired subscription was returned.');

    $beforeRevision = (int)db()->query('SELECT revision FROM vpn_v2_subscriptions WHERE id = ?', [$singleId])->getColumn();
    $newRevision = (new VpnSubscriptionRevisionService())->touchConfig($singleId);
    $afterTouch = $endpoint->respond($tokens[$singleId]);
    $assert($newRevision === $beforeRevision + 1
        && $afterTouch->headers['ETag'] !== $single->headers['ETag']
        && !$afterTouch->cacheHit, 'Revision did not invalidate cached output.');
    $serverTouches = (new VpnSubscriptionRevisionService())->touchByServer($serverIds[1]);
    $assert($serverTouches === 2, 'Server-dependent active subscriptions were not revised.');

    (new VpnSubscriptionRevisionService())->invalidateCache($singleId);
    $afterInvalidation = $endpoint->respond($tokens[$singleId]);
    $assert(!$afterInvalidation->cacheHit, 'Explicit cache invalidation failed.');

    $qr = (new QrCodeService())->renderForToken($tokens[$singleId]);
    $assert(str_contains($qr, '<svg') && !str_contains($qr, 'api.qrserver'), 'QR was not generated locally.');

    echo json_encode([
        'status' => 'ok',
        'results' => [
            'single_server' => true,
            'multiple_servers' => true,
            'tcp_reality_uri' => true,
            'vision_flow' => true,
            'xhttp_without_flow' => true,
            'qr_local_svg' => true,
            'cache_hit' => true,
            'etag_304' => true,
            'last_modified_304' => true,
            'revision_invalidation' => true,
            'invalid_token_404' => true,
            'suspended_403' => true,
            'expired_410' => true,
        ],
        'fixtures_cleaned' => true,
    ], JSON_UNESCAPED_SLASHES), PHP_EOL;
} finally {
    foreach ($tokens as $token) {
        for ($revision = 1; $revision <= 8; $revision++) {
            $cache->invalidate($token, $revision);
        }
    }
    if ($subscriptionIds !== []) {
        $placeholders = implode(',', array_fill(0, count($subscriptionIds), '?'));
        db()->query("DELETE FROM vpn_v2_events WHERE subscription_id IN ({$placeholders})", $subscriptionIds);
        db()->query("DELETE FROM vpn_v2_subscriptions WHERE id IN ({$placeholders})", $subscriptionIds);
    }
    if ($planId > 0) {
        db()->query('DELETE FROM vpn_v2_plans WHERE id = ?', [$planId]);
    }
    foreach ($inboundIds as $inboundId) {
        db()->query('DELETE FROM vpn_v2_inbounds WHERE id = ?', [$inboundId]);
    }
    foreach ($serverIds as $serverId) {
        db()->query('DELETE FROM vpn_v2_events WHERE server_id = ?', [$serverId]);
        db()->query('DELETE FROM vpn_v2_servers WHERE id = ?', [$serverId]);
    }
}
