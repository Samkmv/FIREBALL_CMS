<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\Clients\ThreeXuiClient;
use Fireball\VpnManagerV2\Repositories\AutomationRepository;
use Fireball\VpnManagerV2\Repositories\ConfigurationSyncRepository;
use Fireball\VpnManagerV2\Repositories\OperationQueueRepository;
use Fireball\VpnManagerV2\Repositories\PlanReconciliationRepository;
use Fireball\VpnManagerV2\Repositories\ServerRepository;
use Fireball\VpnManagerV2\Repositories\SubscriptionRepository;
use Fireball\VpnManagerV2\Repositories\SyncAuditRepository;

final class RemoteOperationProcessor
{
    public function __construct(
        private readonly ?OperationQueueRepository $operations = null,
        private readonly ?ConfigurationSyncRepository $configuration = null,
        private readonly ?ConfigurationSyncService $sync = null,
        private readonly ?VpnPlanSubscriptionReconciler $reconciler = null,
        private readonly ?RemoteClientSyncService $remoteSync = null,
        private readonly ?RemoteClientDeletionService $remoteDeletion = null,
        private readonly ?PlanReconciliationRepository $plans = null,
        private readonly ?SubscriptionRepository $subscriptions = null,
        private readonly ?SyncAuditRepository $audit = null,
    ) {
    }

    public function processNext(?array $types = null): array
    {
        $queue = $this->operations ?? new OperationQueueRepository();
        $operation = $queue->claimNext($types);
        if (!$operation) {
            return ['idle' => true, 'processed' => 0, 'success' => 0, 'failure' => 0];
        }
        $started = microtime(true);
        try {
            $result = $this->execute($operation);
            $processed = max(1, (int)($result['processed'] ?? 1));
            $total = max($processed, (int)($result['total'] ?? $processed));
            $completionStatus = (int)($result['errors'] ?? 0) > 0 ? 'completed_partial' : 'completed';
            $queue->complete((int)$operation['id'], $processed, $total, $completionStatus);
            ($this->audit ?? new SyncAuditRepository())->log([
                'operation_id' => (string)$operation['operation_id'],
                'operation_type' => (string)$operation['operation_type'],
                'source' => (string)$operation['source'],
                'server_id' => $operation['server_id'] ?? null,
                'subscription_id' => $operation['subscription_id'] ?? null,
                'connection_id' => $operation['connection_id'] ?? null,
                'status' => $completionStatus,
                'duration_ms' => (int)round((microtime(true) - $started) * 1000),
            ]);

            return ['idle' => false, 'processed' => $processed, 'success' => 1, 'failure' => 0]
                + $result + ['operation_id' => (string)$operation['operation_id']];
        } catch (\Throwable $exception) {
            $safeError = $this->safeError($exception);
            $status = $queue->fail((int)$operation['id'], $safeError);
            ($this->audit ?? new SyncAuditRepository())->log([
                'operation_id' => (string)$operation['operation_id'],
                'operation_type' => (string)$operation['operation_type'],
                'source' => (string)$operation['source'],
                'server_id' => $operation['server_id'] ?? null,
                'subscription_id' => $operation['subscription_id'] ?? null,
                'connection_id' => $operation['connection_id'] ?? null,
                'status' => $status,
                'error_code' => $this->errorCode($exception),
                'safe_error' => $safeError,
                'duration_ms' => (int)round((microtime(true) - $started) * 1000),
            ]);

            return [
                'idle' => false,
                'processed' => 1,
                'success' => 0,
                'failure' => 1,
                'status' => $status,
                'operation_id' => (string)$operation['operation_id'],
            ];
        }
    }

    private function execute(array $operation): array
    {
        $type = (string)$operation['operation_type'];
        $serverId = (int)($operation['server_id'] ?? 0);
        $subscriptionId = (int)($operation['subscription_id'] ?? 0);
        $nodeId = (int)($operation['connection_id'] ?? 0);
        $source = (string)($operation['source'] ?? 'reconciliation');
        $operationId = (string)$operation['operation_id'];

        return match ($type) {
            'sync_server', 'sync_inbound' => ($this->sync ?? new ConfigurationSyncService())
                ->syncServer($serverId, $source, $operationId),
            'sync_client' => $this->syncClient($nodeId, $source, $operationId),
            'sync_subscription' => $this->syncSubscription($subscriptionId, $source, $operationId),
            'full_reconcile' => ($this->sync ?? new ConfigurationSyncService())->syncAllPages(1000, 100, $source),
            'create_client' => $this->provision($nodeId),
            'update_client', 'rename_client', 'enable_client', 'disable_client' => $this->push($nodeId, $source, $operationId),
            'delete_client' => $this->delete($nodeId),
            'reset_traffic' => $this->resetTraffic($nodeId),
            default => throw new \RuntimeException('The queued VPN operation is not implemented.'),
        };
    }

    private function syncClient(int $nodeId, string $source, string $operationId): array
    {
        $node = ($this->plans ?? new PlanReconciliationRepository())->node($nodeId);
        if (!$node) {
            throw new \RuntimeException('VPN connection not found.');
        }

        return ($this->sync ?? new ConfigurationSyncService())
            ->syncServer((int)$node['server_id'], $source, $operationId);
    }

    private function syncSubscription(int $subscriptionId, string $source, string $operationId): array
    {
        $serverIds = ($this->configuration ?? new ConfigurationSyncRepository())
            ->serverIdsForSubscription($subscriptionId);
        $result = ['processed' => 0, 'changed' => 0, 'errors' => 0, 'total' => count($serverIds)];
        foreach ($serverIds as $serverId) {
            try {
                $sync = ($this->sync ?? new ConfigurationSyncService())
                    ->syncServer($serverId, $source, $operationId);
                $result['changed'] += (int)($sync['changed'] ?? 0);
            } catch (\Throwable) {
                $result['errors']++;
            }
            $result['processed']++;
            ($this->operations ?? new OperationQueueRepository())->heartbeat(
                (int)($this->operationRowId($operationId) ?? 0),
                $result['processed'],
                $result['total']
            );
        }

        return $result;
    }

    private function provision(int $nodeId): array
    {
        $result = ($this->reconciler ?? new VpnPlanSubscriptionReconciler())->retryFailedNode($nodeId);

        return [
            'processed' => 1,
            'total' => 1,
            'changed' => $result->changed ? 1 : 0,
        ];
    }

    private function push(int $nodeId, string $source, string $operationId): array
    {
        $plans = $this->plans ?? new PlanReconciliationRepository();
        $node = $plans->node($nodeId);
        if (!$node) {
            throw new \RuntimeException('VPN connection not found.');
        }
        $subscription = $plans->subscription((int)$node['subscription_id']);
        if (!$subscription) {
            throw new \RuntimeException('VPN subscription not found.');
        }
        $result = ($this->remoteSync ?? new RemoteClientSyncService())->push($node, $subscription);
        ($this->subscriptions ?? new SubscriptionRepository())->updateNodeConfirmed(
            $nodeId,
            $result['flow'] ?? null,
            $result['traffic_limit_bytes'] ?? null,
            $result['traffic_used_bytes'] ?? null,
            empty($node['desired_enabled']) ? 'disabled' : 'active',
            !empty($node['desired_enabled'])
        );
        ($this->sync ?? new ConfigurationSyncService())->syncServer(
            (int)$node['server_id'],
            $source,
            $operationId
        );

        return ['processed' => 1, 'total' => 1, 'changed' => !empty($result['remote_updated']) ? 1 : 0];
    }

    private function delete(int $nodeId): array
    {
        $node = ($this->plans ?? new PlanReconciliationRepository())->node($nodeId);
        if (!$node) {
            return ['processed' => 1, 'total' => 1, 'changed' => 0];
        }
        ($this->remoteDeletion ?? new RemoteClientDeletionService())->delete($node);
        $subscriptions = $this->subscriptions ?? new SubscriptionRepository();
        $subscriptions->markNodeDeleted($nodeId);
        if ($subscriptions->allNodesDeleted((int)$node['subscription_id'])) {
            $subscription = $subscriptions->findForDeletion((int)$node['subscription_id']);
            if ($subscription) {
                (new VpnSubscriptionCache())->invalidate(
                    (string)$subscription['subscription_token'],
                    (int)$subscription['revision']
                );
                (new QrCodeService())->invalidateToken((string)$subscription['subscription_token']);
                $subscriptions->finalizeDeletion(
                    (int)$node['subscription_id'],
                    'revoked-' . bin2hex(random_bytes(32))
                );
            }
        }

        return ['processed' => 1, 'total' => 1, 'changed' => 1];
    }

    private function resetTraffic(int $nodeId): array
    {
        $node = ($this->subscriptions ?? new SubscriptionRepository())->connectionForProvisioning($nodeId);
        if (!$node) {
            throw new \RuntimeException('VPN connection not found.');
        }
        $server = (new ServerRepository())->findWithSecrets((int)$node['server_id']);
        $inbound = ($this->subscriptions ?? new SubscriptionRepository())->inbound((int)$node['inbound_id']);
        if (!$server || !$inbound) {
            throw new \RuntimeException('VPN server or inbound not found.');
        }
        $client = new ThreeXuiClient((new ServerSecretService())->clientConfig($server));
        $client->resetClientTraffic((int)$inbound['remote_inbound_id'], (string)$node['client_email']);
        $traffic = TrafficSyncService::trafficFromResponse($client->getClientTraffic((string)$node['client_email']));
        if ($traffic['total'] !== 0) {
            throw new \RuntimeException('3x-ui did not confirm the traffic reset.');
        }
        $subscriptionId = (new AutomationRepository())->recordExplicitTrafficReset($nodeId);
        (new SubscriptionRepository())->logEvent(
            'connection.traffic_reset',
            $subscriptionId,
            $nodeId,
            (int)$node['server_id'],
            (int)$node['user_id'],
            null,
            ['confirmed' => true]
        );

        return ['processed' => 1, 'total' => 1, 'changed' => 1];
    }

    private function operationRowId(string $operationId): ?int
    {
        $value = db()->query(
            'SELECT id FROM vpn_v2_operations WHERE operation_id = ? LIMIT 1',
            [$operationId]
        )->getColumn();

        return (int)$value > 0 ? (int)$value : null;
    }

    private function safeError(\Throwable $exception): string
    {
        return $exception instanceof \Fireball\VpnManagerV2\Exceptions\VpnManagerV2Exception
            ? mb_substr(trim($exception->getMessage()), 0, 1000)
            : \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_sync_generic');
    }

    private function errorCode(\Throwable $exception): string
    {
        $class = get_class($exception);
        $position = strrpos($class, '\\');

        return mb_substr($position === false ? $class : substr($class, $position + 1), 0, 120);
    }
}
