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
use Fireball\VpnManagerV2\Services\RemoteClientDeletionService;
use Fireball\VpnManagerV2\Services\SubscriptionProvisioningService;
use Fireball\VpnManagerV2\Services\VpnPlanSubscriptionReconciler;
use Fireball\VpnManagerV2\Services\VpnSubscriptionEndpointService;
use Fireball\VpnManagerV2\Support\VpnReconciliationLock;

final class Stage14FakeThreeXuiClient implements ThreeXuiClientInterface
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
        return ['id' => $remoteInboundId, 'settings' => ['clients' => array_values($this->clients[$remoteInboundId] ?? [])]];
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
$planId = 0;
$remote = new Stage14FakeThreeXuiClient();

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
) use ($now, &$subscriptionIds, $remote): array {
    $token = bin2hex(random_bytes(32));
    db()->query(
        'INSERT INTO vpn_v2_subscriptions
            (user_id, plan_id, status, starts_at, expires_at, traffic_limit_bytes,
             traffic_used_bytes, device_limit, subscription_token, revision, config_updated_at,
             created_by, internal_comment, last_error, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, 1048576, 77, 2, ?, 1, ?, ?, NULL, NULL, ?, ?)',
        [$userId, $planId, $status, $now, $expiresAt, $token, $now, $userId, $now, $now]
    );
    $subscriptionId = (int)db()->getInsertId();
    $subscriptionIds[] = $subscriptionId;
    $uuid = sprintf('00000000-0000-4000-8000-%012d', $subscriptionId);
    $email = 'stage14-a-' . $subscriptionId . '@example.invalid';
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

    return compact('subscriptionId', 'nodeId', 'uuid', 'email', 'token');
};

try {
    $serverA = $insertServer('Stage 14 A', 'stage14-a-' . $suffix, 'https://a-' . $suffix . '.example.invalid');
    $serverB = $insertServer('Stage 14 B', 'stage14-b-' . $suffix, 'https://b-' . $suffix . '.example.invalid');
    $inboundA = $insertInbound($serverA, 14001, 'Stage 14 inbound A');
    $inboundB = $insertInbound($serverB, 14002, 'Stage 14 inbound B');
    $remote->clients[14001] = [];
    $remote->clients[14002] = [];

    db()->query(
        'INSERT INTO vpn_v2_plans
            (name, description, duration_days, traffic_limit_bytes, device_limit, is_active, created_at, updated_at)
         VALUES (?, NULL, 30, 1048576, 2, 1, ?, ?)',
        ['Stage 14 ' . $suffix, $now, $now]
    );
    $planId = (int)db()->getInsertId();
    foreach ([[$serverA, $inboundA, 0], [$serverB, $inboundB, 1]] as [$serverId, $inboundId, $order]) {
        db()->query(
            'INSERT INTO vpn_v2_plan_nodes
                (plan_id, server_id, inbound_id, flow_override, is_enabled, sort_order, created_at, updated_at)
             VALUES (?, ?, ?, NULL, 1, ?, ?, ?)',
            [$planId, $serverId, $inboundId, $order, $now, $now]
        );
    }

    $main = $createSubscription('active', $future, $planId, $adminId, $serverA, $inboundA, 14001, true);
    $suspended = $createSubscription('suspended', $future, $planId, $adminId, $serverA, $inboundA, 14001, false);
    $expired = $createSubscription('expired', $past, $planId, $adminId, $serverA, $inboundA, 14001, false);
    $failed = $createSubscription('active', $future, $planId, $adminId, $serverA, $inboundA, 14001, true);

    $factory = static fn(): ThreeXuiClientInterface => $remote;
    $provisioning = new SubscriptionProvisioningService(
        clientFactory: $factory,
        notificationCallback: static function (): void {}
    );
    $reconciler = new VpnPlanSubscriptionReconciler(provisioning: $provisioning);
    $endpoint = new VpnSubscriptionEndpointService();

    db()->query(
        'UPDATE vpn_v2_subscriptions SET config_updated_at = ? WHERE id = ?',
        [date('Y-m-d H:i:s', time() - 60), $main['subscriptionId']]
    );
    $before = $endpoint->respond($main['token']);
    $oldEtag = (string)($before->headers['ETag'] ?? '');
    $oldLastModified = (string)($before->headers['Last-Modified'] ?? '');
    $result = $reconciler->reconcileSubscription($main['subscriptionId'], ['authorized' => true]);
    $mainAfter = db()->query(
        'SELECT revision, subscription_token, traffic_used_bytes FROM vpn_v2_subscriptions WHERE id = ?',
        [$main['subscriptionId']]
    )->getOne();
    $mainNodes = db()->query(
        'SELECT id, server_id, inbound_id, client_uuid, status, traffic_used_bytes
         FROM vpn_v2_subscription_nodes WHERE subscription_id = ? ORDER BY id',
        [$main['subscriptionId']]
    )->get() ?: [];
    $after = $endpoint->respond($main['token'], 'base64', $oldEtag);
    $assert($before->status === 200 && $before->configCount === 1
        && $result->created === 1 && count($mainNodes) === 2
        && $after->status === 200 && $after->configCount === 2
        && (string)($after->headers['ETag'] ?? '') !== $oldEtag
        && (string)($after->headers['Last-Modified'] ?? '') !== $oldLastModified,
        'The main plan-to-endpoint reconciliation chain failed.');
    $assert(hash_equals($main['token'], (string)$mainAfter['subscription_token'])
        && hash_equals($main['uuid'], (string)$mainNodes[0]['client_uuid'])
        && (int)$mainNodes[0]['traffic_used_bytes'] === 77,
        'An existing identity, token, or traffic counter changed.');

    $revision = (int)$mainAfter['revision'];
    $addCalls = $remote->addCalls;
    $repeat = $reconciler->reconcileSubscription($main['subscriptionId'], ['authorized' => true]);
    $revisionAfterRepeat = (int)db()->query(
        'SELECT revision FROM vpn_v2_subscriptions WHERE id = ?', [$main['subscriptionId']]
    )->getColumn();
    $assert($repeat->noChanges() && $revisionAfterRepeat === $revision && $remote->addCalls === $addCalls,
        'Repeated reconciliation is not idempotent.');

    $suspendedResult = $reconciler->reconcileSubscription($suspended['subscriptionId'], ['authorized' => true]);
    $suspendedNode = db()->query(
        'SELECT client_uuid, status, desired_enabled FROM vpn_v2_subscription_nodes
         WHERE subscription_id = ? AND server_id = ?',
        [$suspended['subscriptionId'], $serverB]
    )->getOne();
    $remoteSuspended = $remote->findClient(14002, (string)$suspendedNode['client_uuid']);
    $suspendedStatus = (string)db()->query(
        'SELECT status FROM vpn_v2_subscriptions WHERE id = ?', [$suspended['subscriptionId']]
    )->getColumn();
    $assert($suspendedResult->created === 1 && $suspendedStatus === 'suspended'
        && (string)$suspendedNode['status'] === 'disabled' && (int)$suspendedNode['desired_enabled'] === 0
        && is_array($remoteSuspended) && empty($remoteSuspended['enable']),
        'Suspended subscription provisioning was not confirmed as disabled.');

    $expiredResult = $reconciler->reconcileSubscription($expired['subscriptionId'], ['authorized' => true]);
    $expiredNodes = (int)db()->query(
        'SELECT COUNT(*) FROM vpn_v2_subscription_nodes WHERE subscription_id = ?',
        [$expired['subscriptionId']]
    )->getColumn();
    $assert($expiredResult->skipped === 1 && $expiredNodes === 1,
        'Expired subscription received a new connection.');

    $remote->failAdds = true;
    $failedResult = $reconciler->reconcileSubscription($failed['subscriptionId'], ['authorized' => true]);
    $failedNode = db()->query(
        'SELECT id, client_uuid, status FROM vpn_v2_subscription_nodes
         WHERE subscription_id = ? AND server_id = ?',
        [$failed['subscriptionId'], $serverB]
    )->getOne();
    $assert($failedResult->failed === 1 && (string)$failedNode['status'] === 'create_failed',
        'Unavailable server did not leave a recoverable local node.');
    $failedNodeId = (int)$failedNode['id'];
    $failedUuid = (string)$failedNode['client_uuid'];
    $remote->failAdds = false;
    $retryResult = $reconciler->reconcileSubscription($failed['subscriptionId'], ['authorized' => true]);
    $retriedNode = db()->query(
        'SELECT id, client_uuid, status FROM vpn_v2_subscription_nodes WHERE id = ?',
        [$failedNodeId]
    )->getOne();
    $assert($retryResult->created === 1 && (int)$retriedNode['id'] === $failedNodeId
        && hash_equals($failedUuid, (string)$retriedNode['client_uuid'])
        && (string)$retriedNode['status'] === 'active',
        'create_failed retry created a duplicate or changed UUID.');

    $newNodeId = (int)$mainNodes[1]['id'];
    $syncCandidates = array_column((new AutomationRepository())->activeNodesForTrafficSync(2000), 'id');
    $assert(in_array($newNodeId, array_map('intval', $syncCandidates), true),
        'The new confirmed node is absent from traffic synchronization.');
    $trafficRepository = new AutomationRepository();
    $trafficRepository->recordNodeTraffic($newNodeId, 500, 200, 300);
    $trafficRepository->recordNodeTraffic($newNodeId, 400, 150, 250);
    $trafficRepository->recalculateSubscriptionTraffic($main['subscriptionId']);
    $traffic = db()->query(
        'SELECT traffic_used_bytes, upload_bytes, download_bytes, traffic_sync_status
         FROM vpn_v2_subscription_nodes WHERE id = ?', [$newNodeId]
    )->getOne();
    $assert((int)$traffic['traffic_used_bytes'] === 500 && (int)$traffic['upload_bytes'] === 200
        && (int)$traffic['download_bytes'] === 300 && (string)$traffic['traffic_sync_status'] === 'synced',
        'Absolute traffic synchronization doubled or regressed counters.');

    $profileServers = (new ProfileVpnRepository())->serversForUserSubscription($main['subscriptionId'], $adminId);
    $assert(count($profileServers) === 2,
        'The confirmed server is absent from My VPN.');

    $reconciliationRepository = new PlanReconciliationRepository();
    $assert($reconciliationRepository->mismatchSubscriptionCount($planId) === 0,
        'A fully reconciled plan is still reported as mismatched.');
    db()->query('UPDATE vpn_v2_subscription_nodes SET flow = ? WHERE id = ?', ['xtls-rprx-vision', $newNodeId]);
    $assert($reconciliationRepository->mismatchSubscriptionCount($planId) === 1,
        'A flow-only discrepancy is absent from the plan mismatch counter.');
    db()->query('UPDATE vpn_v2_subscription_nodes SET flow = NULL WHERE id = ?', [$newNodeId]);

    $lockName = 'vpn_v2:plan_reconcile:' . $planId;
    $otherConnection = new FBL\Database();
    $otherConnection->query('SELECT GET_LOCK(?, 0)', [$lockName]);
    $blocked = false;
    try {
        (new VpnReconciliationLock())->plan($planId, static fn(): bool => true, 0);
    } catch (Throwable) {
        $blocked = true;
    } finally {
        $otherConnection->query('SELECT RELEASE_LOCK(?)', [$lockName]);
    }
    $releasedAfterException = false;
    try {
        (new VpnReconciliationLock())->plan($planId, static function (): void {
            throw new RuntimeException('lock release test');
        }, 0);
    } catch (RuntimeException) {
        $releasedAfterException = (int)db()->query('SELECT IS_FREE_LOCK(?)', [$lockName])->getColumn() === 1;
    }
    $assert($blocked && $releasedAfterException,
        'Parallel plan lock did not reject a second connection or release after an exception.');

    $operationRepository = new PlanReconciliationRepository();
    $operationUuid = $operationRepository->queueOperation($planId, $adminId, 2);
    $operation = db()->query(
        'SELECT id FROM vpn_v2_reconcile_operations WHERE operation_id = ?', [$operationUuid]
    )->getOne();
    $job = new VpnV2ReconcilePlanSubscriptionsJob(
        repository: $operationRepository,
        reconciler: $reconciler
    );
    $firstBatch = $job->handle($operationUuid);
    $secondBatch = $job->handle($operationUuid);
    $operationProgress = $operationRepository->operationProgress((int)$operation['id']);
    $assert((int)$firstBatch['processed'] === 2 && (int)$secondBatch['processed'] === 1
        && (string)$operationProgress['status'] === 'completed'
        && (int)$operationProgress['processed_count'] === 3
        && (int)$operationProgress['skipped_count'] === 3
        && (int)$operationProgress['failure_count'] === 0,
        'Background operation cursor and cumulative progress are inconsistent.');

    db()->query(
        'UPDATE vpn_v2_subscription_nodes SET is_obsolete = 1 WHERE id = ?',
        [$newNodeId]
    );
    $beforeRemovalRevision = (int)db()->query(
        'SELECT revision FROM vpn_v2_subscriptions WHERE id = ?', [$main['subscriptionId']]
    )->getColumn();
    $removalReconciler = new VpnPlanSubscriptionReconciler(
        provisioning: $provisioning,
        remoteDeletion: new RemoteClientDeletionService(clientFactory: $factory)
    );
    $removal = $removalReconciler->removeObsoleteNodesForSubscription(
        $main['subscriptionId'],
        $adminId,
        ['authorized' => true]
    );
    $removedNodeStatus = (string)db()->query(
        'SELECT status FROM vpn_v2_subscription_nodes WHERE id = ?', [$newNodeId]
    )->getColumn();
    $afterRemovalRevision = (int)db()->query(
        'SELECT revision FROM vpn_v2_subscriptions WHERE id = ?', [$main['subscriptionId']]
    )->getColumn();
    $afterRemoval = $endpoint->respond($main['token']);
    $assert($removal->removed === 1 && $removedNodeStatus === 'deleted'
        && $afterRemovalRevision === $beforeRemovalRevision + 1 && $afterRemoval->configCount === 1
        && $remote->findClient(14002, (string)$mainNodes[1]['client_uuid']) === null,
        'Confirmed obsolete connection removal did not update remote, local, revision, and endpoint state.');

    echo json_encode([
        'status' => 'ok',
        'cases' => [
            'existing_subscription_new_server',
            'post_create_inbound_confirmation',
            'revision_etag_old_etag_200',
            'last_modified_changed',
            'token_uuid_traffic_preserved',
            'repeated_run_idempotent',
            'suspended_disabled',
            'expired_skipped',
            'unavailable_server_recoverable',
            'failed_node_reused',
            'traffic_selection_absolute_counters',
            'my_vpn_confirmed_server',
            'plan_flow_discrepancy_count',
            'parallel_lock_and_release',
            'background_operation_progress',
            'confirmed_obsolete_removal',
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), PHP_EOL;
} finally {
    if ($subscriptionIds !== []) {
        $placeholders = implode(',', array_fill(0, count($subscriptionIds), '?'));
        db()->query("DELETE FROM vpn_v2_events WHERE subscription_id IN ({$placeholders})", $subscriptionIds);
        db()->query("DELETE FROM vpn_v2_subscriptions WHERE id IN ({$placeholders})", $subscriptionIds);
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
