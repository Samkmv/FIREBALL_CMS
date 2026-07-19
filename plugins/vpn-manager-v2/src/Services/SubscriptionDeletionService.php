<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\DTO\DeletionResult;
use Fireball\VpnManagerV2\DTO\SyncResult;
use Fireball\VpnManagerV2\Exceptions\ProvisioningException;
use Fireball\VpnManagerV2\Exceptions\VpnManagerV2Exception;
use Fireball\VpnManagerV2\Repositories\SubscriptionRepository;
use Fireball\VpnManagerV2\Repositories\OperationQueueRepository;

final class SubscriptionDeletionService
{
    public function __construct(
        private readonly ?SubscriptionRepository $repository = null,
        private readonly ?RemoteClientDeletionService $remoteDeletion = null,
        private readonly ?RemoteClientSyncService $remoteSync = null,
        private readonly ?VpnSubscriptionRevisionService $revisionService = null,
        private readonly ?VpnSubscriptionCache $subscriptionCache = null,
        private readonly ?QrCodeService $qrCode = null,
        private readonly ?OperationQueueRepository $operations = null,
    ) {
    }

    public function suspend(int $subscriptionId, ?int $adminId = null): SyncResult
    {
        $repository = $this->repository ?? new SubscriptionRepository();
        $subscription = $repository->findForDeletion($subscriptionId);
        if (!$subscription) {
            throw new ProvisioningException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_subscription_not_found'));
        }
        $adminId = $adminId ?? $this->adminId();
        $desired = array_replace($subscription, ['status' => 'suspended']);
        $synced = 0;
        $failed = 0;
        $firstError = null;
        $configChanged = (string)$subscription['status'] !== 'suspended';
        foreach ($repository->nodeIdsForSubscription($subscriptionId) as $nodeId) {
            $node = $repository->connectionForProvisioning($nodeId);
            if (!$node || (string)$node['status'] === 'deleted') {
                continue;
            }
            try {
                $result = ($this->remoteSync ?? new RemoteClientSyncService())->push($node, $desired, [
                    'flow' => $this->flow($node['flow'] ?? null),
                    'traffic_limit_bytes' => $this->limit($node['traffic_limit_bytes'] ?? null),
                    'desired_enabled' => false,
                ]);
                $repository->updateNodeConfirmed(
                    $nodeId,
                    $this->flow($node['flow'] ?? null),
                    $this->limit($node['traffic_limit_bytes'] ?? null),
                    $result['traffic_used_bytes'],
                    'disabled',
                    false
                );
                $synced++;
                $configChanged = $configChanged || $result['remote_updated'] || (string)$node['status'] !== 'active';
                $repository->logEvent('node.suspend_confirmed', $subscriptionId, $nodeId,
                    (int)$node['server_id'], (int)$subscription['user_id'], $adminId,
                    ['changed_fields' => $result['changed_fields']]);
            } catch (\Throwable $exception) {
                $failed++;
                $configChanged = true;
                $safeError = $this->safeError($exception);
                $firstError ??= $safeError;
                $repository->markNodeFailure($nodeId, 'sync_error', $safeError);
                $repository->logEvent('node.suspend_failed', $subscriptionId, $nodeId,
                    (int)$node['server_id'], (int)$subscription['user_id'], $adminId,
                    ['error_type' => $this->errorType($exception)]);
            }
        }

        $repository->updateSubscriptionConfirmed($subscriptionId, [
            'expires_at' => $subscription['expires_at'],
            'traffic_limit_bytes' => $subscription['traffic_limit_bytes'],
            'status' => 'suspended',
            'internal_comment' => $subscription['internal_comment'],
        ], $firstError);
        $revision = $configChanged
            ? ($this->revisionService ?? new VpnSubscriptionRevisionService())->touchConfig($subscriptionId)
            : (int)$subscription['revision'];
        $repository->logEvent($failed > 0 ? 'subscription.suspend_partial' : 'subscription.suspended',
            $subscriptionId, null, null, (int)$subscription['user_id'], $adminId,
            ['synced' => $synced, 'failed' => $failed, 'revision' => $revision]);

        return new SyncResult($subscriptionId, $synced, $failed, $revision, $configChanged, true);
    }

    public function deleteForever(int $subscriptionId, ?int $adminId = null): DeletionResult
    {
        $repository = $this->repository ?? new SubscriptionRepository();
        $subscription = $repository->findForDeletion($subscriptionId);
        if (!$subscription) {
            return new DeletionResult($subscriptionId, 0, 0, 0, true, true);
        }
        $adminId = $adminId ?? $this->adminId();
        if (!$repository->markDeleting($subscriptionId)) {
            return new DeletionResult($subscriptionId, 0, 0, 0, true, true);
        }
        $repository->logEvent('subscription.deleting', $subscriptionId, null, null,
            (int)$subscription['user_id'], $adminId,
            ['node_count' => count($repository->nodeIdsForSubscription($subscriptionId))]);

        $deleted = 0;
        $alreadyAbsent = 0;
        $failed = 0;
        $firstError = null;
        foreach ($repository->nodeIdsForSubscription($subscriptionId) as $nodeId) {
            $node = $repository->connectionForProvisioning($nodeId);
            if (!$node) {
                $failed++;
                $firstError ??= \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_connection_not_found');
                continue;
            }
            if ((string)$node['status'] === 'deleted') {
                $alreadyAbsent++;
                continue;
            }
            try {
                $result = ($this->remoteDeletion ?? new RemoteClientDeletionService())->delete($node);
                $repository->markNodeDeleted($nodeId);
                if ($result['already_absent']) {
                    $alreadyAbsent++;
                } else {
                    $deleted++;
                }
                $repository->logEvent(
                    $result['already_absent'] ? 'node.delete_already_absent' : 'node.delete_confirmed',
                    $subscriptionId,
                    $nodeId,
                    (int)$node['server_id'],
                    (int)$subscription['user_id'],
                    $adminId,
                    ['inbound_id' => (int)$node['inbound_id']]
                );
            } catch (\Throwable $exception) {
                $failed++;
                $safeError = $this->safeError($exception);
                $firstError ??= $safeError;
                $repository->markNodePendingRemoteDelete($nodeId, $safeError);
                ($this->operations ?? new OperationQueueRepository())->enqueue(
                    'delete_client',
                    'retry',
                    (int)$node['server_id'],
                    $subscriptionId,
                    $nodeId,
                    [],
                    $adminId
                );
                $repository->logEvent('node.delete_failed', $subscriptionId, $nodeId,
                    (int)$node['server_id'], (int)$subscription['user_id'], $adminId,
                    ['error_type' => $this->errorType($exception)]);
            }
        }

        if ($failed > 0 || !$repository->allNodesDeleted($subscriptionId)) {
            $repository->markSubscriptionPendingRemoteDelete(
                $subscriptionId,
                $firstError ?? \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_delete_generic')
            );
            $repository->logEvent('subscription.delete_failed', $subscriptionId, null, null,
                (int)$subscription['user_id'], $adminId,
                ['deleted' => $deleted, 'already_absent' => $alreadyAbsent, 'failed' => max(1, $failed)]);

            return new DeletionResult($subscriptionId, $deleted, $alreadyAbsent, max(1, $failed), false);
        }

        $repository->logEvent('subscription.delete_confirmed', $subscriptionId, null, null,
            (int)$subscription['user_id'], $adminId,
            ['deleted' => $deleted, 'already_absent' => $alreadyAbsent]);
        $token = (string)$subscription['subscription_token'];
        ($this->subscriptionCache ?? new VpnSubscriptionCache())->invalidate($token, (int)$subscription['revision']);
        ($this->qrCode ?? new QrCodeService())->invalidateToken($token);
        try {
            $repository->finalizeDeletion($subscriptionId, 'revoked-' . bin2hex(random_bytes(32)));
        } catch (\Throwable $exception) {
            $safeError = $this->safeError($exception);
            $repository->markSubscriptionDeleteFailed($subscriptionId, $safeError);
            $repository->logEvent('subscription.delete_finalize_failed', $subscriptionId, null, null,
                (int)$subscription['user_id'], $adminId,
                ['error_type' => $this->errorType($exception)]);

            return new DeletionResult($subscriptionId, $deleted, $alreadyAbsent, 1, false);
        }

        return new DeletionResult($subscriptionId, $deleted, $alreadyAbsent, 0, true);
    }

    private function safeError(\Throwable $exception): string
    {
        return $exception instanceof VpnManagerV2Exception
            ? mb_substr(trim($exception->getMessage()), 0, 1000)
            : \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_delete_generic');
    }

    private function errorType(\Throwable $exception): string
    {
        $class = get_class($exception);
        $position = strrpos($class, '\\');

        return mb_substr($position === false ? $class : substr($class, $position + 1), 0, 120);
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

    private function adminId(): int
    {
        $user = get_user();

        return is_array($user) ? (int)($user['id'] ?? 0) : 0;
    }
}
