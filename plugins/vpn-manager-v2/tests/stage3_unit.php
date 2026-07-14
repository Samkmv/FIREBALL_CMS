<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/config/config.php';
require ROOT . '/vendor/autoload.php';
require dirname(__DIR__) . '/Plugin.php';

use Fireball\VpnManagerV2\Services\InboundParser;
use Fireball\VpnManagerV2\Services\VpnFlowResolver;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$parser = new InboundParser();
$flow = new VpnFlowResolver();

$tcpReality = $parser->parse([
    'id' => 101,
    'remark' => 'TCP Reality',
    'protocol' => 'vless',
    'port' => 443,
    'enable' => true,
    'settings' => json_encode([
        'decryption' => 'none',
        'clients' => [['id' => 'client-secret', 'flow' => VpnFlowResolver::VISION]],
    ]),
    'streamSettings' => json_encode([
        'network' => 'tcp',
        'security' => 'reality',
        'tcpSettings' => ['header' => ['type' => 'none']],
        'realitySettings' => [
            'privateKey' => 'server-private-secret',
            'publicKey' => 'safe-public-key',
            'serverNames' => ['vpn.example.test'],
        ],
    ]),
]);
$assert($tcpReality->network === 'tcp', 'TCP network was not parsed.');
$assert($tcpReality->security === 'reality', 'Reality security was not parsed.');
$assert($tcpReality->defaultFlow === VpnFlowResolver::VISION, 'Vision was not selected for VLESS/TCP/Reality.');
$assert($flow->allowedFlows($tcpReality->toRepositoryArray()) === [null, VpnFlowResolver::VISION], 'TCP Reality allowed Flow list is invalid.');
$assert(!str_contains($tcpReality->streamSettingsJson, 'server-private-secret'), 'Reality private key leaked into the snapshot.');
$assert(str_contains($tcpReality->streamSettingsJson, '[redacted]'), 'Reality private key was not explicitly redacted.');
$assert(!str_contains($tcpReality->settingsJson, 'client-secret'), 'Client data leaked into inbound settings.');

$xhttpReality = $parser->parse([
    'id' => 102,
    'name' => 'XHTTP Reality',
    'protocol' => 'vless',
    'port' => 8443,
    'settings' => ['decryption' => 'none'],
    'streamSettings' => [
        'network' => 'xhttp',
        'security' => 'reality',
        'xhttpSettings' => ['path' => '/xhttp', 'mode' => 'auto'],
        'realitySettings' => ['publicKey' => 'safe-public-key'],
    ],
]);
$assert($xhttpReality->defaultFlow === null, 'Vision must not be selected automatically for XHTTP.');
$assert(!$flow->isFlowCompatible(VpnFlowResolver::VISION, $xhttpReality->toRepositoryArray()), 'Vision must be incompatible with XHTTP by default.');
$assert(($xhttpReality->stream->xhttp['path'] ?? '') === '/xhttp', 'XHTTP parameters were not parsed.');

$rawReality = $parser->parse([
    'id' => 103,
    'protocol' => 'vless',
    'port' => 9443,
    'streamSettings' => [
        'network' => 'raw',
        'security' => 'reality',
        'rawSettings' => ['header' => ['type' => 'none']],
        'realitySettings' => [],
    ],
]);
$assert($rawReality->defaultFlow === VpnFlowResolver::VISION, 'Vision was not selected for VLESS/RAW/Reality.');

$malformed = $parser->parse([
    'id' => 104,
    'protocol' => 'vless',
    'port' => 10443,
    'streamSettings' => '{not-json',
]);
$assert($malformed->status === 'parse_error', 'Malformed streamSettings was not marked parse_error.');
$assert($malformed->network === null && $malformed->security === 'none', 'Malformed streamSettings inherited transport data.');
$assert($malformed->defaultFlow === null, 'Malformed streamSettings received a Flow.');

$websocketTls = $parser->parse([
    'id' => 105,
    'protocol' => 'vmess',
    'port' => 443,
    'streamSettings' => [
        'network' => 'ws',
        'security' => 'tls',
        'wsSettings' => ['path' => '/ws', 'headers' => ['Host' => 'ws.example.test']],
        'tlsSettings' => ['serverName' => 'ws.example.test', 'alpn' => ['h2']],
    ],
]);
$grpcNone = $parser->parse([
    'id' => 106,
    'protocol' => 'trojan',
    'port' => 2443,
    'streamSettings' => [
        'network' => 'grpc',
        'security' => 'none',
        'grpcSettings' => ['serviceName' => 'grpc-service', 'multiMode' => false],
    ],
]);
$assert(($websocketTls->stream->websocket['path'] ?? '') === '/ws', 'WebSocket parameters were not parsed.');
$assert($websocketTls->stream->grpc === [], 'gRPC data leaked into the WebSocket inbound.');
$assert(($grpcNone->stream->grpc['serviceName'] ?? '') === 'grpc-service', 'gRPC parameters were not parsed.');
$assert($grpcNone->stream->websocket === [], 'WebSocket data leaked into the gRPC inbound.');
$assert($grpcNone->defaultFlow === null, 'A non-VLESS protocol received a Flow.');
$assert($flow->normalizeFlow('  XTLS-RPRX-VISION ') === VpnFlowResolver::VISION, 'Flow normalization failed.');
$assert($flow->normalizeFlow('') === null, 'Empty Flow normalization failed.');

echo json_encode([
    'status' => 'ok',
    'cases' => [
        'vless_tcp_reality',
        'vless_raw_reality',
        'vless_xhttp_reality',
        'malformed_stream_settings',
        'websocket_tls',
        'grpc_none',
        'no_cross_inbound_state',
        'sensitive_snapshot_redaction',
    ],
], JSON_UNESCAPED_SLASHES), PHP_EOL;
