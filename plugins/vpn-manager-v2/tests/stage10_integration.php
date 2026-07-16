<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/config/config.php';
require ROOT . '/vendor/autoload.php';
require ROOT . '/helpers/helpers.php';

new FBL\Application();
require dirname(__DIR__) . '/Plugin.php';
FBL\Language::registerPluginLanguage('vpn-manager-v2', dirname(__DIR__) . '/lang');

use Fireball\VpnManagerV2\Repositories\ProfileVpnRepository;
use Fireball\VpnManagerV2\Services\ProfileVpnService;
use Fireball\VpnManagerV2\Services\QrCodeService;
use Fireball\VpnManagerV2\Services\VpnSubscriptionEndpointService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$suffix = substr(hash('sha256', uniqid('stage10-', true)), 0, 12);
$now = date('Y-m-d H:i:s');
$userIds = [];
$subscriptionIds = [];
$tokens = [];
$serverId = 0;
$inboundId = 0;
$planId = 0;
$results = [];

try {
    foreach (['owner', 'other', 'empty'] as $role) {
        db()->query(
            'INSERT INTO users (name, login, email, password, role, created_at)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                'Stage 10 ' . $role,
                'stage10-' . $role . '-' . $suffix,
                'stage10-' . $role . '-' . $suffix . '@example.invalid',
                password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
                'user',
                $now,
            ]
        );
        $userIds[$role] = (int)db()->getInsertId();
    }

    $panelMarker = 'panel-secret-' . $suffix . '.invalid';
    db()->query(
        'INSERT INTO vpn_v2_servers
            (name, code, panel_url, panel_path, auth_type, country_code, country_name, city,
             show_flag, status, is_enabled, created_at, updated_at)
         VALUES (?, ?, ?, NULL, ?, ?, ?, ?, 1, ?, 1, ?, ?)',
        ['Germany Stage 10', 'stage10-' . $suffix, 'https://' . $panelMarker, 'token', 'DE', 'Германия', 'Франкфурт', 'online', $now, $now]
    );
    $serverId = (int)db()->getInsertId();
    $remoteInboundId = 991010;
    $internalJsonMarker = 'internal-json-' . $suffix;
    db()->query(
        'INSERT INTO vpn_v2_inbounds
            (server_id, remote_inbound_id, name, protocol, port, network, security,
             settings_json, stream_settings_json, status, is_enabled, synced_at, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)',
        [$serverId, (string)$remoteInboundId, 'Stage 10 inbound', 'vless', 443, 'tcp', 'reality',
            json_encode(['marker' => $internalJsonMarker]), json_encode(['security' => 'reality']),
            'active', $now, $now, $now]
    );
    $inboundId = (int)db()->getInsertId();
    db()->query(
        'INSERT INTO vpn_v2_plans
            (name, description, duration_days, traffic_limit_bytes, device_limit, is_active, created_at, updated_at)
         VALUES (?, ?, 30, ?, 3, 1, ?, ?)',
        ['Stage 10 Premium ' . $suffix, 'Safe customer plan', 10 * (1024 ** 3), $now, $now]
    );
    $planId = (int)db()->getInsertId();

    $createSubscription = static function (int $userId, string $status, string $expiresAt, string $name) use (
        &$subscriptionIds, &$tokens, $planId, $serverId, $inboundId, $now, $suffix
    ): int {
        $token = bin2hex(random_bytes(32));
        db()->query(
            'INSERT INTO vpn_v2_subscriptions
                (user_id, plan_id, status, starts_at, expires_at, traffic_limit_bytes, device_limit,
                 subscription_token, revision, config_updated_at, created_by, internal_comment,
                 last_error, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, 3, ?, 1, ?, ?, ?, ?, ?, ?)',
            [$userId, $planId, $status, date('Y-m-d H:i:s', time() - 86400), $expiresAt,
                10 * (1024 ** 3), $token, $now, $userId, 'internal-' . $name,
                'api-error-' . $name, $now, $now]
        );
        $subscriptionId = (int)db()->getInsertId();
        $subscriptionIds[] = $subscriptionId;
        $tokens[$subscriptionId] = $token;
        $uuid = sprintf('a0000000-0000-4000-8000-%012d', $subscriptionId);
        db()->query(
            'INSERT INTO vpn_v2_subscription_nodes
                (subscription_id, server_id, inbound_id, remote_client_id, client_uuid, client_email,
                 client_sub_id, protocol, network, security, flow, status, traffic_limit_bytes,
                 traffic_used_bytes, last_sync_at, last_error, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?)',
            [$subscriptionId, $serverId, $inboundId, $uuid, $uuid,
                'technical-' . $name . '-' . $suffix, 'sub-' . $name . '-' . $suffix,
                'vless', 'tcp', 'reality', 'active', 10 * (1024 ** 3), 2 * (1024 ** 3),
                $now, 'node-api-error-' . $name, $now, $now]
        );

        return $subscriptionId;
    };

    $activeId = $createSubscription($userIds['owner'], 'active', date('Y-m-d H:i:s', time() + 30 * 86400), 'active');
    $expiredId = $createSubscription($userIds['owner'], 'active', date('Y-m-d H:i:s', time() - 3600), 'expired');
    $foreignId = $createSubscription($userIds['other'], 'active', date('Y-m-d H:i:s', time() + 30 * 86400), 'foreign');
    $deletedId = $createSubscription($userIds['owner'], 'active', date('Y-m-d H:i:s', time() + 30 * 86400), 'deleted');
    $deletedToken = $tokens[$deletedId];
    db()->query('DELETE FROM vpn_v2_subscriptions WHERE id = ?', [$deletedId]);

    $repository = new ProfileVpnRepository();
    $service = new ProfileVpnService($repository);
    $assert($repository->hasSubscriptionsForUser($userIds['owner']), 'Owner profile menu should be visible.');
    $assert(!$repository->hasSubscriptionsForUser($userIds['empty']), 'Empty user profile menu should be hidden.');
    $results['user_with_subscription'] = true;
    $results['user_without_subscription'] = true;

    $active = $service->dashboard($userIds['owner'], $activeId, 'ios');
    $assert($active['requestedSubscriptionFound'] && count($active['subscriptions']) === 2
        && $active['linkReady'] && count($active['servers']) === 1, 'Owner dashboard data is incomplete.');
    $results['multiple_subscriptions'] = true;

    $foreign = $service->dashboard($userIds['owner'], $foreignId);
    $assert(!$foreign['requestedSubscriptionFound'] && $foreign['selectedSubscription'] === null,
        'Another user subscription was accessible by ID.');
    $results['foreign_subscription_404_contract'] = true;

    (new FireballPluginVpnManagerV2())->boot();
    $ownerMenu = apply_filters('profile_menu', [], ['id' => $userIds['owner']]);
    $emptyMenu = apply_filters('profile_menu', [], ['id' => $userIds['empty']]);
    $assert(count(array_filter($ownerMenu, static fn(array $item): bool => ($item['key'] ?? '') === 'vpn-v2')) === 1
        && $emptyMenu === [], 'Profile Plugin API registration is incorrect.');
    $results['profile_menu_hook'] = true;

    $html = plugin_view('vpn-manager-v2', 'public/my-vpn', FireballPluginVpnManagerV2::publicViewData(array_merge($active, [
        'title' => FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_title'),
        'subtitle' => FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_subtitle'),
    ])), false);
    $uuidMarker = sprintf('a0000000-0000-4000-8000-%012d', $activeId);
    $assert(str_contains($html, 'data-vpn-v2-copy-value') && str_contains($html, 'data-vpn-v2-manual-copy')
        && str_contains($html, 'data-vpn-v2-copy-input') && str_contains($html, '<svg')
        && str_contains($html, '🇩🇪') && str_contains($html, 'iPhone / iPad')
        && str_contains($html, 'Android') && str_contains($html, 'Windows') && str_contains($html, 'macOS')
        && str_contains($html, 'После изменения параметров обновите подписку в VPN-приложении.'),
        'Safe customer cabinet content is incomplete.');
    foreach ([$panelMarker, (string)$remoteInboundId, $internalJsonMarker, $uuidMarker,
        'technical-active-' . $suffix, 'api-error-active', 'node-api-error-active'] as $forbidden) {
        $assert(!str_contains($html, $forbidden), 'Technical data leaked into profile HTML.');
    }
    $results['copy_link_iphone_markup'] = true;
    $results['phone_qr_svg'] = true;
    $results['safe_projection'] = true;
    $results['four_platforms'] = true;

    $expired = $service->dashboard($userIds['owner'], $expiredId);
    $assert($expired['selectedSubscription']['effective_status'] === 'expired'
        && !$expired['linkReady'] && $expired['subscriptionUrl'] === '' && $expired['subscriptionQr'] === '',
        'Expired subscription still exposes access credentials.');
    $results['expired_subscription'] = true;

    $deleted = $service->dashboard($userIds['owner'], $deletedId);
    $assert(!$deleted['requestedSubscriptionFound']
        && (new VpnSubscriptionEndpointService())->respond($deletedToken)->status === 404,
        'Deleted subscription remained accessible.');
    $results['deleted_subscription'] = true;

    echo json_encode(['status' => 'ok', 'results' => $results, 'fixtures_cleaned' => true], JSON_UNESCAPED_SLASHES), PHP_EOL;
} finally {
    foreach ($tokens as $token) {
        (new QrCodeService())->invalidateToken($token);
    }
    foreach ($subscriptionIds as $subscriptionId) {
        db()->query('DELETE FROM vpn_v2_events WHERE subscription_id = ?', [$subscriptionId]);
        db()->query('DELETE FROM vpn_v2_subscriptions WHERE id = ?', [$subscriptionId]);
    }
    if ($planId > 0) {
        db()->query('DELETE FROM vpn_v2_plan_nodes WHERE plan_id = ?', [$planId]);
        db()->query('DELETE FROM vpn_v2_plans WHERE id = ?', [$planId]);
    }
    if ($inboundId > 0) {
        db()->query('DELETE FROM vpn_v2_inbounds WHERE id = ?', [$inboundId]);
    }
    if ($serverId > 0) {
        db()->query('DELETE FROM vpn_v2_events WHERE server_id = ?', [$serverId]);
        db()->query('DELETE FROM vpn_v2_servers WHERE id = ?', [$serverId]);
    }
    foreach (array_reverse($userIds) as $userId) {
        db()->query('DELETE FROM users WHERE id = ?', [$userId]);
    }
}
