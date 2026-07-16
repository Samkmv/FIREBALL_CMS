<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\DTO\SyncResult;
use Fireball\VpnManagerV2\Exceptions\ProvisioningException;
use Fireball\VpnManagerV2\Exceptions\VpnManagerV2Exception;
use Fireball\VpnManagerV2\Repositories\SubscriptionRepository;
use Fireball\VpnManagerV2\Validators\ConnectionEditValidator;

final class ConnectionEditingService
{
    public function __construct(
        private readonly ?SubscriptionRepository $repository = null,
        private readonly ?ConnectionEditValidator $validator = null,
        private readonly ?RemoteClientSyncService $remoteSync = null,
        private readonly ?VpnSubscriptionRevisionService $revisionService = null,
    ) {
    }

    public function update(int $nodeId, array $input, ?int $adminId = null): SyncResult
    {
        $repository = $this->repository ?? new SubscriptionRepository();
        $node = $this->node($nodeId, $repository);
        $edit = ($this->validator ?? new ConnectionEditValidator())->validate($input, $node);
        $localChanged = $this->flow($node['flow'] ?? null) !== $edit->flow
            || $this->limit($node['traffic_limit_bytes'] ?? null) !== $edit->trafficLimitBytes;

        return $this->pushAndSave($node, $edit->toArray(), $localChanged, 'node.update_confirmed', $adminId);
    }

    public function sendToRemote(int $nodeId, ?int $adminId = null): SyncResult
    {
        $repository = $this->repository ?? new SubscriptionRepository();
        $node = $this->node($nodeId, $repository);

        return $this->pushAndSave($node, [
            'flow' => $this->flow($node['flow'] ?? null),
            'traffic_limit_bytes' => $this->limit($node['traffic_limit_bytes'] ?? null),
        ], false, 'node.sync_pushed', $adminId);
    }

    public function receiveFromRemote(int $nodeId, ?int $adminId = null): SyncResult
    {
        $repository = $this->repository ?? new SubscriptionRepository();
        $node = $this->node($nodeId, $repository);
        $subscriptionId = (int)$node['subscription_id'];
        $adminId = $adminId ?? $this->adminId();
        try {
            $remote = ($this->remoteSync ?? new RemoteClientSyncService())->pull($node);
            $changed = $this->flow($node['flow'] ?? null) !== $remote['flow']
                || $this->limit($node['traffic_limit_bytes'] ?? null) !== $remote['traffic_limit_bytes']
                || (string)$node['status'] !== 'active';
            $repository->updateNodeConfirmed(
                $nodeId,
                $remote['flow'],
                $remote['traffic_limit_bytes'],
                $remote['traffic_used_bytes']
            );
            $revision = $changed
                ? ($this->revisionService ?? new VpnSubscriptionRevisionService())->touchConfig($subscriptionId)
                : (int)$this->subscriptionRevision($subscriptionId);
            $repository->logEvent('node.sync_pulled', $subscriptionId, $nodeId, (int)$node['server_id'],
                (int)$node['user_id'], $adminId, ['revision' => $revision, 'config_changed' => $changed]);

            return new SyncResult($subscriptionId, 1, 0, $revision, $changed, true);
        } catch (\Throwable $exception) {
            $this->fail($repository, $node, $exception, $adminId);
        }
    }

    private function pushAndSave(
        array $node,
        array $desiredNode,
        bool $localChanged,
        string $eventType,
        ?int $adminId
    ): SyncResult {
        $repository = $this->repository ?? new SubscriptionRepository();
        $subscriptionId = (int)$node['subscription_id'];
        $adminId = $adminId ?? $this->adminId();
        try {
            $result = ($this->remoteSync ?? new RemoteClientSyncService())->push(
                $node,
                $this->subscriptionState($node),
                $desiredNode
            );
            $repository->updateNodeConfirmed(
                (int)$node['id'],
                $desiredNode['flow'],
                $desiredNode['traffic_limit_bytes'],
                $result['traffic_used_bytes']
            );
            $changed = $localChanged || $result['remote_updated'] || (string)$node['status'] !== 'active';
            $revision = $changed
                ? ($this->revisionService ?? new VpnSubscriptionRevisionService())->touchConfig($subscriptionId)
                : (int)$this->subscriptionRevision($subscriptionId);
            $repository->logEvent($eventType, $subscriptionId, (int)$node['id'], (int)$node['server_id'],
                (int)$node['user_id'], $adminId,
                ['changed_fields' => $result['changed_fields'], 'revision' => $revision]);

            return new SyncResult($subscriptionId, 1, 0, $revision, $changed, true);
        } catch (\Throwable $exception) {
            $this->fail($repository, $node, $exception, $adminId);
        }
    }

    private function fail(SubscriptionRepository $repository, array $node, \Throwable $exception, int $adminId): never
    {
        $safeError = $exception instanceof VpnManagerV2Exception
            ? mb_substr(trim($exception->getMessage()), 0, 1000)
            : \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_sync_generic');
        $repository->markNodeFailure((int)$node['id'], 'sync_error', $safeError);
        $repository->logEvent('node.sync_failed', (int)$node['subscription_id'], (int)$node['id'],
            (int)$node['server_id'], (int)$node['user_id'], $adminId,
            ['error_type' => $this->errorType($exception)]);
        if ($exception instanceof VpnManagerV2Exception) {
            throw $exception;
        }

        throw new ProvisioningException($safeError);
    }

    private function node(int $id, SubscriptionRepository $repository): array
    {
        $node = $repository->connectionForProvisioning($id);
        if (!$node) {
            throw new ProvisioningException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_connection_not_found'));
        }

        return $node;
    }

    private function subscriptionState(array $node): array
    {
        return [
            'expires_at' => $node['expires_at'] ?? null,
            'status' => $node['subscription_status'] ?? 'active',
            'device_limit' => $node['device_limit'] ?? 0,
            'traffic_limit_bytes' => $node['subscription_traffic_limit_bytes'] ?? null,
        ];
    }

    private function subscriptionRevision(int $subscriptionId): int
    {
        $row = ($this->repository ?? new SubscriptionRepository())->findForProvisioning($subscriptionId);

        return max(1, (int)($row['revision'] ?? 1));
    }

    private function flow(mixed $value): ?string
    {
        $value = trim((string)$value);

        return $value !== '' ? $value : null;
    }

    private function limit(mixed $value): ?int
    {
        return $value !== null && (int)$value > 0 ? (int)$value : null;
    }

    private function errorType(\Throwable $exception): string
    {
        $class = get_class($exception);
        $position = strrpos($class, '\\');

        return mb_substr($position === false ? $class : substr($class, $position + 1), 0, 120);
    }

    private function adminId(): int
    {
        $user = get_user();

        return is_array($user) ? (int)($user['id'] ?? 0) : 0;
    }
}
