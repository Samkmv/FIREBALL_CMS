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

use Fireball\VpnManagerV2\Clients\ThreeXuiClient;
use Fireball\VpnManagerV2\DTO\ThreeXuiHttpResponse;
use Fireball\VpnManagerV2\DTO\ThreeXuiServerConfig;
use Fireball\VpnManagerV2\Services\RemoteClientNameGenerator;
use Fireball\VpnManagerV2\Services\ServerMetricsService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$json = static fn(array $body, int $status = 200): ThreeXuiHttpResponse => new ThreeXuiHttpResponse(
    $status,
    'application/json',
    json_encode($body, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
);
$config = static fn(string $authType): ThreeXuiServerConfig => new ThreeXuiServerConfig(
    panelUrl: 'https://panel.example.invalid',
    panelPath: 'secret-path',
    authType: $authType,
    username: $authType === 'password' ? 'admin' : '',
    password: $authType === 'password' ? 'password' : '',
    token: $authType === 'token' ? 'admin-token' : '',
);

$names = new RemoteClientNameGenerator();
$baseName = $names->generate('Иван Петров', 'ivan@example.com', 'de');
$assert($names->forConnection($baseName, 7, 11) === 'ivan-petrov-ivan-example-com-DE-s7-i11'
    && $names->forConnection($baseName, 7, 12) !== $names->forConnection($baseName, 7, 11),
    'New clients are not uniquely scoped to one panel inbound.');

$passwordCalls = [];
$passwordTransport = static function (
    string $method,
    string $url,
    array $payload,
    string $encoding,
    array $headers
) use (&$passwordCalls, $json): ThreeXuiHttpResponse {
    $passwordCalls[] = compact('method', 'url', 'payload', 'encoding', 'headers');
    $path = (string)parse_url($url, PHP_URL_PATH);

    return match (true) {
        str_ends_with($path, '/csrf-token') => $json(['success' => true, 'obj' => 'csrf-token']),
        str_ends_with($path, '/login') => $json(['success' => true]),
        str_ends_with($path, '/panel/api/inbounds/list') => $json(['success' => true, 'obj' => []]),
        default => $json(['success' => false], 404),
    };
};
$passwordResult = (new ThreeXuiClient($config('password'), $passwordTransport))->testConnection();
$loginCall = $passwordCalls[1] ?? [];
$assert($passwordResult->success && count($passwordCalls) === 3,
    'Password authentication did not complete the CSRF/login/list sequence.');
$assert(($loginCall['encoding'] ?? '') === 'form'
    && ($loginCall['payload']['twoFactorCode'] ?? null) === ''
    && in_array('X-CSRF-Token: csrf-token', $loginCall['headers'] ?? [], true),
    'Password login did not carry the current 3x-ui CSRF contract.');

$modernCalls = [];
$modernTransport = static function (
    string $method,
    string $url,
    array $payload,
    string $encoding,
    array $headers
) use (&$modernCalls, $json): ThreeXuiHttpResponse {
    $modernCalls[] = compact('method', 'url', 'payload', 'encoding', 'headers');
    $path = (string)parse_url($url, PHP_URL_PATH);
    if (str_ends_with($path, '/panel/api/inbounds/list')) {
        return $json(['success' => true, 'obj' => []]);
    }
    if (str_ends_with($path, '/panel/api/server/status')) {
        return $json(['success' => true, 'obj' => [
            'cpu' => 12.5,
            'cpuCores' => 4,
            'cpuSpeedMhz' => 2400,
            'mem' => ['current' => 2147483648, 'total' => 8589934592],
            'swap' => ['current' => 536870912, 'total' => 4294967296],
            'disk' => ['current' => 10737418240, 'total' => 107374182400],
            'loads' => [0.25, 0.5, 0.75],
            'uptime' => 90061,
            'netIO' => ['up' => 1024, 'down' => 2048],
            'netTraffic' => ['sent' => 4096, 'recv' => 8192],
            'tcpCount' => 21,
            'udpCount' => 8,
            'xray' => ['state' => 'running', 'version' => '25.8.3'],
        ]]);
    }
    if (str_ends_with($path, '/panel/api/clients/get/shared-client')) {
        return $json(['success' => true, 'obj' => ['inboundIds' => [11, 12]]]);
    }
    if (str_ends_with($path, '/panel/api/clients/shared-client/detach')) {
        return $json(['success' => true]);
    }
    if (str_ends_with($path, '/panel/api/clients/get/single-client')) {
        return $json(['success' => true, 'obj' => ['inboundIds' => [11]]]);
    }
    if (str_ends_with($path, '/panel/api/clients/del/single-client')) {
        return $json(['success' => true]);
    }
    if (str_ends_with($path, '/panel/api/clients/resetTraffic/single-client')) {
        return $json(['success' => true]);
    }

    return $json(['success' => false], 404);
};
$modern = new ThreeXuiClient($config('token'), $modernTransport);
$modern->deleteClient(11, 'shared-id', 'shared-client');
$modern->deleteClient(11, 'single-id', 'single-client');
$modern->resetClientTraffic(11, 'single-client');
$serverMetrics = (new ServerMetricsService())->normalize($modern->serverStatus());
$sharedDetach = array_values(array_filter(
    $modernCalls,
    static fn(array $call): bool => str_ends_with(
        (string)parse_url($call['url'], PHP_URL_PATH),
        '/panel/api/clients/shared-client/detach'
    )
));
$singleDelete = array_values(array_filter(
    $modernCalls,
    static fn(array $call): bool => str_ends_with(
        (string)parse_url($call['url'], PHP_URL_PATH),
        '/panel/api/clients/del/single-client'
    )
));
$assert(count($sharedDetach) === 1
    && ($sharedDetach[0]['payload']['inboundIds'] ?? []) === [11],
    'A shared modern client was globally deleted instead of detached from one inbound.');
$assert(count($singleDelete) === 1
    && (string)parse_url($singleDelete[0]['url'], PHP_URL_QUERY) === 'keepTraffic=0',
    'A single-inbound modern client was not fully deleted.');
$assert(count(array_filter(
    $modernCalls,
    static fn(array $call): bool => str_ends_with(
        (string)parse_url($call['url'], PHP_URL_PATH),
        '/panel/api/clients/resetTraffic/single-client'
    )
)) === 1, 'The current client traffic-reset endpoint was not used.');
$assert(($serverMetrics['cpu']['percent'] ?? null) === 12.5
    && ($serverMetrics['memory']['percent'] ?? null) === 25.0
    && ($serverMetrics['swap']['percent'] ?? null) === 12.5
    && ($serverMetrics['disk']['percent'] ?? null) === 10.0
    && ($serverMetrics['load']['fifteen'] ?? null) === 0.75
    && ($serverMetrics['uptime_seconds'] ?? null) === 90061
    && ($serverMetrics['connections']['tcp'] ?? null) === 21
    && ($serverMetrics['xray']['state'] ?? null) === 'running',
    'The 3x-ui server status response was not normalized for the overview.');
$statusCalls = array_values(array_filter(
    $modernCalls,
    static fn(array $call): bool => str_ends_with(
        (string)parse_url($call['url'], PHP_URL_PATH),
        '/panel/api/server/status'
    )
));
$assert(count($statusCalls) === 1
    && in_array('Authorization: Bearer admin-token', $statusCalls[0]['headers'] ?? [], true),
    'The server metrics request did not use the configured 3x-ui token.');

$legacyCalls = [];
$legacyTransport = static function (
    string $method,
    string $url,
    array $payload,
    string $encoding,
    array $headers
) use (&$legacyCalls, $json): ThreeXuiHttpResponse {
    $legacyCalls[] = compact('method', 'url', 'payload', 'encoding', 'headers');
    $path = (string)parse_url($url, PHP_URL_PATH);
    if (str_ends_with($path, '/panel/api/inbounds/list')) {
        return $json(['success' => true, 'obj' => [[
            'id' => 21,
            'settings' => ['clients' => [['id' => 'legacy-id', 'email' => 'legacy-client']]],
        ]]]);
    }
    if (str_contains($path, '/panel/api/clients/')) {
        return $json(['success' => false], 404);
    }
    if (str_ends_with($path, '/panel/api/inbounds/21/delClient/legacy-id')
        || str_ends_with($path, '/panel/api/inbounds/21/resetClientTraffic/legacy-client')) {
        return $json(['success' => true]);
    }

    return $json(['success' => false], 404);
};
$legacy = new ThreeXuiClient($config('token'), $legacyTransport);
$legacy->deleteClient(21, 'legacy-id', 'legacy-client');
$legacy->resetClientTraffic(21, 'legacy-client');
$assert(count(array_filter(
    $legacyCalls,
    static fn(array $call): bool => str_contains(
        (string)parse_url($call['url'], PHP_URL_PATH),
        '/panel/api/inbounds/21/'
    )
)) === 2, 'Legacy delete/reset fallbacks are no longer available.');

echo json_encode([
    'status' => 'ok',
    'cases' => [
        'password_csrf_handshake',
        'connection_scoped_client_identity',
        'modern_shared_client_detach',
        'modern_single_client_delete',
        'modern_traffic_reset',
        'server_status_metrics',
        'legacy_delete_reset_fallbacks',
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
