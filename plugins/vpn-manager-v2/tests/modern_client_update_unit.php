<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/config/config.php';
require ROOT . '/vendor/autoload.php';
if (!function_exists('return_translation')) {
    function return_translation(string $key): string { return $key; }
}
require dirname(__DIR__) . '/Plugin.php';

use Fireball\VpnManagerV2\Clients\ThreeXuiClient;
use Fireball\VpnManagerV2\DTO\ThreeXuiHttpResponse;
use Fireball\VpnManagerV2\DTO\ThreeXuiServerConfig;
use Fireball\VpnManagerV2\Exceptions\ThreeXuiHttpException;
use Fireball\VpnManagerV2\Exceptions\ThreeXuiResponseException;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
};
$json = static fn(array $body, int $status = 200): ThreeXuiHttpResponse =>
    new ThreeXuiHttpResponse($status, 'application/json', json_encode($body, JSON_THROW_ON_ERROR));
$config = new ThreeXuiServerConfig(panelUrl: 'https://panel.example.invalid', panelPath: '',
    authType: 'token', username: '', password: '', token: 'fixture');
$client = ['id' => '00000000-0000-4000-8000-000000000001', 'email' => 'fixture@example.invalid',
    'enable' => false, 'expiryTime' => 1700000000000, 'reset' => 0, 'resetDay' => 0];
foreach (['modern', 'legacy', 'unavailable', 'malformed'] as $mode) {
    $writes = [];
    $transport = static function (string $method, string $url, array $payload, string $encoding) use (
        $json, $mode, $client, &$writes
    ): ThreeXuiHttpResponse {
        $path = (string)parse_url($url, PHP_URL_PATH);
        if (str_ends_with($path, '/inbounds/list')) {
            return $json(['success' => true, 'obj' => []]);
        }
        if (str_contains($path, '/clients/get/')) {
            if ($mode === 'legacy') { return $json(['success' => false], 404); }
            if ($mode === 'unavailable') { return $json(['success' => false], 503); }
            if ($mode === 'malformed') { return $json(['success' => true, 'obj' => []]); }
            // FIREBALL_VPN_REPAIR_V3: allowed-ips-regression
            return $json(['success' => true, 'obj' => ['client' => array_replace($client,
                [
                    'enable' => true,
                    'expiryTime' => 0,
                    'limitHwid' => 3,
                    'reset' => 30,
                    'resetDay' => 0,
                    'resetWeekday' => 0,
                    'resetMax' => 6,
                    'adTag' => 'preserved',
                    'allowedIPs' => '10.0.0.2/32,10.0.0.3/32',
                ])]]);
        }
        if ($method === 'POST') {
            $writes[] = compact('path', 'payload', 'encoding', 'url');
            return $json(['success' => true]);
        }
        throw new LogicException('Unexpected fixture request.');
    };
    $exception = null;
    try { (new ThreeXuiClient($config, $transport))->updateClient(11, $client['id'], $client); }
    catch (ThreeXuiHttpException | ThreeXuiResponseException $caught) { $exception = $caught; }
    if (in_array($mode, ['unavailable', 'malformed'], true)) {
        $assert($exception !== null && $writes === [], 'Failed read still overwrote remote client fields.');
        continue;
    }
    $assert($exception === null && count($writes) === 1, 'Client update did not make exactly one write.');
    $write = $writes[0];
    if ($mode === 'legacy') {
        $decoded = json_decode($write['payload']['settings'], true);
        $assert(str_contains($write['path'], '/inbounds/updateClient/')
            && $decoded['clients'][0] === $client, 'Legacy client update no longer works.');
    } else {
        $assert($write['encoding'] === 'json' && str_contains($write['url'], '?inboundIds=11')
            && $write['payload']['limitHwid'] === 3 && $write['payload']['adTag'] === 'preserved'
            && $write['payload']['reset'] === 0 && $write['payload']['resetMax'] === 6
            && $write['payload']['enable'] === false && $write['payload']['expiryTime'] === $client['expiryTime']
            && ($write['payload']['allowedIPs'] ?? null) === ['10.0.0.2/32', '10.0.0.3/32'],
            'Current API lost fields or allowedIPs was not normalized to []string.');
    }
}
echo json_encode(['status' => 'ok', 'cases' => ['modern_device_limit_preserved', 'legacy_fallback',
    'remote_read_failure_no_write', 'malformed_response_no_write']]), PHP_EOL;
