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
use Fireball\VpnManagerV2\Jobs\VpnV2ReconcilePlanSubscriptionsJob;
use Fireball\VpnManagerV2\Repositories\AutomationRepository;
use Fireball\VpnManagerV2\Repositories\PlanReconciliationRepository;
use Fireball\VpnManagerV2\Repositories\ProfileVpnRepository;
use Fireball\VpnManagerV2\Repositories\VpnProfileRepository;
use Fireball\VpnManagerV2\Services\RemoteClientDeletionService;
use Fireball\VpnManagerV2\Services\RemoteClientSyncService;
use Fireball\VpnManagerV2\Services\SubscriptionProvisioningService;
use Fireball\VpnManagerV2\Services\SubscriptionHwidGatewayService;
use Fireball\VpnManagerV2\Services\VpnPlanSubscriptionReconciler;
use Fireball\VpnManagerV2\Services\VpnSubscriptionEndpointService;
use Fireball\VpnManagerV2\Support\VpnReconciliationLock;

final class RecoveryFakeThreeXuiClient implements ThreeXuiClientInterface
{
    /** @var array<int, array<int, array<string, mixed>>> */
    public array $clients = [];
    public int $addCalls = 0;
    public bool $failAdds = false;

    public function authenticate(): void {}

    public function testConnection(): ConnectionTestResult
    {
        return new ConnectionTestResult(true, 'ok', count($this->clients), 'online');
    }

    public function listInbounds(): array
    {
        return array_map(fn(int $id): array => $this->getInbound($id), array_keys($this->clients));
    }

    public function getInbound(int $remoteInboundId): array
    {
        return ['id' => $remoteInboundId, 'enable' => true, 'remark' => 'Recovery inbound', 'protocol' => 'vless', 'port' => 443, 'settings' => ['clients' => array_values($this->clients[$remoteInboundId] ?? [])], 'streamSettings' => ['network' => 'ws', 'security' => 'none', 'wsSettings' => ['path' => '/vpn']]];
    }

    public function getClientTraffic(string $clientIdentifier): array
    {
        return ['obj' => ['email' => $clientIdentifier, 'up' => 200, 'down' => 300]];
    }

    public function findClient(int $remoteInboundId, string $clientId = '', string $clientEmail = ''): ?array
    {
        foreach ($this->clients[$remoteInboundId] ?? [] as $client) {
            if (($clientId !== '' && hash_equals((string)($client['id'] ?? ''), $clientId))
                || ($clientEmail !== '' && hash_equals((string)($client['email'] ?? ''), $clientEmail))) {
                return $client;
            }
        }

        return null;
    }

    public function addClient(int $remoteInboundId, array $client): array
    {
        if ($this->failAdds) {
            throw new RuntimeException('simulated unavailable server');
        }
        $this->addCalls++;
        $key = (string)($client['id'] ?? $client['password'] ?? '');
        $this->clients[$remoteInboundId][$key] = $client;

        return ['success' => true];
    }

    public function updateClient(int $remoteInboundId, string $clientId, array $client): array
    {
        $this->clients[$remoteInboundId][$clientId] = $client;

        return ['success' => true];
    }

    public function deleteClient(int $remoteInboundId, string $clientId, ?string $clientEmail = null): array
    {
        unset($this->clients[$remoteInboundId][$clientId]);

        return ['success' => true];
    }

    public function resetClientTraffic(int $remoteInboundId, string $clientEmail): array
    {
        return ['success' => true];
    }

    public function probeSubscriptionHwid(string $subId, array $deviceHeaders): int
    {
        return 200;
    }
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$admin = db()->query("SELECT id FROM users WHERE role IN ('creator', 'admin') ORDER BY id LIMIT 1")->getOne();
$assert(is_array($admin), 'An administrator fixture is required.');
$adminId = (int)$admin['id'];
$suffix = bin2hex(random_bytes(4));
$now = date('Y-m-d H:i:s');
$future = date('Y-m-d H:i:s', time() + 86400 * 30);
$past = date('Y-m-d H:i:s', time() - 3600);
$serverIds = [];
$inboundIds = [];
$subscriptionIds = [];
$profileIds = [];
$planId = 0;
$remote = new RecoveryFakeThreeXuiClient();

$insertServer = static function (string $name, string $code, string $host) use ($now, &$serverIds): int {
    db()->query(
        'INSERT INTO vpn_v2_servers
            (name, code, panel_url, panel_path, auth_type, encrypted_username, encrypted_password,
             encrypted_token, country_code, country_name, city, show_flag, status, is_enabled,
             created_at, updated_at)
         VALUES (?, ?, ?, NULL, \'password\', NULL, NULL, NULL, \'DE\', \'Germany\', \'Test\', 1,
                 \'online\', 1, ?, ?)',
        [$name, $code, $host, $now, $now]
    );
    $id = (int)db()->getInsertId();
    $serverIds[] = $id;

    return $id;
};
$insertInbound = static function (int $serverId, int $remoteId, string $name) use ($now, &$inboundIds): int {
    $stream = json_encode([
        'network' => 'ws',
        'security' => 'none',
        'wsSettings' => ['path' => '/vpn'],
    ], JSON_UNESCAPED_SLASHES);
    db()->query(
        'INSERT INTO vpn_v2_inbounds
            (server_id, remote_inbound_id, name, remark, protocol, port, network, security,
             default_flow, settings_json, stream_settings_json, status, is_enabled, synced_at,
             created_at, updated_at)
         VALUES (?, ?, ?, ?, \'vless\', 443, \'ws\', \'none\', NULL, \'{"clients":[]}\', ?,
                 \'active\', 1, ?, ?, ?)',
        [$serverId, (string)$remoteId, $name, $name, $stream, $now, $now, $now]
    );
    $id = (int)db()->getInsertId();
    $inboundIds[] = $id;

    return $id;
};
$createSubscription = static function (
    string $status,
    string $expiresAt,
    int $planId,
    int $userId,
    int $serverId,
    int $inboundId,
    int $remoteInboundId,
    bool $enabled
) use ($now, $adminId, &$subscriptionIds, &$profileIds, $remote): array {
    $token = bin2hex(random_bytes(32));
    $profile = $userId > 0
        ? (new VpnProfileRepository())->getOrCreate($userId)
        : (new VpnProfileRepository())->createManual();
    $profileIds[] = (int)$profile['id'];
    db()->query(
        'INSERT INTO vpn_v2_subscriptions
            (user_id, manual_customer_name, profile_id, plan_id, status, starts_at, expires_at, traffic_limit_bytes,
             traffic_used_bytes, device_limit, subscription_token, subscription_token_hash, revision, config_updated_at,
             created_by, internal_comment, last_error, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, 1048576, 77, 2, ?, ?, 1, ?, ?, NULL, NULL, ?, ?)',
        [
            $userId > 0 ? $userId : null,
            $userId > 0 ? null : 'Recovery manual ' . count($subscriptionIds),
            (int)$profile['id'], $planId, $status, $now, $expiresAt,
            $token, hash('sha256', $token), $now, $adminId, $now, $now,
        ]
    );
    $subscriptionId = (int)db()->getInsertId();
    $subscriptionIds[] = $subscriptionId;
    $uuid = sprintf('00000000-0000-4000-8000-%012d', $subscriptionId);
    $email = 'recovery-a-' . $subscriptionId . '@example.invalid';
    $subId = bin2hex(random_bytes(8));
    db()->query(
        'INSERT INTO vpn_v2_subscription_nodes
            (subscription_id, server_id, inbound_id, remote_client_id, client_uuid, client_email,
             client_sub_id, protocol, network, security, flow, status, desired_enabled, is_obsolete,
             expires_at, device_limit, traffic_limit_bytes, traffic_used_bytes, upload_bytes,
             download_bytes, traffic_synced_at, traffic_sync_status, last_sync_at, last_error,
             created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, \'vless\', \'ws\', \'none\', NULL, ?, ?, 0,
                 ?, 2, 1048576, 77, 30, 47, ?, \'synced\', ?, NULL, ?, ?)',
        [
            $subscriptionId, $serverId, $inboundId, $uuid, $uuid, $email, $subId,
            $enabled ? 'active' : 'disabled', $enabled ? 1 : 0, $expiresAt,
            $now, $now, $now, $now,
        ]
    );
    $nodeId = (int)db()->getInsertId();
    $remote->clients[$remoteInboundId][$uuid] = [
        'id' => $uuid,
        'email' => $email,
        'subId' => $subId,
        'flow' => '',
        'enable' => $enabled,
        'expiryTime' => strtotime($expiresAt) * 1000,
        'totalGB' => 1048576,
        'limitIp' => 2,
    ];

    return compact('subscriptionId', 'nodeId', 'uuid', 'email', 'token', 'subId');
};

try {
    $serverId = $insertServer('Recovery test', 'recovery-' . $suffix, 'https://recovery.example.invalid');
    $oldInbound = $insertInbound($serverId, 90001, 'Old panel');
    $newInbound = $insertInbound($serverId, 90002, 'Replacement panel');
    db()->query('INSERT INTO vpn_v2_plans (name, duration_days, traffic_limit_bytes, device_limit, is_active, created_at, updated_at) VALUES (?, 30, 1048576, 2, 1, ?, ?)', ['Recovery ' . $suffix, $now, $now]);
    $planId = (int)db()->getInsertId();
    db()->query('INSERT INTO vpn_v2_plan_nodes (plan_id, server_id, inbound_id, is_enabled, sort_order, created_at, updated_at) VALUES (?, ?, ?, 1, 0, ?, ?)', [$planId, $serverId, $oldInbound, $now, $now]);
    $first = $createSubscription('active', $future, $planId, 0, $serverId, $oldInbound, 90001, true);
    $second = $createSubscription('active', $future, $planId, 0, $serverId, $oldInbound, 90001, true);
    $suspended = $createSubscription('suspended', $future, $planId, 0, $serverId, $oldInbound, 90001, false);
    $expired = $createSubscription('expired', $past, $planId, 0, $serverId, $oldInbound, 90001, false);
    $config = new \Fireball\VpnManagerV2\Repositories\ConfigurationSyncRepository();
    $plans = new PlanReconciliationRepository();
    $queue = new \Fireball\VpnManagerV2\Repositories\OperationQueueRepository();
    $recovery = new \Fireball\VpnManagerV2\Repositories\ServerRecoveryRepository();
    $assert(count($config->nodesForServer($serverId)) === 4, 'Inventory must include manual customers without CMS users');
    $assert(count($recovery->subscriptions($serverId)) === 3, 'Expired subscriptions must be excluded');
    db()->query("UPDATE vpn_v2_subscription_nodes SET sync_status = 'synced' WHERE server_id = ?", [$serverId]);
    $config->archiveRemoteDeletedNode($plans->node($first['nodeId']));
    db()->query("UPDATE vpn_v2_inbounds SET status = 'sync_missing' WHERE id = ?", [$oldInbound]);
    $recovery->remap($serverId, [$oldInbound => $newInbound]);
    $assert(db()->query('SELECT sync_status FROM vpn_v2_subscription_nodes WHERE id = ?', [$first['nodeId']])->getOne()['sync_status'] === 'remote_deleted', 'Mapping must retain the archive reason for explicit restoration');
    $assert((int)$plans->node($second['nodeId'])['inbound_id'] === $newInbound, 'Mapping updates existing node, not its identity');
    $remote->clients = [90002 => []];
    $factory = static fn(): ThreeXuiClientInterface => $remote;
    $provisioning = new SubscriptionProvisioningService(clientFactory: $factory, notificationCallback: static function (): void {});
    $remoteSync = new RemoteClientSyncService(clientFactory: $factory);
    $reconciler = new VpnPlanSubscriptionReconciler(provisioning: $provisioning, remoteSync: $remoteSync);
    $sync = new \Fireball\VpnManagerV2\Services\ConfigurationSyncService(clientFactory: $factory);
    $processor = new \Fireball\VpnManagerV2\Services\RemoteOperationProcessor(sync: $sync, reconciler: $reconciler, remoteSync: $remoteSync);
    $repairPayload = ['repair_server_id' => $serverId, 'repair_server_signature' => \Fireball\VpnManagerV2\Support\ServerConfigurationSignature::hash((new \Fireball\VpnManagerV2\Repositories\ServerRepository())->findWithSecrets($serverId))];
    $operation = $queue->enqueue('sync_subscription', 'manual_sync', $serverId, $first['subscriptionId'], null, $repairPayload, $adminId);
    $assert($recovery->operation($serverId, $operation['operation_id']) !== null && $recovery->operation($serverId + 1, $operation['operation_id']) === null, 'Processing must be scoped to the selected server');
    $remote->failAdds = true;
    $processor->processOperation($operation['operation_id']);
    $assert($queue->progress($operation['operation_id'])['status'] !== 'completed', 'Failed creation must never show success');
    $assert($plans->node($second['nodeId'])['status'] !== 'deleted', 'Repair inventory must not archive subscriptions awaiting recovery');
    $remote->failAdds = false;
    $queue->retryOperation($operation['operation_id']);
    $processor->processOperation($operation['operation_id']);
    $assert($queue->progress($operation['operation_id'])['status'] === 'completed', 'Retry restores an archived client on the replacement panel');
    $next = $queue->enqueue('sync_subscription', 'manual_sync', $serverId, $second['subscriptionId'], null, $repairPayload, $adminId);
    $processor->processOperation($next['operation_id']);
    $assert($queue->progress($next['operation_id'])['status'] === 'completed', 'Other manual subscriptions also recover');
    foreach ([$first, $second] as $fixture) {
        $node = $plans->node($fixture['nodeId']);
        $assert($node['status'] === 'active' && (int)$node['inbound_id'] === $newInbound, 'Recovered node remains active under the current target');
        $assert($node['client_email'] === $fixture['email'], 'Existing remote client name must remain stable after a target replacement');
        $assert($node['client_sub_id'] === $fixture['subId'], 'Panel subscription identity must not change');
        $assert($node['client_uuid'] === $fixture['uuid'], 'Client credential must not change');
        $assert(db()->query('SELECT subscription_token FROM vpn_v2_subscriptions WHERE id = ?', [$fixture['subscriptionId']])->getOne()['subscription_token'] === $fixture['token'], 'Subscription URL token must not change');
        $assert((int)$node['traffic_used_bytes'] >= 77, 'Recovery must preserve traffic accounting');
        $assert($remote->findClient(90002, $fixture['uuid']) !== null, 'Client must actually exist on replacement panel');
    }
    $suspendedOp = $queue->enqueue('sync_subscription', 'manual_sync', $serverId, $suspended['subscriptionId'], null, $repairPayload, $adminId);
    $processor->processOperation($suspendedOp['operation_id']);
    $assert($plans->node($suspended['nodeId'])['status'] === 'disabled' && !$remote->findClient(90002, $suspended['uuid'])['enable'], 'Suspended subscriptions stay disabled after restoration');
    $assert($remote->findClient(90002, $expired['uuid']) === null, 'Expired client must not be recreated');
    $adds = $remote->addCalls;
    $again = $queue->enqueue('sync_subscription', 'manual_sync', $serverId, $first['subscriptionId'], null, $repairPayload, $adminId);
    $processor->processOperation($again['operation_id']);
    $assert($remote->addCalls === $adds, 'Repeating recovery must reuse existing clients');
    unset($remote->clients[90002][$first['uuid']]);
    $config->archiveRemoteDeletedNode($plans->node($first['nodeId']));
    $ordinary = $queue->enqueue('sync_subscription', 'manual_sync', $serverId, $first['subscriptionId'], null, [], $adminId);
    $processor->processOperation($ordinary['operation_id']);
    $assert($queue->progress($ordinary['operation_id'])['status'] !== 'completed', 'An archived missing target cannot count as successful synchronization');
    try { (new \Fireball\VpnManagerV2\Services\ServerManagerService())->delete($serverId); throw new LogicException('Used server deletion unexpectedly allowed'); }
    catch (\Fireball\VpnManagerV2\Exceptions\ValidationException) {}
    $stale = $queue->enqueue('sync_subscription', 'manual_sync', $serverId, $second['subscriptionId'], null, $repairPayload, $adminId);
    db()->query('UPDATE vpn_v2_servers SET panel_url = ? WHERE id = ?', ['https://changed.example.invalid', $serverId]);
    $calls = $remote->addCalls;
    $processor->processOperation($stale['operation_id']);
    $assert($queue->progress($stale['operation_id'])['status'] !== 'completed' && $remote->addCalls === $calls, 'An old operation must not write clients after the server settings change');
    // A collision is rejected before any plan or subscription target is changed.
    db()->query('INSERT INTO vpn_v2_plan_nodes (plan_id, server_id, inbound_id, is_enabled, sort_order, created_at, updated_at) VALUES (?, ?, ?, 1, 1, ?, ?)', [$planId, $serverId, $oldInbound, $now, $now]);
    try { $recovery->remap($serverId, [$newInbound => $newInbound, $oldInbound => $newInbound]); throw new LogicException('Merged targets unexpectedly allowed'); }
    catch (\Fireball\VpnManagerV2\Exceptions\ValidationException) {}
    $assert(count($recovery->targets($serverId)) === 2, 'Invalid mapping must roll back all changes');
    echo "PASS server replacement: manual customers, changed inbound IDs, archived clients, failed creation and retry, preserved identities and counters, expiration and deletion protection\n";
} finally {
    foreach ($serverIds as $serverId) {
        db()->query('DELETE FROM vpn_v2_operations WHERE server_id = ?', [$serverId]);
        db()->query('DELETE FROM vpn_v2_sync_logs WHERE server_id = ?', [$serverId]);
        db()->query('DELETE FROM vpn_v2_sync_conflicts WHERE server_id = ?', [$serverId]);
        db()->query('DELETE FROM vpn_v2_remote_clients WHERE server_id = ?', [$serverId]);
    }
    if ($subscriptionIds !== []) {
        $placeholders = implode(',', array_fill(0, count($subscriptionIds), '?'));
        db()->query("DELETE FROM vpn_v2_events WHERE subscription_id IN ({$placeholders})", $subscriptionIds);
        db()->query("DELETE FROM vpn_v2_subscriptions WHERE id IN ({$placeholders})", $subscriptionIds);
    }
    foreach (array_unique($profileIds) as $profileId) {
        (new VpnProfileRepository())->deleteManualIfUnused((int)$profileId);
    }
    if ($planId > 0) {
        db()->query('DELETE FROM vpn_v2_reconcile_operations WHERE plan_id = ?', [$planId]);
        db()->query('DELETE FROM vpn_v2_plan_nodes WHERE plan_id = ?', [$planId]);
        db()->query('DELETE FROM vpn_v2_plans WHERE id = ?', [$planId]);
    }
    foreach (array_reverse($inboundIds) as $inboundId) {
        db()->query('DELETE FROM vpn_v2_inbounds WHERE id = ?', [$inboundId]);
    }
    foreach (array_reverse($serverIds) as $serverId) {
        db()->query('DELETE FROM vpn_v2_events WHERE server_id = ?', [$serverId]);
        db()->query('DELETE FROM vpn_v2_servers WHERE id = ?', [$serverId]);
    }
}
