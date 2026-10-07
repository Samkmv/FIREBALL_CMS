<?php

declare(strict_types=1);

// Actual synchronization service and HTTP client; only the transport is simulated.
// No Application, database, real panel or live HTTP request is initialized.
require dirname(__DIR__, 3) . '/config/config.php';
require ROOT . '/vendor/autoload.php';
if (!function_exists('return_translation')) {
    function return_translation(string $key): string { return $key; }
}
require dirname(__DIR__) . '/Plugin.php';

use Fireball\VpnManagerV2\Clients\ThreeXuiClient;
use Fireball\VpnManagerV2\DTO\ThreeXuiHttpResponse;
use Fireball\VpnManagerV2\DTO\ThreeXuiServerConfig;
use Fireball\VpnManagerV2\Exceptions\ClientVerificationException;
use Fireball\VpnManagerV2\Exceptions\ProvisioningException;
use Fireball\VpnManagerV2\Exceptions\ThreeXuiHttpException;
use Fireball\VpnManagerV2\Exceptions\ThreeXuiResponseException;
use Fireball\VpnManagerV2\Services\ClientPayloadFactory;
use Fireball\VpnManagerV2\Services\RemoteClientSyncService;

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (!$condition) { throw new RuntimeException($message); }
};
$json = static fn(array $body, int $status = 200): ThreeXuiHttpResponse =>
    new ThreeXuiHttpResponse($status, 'application/json', json_encode($body, JSON_THROW_ON_ERROR));
$config = new ThreeXuiServerConfig(panelUrl: 'https://panel.example.invalid', panelPath: '',
    authType: 'token', username: '', password: '', token: 'fixture');
$service = new RemoteClientSyncService();
$factory = new ClientPayloadFactory();
$cases = [];
foreach (['modern', 'modern_uuid', 'disabled', 'password', 'legacy404', 'legacy405', 'missing', 'identity_race',
    'wrong_email', 'unavailable', 'malformed', 'write_rejected', 'not_confirmed'] as $mode) {
    $credential = $mode === 'password' ? 'fixture-not-a-real-password' : '00000000-0000-4000-8000-000000000011';
    $oldEmail = 'old-client+fixture@example.invalid';
    $newEmail = 'new-client-fixture';
    $node = ['protocol' => $mode === 'password' ? 'trojan' : 'vless',
        'client_uuid' => $credential, 'client_password' => $credential, 'client_email' => $newEmail,
        'client_sub_id' => 'fixture-sub-id', 'traffic_limit_bytes' => 10000, 'flow' => '',
        'status' => $mode === 'disabled' ? 'disabled' : 'active',
        'desired_enabled' => $mode === 'disabled' ? 0 : 1];
    $subscription = ['status' => 'active', 'expires_at' => date('Y-m-d H:i:s', time() + 86400),
        'device_limit' => 3, 'ip_limit' => 2, 'traffic_limit_bytes' => 10000];
    $expected = $factory->build($subscription, $node);
    $initial = $remote = array_replace($expected, ['email' => $oldEmail, 'reset' => 30,
        'resetDay' => 9, 'resetWeekday' => 2, 'resetMax' => 6, 'comment' => 'keep comment',
        'group' => 'keep group', 'tgId' => 123, 'security' => 'keep cipher', 'up' => 100, 'down' => 200]);
    $globalExtras = ['adTag' => 'keep global metadata', 'allowedIPs' => '10.0.0.2/32,10.0.0.3/32'];
    if ($mode === 'modern_uuid') { $globalExtras += ['id' => 42, 'uuid' => $credential]; }
    $requests = $writes = [];
    $globalReads = 0;
    $transport = static function (string $method, string $url, array $payload, string $encoding) use (
        $json, $mode, $credential, $oldEmail, &$remote, $globalExtras, &$requests, &$writes, &$globalReads
    ): ThreeXuiHttpResponse {
        $path = (string)parse_url($url, PHP_URL_PATH);
        $requests[] = compact('method', 'path', 'url');
        if ($method === 'GET' && str_ends_with($path, '/inbounds/list')) {
            return $json(['success' => true, 'obj' => []]);
        }
        if ($method === 'GET' && $path === '/panel/api/inbounds/get/11') {
            return $json(['success' => true, 'obj' => ['id' => 11, 'protocol' => 'vless',
                'settings' => json_encode(['clients' => [$remote]], JSON_THROW_ON_ERROR),
                'clientStats' => [['email' => $remote['email'], 'up' => 100, 'down' => 200]]]]);
        }
        if ($method === 'GET' && str_starts_with($path, '/panel/api/clients/get/')) {
            $globalReads++;
            $email = rawurldecode(substr($path, strlen('/panel/api/clients/get/')));
            if ($mode === 'legacy404' || $mode === 'legacy405') {
                return $json(['success' => false], $mode === 'legacy404' ? 404 : 405);
            }
            if ($mode === 'unavailable') { return $json(['success' => false], 503); }
            if ($mode === 'malformed') { return $json(['success' => true, 'obj' => []]); }
            if ($mode === 'missing' || $email !== $remote['email']) {
                return $json(['success' => false, 'msg' => 'Obtain (record not found)']);
            }
            $record = array_replace($remote, $globalExtras);
            if ($mode === 'identity_race' && $globalReads >= 2) {
                $record['id'] = '00000000-0000-4000-8000-000000000099';
            }
            if ($mode === 'wrong_email') { $record['email'] = 'someone-else'; }
            return $json(['success' => true, 'obj' => ['client' => $record, 'inboundIds' => [11]]]);
        }
        if ($method === 'POST') {
            $writes[] = compact('path', 'payload', 'encoding', 'url');
            if ($mode === 'write_rejected') {
                return $json(['success' => false, 'msg' => 'Duplicate client email']);
            }
            if (in_array($mode, ['legacy404', 'legacy405'], true)) {
                if ($path !== '/panel/api/inbounds/updateClient/' . rawurlencode($credential)) {
                    throw new LogicException('Unexpected legacy write target');
                }
                $remote = json_decode($payload['settings'], true, flags: JSON_THROW_ON_ERROR)['clients'][0];
            } else {
                if ($path !== '/panel/api/clients/update/' . rawurlencode($oldEmail)) {
                    throw new LogicException('Rename addressed the new name or an unrelated endpoint');
                }
                $remote = $payload;
            }
            if ($mode === 'not_confirmed') { $remote['email'] = $oldEmail; }
            return $json(['success' => true]);
        }
        throw new LogicException('Unexpected fixture request');
    };
    $client = new ThreeXuiClient($config, $transport);
    $exception = null;
    $result = null;
    try { $result = $service->applyClientState($client, 11, $node, $expected); }
    catch (ClientVerificationException | ProvisioningException | ThreeXuiHttpException | ThreeXuiResponseException $caught) {
        $exception = $caught;
    }
    if (in_array($mode, ['missing', 'identity_race', 'wrong_email', 'unavailable', 'malformed'], true)) {
        $assert($exception !== null && $writes === [], "$mode must fail without any panel write");
        $assert($remote === $initial, "$mode changed client data");
    } elseif (in_array($mode, ['write_rejected', 'not_confirmed'], true)) {
        $assert($exception !== null && $result === null && count($writes) === 1,
            "$mode must not report a successful synchronization");
    } else {
        $assert($exception === null && count($writes) === 1, "$mode did not rename with exactly one update");
        $assert($result['remote_updated'] && $result['changed_fields'] === ['email'], "$mode changed unrelated fields");
        $assert($remote['email'] === $newEmail, "$mode did not save the new name");
        foreach ($initial as $field => $value) {
            if ($field !== 'email') { $assert(($remote[$field] ?? null) === $value, "$mode changed $field during rename"); }
        }
        $assert($result['traffic_used_bytes'] === 300 && $result['enable'] === $expected['enable'],
            "$mode lost confirmed traffic/access");
        if (!in_array($mode, ['legacy404', 'legacy405'], true)) {
            $assert($writes[0]['encoding'] === 'json' && str_ends_with($writes[0]['url'], '?inboundIds=11'),
                "$mode changed modern API encoding/attachment");
            $assert($remote['adTag'] === $globalExtras['adTag']
                && $remote['allowedIPs'] === ['10.0.0.2/32', '10.0.0.3/32'], "$mode lost global metadata");
            if ($mode === 'modern_uuid') { $assert($remote['uuid'] === $credential, 'Global protocol UUID changed'); }
        }
        $again = $service->applyClientState($client, 11, $node, $expected);
        $assert(!$again['remote_updated'] && $again['changed_fields'] === [] && count($writes) === 1,
            "$mode retry was not idempotent");
    }
    foreach ($requests as $request) {
        $assert(!preg_match('~/(add|delete|reset|attach)(/|$)~i', $request['path']), "$mode created/deleted/reset a client");
    }
    $cases[] = $mode;
}

// Transport guards also reject a changed payload credential before a write.
$writes = [];
$transport = static function (string $method, string $url, array $payload, string $encoding) use ($json, &$writes): ThreeXuiHttpResponse {
    if ($method === 'POST') { $writes[] = $payload; }
    return $json(['success' => true, 'obj' => ['client' => ['id' => 'original', 'email' => 'old']]]);
};
$exception = null;
try { (new ThreeXuiClient($config, $transport))->updateClientAtEmail(11, 'original', 'old', ['id' => 'changed', 'email' => 'new']); }
catch (ThreeXuiResponseException $caught) { $exception = $caught; }
$assert($exception !== null && $writes === [], 'Changed payload credential was written');

echo json_encode(['status' => 'ok', 'checks' => $checks, 'cases' => $cases,
    'real_network' => false, 'database' => false], JSON_THROW_ON_ERROR), PHP_EOL;
