<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/config/config.php';
require ROOT . '/vendor/autoload.php';
require ROOT . '/helpers/helpers.php';

new FBL\Application();
require dirname(__DIR__) . '/Plugin.php';
FBL\Language::registerPluginLanguage('vpn-manager-v2', dirname(__DIR__) . '/lang');

use Fireball\VpnManagerV2\Repositories\SettingsRepository;
use Fireball\VpnManagerV2\Services\ProfileVpnService;
use Fireball\VpnManagerV2\Services\QrCodeService;
use Fireball\VpnManagerV2\Services\SettingsService;
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
$uriFrom = static function (string $body): string {
    $plain = base64_decode($body, true);
    if (!is_string($plain)) {
        return '';
    }

    return trim((string)(explode("\n", trim($plain))[0] ?? ''));
};
$displayName = static fn(string $uri): string => rawurldecode((string)(parse_url($uri, PHP_URL_FRAGMENT) ?? ''));

$admin = db()->query("SELECT id FROM users WHERE role IN ('creator', 'admin') ORDER BY id LIMIT 1")->getOne();
$user = db()->query("SELECT id FROM users WHERE role = 'user' ORDER BY id LIMIT 1")->getOne()
    ?: db()->query('SELECT id FROM users ORDER BY id LIMIT 1')->getOne();
$assert(is_array($admin) && is_array($user), 'CMS users are required.');

$settingsRepository = new SettingsRepository();
$settingsRepository->invalidateCache();
$cache = new VpnSubscriptionCache();
$qrCode = new QrCodeService();
$tokens = [];
$revisions = [];
$now = date('Y-m-d H:i:s');
$future = date('Y-m-d H:i:s', time() + 30 * 86400);
$past = date('Y-m-d H:i:s', time() - 3600);
$suffix = substr(hash('sha256', uniqid('stage11-', true)), 0, 10);

db()->beginTransaction();
try {
    db()->query(
        'INSERT INTO vpn_v2_servers
            (name, code, panel_url, auth_type, encrypted_username, encrypted_password, encrypted_token,
             country_code, country_name, city, show_flag, status, is_enabled, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, 1, ?, ?)',
        [
            'Stage 11 Germany',
            'stage11-' . $suffix,
            'https://stage11.example.invalid',
            'token',
            'encrypted-user-marker',
            'encrypted-password-marker',
            'encrypted-token-marker',
            'DE',
            'Germany',
            'Berlin',
            'online',
            $now,
            $now,
        ]
    );
    $serverId = (int)db()->getInsertId();
    $stream = [
        'network' => 'tcp',
        'security' => 'reality',
        'tcpSettings' => ['header' => ['type' => 'none']],
        'realitySettings' => [
            'serverNames' => ['stage11-reality.example'],
            'shortIds' => ['a1b2c3d4'],
            'settings' => ['publicKey' => 'stage11-public-key', 'fingerprint' => 'chrome'],
        ],
    ];
    db()->query(
        'INSERT INTO vpn_v2_inbounds
            (server_id, remote_inbound_id, name, remark, protocol, port, network, security,
             default_flow, settings_json, stream_settings_json, status, is_enabled,
             synced_at, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, 443, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)',
        [
            $serverId,
            '71101',
            'Stage 11 Reality',
            'Stage 11',
            'vless',
            'tcp',
            'reality',
            'xtls-rprx-vision',
            '{}',
            json_encode($stream, JSON_UNESCAPED_SLASHES),
            'active',
            $now,
            $now,
            $now,
        ]
    );
    $inboundId = (int)db()->getInsertId();
    db()->query(
        'INSERT INTO vpn_v2_plans
            (name, description, duration_days, traffic_limit_bytes, device_limit, is_active, created_at, updated_at)
         VALUES (?, ?, 30, ?, 2, 1, ?, ?)',
        ['Stage 11 ' . $suffix, 'Settings fixture', 20 * (1024 ** 3), $now, $now]
    );
    $planId = (int)db()->getInsertId();

    $createSubscription = static function (string $expiresAt) use (
        &$tokens,
        $user,
        $admin,
        $planId,
        $serverId,
        $inboundId,
        $now
    ): array {
        $token = SubscriptionToken::generate();
        db()->query(
            'INSERT INTO vpn_v2_subscriptions
                (user_id, plan_id, status, starts_at, expires_at, traffic_limit_bytes, device_limit,
                 subscription_token, revision, config_updated_at, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, 2, ?, 1, ?, ?, ?, ?)',
            [(int)$user['id'], $planId, 'active', date('Y-m-d H:i:s', time() - 3600), $expiresAt,
                20 * (1024 ** 3), $token, $now, (int)$admin['id'], $now, $now]
        );
        $subscriptionId = (int)db()->getInsertId();
        $tokens[] = $token;
        $uuid = Uuid::v4();
        $email = 'stage11-u' . (int)$user['id'] . '-s' . $subscriptionId;
        $subId = bin2hex(random_bytes(8));
        db()->query(
            'INSERT INTO vpn_v2_subscription_nodes
                (subscription_id, server_id, inbound_id, remote_client_id, client_uuid, client_email,
                 client_sub_id, protocol, network, security, flow, status, traffic_limit_bytes,
                 traffic_used_bytes, last_sync_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?)',
            [$subscriptionId, $serverId, $inboundId, $uuid, $uuid, $email, $subId, 'vless', 'tcp',
                'reality', 'xtls-rprx-vision', 'active', 20 * (1024 ** 3), $now, $now, $now]
        );

        return ['id' => $subscriptionId, 'token' => $token, 'uuid' => $uuid, 'email' => $email, 'sub_id' => $subId];
    };

    $active = $createSubscription($future);
    $expired = $createSubscription($past);
    $nodeBefore = db()->query(
        'SELECT id, client_uuid, client_email, client_sub_id FROM vpn_v2_subscription_nodes WHERE subscription_id = ?',
        [$active['id']]
    )->getOne();

    db()->query(
        'INSERT INTO plugin_settings (plugin_slug, setting_key, setting_value, updated_at)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)',
        ['vpn-manager-v2', 'future_secret', json_encode('preserved-secret-marker'), $now]
    );

    $service = new SettingsService();
    $payload = array_replace($service->current(), [
        'service_name' => 'Stage 11 Alpha',
        'server_name_template' => '{flag} {service} {country} {country_code} {city} {server} {protocol}',
        'global_show_flags' => true,
        'support_name' => 'Stage 11 Support',
        'support_url' => 'https://support.example.invalid',
        'future_secret' => '',
    ]);
    $saved = $service->save($payload);
    $assert($saved['settings']['service_name'] === 'Stage 11 Alpha'
        && $saved['settings']['global_show_flags'] === true,
        'Initial settings were not read back.');
    $assert(($settingsRepository->stored()['future_secret'] ?? '') === 'preserved-secret-marker',
        'An empty unknown secret field overwrote stored data.');

    $endpoint = new VpnSubscriptionEndpointService();
    $alpha = $endpoint->respond($active['token']);
    $alphaUri = $uriFrom($alpha->body);
    $alphaName = $displayName($alphaUri);
    $assert($alpha->status === 200 && str_contains($alphaName, 'Stage 11 Alpha')
        && str_contains($alphaName, '🇩🇪') && str_contains($alphaName, 'Germany')
        && str_contains($alphaName, 'DE') && str_contains($alphaName, 'Berlin')
        && str_contains($alphaName, 'Stage 11 Germany') && str_contains($alphaName, 'VLESS'),
        'Configured display name was not returned.');
    $assert((string)(parse_url($alphaUri, PHP_URL_USER) ?? '') === $active['uuid'],
        'Display settings changed the URI UUID.');
    $revisionAlpha = (int)db()->query('SELECT revision FROM vpn_v2_subscriptions WHERE id = ?', [$active['id']])->getColumn();
    $revisions[] = $revisionAlpha;

    $payload['service_name'] = 'Stage 11 Beta';
    $betaSave = $service->save($payload);
    $beta = $endpoint->respond($active['token']);
    $betaName = $displayName($uriFrom($beta->body));
    $revisionBeta = (int)db()->query('SELECT revision FROM vpn_v2_subscriptions WHERE id = ?', [$active['id']])->getColumn();
    $revisions[] = $revisionBeta;
    $assert($betaSave['settings']['service_name'] === 'Stage 11 Beta'
        && $revisionBeta === $revisionAlpha + 1
        && $beta->headers['ETag'] !== $alpha->headers['ETag']
        && str_contains($betaName, 'Stage 11 Beta'),
        'Service name did not update revision, ETag, and the same subscription response.');
    $assert($cache->get($active['token'], $revisionAlpha, 'base64') === null,
        'Old subscription cache was not invalidated.');

    $payload['global_show_flags'] = '0';
    $flagsOffSave = $service->save($payload);
    $flagsOff = $endpoint->respond($active['token']);
    $assert($flagsOffSave['settings']['global_show_flags'] === false
        && ($settingsRepository->stored()['global_show_flags'] ?? true) === false
        && !str_contains($displayName($uriFrom($flagsOff->body)), '🇩🇪'),
        'Checkbox=false or global flag disable failed.');
    $reloaded = (new SettingsService())->current();
    $assert($reloaded['global_show_flags'] === false && $reloaded['service_name'] === 'Stage 11 Beta',
        'Settings did not persist after a new service instance reloaded them.');

    $payload['global_show_flags'] = '1';
    $service->save($payload);
    db()->query('UPDATE vpn_v2_servers SET show_flag = 0, updated_at = ? WHERE id = ?', [$now, $serverId]);
    (new VpnSubscriptionRevisionService())->touchByServer($serverId);
    $serverFlagOff = $endpoint->respond($active['token']);
    $assert(!str_contains($displayName($uriFrom($serverFlagOff->body)), '🇩🇪'),
        'The individual server flag was ignored.');

    $payload['expired_subscription_behavior'] = 'not_found';
    $service->save($payload);
    $assert($endpoint->respond($expired['token'])->status === 404,
        'Configured expired subscription behavior was ignored.');

    $currentRevision = (int)db()->query('SELECT revision FROM vpn_v2_subscriptions WHERE id = ?', [$active['id']])->getColumn();
    $revisions[] = $currentRevision;
    $cache->set($active['token'], $currentRevision, 'base64', 'cached-marker', 1);
    $payload['subscription_cache_ttl_seconds'] = 301;
    $service->save($payload);
    $assert($cache->get($active['token'], $currentRevision, 'base64') === null,
        'Changing subscription cache settings did not invalidate the current cache.');

    $qrCode->renderForToken($active['token']);
    $assert(is_string(cache()->get($qrCode->cacheKey($active['token']))), 'QR cache fixture was not created.');
    $payload['qr_cache_ttl_seconds'] = 3601;
    $service->save($payload);
    $assert(cache()->get($qrCode->cacheKey($active['token'])) === null,
        'Changing QR cache settings did not invalidate QR cache.');

    $settingsRepository->read(SettingsService::defaults());
    $assert(is_array(cache()->get($settingsRepository->cacheKey())), 'Settings cache fixture was not created.');
    $payload['support_name'] = 'Reloaded Support';
    $finalSave = $service->save($payload);
    $assert(cache()->get($settingsRepository->cacheKey()) === null
        && $finalSave['settings']['support_name'] === 'Reloaded Support',
        'Settings cache invalidation or read-after-write failed.');

    $payload['public_account_enabled'] = '0';
    $payload['show_qr_in_profile'] = '0';
    $service->save($payload);
    (new FireballPluginVpnManagerV2())->boot();
    $assert(apply_filters('profile_menu', [], ['id' => (int)$user['id']]) === [],
        'Disabled customer account remained in the profile menu.');
    $profile = (new ProfileVpnService())->dashboard((int)$user['id'], (int)$active['id']);
    $assert($profile['subscriptionQr'] === '' && $profile['showQrInProfile'] === false,
        'Disabled profile QR remained visible.');

    $nodeAfter = db()->query(
        'SELECT id, client_uuid, client_email, client_sub_id FROM vpn_v2_subscription_nodes WHERE subscription_id = ?',
        [$active['id']]
    )->getOne();
    $secretsAfter = db()->query(
        'SELECT encrypted_username, encrypted_password, encrypted_token FROM vpn_v2_servers WHERE id = ?',
        [$serverId]
    )->getOne();
    $assert($nodeAfter === $nodeBefore, 'A client was recreated or a technical identity changed.');
    $assert($secretsAfter === [
        'encrypted_username' => 'encrypted-user-marker',
        'encrypted_password' => 'encrypted-password-marker',
        'encrypted_token' => 'encrypted-token-marker',
    ], 'Server secrets changed while saving settings.');

    echo json_encode([
        'status' => 'ok',
        'results' => [
            'service_name' => true,
            'checkbox_false' => true,
            'reload_persistence' => true,
            'global_flags' => true,
            'per_server_flag' => true,
            'display_name_without_client_recreation' => true,
            'revision_and_etag' => true,
            'same_subscription_url_updated' => true,
            'expired_behavior' => true,
            'settings_cache_invalidation' => true,
            'subscription_cache_invalidation' => true,
            'qr_cache_invalidation' => true,
            'blank_secret_preserved' => true,
            'server_secrets_unchanged' => true,
            'customer_account_toggle' => true,
            'profile_qr_toggle' => true,
        ],
        'transaction_rolled_back' => true,
    ], JSON_UNESCAPED_SLASHES), PHP_EOL;
} finally {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    $settingsRepository->invalidateCache();
    foreach ($tokens as $token) {
        foreach (array_unique(array_merge(range(1, 20), $revisions)) as $revision) {
            $cache->invalidate($token, (int)$revision);
        }
        $qrCode->invalidateToken($token);
    }
}
