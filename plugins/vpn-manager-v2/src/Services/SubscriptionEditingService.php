<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\DTO\SyncResult;
use Fireball\VpnManagerV2\DTO\SubscriptionEditData;
use Fireball\VpnManagerV2\Exceptions\ProvisioningException;
use Fireball\VpnManagerV2\Exceptions\VpnManagerV2Exception;
use Fireball\VpnManagerV2\Exceptions\ValidationException;
use Fireball\VpnManagerV2\Repositories\SubscriptionRepository;
use Fireball\VpnManagerV2\Validators\SubscriptionEditValidator;
use Fireball\VpnManagerV2\Support\TrafficFormatter;
use Fireball\VpnManagerV2\Support\ProvisioningStatus;

final class SubscriptionEditingService
{
    public function __construct(
        private readonly ?SubscriptionRepository $repository = null,
        private readonly ?SubscriptionEditValidator $validator = null,
        private readonly ?RemoteClientSyncService $remoteSync = null,
        private readonly ?VpnSubscriptionRevisionService $revisionService = null,
        private readonly ?VpnV2SubscriptionDependencyService $dependencies = null,
        private readonly ?SubscriptionRenewalPolicy $renewalPolicy = null,
        private readonly ?VpnPlanSubscriptionReconciler $planReconciler = null,
    ) {
    }

    public function update(int $subscriptionId, array $input, ?int $adminId = null): SyncResult
    {
        $repository = $this->repository ?? new SubscriptionRepository();
        $current = $repository->findForProvisioning($subscriptionId);
        if (!$current) {
            throw new ProvisioningException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_subscription_not_found'));
        }
        if (ProvisioningStatus::deletionStarted((string)$current['status'])) {
            throw new ProvisioningException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_subscription_deletion_started'));
        }
        [$input, $planChanges] = $this->preparePlanAndTerm($repository, $current, $input);
        $validatedEdit = ($this->validator ?? new SubscriptionEditValidator())->validate($input);
        if ($planChanges !== []) {
            $validatedEdit = new SubscriptionEditData($validatedEdit->expiresAt,
                $planChanges['traffic_limit_bytes'], $validatedEdit->status, $validatedEdit->internalComment);
        }
        $edit = ($this->renewalPolicy ?? new SubscriptionRenewalPolicy())->normalize($current, $validatedEdit);
        $autoReactivated = $validatedEdit->status !== $edit->status;
        $desired = array_replace($current, $edit->toArray(), $planChanges);
        $planChanged = $planChanges !== [];
        $configChanged = $planChanged || $this->different($current['expires_at'] ?? null, $edit->expiresAt)
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
                    $edit->status === 'active',
                    $desired
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

        $repository->updateSubscriptionConfirmed($subscriptionId, array_replace($edit->toArray(), $planChanges), $firstError);
        $revision = ($this->revisionService ?? new VpnSubscriptionRevisionService())->touchConfig($subscriptionId);
        // Renewal/reactivation must pick up plan connections that were skipped while expired.
        if ($edit->status === 'active'
            && ($edit->expiresAt === null || strtotime($edit->expiresAt) === false || strtotime($edit->expiresAt) > time())) {
            try {
                $reconcileResult = ($this->planReconciler ?? new VpnPlanSubscriptionReconciler())->reconcileSubscription($subscriptionId, [
                    'initiated_by' => $adminId,
                    'authorized' => true,
                    'sync_parameters' => $planChanged,
                ]);
                $synced += $reconcileResult->created + $reconcileResult->reused;
                $failed += $reconcileResult->failed + $reconcileResult->syncErrors;
            } catch (\Throwable $exception) {
                $failed++;
                $firstError ??= $this->safeError($exception);
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

        if ($edit->status === 'active' && $failed > 0) {
            $repository->markSubscriptionPartialSync($subscriptionId, $firstError);
        }

        $repository->logEvent(
            $failed > 0 ? 'subscription.update_partial' : 'subscription.update_confirmed',
            $subscriptionId,
            null,
            null,
            (int)$current['user_id'],
            $adminId,
            [
                'synced' => $synced,
                'failed' => $failed,
                'revision' => $revision,
                'auto_reactivated' => $autoReactivated,
                'previous_plan_id' => (int)$current['plan_id'],
                'plan_id' => (int)$desired['plan_id'],
            ]
        );

        return new SyncResult($subscriptionId, $synced, $failed, $revision, true, true);
    }

    private function preparePlanAndTerm(SubscriptionRepository $repository, array $current, array $input): array
    {
        $planId = (int)$current['plan_id'];
        if (array_key_exists('plan_id', $input)) {
            $selectedId = filter_var($input['plan_id'], FILTER_VALIDATE_INT);
            if ($selectedId === false || $selectedId <= 0) {
                throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_subscription_plan_required'));
            }
            $planId = (int)$selectedId;
        }
        $changed = $planId !== (int)$current['plan_id'];
        $mode = (string)($input['expiry_mode'] ?? '');
        if (!in_array($mode, ['', 'preserve', 'plan', 'manual', 'lifetime'], true)) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_subscription_edit_expiry'));
        }
        $plan = ($changed || $mode === 'plan') ? $repository->activePlan($planId) : null;
        if (($changed || $mode === 'plan') && !$plan) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_subscription_plan_inactive'));
        }
        if ($changed && $repository->activePlanNodes($planId) === []) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_subscription_plan_nodes_empty'));
        }
        $planChanges = [];
        if ($changed) {
            $planChanges = ['plan_id' => $planId, 'device_limit' => (int)$plan['device_limit'],
                'ip_limit' => (int)($plan['ip_limit'] ?? 0),
                'traffic_limit_bytes' => $plan['traffic_limit_bytes'] !== null ? (int)$plan['traffic_limit_bytes'] : null];
            $traffic = TrafficFormatter::inputParts($plan['traffic_limit_bytes'] !== null ? (int)$plan['traffic_limit_bytes'] : null);
            $input['traffic_limit_value'] = $traffic['value'];
            $input['traffic_unit'] = $traffic['unit'];
        }
        if ($mode === 'preserve') {
            $input['expires_at'] = $current['expires_at'] ?? null;
            $input['lifetime'] = empty($current['expires_at']) ? '1' : '0';
        } elseif ($mode === 'lifetime') {
            $input['lifetime'] = '1';
        } elseif ($mode === 'plan') {
            $base = new \DateTimeImmutable(date('Y-m-d H:i:00'));
            $startsAt = new \DateTimeImmutable((string)$current['starts_at']);
            if ($startsAt > $base) { $base = $startsAt; }
            $input['expires_at'] = $base->add(new \DateInterval('P' . max(1, (int)$plan['duration_days']) . 'D'))
                ->format('Y-m-d H:i:s');
            $input['lifetime'] = '0';
        } elseif ($mode === 'manual') {
            if (trim((string)($input['expires_at'] ?? '')) === '') {
                throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_subscription_edit_expiry'));
            }
            $input['lifetime'] = '0';
        }
        return [$input, $planChanges];
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
