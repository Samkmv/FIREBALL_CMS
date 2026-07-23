<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/config/config.php';
require ROOT . '/vendor/autoload.php';
if (!function_exists('return_translation')) {
    function return_translation(string $key): string
    {
        return $key;
    }
}
require dirname(__DIR__) . '/Plugin.php';

use Fireball\VpnManagerV2\Exceptions\VpnManagerV2Exception;
use Fireball\VpnManagerV2\Services\ExternalVpnSourceService;
use Fireball\VpnManagerV2\Services\VpnConfigValidator;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$validator = new VpnConfigValidator();
$uuid = '018f6a7e-766c-4700-9768-4099fcfd36af';
$validator->validateUri('vless://' . $uuid
    . '@vpn.example.com:443?encryption=none&security=tls&type=tcp&sni=vpn.example.com');
$validator->validateUri('trojan://strong-password@vpn.example.com:443');

$vmessPayload = json_encode([
    'v' => '2',
    'add' => 'vpn.example.com',
    'port' => '443',
    'id' => $uuid,
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$vmessUrlSafe = rtrim(strtr(base64_encode($vmessPayload), '+/', '-_'), '=');
$validator->validateUri('vmess://' . $vmessUrlSafe);

$invalidPortRejected = false;
try {
    $validator->validateUri('vless://' . $uuid
        . '@vpn.example.com:70000?encryption=none&security=tls&type=tcp');
} catch (VpnManagerV2Exception) {
    $invalidPortRejected = true;
}
$assert($invalidPortRejected, 'An out-of-range direct-link port was accepted.');

$external = new ExternalVpnSourceService();
$validShadowsocks = 'ss://' . base64_encode('aes-256-gcm:secret@vpn.example.com:8388');
$assert($external->extractUrisFromPayload($validShadowsocks) === [$validShadowsocks],
    'A valid Shadowsocks direct link was rejected.');
$invalidShadowsocksRejected = false;
try {
    $external->extractUrisFromPayload(
        'ss://' . base64_encode('aes-256-gcm:secret@vpn.example.com:70000')
    );
} catch (VpnManagerV2Exception) {
    $invalidShadowsocksRejected = true;
}
$assert($invalidShadowsocksRejected, 'An out-of-range Shadowsocks port was accepted.');

$root = dirname(__DIR__);
$connectionController = (string)file_get_contents($root . '/src/Controllers/Admin/ConnectionController.php');
$revisionService = (string)file_get_contents($root . '/src/Services/VpnSubscriptionRevisionService.php');
$subscriptionController = (string)file_get_contents($root . '/src/Controllers/Admin/SubscriptionController.php');
$processor = (string)file_get_contents($root . '/src/Services/RemoteOperationProcessor.php');
$syncJob = (string)file_get_contents($root . '/src/Jobs/VpnV2SyncConfigurationJob.php');
$retryJob = (string)file_get_contents($root . '/src/Jobs/VpnV2RetryFailedOperationsJob.php');
$client = (string)file_get_contents($root . '/src/Clients/ThreeXuiClient.php');

$assert(str_contains($connectionController, "request()->post('mode', request()->get('mode', ''))"),
    'The POST connection sync direction is still read only from the query string.');
$assert(str_contains($revisionService, 'public function touchParents(')
    && str_contains($subscriptionController, 'touchParents($subscriptionId)'),
    'Child connection ordering does not invalidate parent subscription revisions.');
$assert(str_contains($processor, 'public function processDue(')
    && str_contains($processor, "if ((int)(\$result['errors'] ?? 0) > 0)")
    && str_contains($processor, '$queue->heartbeat('),
    'Pending batches or partial-operation retries are not implemented.');
$assert(str_contains($syncJob, 'processDue(5, [') && str_contains($retryJob, 'processDue(5, ['),
    'Background workers still process only one queued item per run.');
$assert(str_contains($client, 'CURLOPT_PROTOCOLS')
    && str_contains($client, 'CURLPROTO_HTTP | CURLPROTO_HTTPS')
    && str_contains($client, 'X-Requested-With: XMLHttpRequest'),
    '3x-ui requests lack transport restriction or explicit API authentication signaling.');

$serverController = (string)file_get_contents($root . '/src/Controllers/Admin/ServerController.php');
$inboundController = (string)file_get_contents($root . '/src/Controllers/Admin/InboundController.php');
$overviewController = (string)file_get_contents($root . '/src/Controllers/Admin/OverviewController.php');
$assert(substr_count($serverController, 'Permissions::authorize(') >= 7
    && substr_count($inboundController, 'Permissions::authorize(') >= 2
    && substr_count($connectionController, 'Permissions::authorize(') >= 6
    && str_contains($overviewController, 'Permissions::authorize(Permissions::VIEW)'),
    'One or more administrator controllers bypass plugin-level permission checks.');

$languages = [];
foreach (glob($root . '/lang/*.php') ?: [] as $file) {
    $languages[basename($file, '.php')] = require $file;
}
$assert(($languages['ru']['vpn_manager_v2_col_permission'] ?? '') === 'Разрешение'
    && ($languages['ru']['vpn_manager_v2_col_revision'] ?? '') === 'Ревизия'
    && ($languages['de']['vpn_manager_v2_settings_subscription'] ?? '') === 'Abonnement'
    && ($languages['zh-cn']['vpn_manager_v2_col_revision'] ?? '') === '修订版本',
    'Visible untranslated interface values remain in the localized dictionaries.');

$plugin = json_decode((string)file_get_contents($root . '/plugin.json'), true, 512, JSON_THROW_ON_ERROR);
$assert(version_compare((string)($plugin['version'] ?? '0.0.0'), '0.19.2', '>='),
    'The audited plugin version was not published.');

echo json_encode([
    'status' => 'ok',
    'cases' => [
        'standard_vless_without_fragment',
        'standard_trojan_without_query_or_fragment',
        'url_safe_vmess_without_display_name',
        'direct_link_port_validation',
        'shadowsocks_port_validation',
        'post_sync_direction',
        'parent_revision_propagation',
        'partial_operation_retry',
        'bounded_queue_drain',
        'http_transport_restriction',
        'granular_controller_permissions',
        'visible_translation_cleanup',
        'plugin_version',
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
