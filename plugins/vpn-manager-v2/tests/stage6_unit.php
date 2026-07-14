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

use Fireball\VpnManagerV2\Exceptions\VpnConfigValidationException;
use Fireball\VpnManagerV2\Services\VpnFlowResolver;
use Fireball\VpnManagerV2\Services\VpnSubscriptionBuilder;
use Fireball\VpnManagerV2\Services\VpnSubscriptionCache;
use Fireball\VpnManagerV2\Services\VpnSubscriptionEndpointService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$query = static function (string $uri): array {
    parse_str((string)(parse_url($uri, PHP_URL_QUERY) ?? ''), $params);

    return $params;
};
$baseNode = static function (array $overrides = []): array {
    $stream = [
        'network' => 'tcp',
        'security' => 'reality',
        'tcpSettings' => ['header' => ['type' => 'none']],
        'realitySettings' => [
            'serverNames' => ['reality-one.example.com'],
            'shortIds' => ['1a2b3c4d'],
            'settings' => [
                'publicKey' => 'public-key-one',
                'fingerprint' => 'chrome',
                'spiderX' => '/vpn-v2',
            ],
        ],
    ];

    return array_replace([
        'node_id' => 1,
        'subscription_id' => 1,
        'client_uuid' => '11111111-2222-4333-8444-555555555555',
        'flow' => VpnFlowResolver::VISION,
        'server_id' => 1,
        'server_name' => 'Node One',
        'server_code' => 'node-one',
        'panel_url' => 'https://vpn-one.example.com:2053',
        'country_code' => 'DE',
        'country_name' => 'Germany',
        'city' => 'Berlin',
        'show_flag' => 1,
        'inbound_id' => 1,
        'inbound_name' => 'Reality TCP',
        'inbound_remark' => 'Primary',
        'protocol' => 'vless',
        'port' => 443,
        'network' => 'tcp',
        'security' => 'reality',
        'settings_json' => '{}',
        'stream_settings_json' => json_encode($stream, JSON_UNESCAPED_SLASHES),
    ], $overrides);
};

$builder = new VpnSubscriptionBuilder();
$tcpUri = $builder->buildFromNodes(['id' => 1], [$baseNode()])[0];
$tcp = $query($tcpUri);
$assert(str_starts_with($tcpUri, 'vless://') && ($tcp['type'] ?? '') === 'tcp', 'TCP VLESS URI is invalid.');
$assert(($tcp['security'] ?? '') === 'reality' && ($tcp['pbk'] ?? '') === 'public-key-one', 'Reality parameters are missing.');
$assert(($tcp['sid'] ?? '') === '1a2b3c4d' && ($tcp['sni'] ?? '') === 'reality-one.example.com', 'Reality identity is incomplete.');
$assert(($tcp['flow'] ?? '') === VpnFlowResolver::VISION, 'Vision Flow is missing.');

$noFlowUri = $builder->buildFromNodes(['id' => 2], [$baseNode(['flow' => null])])[0];
$assert(!array_key_exists('flow', $query($noFlowUri)) && !str_contains($noFlowUri, 'flow='), 'Empty Flow leaked into URI.');

$xhttpStream = [
    'network' => 'xhttp',
    'security' => 'reality',
    'xhttpSettings' => [
        'path' => '/actual-node-two',
        'host' => 'xhttp.example.com',
        'mode' => 'packet-up',
        'xPaddingBytes' => '100-900',
        'noGRPCHeader' => true,
        'xmux' => ['maxConcurrency' => '8-16'],
    ],
    'realitySettings' => [
        'serverNames' => ['reality-two.example.com'],
        'shortIds' => ['abcdef12'],
        'settings' => ['publicKey' => 'public-key-two', 'fingerprint' => 'firefox'],
    ],
];
$xhttpNode = $baseNode([
    'node_id' => 2,
    'client_uuid' => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
    'server_name' => 'Node Two',
    'panel_url' => 'https://vpn-two.example.com',
    'inbound_name' => 'Reality XHTTP',
    'network' => 'xhttp',
    'flow' => null,
    'stream_settings_json' => json_encode($xhttpStream, JSON_UNESCAPED_SLASHES),
]);
$multi = $builder->buildFromNodes(['id' => 3], [$baseNode(), $xhttpNode]);
$xhttp = $query($multi[1]);
$extra = json_decode((string)($xhttp['extra'] ?? ''), true);
$assert(count($multi) === 2, 'Multi-node subscription is incomplete.');
$assert(($xhttp['path'] ?? '') === '/actual-node-two' && ($xhttp['mode'] ?? '') === 'packet-up', 'XHTTP node parameters are stale.');
$assert(($xhttp['pbk'] ?? '') === 'public-key-two' && ($xhttp['sni'] ?? '') === 'reality-two.example.com', 'XHTTP mixed another node settings.');
$assert(!isset($xhttp['flow']) && is_array($extra) && ($extra['noGRPCHeader'] ?? false) === true, 'XHTTP Flow or extra parameters are invalid.');

$tlsStream = [
    'network' => 'ws',
    'security' => 'tls',
    'wsSettings' => ['path' => '/ws', 'headers' => ['Host' => 'cdn.example.com']],
    'tlsSettings' => ['serverName' => 'tls.example.com', 'fingerprint' => 'chrome'],
];
$tlsUri = $builder->buildFromNodes(['id' => 4], [$baseNode([
    'client_uuid' => 'bbbbbbbb-cccc-4ddd-8eee-ffffffffffff',
    'network' => 'ws',
    'security' => 'tls',
    'flow' => null,
    'stream_settings_json' => json_encode($tlsStream, JSON_UNESCAPED_SLASHES),
])])[0];
$tls = $query($tlsUri);
$assert(($tls['security'] ?? '') === 'tls' && !isset($tls['pbk'], $tls['sid'], $tls['spx']), 'TLS URI contains Reality parameters.');

$noneStream = ['network' => 'tcp', 'security' => 'none', 'tcpSettings' => ['header' => ['type' => 'none']]];
$none = $query($builder->buildFromNodes(['id' => 5], [$baseNode([
    'client_uuid' => 'cccccccc-dddd-4eee-8fff-000000000000',
    'security' => 'none',
    'flow' => null,
    'stream_settings_json' => json_encode($noneStream, JSON_UNESCAPED_SLASHES),
])])[0]);
$assert(($none['security'] ?? '') === 'none' && !isset($none['sni'], $none['fp'], $none['pbk']), 'None security contains TLS/Reality parameters.');

$invalidRejected = false;
$invalidStream = $baseNode();
$decoded = json_decode((string)$invalidStream['stream_settings_json'], true);
unset($decoded['realitySettings']['settings']['publicKey']);
$invalidStream['stream_settings_json'] = json_encode($decoded, JSON_UNESCAPED_SLASHES);
try {
    $builder->buildFromNodes(['id' => 6], [$invalidStream]);
} catch (VpnConfigValidationException) {
    $invalidRejected = true;
}
$assert($invalidRejected, 'Broken Reality config was returned.');

$endpoint = new VpnSubscriptionEndpointService();
$token = str_repeat('a', 64);
$assert($endpoint->etag($token, 1, 'base64') !== $endpoint->etag($token, 2, 'base64'), 'ETag ignores revision.');
$assert($endpoint->etag($token, 2, 'base64') !== $endpoint->etag($token, 2, 'plain'), 'ETag ignores format.');
$cache = new VpnSubscriptionCache();
$cacheKey = $cache->key($token, 7, 'plain');
$assert(str_contains($cacheKey, ':revision:7:format:plain'), 'Cache key ignores revision or format.');
$assert(!str_contains($cacheKey, $token), 'Cache key exposes the raw token.');

echo json_encode([
    'status' => 'ok',
    'cases' => [
        'single_tcp_reality',
        'vision_flow',
        'empty_flow_omitted',
        'multiple_nodes_isolated',
        'xhttp_actual_parameters',
        'tls_without_reality',
        'none_without_tls_or_reality',
        'invalid_config_rejected',
        'etag_revision_and_format',
        'cache_key_revision',
    ],
], JSON_UNESCAPED_SLASHES), PHP_EOL;
