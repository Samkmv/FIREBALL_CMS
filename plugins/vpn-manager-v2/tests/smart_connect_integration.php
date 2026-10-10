<?php
declare(strict_types=1);
// CLI-only, isolated DB fixtures rolled back; no real 3x-ui requests.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__, 3) . '/config/config.php';
require ROOT . '/vendor/autoload.php';
require ROOT . '/helpers/helpers.php';
ini_set('session.save_path', sys_get_temp_dir());
new FBL\Application();
require dirname(__DIR__) . '/Plugin.php';
FBL\Language::registerPluginLanguage('vpn-manager-v2', dirname(__DIR__) . '/lang');

use Fireball\VpnManagerV2\Services\SettingsService;
use Fireball\VpnManagerV2\Services\VpnSubscriptionEndpointService;
use Fireball\VpnManagerV2\Services\VpnSubscriptionBuilder;
use Fireball\VpnManagerV2\Services\VpnSubscriptionCache;
use Fireball\VpnManagerV2\Services\VpnV2SchemaUpgradeService;
use Fireball\VpnManagerV2\Services\ExternalVpnSourceService;
use Fireball\VpnManagerV2\Services\SubscriptionHwidGatewayService;
use Fireball\VpnManagerV2\Services\ServerHealthMonitorService;
use Fireball\VpnManagerV2\Repositories\SubscriptionConfigRepository;
use Fireball\VpnManagerV2\Repositories\SettingsRepository;
use Fireball\VpnManagerV2\Repositories\ServerHealthRepository;

(new VpnV2SchemaUpgradeService())->ensureCurrent();
$cases = [];
$assert = static function (bool $value, string $case) use (&$cases): void {
    if (!$value) { throw new RuntimeException($case); }
    $cases[] = $case;
};
$repo = new SubscriptionConfigRepository();
$metadataBefore = $repo->activeRevisionMetadata();
$cache = new VpnSubscriptionCache();
$id = 0; $tokens = []; $metadataAfter = [];
db()->beginTransaction();
try {
    $now = date('Y-m-d H:i:s');
    $user = (int)db()->query('SELECT id FROM users ORDER BY id LIMIT 1')->getColumn();
    $assert($user > 0, 'cms_fixture_user');
    $suffix = bin2hex(random_bytes(6));
    db()->query('INSERT INTO vpn_v2_plans (name, duration_days, device_limit, is_active, created_at, updated_at) VALUES (?, 30, 0, 1, ?, ?)', ['Smart fixture ' . $suffix, $now, $now]);
    $plan = (int)db()->getInsertId();
    $servers = [];
    foreach ([0, 1] as $i) {
        db()->query('INSERT INTO vpn_v2_servers (name, code, panel_url, auth_type, is_enabled, status, created_at, updated_at) VALUES (?, ?, ?, ?, 1, ?, ?, ?)',
            ['Smart ' . $i, 'smart-' . $suffix . '-' . $i, 'https://smart' . $i . '.example.com', 'token', 'online', $now, $now]);
        $servers[$i] = (int)db()->getInsertId();
        db()->query('INSERT INTO vpn_v2_inbounds (server_id, remote_inbound_id, name, protocol, port, network, security, settings_json, stream_settings_json, status, is_enabled, created_at, updated_at) VALUES (?, ?, ?, ?, 443, ?, ?, ?, ?, ?, 1, ?, ?)',
            [$servers[$i], (string)(1 + $i), 'Smart inbound ' . $i, 'vless', 'tcp', 'none', '{}', '{"network":"tcp","security":"none"}', 'active', $now, $now]);
        $inbounds[$i] = (int)db()->getInsertId();
    }
    foreach ([0, 1] as $i) {
        $tokens[$i] = bin2hex(random_bytes(32));
        db()->query('INSERT INTO vpn_v2_subscriptions (user_id, plan_id, status, starts_at, expires_at, device_limit, subscription_token, subscription_token_hash, revision, created_by, created_at, updated_at, config_updated_at) VALUES (?, ?, ?, ?, ?, 0, ?, ?, 1, ?, ?, ?, ?)',
            [$user, $plan, 'active', date('Y-m-d H:i:s', time() - 3600), date('Y-m-d H:i:s', time() + 86400), $tokens[$i], hash('sha256', $tokens[$i]), $user, $now, $now, $now]);
        $subscriptions[$i] = (int)db()->getInsertId();
        db()->query('INSERT INTO vpn_v2_subscription_nodes (subscription_id, server_id, inbound_id, client_uuid, client_email, client_sub_id, protocol, status, desired_enabled, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)',
            [$subscriptions[$i], $servers[$i], $inbounds[$i], '81111111-1111-4111-8111-11111111111' . $i, 'smart-' . $suffix . '-' . $i, 'smart' . $i, 'vless', 'active', $i === 0 ? 20 : 10, $now, $now]);
    }
    $id = $subscriptions[0];
    // Shared child included in parent; no cascade or remote mutations in this fixture.
    db()->query('INSERT INTO vpn_v2_subscription_items (parent_subscription_id, item_type, child_subscription_id, ownership_type, is_enabled, sort_order, effective_status, created_at, updated_at) VALUES (?, ?, ?, ?, 1, 0, ?, ?, ?)',
        [$id, 'subscription', $subscriptions[1], 'shared', 'active', $now, $now]);
    $settingsService = new SettingsService();
    $manualSettings = array_replace(SettingsService::defaults(), ['smart_connect_enabled' => false]);
    $settingsService->save($manualSettings);
    $endpoint = new VpnSubscriptionEndpointService();
    $manual = $endpoint->respond($tokens[0], 'plain');
    $assert($manual->status === 200 && $manual->configCount === 2 && !isset($manual->headers['providerid']), 'existing_subscription_disabled');
    $assert($endpoint->respond($tokens[0], 'singbox')->status === 404, 'export_disabled_by_default');
    $storedSub = $repo->findByToken($tokens[0]);
    $originalUuid = db()->query('SELECT client_uuid FROM vpn_v2_subscription_nodes WHERE subscription_id = ?', [$id])->getColumn();
    $originalOrder = db()->query('SELECT sort_order FROM vpn_v2_subscription_nodes WHERE subscription_id = ?', [$id])->getColumn();
    foreach (['hide' => '1', 'show' => '0', 'default' => null] as $policy => $hideHeader) {
        $policySettings = array_replace($manualSettings, ['happ_server_settings_policy' => $policy]);
        $settingsService->save($policySettings);
        $assert($settingsService->current()['happ_server_settings_policy'] === $policy, 'happ_protection_store_' . $policy);
        $protected = $endpoint->respond($tokens[0], 'plain', $manual->headers['ETag']);
        $assert($protected->status === 200 && $protected->body === $manual->body
            && ($protected->headers['hide-settings'] ?? null) === $hideHeader
            && !isset($protected->headers['providerid']), 'happ_protection_header_manual_' . $policy);
        $protected304 = $endpoint->respond($tokens[0], 'plain', $protected->headers['ETag']);
        $assert($protected304->status === 304 && ($protected304->headers['hide-settings'] ?? null) === $hideHeader,
            'happ_protection_header_304_' . $policy);
    }
    $smart = array_replace($manualSettings, ['smart_connect_enabled' => true, 'smart_connect_happ_enabled' => true,
        'smart_connect_happ_provider_id' => 'fixture-provider', 'smart_connect_singbox_enabled' => true,
        'smart_connect_mode' => 'failover', 'smart_connect_server_priorities' => [$servers[0] => 50, $servers[1] => 0],
        'happ_server_settings_policy' => 'hide']);
    $saved = $settingsService->save($smart);
    $assert($saved['settings']['smart_connect_mode'] === 'failover'
        && $settingsService->current()['smart_connect_server_priorities'] === $smart['smart_connect_server_priorities'], 'settings_persist_and_load');
    $active = $endpoint->respond($tokens[0], 'plain');
    $assert($active->body === $manual->body && $active->headers['ETag'] !== $manual->headers['ETag']
        && $active->headers['subscription-autoconnect-type'] === 'lowestdelay', 'manual_payload_revision_headers');
    $cached = $endpoint->respond($tokens[0], 'plain');
    $assert($cached->cacheHit && $cached->body === $active->body, 'plain_cache');
    $notModified = $endpoint->respond($tokens[0], 'plain', $active->headers['ETag']);
    $assert($notModified->status === 304 && $notModified->body === ''
        && $notModified->headers['providerid'] === 'fixture-provider', 'happ_headers_on_304');
    $jsonResponse = $endpoint->respond($tokens[0], 'singbox');
    $json = json_decode($jsonResponse->body, true, 64, JSON_THROW_ON_ERROR);
    $assert($jsonResponse->status === 200 && $jsonResponse->configCount === 2
        && !isset($jsonResponse->headers['hide-settings'])
        && $json['outbounds'][0]['interrupt_exist_connections']
        && $json['outbounds'][1]['server'] === 'smart1.example.com', 'explicit_json_child_and_priority');
    $assert($endpoint->respond($tokens[0], 'singbox')->cacheHit
        && $endpoint->respond($tokens[0], 'singbox', $jsonResponse->headers['ETag'])->status === 304
        && $jsonResponse->headers['ETag'] !== $active->headers['ETag'], 'json_cache_and_304');
    $assert($endpoint->respond($tokens[0], 'plain', '', gmdate('D, d M Y H:i:s', time() + 10) . ' GMT')->status === 304, 'last_modified_cache');
    foreach (['expired', 'suspended', 'deleted', 'traffic_exceeded', 'future'] as $state) {
        db()->query('UPDATE vpn_v2_subscriptions SET status = ?, starts_at = ?, expires_at = ? WHERE id = ?',
            [$state === 'future' ? 'active' : $state, date('Y-m-d H:i:s', time() + ($state === 'future' ? 3600 : -3600)),
                date('Y-m-d H:i:s', time() + ($state === 'expired' ? -1 : 86400)), $id]);
        $inactive = $endpoint->respond($tokens[0], 'singbox', $jsonResponse->headers['ETag']);
        $inactiveJson = json_decode($inactive->body, true);
        $assert($inactive->status === 200 && $inactiveJson['outbounds'] === [] && $inactive->headers['subscription-autoconnect'] === '0'
            && !isset($inactive->headers['hide-settings'])
            && $inactive->headers['X-Fireball-VPN-Status'] === 'inactive' && !isset($inactive->headers['ETag']), 'inactive_' . $state);
        $inactivePlain = $endpoint->respond($tokens[0], 'plain', $active->headers['ETag']);
        $assert($inactivePlain->status === 200 && $inactivePlain->headers['hide-settings'] === '1'
            && !isset($inactivePlain->headers['ETag']), 'happ_protection_inactive_' . $state);
    }
    db()->query('UPDATE vpn_v2_subscriptions SET status = ?, starts_at = ?, expires_at = ?, device_limit = 1 WHERE id = ?',
        ['active', date('Y-m-d H:i:s', time() - 3600), date('Y-m-d H:i:s', time() + 86400), $id]);
    $assert($endpoint->respond($tokens[0], 'singbox', $jsonResponse->headers['ETag'])->status === 404, 'hwid_not_bypassed_by_json_304');
    $hwidStatus = 204; $hwidCalls = 0;
    $gateway = new SubscriptionHwidGatewayService(static function () use (&$hwidStatus, &$hwidCalls): object {
        return new class($hwidStatus, $hwidCalls) {
            private $status; private $calls;
            public function __construct(&$status, &$calls) { $this->status =& $status; $this->calls =& $calls; }
            public function probeSubscriptionHwid(): int { $this->calls++; return $this->status; }
        };
    });
    $guarded = new VpnSubscriptionEndpointService(hwidGateway: $gateway);
    $allowed = $guarded->respond($tokens[0], 'singbox', $jsonResponse->headers['ETag'], '', ['X-HWID' => 'fixture-hwid']);
    $assert($allowed->status === 304 && $hwidCalls === 2, 'hwid_gateway_before_cached_response');
    $hwidStatus = 503;
    $assert($guarded->respond($tokens[0], 'singbox', $jsonResponse->headers['ETag'], '', ['X-HWID' => 'fixture-hwid'])->status === 503, 'unavailable_hwid_gate_fail_closed');
    db()->query('UPDATE vpn_v2_subscriptions SET device_limit = 0 WHERE id = ?', [$id]);
    $externalUri = 'trojan://external-password@external.example.com:443?security=tls&type=tcp#External';
    $encrypted = \Fireball\VpnManagerV2\Support\SecretCipher::encrypt($externalUri);
    $encryptedSnapshot = \Fireball\VpnManagerV2\Support\SecretCipher::encrypt(json_encode([$externalUri]));
    db()->query('INSERT INTO vpn_v2_external_sources (parent_subscription_id, source_type, name, encrypted_source, source_hash, source_preview, encrypted_snapshot, config_count, is_enabled, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1, ?, ?)',
        [$id, 'connection_uri', 'Fixture external', $encrypted, hash('sha256', $externalUri), 'trojan://hidden', $encryptedSnapshot, $now, $now]);
    (new \Fireball\VpnManagerV2\Services\VpnSubscriptionRevisionService())->touchConfig($id);
    $withExternal = $endpoint->respond($tokens[0], 'singbox');
    $assert($withExternal->configCount === 3 && count(json_decode($withExternal->body, true)['outbounds']) === 4, 'external_source_preserved');
    $revision = $repo->findByToken($tokens[0])['revision'];
    $healthRepo = new ServerHealthRepository();
    $monitor = new ServerHealthMonitorService(static fn(): array => ['xray' => ['state' => 'running']], static fn(): bool => true);
    $snapshot = $monitor->inspect(['panel_url' => 'https://smart0.example.com'], [['id' => $inbounds[0], 'network' => 'tcp', 'port' => 443]]);
    $healthRepo->save($servers[0], $snapshot, 0, 600);
    $snapshot['state'] = 'unavailable'; $snapshot['error'] = 'panel_probe_failed';
    $healthRepo->save($servers[0], $snapshot, 1, 600);
    $health = db()->query('SELECT * FROM vpn_v2_server_health WHERE server_id = ?', [$servers[0]])->getOne();
    $assert($health['last_success_at'] !== null && (int)$health['consecutive_failures'] === 1
        && $repo->findByToken($tokens[0])['revision'] === $revision, 'health_history_no_revision_or_membership_change');
    db()->query('UPDATE vpn_v2_servers SET status = ? WHERE id IN (?, ?)', ['offline', ...$servers]);
    $stillAvailable = $endpoint->respond($tokens[0], 'singbox');
    $assert($stillAvailable->status === 200 && $stillAvailable->body === $withExternal->body, 'global_offline_not_user_block');
    $snapshot['state'] = 'healthy'; $snapshot['error'] = '';
    $healthRepo->save($servers[0], $snapshot, 0, 600);
    $assert((int)db()->query('SELECT consecutive_failures FROM vpn_v2_server_health WHERE server_id = ?', [$servers[0]])->getColumn() === 0, 'health_recovery_persisted');
    db()->query('UPDATE vpn_v2_inbounds SET network = ?, stream_settings_json = ? WHERE id = ?', ['xhttp', '{"network":"xhttp","security":"none","xhttpSettings":{}}', $inbounds[0]]);
    db()->query('UPDATE vpn_v2_subscriptions SET revision = revision + 1 WHERE id = ?', [$id]);
    $unsupported = $endpoint->respond($tokens[0], 'singbox');
    $assert($unsupported->status === 422 && $unsupported->headers['X-Fireball-VPN-Config'] === 'unsupported'
        && !str_contains($unsupported->body, 'external-password'), 'unsupported_export_safe_422');
    $assert($endpoint->respond($tokens[0], 'plain')->status === 200, 'xhttp_regular_subscription_unchanged');
    $assert(db()->query('SELECT client_uuid FROM vpn_v2_subscription_nodes WHERE subscription_id = ?', [$id])->getColumn() === $originalUuid
        && db()->query('SELECT sort_order FROM vpn_v2_subscription_nodes WHERE subscription_id = ?', [$id])->getColumn() === $originalOrder
        && hash_equals($tokens[0], $repo->findByToken($tokens[0])['subscription_token']), 'identifiers_and_manual_order_preserved');
} finally {
    $metadataAfter = $repo->activeRevisionMetadata();
    if (db()->inTransaction()) { db()->rollBack(); }
    (new SettingsRepository())->invalidateCache();
    foreach (array_merge($metadataBefore, $metadataAfter) as $meta) {
        $cache->invalidate((string)$meta['subscription_token'], (int)$meta['revision']);
    }
    foreach ($tokens as $token) { for ($revision = 1; $revision <= 20; $revision++) { $cache->invalidate($token, $revision); } }
}
echo json_encode(['status' => 'ok', 'count' => count($cases), 'cases' => $cases]), PHP_EOL;
