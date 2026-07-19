<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/config/config.php';
require ROOT . '/vendor/autoload.php';
require ROOT . '/helpers/helpers.php';
require dirname(__DIR__) . '/Plugin.php';

$app = new FBL\Application();
FBL\Language::registerPluginLanguage('vpn-manager-v2', dirname(__DIR__) . '/lang');

use Fireball\VpnManagerV2\Clients\ThreeXuiCapabilities;
use Fireball\VpnManagerV2\Clients\ThreeXuiResponseMapper;
use Fireball\VpnManagerV2\Exceptions\ValidationException;
use Fireball\VpnManagerV2\Services\ConfigurationSnapshotService;
use Fireball\VpnManagerV2\Services\RemoteClientCredentialService;
use Fireball\VpnManagerV2\Services\RemoteClientNameGenerator;
use Fireball\VpnManagerV2\Services\VpnConfigValidator;
use Fireball\VpnManagerV2\Services\VpnSubscriptionBuilder;
use Fireball\VpnManagerV2\Services\SettingsService;
use Fireball\VpnManagerV2\Support\NetworkTargetGuard;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$names = new RemoteClientNameGenerator();
$assert($names->generate('Иван Петров', 'ivan@example.com', 'de') === 'ivan-petrov-ivan-example-com-DE',
    'The deterministic 3x-ui client name is invalid.');

$mapper = new ThreeXuiResponseMapper();
$inbounds = $mapper->inbounds(['obj' => [[
    'id' => 42,
    'protocol' => 'vless',
    'settings' => '{"clients":[{"id":"u1","email":"ivan-DE"}]}',
    'streamSettings' => '{"network":"xhttp","security":"reality"}',
]]]);
$assert(count($inbounds) === 1
    && count($mapper->clients($inbounds[0])) === 1
    && ($inbounds[0]['streamSettings']['network'] ?? '') === 'xhttp',
    '3x-ui response variants are not normalized.');

$capabilities = (new ThreeXuiCapabilities())->detect($inbounds);
$assert($capabilities['protocols'] === ['vless'] && $capabilities['transports'] === ['xhttp'],
    'Server capabilities were not derived from the observed response.');

$snapshots = new ConfigurationSnapshotService();
$remote = $snapshots->fromRemote(
    ['id' => 1, 'name' => 'DE 1', 'code' => 'de-1', 'panel_url' => 'vpn.example.com', 'country_code' => 'DE'],
    [
        'id' => 42,
        'remark' => 'Reality',
        'protocol' => 'vless',
        'port' => 443,
        'settings' => ['clients' => []],
        'streamSettings' => [
            'network' => 'tcp',
            'security' => 'reality',
            'realitySettings' => ['settings' => ['publicKey' => 'public', 'serverName' => 'vpn.example.com']],
        ],
    ],
    ['id' => '00000000-0000-4000-8000-000000000001', 'email' => 'ivan-DE', 'enable' => true],
    ['id' => 5, 'subscription_id' => 9, 'inbound_id' => 2]
);
$assert($snapshots->validate($remote) === null, 'A valid Reality snapshot was rejected.');
$reordered = array_reverse($remote, true);
$assert(hash_equals($snapshots->hash($remote), $snapshots->hash($reordered)),
    'Snapshot hashing is not canonical.');
$modified = $remote;
$modified['port'] = 8443;
$assert($snapshots->changedFields($remote, $modified) === ['port'],
    'Snapshot field diff is not deterministic.');
unset($modified['stream_settings_json']);
$modified['security'] = 'reality';
$assert($snapshots->validate($modified) === 'reality_required_field_missing',
    'An incomplete Reality snapshot was accepted.');

$credentials = new RemoteClientCredentialService();
$trojanPassword = 'correct-horse-battery-staple-' . bin2hex(random_bytes(8));
$encryptedTrojanPassword = $credentials->encryptForStorage('trojan', $trojanPassword);
$assert(is_string($encryptedTrojanPassword)
    && $encryptedTrojanPassword !== ''
    && !str_contains($encryptedTrojanPassword, $trojanPassword)
    && $credentials->credential([
        'protocol' => 'trojan',
        'client_uuid' => '00000000-0000-4000-8000-000000000099',
        'encrypted_client_credential' => $encryptedTrojanPassword,
    ]) === $trojanPassword,
    'A password-protocol client credential is not encrypted at rest.');
$trojanSnapshot = $snapshots->fromRemote(
    ['id' => 1, 'name' => 'DE 1', 'code' => 'de-1', 'panel_url' => 'vpn.example.com', 'country_code' => 'DE'],
    [
        'id' => 43,
        'protocol' => 'trojan',
        'port' => 443,
        'settings' => ['clients' => [['password' => $trojanPassword]]],
        'streamSettings' => [
            'network' => 'tcp',
            'security' => 'tls',
            'privateKey' => 'server-private-key',
            'tlsSettings' => ['serverName' => 'vpn.example.com'],
        ],
    ],
    ['password' => $trojanPassword, 'email' => 'ivan-DE', 'enable' => true],
    [
        'id' => 6,
        'subscription_id' => 9,
        'inbound_id' => 3,
        'client_uuid' => '00000000-0000-4000-8000-000000000099',
    ]
);
$trojanSnapshotJson = json_encode($trojanSnapshot, JSON_THROW_ON_ERROR);
$assert(!str_contains($trojanSnapshotJson, $trojanPassword)
    && !str_contains($trojanSnapshotJson, 'server-private-key')
    && ($trojanSnapshot['client_uuid'] ?? '') === '00000000-0000-4000-8000-000000000099'
    && ($trojanSnapshot['client_credential_hash'] ?? '') === hash('sha256', $trojanPassword),
    'A factual snapshot leaked a password or server private key.');

$blocked = false;
try {
    (new NetworkTargetGuard())->assertConfigurationHost('127.0.0.1', false);
} catch (ValidationException) {
    $blocked = true;
}
$assert($blocked, 'A private panel target bypassed the SSRF guard.');
(new NetworkTargetGuard())->assertConfigurationHost('10.0.0.10', true);

$configValidator = new VpnConfigValidator();
$configValidator->validateUri('trojan://secret@vpn.example.com:443?security=tls&type=tcp#DE');
$configValidator->validateUri('vmess://' . base64_encode(json_encode([
    'v' => '2',
    'ps' => 'DE',
    'add' => 'vpn.example.com',
    'port' => '443',
    'id' => '00000000-0000-4000-8000-000000000001',
], JSON_THROW_ON_ERROR)));
cache()->set('vpn-v2:settings:v1', SettingsService::defaults(), 60);
$protocolUris = (new VpnSubscriptionBuilder())->buildFromNodes(['id' => 1], [[
    'protocol' => 'trojan',
    'client_uuid' => '00000000-0000-4000-8000-000000000099',
    'encrypted_client_credential' => $encryptedTrojanPassword,
    'panel_url' => 'https://vpn.example.com',
    'port' => 443,
    'network' => 'tcp',
    'security' => 'tls',
    'stream_settings_json' => json_encode([
        'network' => 'tcp',
        'security' => 'tls',
        'tlsSettings' => ['serverName' => 'vpn.example.com'],
    ], JSON_THROW_ON_ERROR),
    'server_name' => 'DE',
    'country_code' => 'DE',
], [
    'protocol' => 'vmess',
    'client_uuid' => '00000000-0000-4000-8000-000000000001',
    'panel_url' => 'https://vpn.example.com',
    'port' => 8443,
    'network' => 'ws',
    'security' => 'none',
    'stream_settings_json' => json_encode([
        'network' => 'ws',
        'security' => 'none',
        'wsSettings' => ['path' => '/vmess'],
    ], JSON_THROW_ON_ERROR),
    'server_name' => 'DE VMess',
    'country_code' => 'DE',
]]);
$assert(count($protocolUris) === 2
    && str_starts_with($protocolUris[0], 'trojan://')
    && str_starts_with($protocolUris[1], 'vmess://'),
    'Trojan or VMess subscription URI generation failed.');

$migration = (string)file_get_contents(dirname(__DIR__) . '/migrations/007_add_bidirectional_sync.sql');
$queue = (string)file_get_contents(dirname(__DIR__) . '/src/Repositories/OperationQueueRepository.php');
$sync = (string)file_get_contents(dirname(__DIR__) . '/src/Services/ConfigurationSyncService.php');
$endpoint = (string)file_get_contents(dirname(__DIR__) . '/src/Controllers/Public/SubscriptionController.php');
$assert(str_contains($migration, 'vpn_v2_profiles')
    && str_contains($migration, 'vpn_v2_operations')
    && str_contains($migration, 'vpn_v2_connection_snapshots')
    && str_contains($migration, 'vpn_v2_sync_conflicts')
    && str_contains($migration, 'vpn_v2_sync_logs')
    && str_contains($migration, 'encrypted_client_credential'),
    'The bidirectional synchronization schema is incomplete.');
$assert(str_contains($queue, 'lease_until') && str_contains($queue, 'idempotency_key')
    && str_contains($queue, '2 **'), 'The persistent queue has no lease, dedupe or backoff contract.');
$assert(str_contains($sync, 'missing_remote') && str_contains($sync, 'remote_unavailable')
    && str_contains($sync, 'ambiguous_match'), 'Reconciliation state classification is incomplete.');
$assert(str_contains($endpoint, 'RateLimiter') && str_contains($endpoint, '429'),
    'The public subscription endpoint is not rate-limited.');
$tokenRepository = (string)file_get_contents(dirname(__DIR__) . '/src/Repositories/SubscriptionConfigRepository.php');
$assert(str_contains($tokenRepository, 'subscription_token_hash') && str_contains($tokenRepository, 'hash_equals'),
    'The public subscription token is not compared through a constant-time verification path.');

$languages = [];
foreach (glob(dirname(__DIR__) . '/lang/*.php') ?: [] as $file) {
    $languages[basename($file)] = array_keys(require $file);
}
$reference = reset($languages);
foreach ($languages as $name => $keys) {
    $assert($keys === $reference, 'Translation key parity failed for ' . $name . '.');
}

echo json_encode([
    'status' => 'ok',
    'cases' => [
        'deterministic_client_name',
        'response_normalization',
        'capability_detection',
        'snapshot_validation_hash_and_diff',
        'encrypted_password_protocol_credentials',
        'snapshot_secret_redaction',
        'ssrf_private_target_guard',
        'vless_vmess_trojan_uri_validation',
        'bidirectional_schema',
        'persistent_queue_contract',
        'reconciliation_classification',
        'public_endpoint_rate_limit',
        'constant_time_subscription_token_verification',
        'translation_parity',
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), PHP_EOL;
