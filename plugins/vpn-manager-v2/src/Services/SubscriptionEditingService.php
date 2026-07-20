<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\DTO\SyncResult;
use Fireball\VpnManagerV2\Exceptions\ProvisioningException;
use Fireball\VpnManagerV2\Exceptions\VpnManagerV2Exception;
use Fireball\VpnManagerV2\Repositories\SubscriptionRepository;
use Fireball\VpnManagerV2\Validators\SubscriptionEditValidator;

final class SubscriptionEditingService
{
    public function __construct(
        private readonly ?SubscriptionRepository $repository = null,
        private readonly ?SubscriptionEditValidator $validator = null,
        private readonly ?RemoteClientSyncService $remoteSync = null,
        private readonly ?VpnSubscriptionRevisionService $revisionService = null,
        private readonly ?VpnV2SubscriptionDependencyService $dependencies = null,
    ) {
    }

    public function update(int $subscriptionId, array $input, ?int $adminId = null): SyncResult
    {
        $repository = $this->repository ?? new SubscriptionRepository();
        $current = $repository->findForProvisioning($subscriptionId);
        if (!$current) {
            throw new ProvisioningException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_subscription_not_found'));
        }
        $edit = ($this->validator ?? new SubscriptionEditValidator())->validate($input);
        $desired = array_replace($current, $edit->toArray());
        $configChanged = $this->different($current['expires_at'] ?? null, $edit->expiresAt)
            || $this->differentLimit($current['traffic_limit_bytes'] ?? null, $edit->trafficLimitBytes)
            || (string)$current['status'] !== $edit->status;
        $commentChanged = $this->different($current['internal_comment'] ?? null, $edit->internalComment);
        $adminId = $adminId ?? $this->adminId();

        if (!$configChanged) {
            if ($commentChanged) {
                $repository->updateInternalComment($subscriptionId, $edit->internalComment);
                $repository->logEvent('subscription.comment_updated', $subscriptionId, null, null,
                    (int)$current['user_id'], $adminId, ['remote_request' => false]);
            }

            return new SyncResult($subscriptionId, 0, 0, (int)$current['revision'], $commentChanged, false);
        }

        $synced = 0;
        $failed = 0;
        $firstError = null;
        $dependencies = $this->dependencies ?? new VpnV2SubscriptionDependencyService();
        foreach ($repository->nodeIdsForSubscription($subscriptionId) as $nodeId) {
            $node = $repository->connectionForProvisioning($nodeId);
            if (!$node) {
                $failed++;
                continue;
            }
            if (in_array((string)$node['status'], ['creating', 'create_failed', 'deleted', 'deleting'], true)) {
                continue;
            }
            if ($edit->status !== 'active' && $dependencies->countActiveConsumers($nodeId, $subscriptionId) > 0) {
                $repository->logEvent('node.disable_skipped_shared_consumer', $subscriptionId, $nodeId,
                    (int)$node['server_id'], (int)$current['user_id'], $adminId,
                    ['source' => 'subscription_update']);
                continue;
            }
            try {
                $result = ($this->remoteSync ?? new RemoteClientSyncService())->push(
                    $node,
                    $desired,
                    [
                        'traffic_limit_bytes' => $edit->trafficLimitBytes,
                        'desired_enabled' => $edit->status === 'active',
                    ]
                );
                $repository->updateNodeConfirmed(
                    $nodeId,
                    $this->flow($node['flow'] ?? null),
                    $edit->trafficLimitBytes,
                    $result['traffic_used_bytes'],
                    $edit->status === 'active' ? 'active' : 'disabled',
                    $edit->status === 'active'
                );
                $synced++;
                $repository->logEvent('node.subscription_update_confirmed', $subscriptionId, $nodeId,
                    (int)$node['server_id'], (int)$current['user_id'], $adminId,
                    ['changed_fields' => $result['changed_fields']]);
            } catch (\Throwable $exception) {
                $failed++;
                $safeError = $this->safeError($exception);
                $firstError ??= $safeError;
                $repository->markNodeFailure($nodeId, 'sync_error', $safeError);
                $repository->logEvent('node.subscription_update_failed', $subscriptionId, $nodeId,
                    (int)$node['server_id'], (int)$current['user_id'], $adminId,
                    ['error_type' => $this->errorType($exception)]);
            }
        }

        $repository->updateSubscriptionConfirmed($subscriptionId, $edit->toArray(), $firstError);
        $revision = ($this->revisionService ?? new VpnSubscriptionRevisionService())->touchConfig($subscriptionId);
        $repository->logEvent(
            $failed > 0 ? 'subscription.update_partial' : 'subscription.update_confirmed',
            $subscriptionId,
            null,
            null,
            (int)$current['user_id'],
            $adminId,
            ['synced' => $synced, 'failed' => $failed, 'revision' => $revision]
        );

        // Renewal/reactivation must pick up plan connections that were skipped while expired.
        if ($edit->status === 'active'
            && ($edit->expiresAt === null || strtotime($edit->expiresAt) === false || strtotime($edit->expiresAt) > time())) {
            try {
                (new VpnPlanSubscriptionReconciler())->reconcileSubscription($subscriptionId, [
                    'initiated_by' => $adminId,
                    'authorized' => true,
                ]);
            } catch (\Throwable $exception) {
                $repository->logEvent('plan_reconcile_failed', $subscriptionId, null, null,
                    (int)$current['user_id'], $adminId, ['safe_error_code' => $this->errorType($exception)]);
            }
            $fresh = $repository->findForProvisioning($subscriptionId);
            $revision = max($revision, (int)($fresh['revision'] ?? $revision));
        }

        $dependencyResult = $edit->status === 'active'
            && ($edit->expiresAt === null || strtotime($edit->expiresAt) === false || strtotime($edit->expiresAt) > time())
            ? $dependencies->cascadeEnable($subscriptionId, $adminId)
            : $dependencies->cascadeDisable(
                $subscriptionId,
                $edit->status === 'expired' ? 'parent_subscription_expired' : 'parent_subscription_suspended',
                $adminId
            );
        $synced += (int)($dependencyResult['synced'] ?? 0);
        $failed += (int)($dependencyResult['failed'] ?? 0);

        return new SyncResult($subscriptionId, $synced, $failed, $revision, true, true);
    }

    private function different(mixed $left, mixed $right): bool
    {
        $left = trim((string)$left);
        $right = trim((string)$right);

        return ($left !== '' ? $left : null) !== ($right !== '' ? $right : null);
    }

    private function differentLimit(mixed $left, ?int $right): bool
    {
        $left = $left !== null && (int)$left > 0 ? (int)$left : null;

        return $left !== $right;
    }

    private function flow(mixed $flow): ?string
    {
        $flow = trim((string)$flow);

        return $flow !== '' ? $flow : null;
    }

    private function safeError(\Throwable $exception): string
    {
        return $exception instanceof VpnManagerV2Exception
            ? mb_substr(trim($exception->getMessage()), 0, 1000)
            : \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_sync_generic');
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
