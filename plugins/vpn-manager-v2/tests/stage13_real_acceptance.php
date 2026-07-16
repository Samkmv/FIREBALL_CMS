<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/config/config.php';
require ROOT . '/vendor/autoload.php';
require ROOT . '/helpers/helpers.php';

new FBL\Application();
require dirname(__DIR__) . '/Plugin.php';
FBL\Language::registerPluginLanguage('vpn-manager-v2', dirname(__DIR__) . '/lang');

use Fireball\VpnManagerV2\Clients\ThreeXuiClient;
use Fireball\VpnManagerV2\Repositories\ServerRepository;
use Fireball\VpnManagerV2\Repositories\SubscriptionRepository;
use Fireball\VpnManagerV2\Services\ConnectionEditingService;
use Fireball\VpnManagerV2\Services\ServerSecretService;
use Fireball\VpnManagerV2\Services\SubscriptionDeletionService;
use Fireball\VpnManagerV2\Services\SubscriptionEditingService;
use Fireball\VpnManagerV2\Services\SubscriptionProvisioningService;
use Fireball\VpnManagerV2\Services\VpnSubscriptionEndpointService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$enabled = static function (mixed $value): bool {
    if (is_bool($value)) {
        return $value;
    }
    if (is_int($value) || is_float($value)) {
        return (int)$value === 1;
    }

    return filter_var($value, FILTER_VALIDATE_BOOL) === true;
};
$remoteTraffic = static function (ThreeXuiClient $client, string $email): ?int {
    try {
        $response = $client->getClientTraffic($email);
        $stats = $response['obj'] ?? $response;
        if (!is_array($stats)) {
            return null;
        }

        return max(0, (int)($stats['up'] ?? 0)) + max(0, (int)($stats['down'] ?? 0));
    } catch (Throwable) {
        return null;
    }
};
$uriQuery = static function (string $body): array {
    $plain = base64_decode($body, true);
    if (!is_string($plain)) {
        return [];
    }
    $uri = trim((string)(explode("\n", trim($plain))[0] ?? ''));
    $query = [];
    parse_str((string)(parse_url($uri, PHP_URL_QUERY) ?: ''), $query);

    return $query;
};

$fixtureUser = db()->query(
    'SELECT id FROM users WHERE login LIKE ? ORDER BY id DESC LIMIT 1',
    ['stage10-browser-%']
)->getOne();
$admin = db()->query(
    "SELECT id FROM users WHERE role IN ('creator', 'admin') AND login NOT LIKE ? ORDER BY id LIMIT 1",
    ['stage13-browser-%']
)->getOne();
$assert(is_array($fixtureUser) && is_array($admin), 'Acceptance fixture user and an existing admin are required.');

$server = (new ServerRepository())->findWithSecrets(1);
$assert(is_array($server) && !empty($server['is_enabled']), 'The real acceptance server is unavailable.');
$client = new ThreeXuiClient((new ServerSecretService())->clientConfig($server));
$remoteInboundId = 1;
$beforeInbound = $client->getInbound($remoteInboundId);
$beforeSettings = $beforeInbound['settings'] ?? [];
if (is_string($beforeSettings)) {
    $decoded = json_decode($beforeSettings, true);
    $beforeSettings = is_array($decoded) ? $decoded : [];
}
$remoteCountBefore = count((array)($beforeSettings['clients'] ?? []));

$subscriptionId = 0;
$deleted = false;
$results = [];
$cleanup = null;

try {
    $provisioning = new SubscriptionProvisioningService(
        notificationCallback: static function (): void {}
    );
    $provisioned = $provisioning->create([
        'user_id' => (int)$fixtureUser['id'],
        'plan_id' => 1,
        'starts_at' => date('Y-m-d H:i:s'),
    ], (int)$admin['id']);
    $subscriptionId = $provisioned->subscriptionId;
    $assert($provisioned->successful() && $provisioned->created === 1,
        'Real client provisioning was not confirmed.');

    $subscription = db()->query(
        'SELECT id, status, expires_at, traffic_limit_bytes, device_limit, subscription_token, revision
         FROM vpn_v2_subscriptions WHERE id = ?',
        [$subscriptionId]
    )->getOne();
    $node = db()->query(
        'SELECT id, client_uuid, client_email, client_sub_id, flow, status, traffic_limit_bytes,
                traffic_used_bytes FROM vpn_v2_subscription_nodes WHERE subscription_id = ?',
        [$subscriptionId]
    )->getOne();
    $assert(is_array($subscription) && is_array($node) && $subscription['status'] === 'active'
        && $node['status'] === 'active', 'Local subscription did not become active.');

    $token = (string)$subscription['subscription_token'];
    $uuid = (string)$node['client_uuid'];
    $email = (string)$node['client_email'];
    $subId = (string)$node['client_sub_id'];
    $nodeId = (int)$node['id'];
    $trafficLocalBefore = (int)$node['traffic_used_bytes'];
    $trafficRemoteBefore = $remoteTraffic($client, $email);
    $remote = $client->findClient($remoteInboundId, $uuid, $email);
    $assert(is_array($remote) && hash_equals($uuid, (string)($remote['id'] ?? ''))
        && hash_equals($email, (string)($remote['email'] ?? ''))
        && trim((string)($remote['flow'] ?? '')) === 'xtls-rprx-vision',
        'Created client identity or Vision flow was not confirmed.');

    $endpoint = new VpnSubscriptionEndpointService();
    $initialResponse = $endpoint->respond($token);
    $initialEtag = (string)($initialResponse->headers['ETag'] ?? '');
    $initialRevision = (int)$subscription['revision'];
    $assert($initialResponse->status === 200 && $initialResponse->configCount === 1
        && $initialEtag !== '', 'Initial subscription endpoint response is invalid.');
    $results['real_provisioning'] = true;
    $results['tcp_reality_vision'] = true;

    $newExpiry = date('Y-m-d H:i:s', time() + 45 * 86400);
    $expirationEdit = (new SubscriptionEditingService())->update($subscriptionId, [
        'expires_at' => $newExpiry,
        'traffic_limit_value' => '0',
        'traffic_unit' => 'gb',
        'status' => 'active',
        'internal_comment' => '',
    ], (int)$admin['id']);
    $assert($expirationEdit->successful() && $expirationEdit->revision > $initialRevision,
        'Expiration edit was not confirmed.');
    $remote = $client->findClient($remoteInboundId, $uuid, $email);
    $assert(is_array($remote) && (int)($remote['expiryTime'] ?? 0) === strtotime($newExpiry) * 1000,
        'Remote expiration does not match the edit.');
    $results['expiration_edit'] = true;

    $limitEdit = (new SubscriptionEditingService())->update($subscriptionId, [
        'expires_at' => $newExpiry,
        'traffic_limit_value' => '5',
        'traffic_unit' => 'gb',
        'status' => 'active',
        'internal_comment' => '',
    ], (int)$admin['id']);
    $remote = $client->findClient($remoteInboundId, $uuid, $email);
    $assert($limitEdit->successful() && is_array($remote)
        && (int)($remote['totalGB'] ?? 0) === 5 * (1024 ** 3), 'Remote traffic limit does not match the edit.');
    $results['limit_edit'] = true;

    $flowOff = (new ConnectionEditingService())->update($nodeId, [
        'flow' => '__none__',
        'traffic_limit_value' => '5',
        'traffic_unit' => 'gb',
    ], (int)$admin['id']);
    $flowOffResponse = $endpoint->respond($token);
    $flowOffQuery = $uriQuery($flowOffResponse->body);
    $remote = $client->findClient($remoteInboundId, $uuid, $email);
    $assert($flowOff->successful() && is_array($remote) && trim((string)($remote['flow'] ?? '')) === ''
        && !array_key_exists('flow', $flowOffQuery), 'Flow removal was not confirmed end-to-end.');

    $flowOn = (new ConnectionEditingService())->update($nodeId, [
        'flow' => 'xtls-rprx-vision',
        'traffic_limit_value' => '5',
        'traffic_unit' => 'gb',
    ], (int)$admin['id']);
    $flowOnResponse = $endpoint->respond($token);
    $flowOnQuery = $uriQuery($flowOnResponse->body);
    $remote = $client->findClient($remoteInboundId, $uuid, $email);
    $assert($flowOn->successful() && is_array($remote)
        && trim((string)($remote['flow'] ?? '')) === 'xtls-rprx-vision'
        && ($flowOnQuery['flow'] ?? '') === 'xtls-rprx-vision', 'Vision flow restore was not confirmed end-to-end.');
    $results['flow_edit'] = true;

    $suspended = (new SubscriptionEditingService())->update($subscriptionId, [
        'expires_at' => $newExpiry,
        'traffic_limit_value' => '5',
        'traffic_unit' => 'gb',
        'status' => 'suspended',
        'internal_comment' => '',
    ], (int)$admin['id']);
    $remote = $client->findClient($remoteInboundId, $uuid, $email);
    $assert($suspended->successful() && is_array($remote) && !$enabled($remote['enable'] ?? true)
        && $endpoint->respond($token)->status === 403, 'Subscription disable was not confirmed.');
    $results['disable'] = true;

    $enabledEdit = (new SubscriptionEditingService())->update($subscriptionId, [
        'expires_at' => $newExpiry,
        'traffic_limit_value' => '5',
        'traffic_unit' => 'gb',
        'status' => 'active',
        'internal_comment' => '',
    ], (int)$admin['id']);
    $finalResponse = $endpoint->respond($token);
    $finalEtag = (string)($finalResponse->headers['ETag'] ?? '');
    $remote = $client->findClient($remoteInboundId, $uuid, $email);
    $identity = db()->query(
        'SELECT n.client_uuid, n.client_email, n.client_sub_id, n.traffic_used_bytes,
                s.subscription_token, s.revision
         FROM vpn_v2_subscription_nodes n
         INNER JOIN vpn_v2_subscriptions s ON s.id = n.subscription_id
         WHERE n.id = ?',
        [$nodeId]
    )->getOne();
    $assert($enabledEdit->successful() && is_array($remote) && $enabled($remote['enable'] ?? false)
        && is_array($identity) && hash_equals($uuid, (string)$identity['client_uuid'])
        && hash_equals($email, (string)$identity['client_email'])
        && hash_equals($subId, (string)$identity['client_sub_id'])
        && hash_equals($token, (string)$identity['subscription_token'])
        && (int)$identity['traffic_used_bytes'] >= $trafficLocalBefore
        && $finalResponse->status === 200 && $finalEtag !== '' && !hash_equals($initialEtag, $finalEtag)
        && (int)$identity['revision'] > $initialRevision,
        'Enable, immutable identity, traffic, revision, or ETag acceptance failed.');
    $trafficRemoteAfter = $remoteTraffic($client, $email);
    if ($trafficRemoteBefore !== null && $trafficRemoteAfter !== null) {
        $assert($trafficRemoteAfter >= $trafficRemoteBefore, 'Remote traffic counter decreased during ordinary edits.');
    }
    $results['enable'] = true;
    $results['identity_preserved'] = true;
    $results['token_preserved'] = true;
    $results['traffic_not_reset'] = true;
    $results['revision_etag'] = true;

    $afterEditsInbound = $client->getInbound($remoteInboundId);
    $afterEditsSettings = $afterEditsInbound['settings'] ?? [];
    if (is_string($afterEditsSettings)) {
        $decoded = json_decode($afterEditsSettings, true);
        $afterEditsSettings = is_array($decoded) ? $decoded : [];
    }
    $assert(count((array)($afterEditsSettings['clients'] ?? [])) === $remoteCountBefore + 1,
        'Ordinary edits recreated or duplicated the client.');
    $results['no_recreation'] = true;

    $eventPayload = (string)db()->query(
        'SELECT GROUP_CONCAT(COALESCE(context_json, "") SEPARATOR "|")
         FROM vpn_v2_events WHERE subscription_id = ?',
        [$subscriptionId]
    )->getColumn();
    $assert(!str_contains($eventPayload, $token) && !str_contains($eventPayload, $uuid),
        'A full token or UUID leaked into the V2 event journal.');
    $results['no_secret_event_leak'] = true;

    $deletion = (new SubscriptionDeletionService())->deleteForever($subscriptionId, (int)$admin['id']);
    $assert($deletion->successful(), 'Real subscription deletion was not confirmed.');
    $deleted = true;
    $afterDeleteInbound = $client->getInbound($remoteInboundId);
    $afterDeleteSettings = $afterDeleteInbound['settings'] ?? [];
    if (is_string($afterDeleteSettings)) {
        $decoded = json_decode($afterDeleteSettings, true);
        $afterDeleteSettings = is_array($decoded) ? $decoded : [];
    }
    $assert($client->findClient($remoteInboundId, $uuid, $email) === null
        && count((array)($afterDeleteSettings['clients'] ?? [])) === $remoteCountBefore
        && (int)db()->query('SELECT COUNT(*) FROM vpn_v2_subscriptions WHERE id = ?', [$subscriptionId])->getColumn() === 0
        && (int)db()->query('SELECT COUNT(*) FROM vpn_v2_subscription_nodes WHERE subscription_id = ?', [$subscriptionId])->getColumn() === 0
        && $endpoint->respond($token)->status === 404
        && (int)db()->query('SELECT COUNT(*) FROM users WHERE id = ?', [(int)$fixtureUser['id']])->getColumn() === 1,
        'Remote absence, local cleanup, old token, or user preservation failed.');
    $repeatDelete = (new SubscriptionDeletionService())->deleteForever($subscriptionId, (int)$admin['id']);
    $assert($repeatDelete->successful() && $repeatDelete->alreadyDeleted,
        'Repeated deletion was not idempotent.');
    $results['delete_confirmed'] = true;
    $results['delete_idempotent'] = true;
    $results['user_preserved'] = true;

    echo json_encode([
        'status' => 'ok',
        'results' => $results,
        'remote_client_count_restored' => true,
        'secrets_redacted' => true,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), PHP_EOL;
} finally {
    if ($subscriptionId > 0 && !$deleted
        && (int)db()->query('SELECT COUNT(*) FROM vpn_v2_subscriptions WHERE id = ?', [$subscriptionId])->getColumn() > 0) {
        try {
            $cleanup = (new SubscriptionDeletionService())->deleteForever($subscriptionId, (int)$admin['id']);
        } catch (Throwable) {
            $cleanup = null;
        }
    }

    if ($subscriptionId > 0 && !$deleted && (!$cleanup || !$cleanup->successful())) {
        fwrite(STDERR, "Stage 13 real acceptance cleanup requires retry for subscription #{$subscriptionId}.\n");
    }
}
