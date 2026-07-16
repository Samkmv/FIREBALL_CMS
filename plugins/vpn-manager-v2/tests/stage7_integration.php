<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/config/config.php';
require ROOT . '/vendor/autoload.php';
require ROOT . '/helpers/helpers.php';

new FBL\Application();
require dirname(__DIR__) . '/Plugin.php';
FBL\Language::registerPluginLanguage('vpn-manager-v2', dirname(__DIR__) . '/lang');

use Fireball\VpnManagerV2\Clients\ThreeXuiClientInterface;
use Fireball\VpnManagerV2\DTO\ConnectionTestResult;
use Fireball\VpnManagerV2\Exceptions\ThreeXuiTransportException;
use Fireball\VpnManagerV2\Services\ConnectionEditingService;
use Fireball\VpnManagerV2\Services\RemoteClientSyncService;
use Fireball\VpnManagerV2\Services\SubscriptionEditingService;
use Fireball\VpnManagerV2\Services\VpnFlowResolver;
use Fireball\VpnManagerV2\Services\VpnSubscriptionEndpointService;

final class Stage7Panel
{
    public array $clients = [];
    public array $traffic = [];
    public int $updateCount = 0;
    public int $addCount = 0;
    public int $deleteCount = 0;
    public int $readCount = 0;
    public bool $failUpdates = false;
    public bool $transactionViolation = false;
}

final class Stage7Client implements ThreeXuiClientInterface
{
    public function __construct(private readonly Stage7Panel $panel, private readonly int $remoteInboundId)
    {
    }

    public function authenticate(): void
    {
    }

    public function testConnection(): ConnectionTestResult
    {
        return new ConnectionTestResult(true, 'ok', 1, 'online');
    }

    public function listInbounds(): array
    {
        return [$this->getInbound($this->remoteInboundId)];
    }

    public function getInbound(int $remoteInboundId): array
    {
        $this->touch();
        $this->panel->readCount++;
        $stats = [];
        foreach ($this->panel->clients as $client) {
            $email = (string)$client['email'];
            $traffic = $this->panel->traffic[$email] ?? ['up' => 0, 'down' => 0];
            $stats[] = ['email' => $email, 'up' => $traffic['up'], 'down' => $traffic['down']];
        }

        return [
            'id' => $remoteInboundId,
            'settings' => json_encode(['clients' => array_values($this->panel->clients)], JSON_UNESCAPED_SLASHES),
            'clientStats' => $stats,
        ];
    }

    public function getClientTraffic(string $clientIdentifier): array
    {
        $traffic = $this->panel->traffic[$clientIdentifier] ?? ['up' => 0, 'down' => 0];

        return ['success' => true, 'obj' => [
            'email' => $clientIdentifier,
            'up' => (int)$traffic['up'],
            'down' => (int)$traffic['down'],
        ]];
    }

    public function findClient(int $remoteInboundId, string $clientId = '', string $clientEmail = ''): ?array
    {
        foreach ($this->panel->clients as $client) {
            if (($clientId !== '' && (string)$client['id'] === $clientId)
                || ($clientEmail !== '' && (string)$client['email'] === $clientEmail)) {
                return $client;
            }
        }

        return null;
    }

    public function addClient(int $remoteInboundId, array $client): array
    {
        $this->panel->addCount++;
        throw new LogicException('Stage 7 must never create a client.');
    }

    public function updateClient(int $remoteInboundId, string $clientId, array $client): array
    {
        $this->touch();
        $this->panel->updateCount++;
        if ($this->panel->failUpdates) {
            throw new ThreeXuiTransportException('stage7 unavailable');
        }
        if (!isset($this->panel->clients[$clientId])) {
            throw new RuntimeException('Stage 7 attempted to update an unknown client.');
        }
        $before = $this->panel->clients[$clientId];
        if ((string)($client['id'] ?? '') !== $clientId
            || (string)($client['email'] ?? '') !== (string)$before['email']) {
            throw new RuntimeException('Stage 7 attempted to change client identity.');
        }
        if ((int)($client['reset'] ?? -1) !== 0) {
            throw new RuntimeException('Stage 7 attempted to reset traffic.');
        }
        $this->panel->clients[$clientId] = $client;

        return ['success' => true];
    }

    public function deleteClient(int $remoteInboundId, string $clientId, ?string $clientEmail = null): array
    {
        $this->panel->deleteCount++;
        throw new LogicException('Stage 7 must never delete a client.');
    }

    private function touch(): void
    {
        if (db()->inTransaction()) {
            $this->panel->transactionViolation = true;
        }
    }
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$admin = db()->query("SELECT id FROM users WHERE role IN ('creator', 'admin') ORDER BY id LIMIT 1")->getOne();
$subscriber = db()->query("SELECT id FROM users WHERE role = 'user' ORDER BY id LIMIT 1")->getOne()
    ?: db()->query('SELECT id FROM users ORDER BY id LIMIT 1')->getOne();
$assert(is_array($admin) && is_array($subscriber), 'CMS users are required.');
$adminId = (int)$admin['id'];
$userId = (int)$subscriber['id'];
$suffix = substr(hash('sha256', uniqid('stage7-', true)), 0, 10);
$now = date('Y-m-d H:i:s');
$serverIds = [];
$inboundIds = [];
$nodeIds = [];
$panels = [new Stage7Panel(), new Stage7Panel()];
$subscriptionId = 0;
$planId = 0;
$token = bin2hex(random_bytes(32));
$results = [];

try {
    foreach ([0, 1] as $index) {
        db()->query(
            'INSERT INTO vpn_v2_servers
                (name, code, panel_url, panel_path, auth_type, show_flag, status, is_enabled, created_at, updated_at)
             VALUES (?, ?, ?, NULL, ?, 0, ?, 1, ?, ?)',
            ['Stage 7 ' . $index, 'stage7-' . $index . '-' . $suffix, 'https://node' . $index . '.stage7.invalid', 'token', 'online', $now, $now]
        );
        $serverIds[$index] = (int)db()->getInsertId();
        $stream = [
            'network' => 'tcp',
            'security' => 'reality',
            'realitySettings' => [
                'shortIds' => ['abcd1234'],
                'settings' => [
                    'serverName' => 'node' . $index . '.stage7.invalid',
                    'fingerprint' => 'chrome',
                    'publicKey' => 'stage7-public-key-' . $index,
                ],
            ],
        ];
        db()->query(
            'INSERT INTO vpn_v2_inbounds
                (server_id, remote_inbound_id, name, protocol, port, network, security, default_flow,
                 settings_json, stream_settings_json, status, is_enabled, synced_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)',
            [$serverIds[$index], (string)(7700 + $index), 'Stage 7 inbound ' . $index, 'vless', 17700 + $index,
                'tcp', 'reality', VpnFlowResolver::VISION, '{}', json_encode($stream, JSON_UNESCAPED_SLASHES),
                'active', $now, $now, $now]
        );
        $inboundIds[$index] = (int)db()->getInsertId();
    }

    db()->query(
        'INSERT INTO vpn_v2_plans
            (name, description, duration_days, traffic_limit_bytes, device_limit, is_active, created_at, updated_at)
         VALUES (?, ?, 30, ?, 2, 1, ?, ?)',
        ['Stage 7 ' . $suffix, 'Stage 7 fixture', 10 * (1024 ** 3), $now, $now]
    );
    $planId = (int)db()->getInsertId();
    foreach ([0, 1] as $index) {
        db()->query(
            'INSERT INTO vpn_v2_plan_nodes
                (plan_id, server_id, inbound_id, flow_override, is_enabled, sort_order, created_at, updated_at)
             VALUES (?, ?, ?, NULL, 1, ?, ?, ?)',
            [$planId, $serverIds[$index], $inboundIds[$index], $index * 10, $now, $now]
        );
    }

    $expires = date('Y-m-d H:i:s', time() + 30 * 86400);
    db()->query(
        'INSERT INTO vpn_v2_subscriptions
            (user_id, plan_id, status, starts_at, expires_at, traffic_limit_bytes, device_limit,
             subscription_token, revision, config_updated_at, created_by, internal_comment, last_error, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, 2, ?, 1, ?, ?, NULL, NULL, ?, ?)',
        [$userId, $planId, 'active', $now, $expires, 10 * (1024 ** 3), $token, $now, $adminId, $now, $now]
    );
    $subscriptionId = (int)db()->getInsertId();
    foreach ([0, 1] as $index) {
        $uuid = sprintf('70000000-0000-4000-8000-%012d', $index + 1);
        $email = 'stage7-' . $index . '-' . $suffix;
        db()->query(
            'INSERT INTO vpn_v2_subscription_nodes
                (subscription_id, server_id, inbound_id, remote_client_id, client_uuid, client_email,
                 client_sub_id, protocol, network, security, flow, status, traffic_limit_bytes,
                 traffic_used_bytes, last_sync_at, last_error, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, NULL, ?, ?)',
            [$subscriptionId, $serverIds[$index], $inboundIds[$index], $uuid, $uuid, $email,
                'sub-' . $index, 'vless', 'tcp', 'reality', 'active', 10 * (1024 ** 3), 579, $now, $now, $now]
        );
        $nodeIds[$index] = (int)db()->getInsertId();
        $panels[$index]->clients[$uuid] = [
            'id' => $uuid,
            'email' => $email,
            'subId' => 'sub-' . $index,
            'flow' => '',
            'security' => 'auto',
            'limitIp' => 2,
            'totalGB' => 10 * (1024 ** 3),
            'expiryTime' => strtotime($expires) * 1000,
            'enable' => true,
            'tgId' => 0,
            'group' => '',
            'comment' => '',
            'reset' => 0,
        ];
        $panels[$index]->traffic[$email] = ['up' => 123, 'down' => 456];
    }

    $factory = static function (array $server, array $inbound, array $node) use ($serverIds, $panels): ThreeXuiClientInterface {
        $index = array_search((int)$server['id'], $serverIds, true);
        if ($index === false) {
            throw new RuntimeException('Unknown Stage 7 server.');
        }

        return new Stage7Client($panels[$index], (int)$inbound['remote_inbound_id']);
    };
    $remote = new RemoteClientSyncService(clientFactory: $factory);
    $subscriptions = new SubscriptionEditingService(remoteSync: $remote);
    $connections = new ConnectionEditingService(remoteSync: $remote);
    $endpoint = new VpnSubscriptionEndpointService();

    $initialResponse = $endpoint->respond($token);
    $assert($initialResponse->status === 200, 'Initial subscription endpoint failed.');
    $initialEtag = $initialResponse->headers['ETag'];
    $initialBody = $initialResponse->body;
    $initialIdentity = array_map(static fn(Stage7Panel $panel): array => [
        'id' => reset($panel->clients)['id'],
        'email' => reset($panel->clients)['email'],
    ], $panels);

    $newExpires = date('Y-m-d\TH:i', time() + 45 * 86400);
    $expiryResult = $subscriptions->update($subscriptionId, [
        'expires_at' => $newExpires,
        'traffic_limit_value' => 10,
        'traffic_unit' => 'gb',
        'status' => 'active',
        'internal_comment' => '',
    ], $adminId);
    $assert($expiryResult->successful() && $expiryResult->synced === 2, 'Expiration update was not confirmed for all nodes.');
    foreach ($panels as $panel) {
        $assert((int)reset($panel->clients)['expiryTime'] === strtotime($newExpires) * 1000, 'Remote expiryTime mismatch.');
    }
    $results['expiration'] = true;

    $limitResult = $subscriptions->update($subscriptionId, [
        'expires_at' => $newExpires,
        'traffic_limit_value' => 20,
        'traffic_unit' => 'gb',
        'status' => 'active',
        'internal_comment' => '',
    ], $adminId);
    foreach ($panels as $panel) {
        $assert((int)reset($panel->clients)['totalGB'] === 20 * (1024 ** 3), 'Remote totalGB mismatch.');
    }
    $assert($limitResult->revision > $expiryResult->revision, 'Revision did not increase after limit update.');
    $results['traffic_limit'] = true;

    $suspended = $subscriptions->update($subscriptionId, [
        'expires_at' => $newExpires,
        'traffic_limit_value' => 20,
        'traffic_unit' => 'gb',
        'status' => 'suspended',
        'internal_comment' => '',
    ], $adminId);
    foreach ($panels as $panel) {
        $assert(reset($panel->clients)['enable'] === false, 'Suspension did not disable a client.');
    }
    $reenabled = $subscriptions->update($subscriptionId, [
        'expires_at' => $newExpires,
        'traffic_limit_value' => 20,
        'traffic_unit' => 'gb',
        'status' => 'active',
        'internal_comment' => '',
    ], $adminId);
    foreach ($panels as $panel) {
        $assert(reset($panel->clients)['enable'] === true, 'Reactivation did not enable a client.');
    }
    $assert($reenabled->revision > $suspended->revision, 'Revision did not increase after reactivation.');
    $results['disable_enable'] = true;

    $beforeNodeUpdates = [$panels[0]->updateCount, $panels[1]->updateCount];
    $nodeResult = $connections->update($nodeIds[0], [
        'flow' => VpnFlowResolver::VISION,
        'traffic_limit_value' => 20,
        'traffic_unit' => 'gb',
    ], $adminId);
    $assert($nodeResult->successful(), 'Node Flow update failed.');
    $assert(reset($panels[0]->clients)['flow'] === VpnFlowResolver::VISION, 'Vision Flow was not confirmed.');
    $assert($panels[0]->updateCount === $beforeNodeUpdates[0] + 1
        && $panels[1]->updateCount === $beforeNodeUpdates[1], 'More than one multi-server node was updated.');
    $results['single_node_multi'] = true;
    $results['flow'] = true;

    $changedResponse = $endpoint->respond($token);
    $assert($changedResponse->status === 200 && $changedResponse->headers['ETag'] !== $initialEtag,
        'The same subscription URL did not expose a new ETag.');
    $assert($changedResponse->body !== $initialBody, 'The same subscription URL did not expose the Flow change.');
    $assert($endpoint->respond($token, 'base64', $changedResponse->headers['ETag'])->status === 304,
        'New ETag did not return 304.');
    $results['revision_etag_same_url'] = true;

    $updatesBeforeComment = array_sum(array_map(static fn(Stage7Panel $panel): int => $panel->updateCount, $panels));
    $revisionBeforeComment = $nodeResult->revision;
    $comment = $subscriptions->update($subscriptionId, [
        'expires_at' => $newExpires,
        'traffic_limit_value' => 20,
        'traffic_unit' => 'gb',
        'status' => 'active',
        'internal_comment' => 'Stage 7 CMS-only comment',
    ], $adminId);
    $assert(!$comment->remoteRequest && $comment->revision === $revisionBeforeComment,
        'Comment-only edit changed revision or used 3x-ui.');
    $assert(array_sum(array_map(static fn(Stage7Panel $panel): int => $panel->updateCount, $panels)) === $updatesBeforeComment,
        'Comment-only edit updated a remote client.');
    $results['comment_local_only'] = true;

    $uuid0 = array_key_first($panels[0]->clients);
    $panels[0]->clients[$uuid0]['flow'] = '';
    $panels[0]->clients[$uuid0]['totalGB'] = 15 * (1024 ** 3);
    $pull = $connections->receiveFromRemote($nodeIds[0], $adminId);
    $pulledNode = db()->query('SELECT flow, traffic_limit_bytes, traffic_used_bytes FROM vpn_v2_subscription_nodes WHERE id = ?', [$nodeIds[0]])->getOne();
    $assert($pull->successful() && $pulledNode['flow'] === null
        && (int)$pulledNode['traffic_limit_bytes'] === 15 * (1024 ** 3)
        && (int)$pulledNode['traffic_used_bytes'] === 579, 'Pull mode did not import explicit remote node state.');
    $results['explicit_pull'] = true;

    $panels[0]->clients[$uuid0]['totalGB'] = 1;
    $push = $connections->sendToRemote($nodeIds[0], $adminId);
    $assert($push->successful() && (int)$panels[0]->clients[$uuid0]['totalGB'] === 15 * (1024 ** 3),
        'Push mode did not restore local state.');
    $results['explicit_push'] = true;

    $panels[1]->failUpdates = true;
    $partialExpires = date('Y-m-d\TH:i', time() + 50 * 86400);
    $partial = $subscriptions->update($subscriptionId, [
        'expires_at' => $partialExpires,
        'traffic_limit_value' => 15,
        'traffic_unit' => 'gb',
        'status' => 'active',
        'internal_comment' => 'Stage 7 CMS-only comment',
    ], $adminId);
    $failedNode = db()->query('SELECT status FROM vpn_v2_subscription_nodes WHERE id = ?', [$nodeIds[1]])->getColumn();
    $storedExpiry = db()->query('SELECT expires_at FROM vpn_v2_subscriptions WHERE id = ?', [$subscriptionId])->getColumn();
    $assert($partial->synced === 1 && $partial->failed === 1 && $failedNode === 'sync_error'
        && (string)$storedExpiry === date('Y-m-d H:i:s', strtotime($partialExpires)), 'Partial multi-server result was not stored correctly.');
    $results['partial_sync'] = true;

    foreach ($panels as $index => $panel) {
        $client = reset($panel->clients);
        $assert($client['id'] === $initialIdentity[$index]['id'] && $client['email'] === $initialIdentity[$index]['email'],
            'Client identity changed.');
        $assert($panel->addCount === 0 && $panel->deleteCount === 0, 'A client was recreated or deleted.');
        $email = (string)$client['email'];
        $assert($panel->traffic[$email] === ['up' => 123, 'down' => 456], 'Used traffic was reset.');
        $assert(!$panel->transactionViolation, 'HTTP ran inside a DB transaction.');
    }
    $storedToken = (string)db()->query('SELECT subscription_token FROM vpn_v2_subscriptions WHERE id = ?', [$subscriptionId])->getColumn();
    $assert(hash_equals($token, $storedToken), 'Subscription token changed.');
    $results['identity_preserved'] = true;
    $results['no_recreation'] = true;
    $results['traffic_preserved'] = true;

    echo json_encode(['status' => 'ok', 'results' => $results, 'fixtures_cleaned' => true], JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    if ($subscriptionId > 0) {
        db()->query('DELETE FROM vpn_v2_events WHERE subscription_id = ?', [$subscriptionId]);
        db()->query('DELETE FROM vpn_v2_subscriptions WHERE id = ?', [$subscriptionId]);
    }
    if ($planId > 0) {
        db()->query('DELETE FROM vpn_v2_plan_nodes WHERE plan_id = ?', [$planId]);
        db()->query('DELETE FROM vpn_v2_plans WHERE id = ?', [$planId]);
    }
    foreach ($inboundIds as $id) {
        db()->query('DELETE FROM vpn_v2_inbounds WHERE id = ?', [$id]);
    }
    foreach ($serverIds as $id) {
        db()->query('DELETE FROM vpn_v2_events WHERE server_id = ?', [$id]);
        db()->query('DELETE FROM vpn_v2_servers WHERE id = ?', [$id]);
    }
}
