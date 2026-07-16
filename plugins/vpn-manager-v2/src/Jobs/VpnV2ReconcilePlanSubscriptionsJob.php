<?php

namespace Fireball\VpnManagerV2\Jobs;

use Fireball\VpnManagerV2\Exceptions\ProvisioningException;
use Fireball\VpnManagerV2\Repositories\PlanReconciliationRepository;
use Fireball\VpnManagerV2\Repositories\SubscriptionRepository;
use Fireball\VpnManagerV2\Services\VpnPlanSubscriptionReconciler;
use Fireball\VpnManagerV2\Support\Permissions;
use Fireball\VpnManagerV2\Support\VpnReconciliationLock;

final class VpnV2ReconcilePlanSubscriptionsJob
{
    public function __construct(
        private readonly ?PlanReconciliationRepository $repository = null,
        private readonly ?SubscriptionRepository $subscriptions = null,
        private readonly ?VpnPlanSubscriptionReconciler $reconciler = null,
        private readonly ?VpnReconciliationLock $lock = null,
    ) {
    }

    public function handle(?string $operationId = null): array
    {
        $repository = $this->repository ?? new PlanReconciliationRepository();
        $subscriptions = $this->subscriptions ?? new SubscriptionRepository();
        $pending = $repository->nextOperation($operationId);
        if (!$pending) {
            return ['processed' => 0, 'success' => 0, 'failure' => 0, 'skipped' => 0, 'idle' => true];
        }
        $operation = $repository->claimOperation((int)$pending['id']);
        if (!$operation) {
            return ['processed' => 0, 'success' => 0, 'failure' => 0, 'skipped' => 1, 'idle' => false];
        }

        $planId = (int)$operation['plan_id'];
        $rawAdminId = (int)($operation['initiated_by'] ?? 0);
        $adminId = $rawAdminId > 0 ? $rawAdminId : null;
        $batchSize = max(1, min(100, (int)$operation['batch_size']));
        $options = json_decode((string)($operation['options_json'] ?? ''), true);
        $options = is_array($options) ? $options : [];
        $removeObsolete = !empty($options['remove_obsolete']);
        $initiator = $adminId !== null ? $subscriptions->findUser($adminId) : null;
        if (!Permissions::allows(Permissions::RECONCILE, $initiator)
            || (!$removeObsolete
                && (!array_key_exists('provision_missing', $options) || !empty($options['provision_missing']))
                && !Permissions::allows(Permissions::CREATE_CONNECTIONS, $initiator))
            || ($removeObsolete && !Permissions::allows(Permissions::DELETE_CONNECTIONS, $initiator))) {
            $repository->failOperation(
                (int)$operation['id'],
                \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_reconcile_generic')
            );

            return [
                'processed' => 0,
                'success' => 0,
                'failure' => 1,
                'skipped' => 0,
                'idle' => false,
                'operation_id' => (string)$operation['operation_id'],
            ];
        }
        $counts = ['processed' => 0, 'success' => 0, 'failure' => 0, 'skipped' => 0];
        $cursor = (int)$operation['last_subscription_id'];
        try {
            ($this->lock ?? new VpnReconciliationLock())->plan($planId, function () use (
                $repository,
                $subscriptions,
                $operation,
                $planId,
                $adminId,
                $batchSize,
                $options,
                $removeObsolete,
                &$counts,
                &$cursor
            ): void {
                $rows = $removeObsolete
                    ? $repository->obsoleteSubscriptions($planId, $cursor, $batchSize)
                    : $repository->eligibleSubscriptions($planId, $cursor, $batchSize);
                $reconciler = $this->reconciler ?? new VpnPlanSubscriptionReconciler();
                foreach ($rows as $subscription) {
                    $cursor = (int)$subscription['id'];
                    $counts['processed']++;
                    try {
                        $result = $removeObsolete
                            ? $reconciler->removeObsoleteNodesForSubscription(
                                $cursor,
                                $adminId,
                                ['authorized' => true]
                            )
                            : $reconciler->reconcileSubscription($cursor, [
                                'initiated_by' => $adminId,
                                'authorized' => true,
                                'provision_missing' => $options['provision_missing'] ?? true,
                                'sync_flow' => $options['sync_flow'] ?? true,
                            ]);
                        if ($result->failed > 0 || $result->syncErrors > 0) {
                            $counts['failure']++;
                        } elseif ($result->noChanges()) {
                            $counts['skipped']++;
                        } else {
                            $counts['success']++;
                        }
                    } catch (\Throwable $exception) {
                        $counts['failure']++;
                        error_log('VPN Manager V2 reconciliation item failed: ' . get_class($exception));
                    }
                }
                $finished = count($rows) < $batchSize
                    || ($removeObsolete
                        ? $repository->obsoleteSubscriptions($planId, $cursor, 1)
                        : $repository->eligibleSubscriptions($planId, $cursor, 1)) === [];
                $repository->advanceOperation((int)$operation['id'], $cursor, $counts, $finished);

                if ($finished) {
                    $progress = $repository->operationProgress((int)$operation['id']) ?? [];
                    $subscriptions->logEvent(
                        (int)($progress['failure_count'] ?? $counts['failure']) > 0
                            ? 'plan_reconcile_partial'
                            : 'plan_reconcile_completed',
                        null,
                        null,
                        null,
                        null,
                        $adminId,
                        [
                            'plan_id' => $planId,
                            'operation_id' => (string)$operation['operation_id'],
                            'processed_count' => (int)($progress['processed_count'] ?? $counts['processed']),
                            'success_count' => (int)($progress['success_count'] ?? $counts['success']),
                            'failure_count' => (int)($progress['failure_count'] ?? $counts['failure']),
                            'skipped_count' => (int)($progress['skipped_count'] ?? $counts['skipped']),
                        ]
                    );
                }
            }, 1);
        } catch (ProvisioningException) {
            $repository->releaseOperation((int)$operation['id']);
            $counts['skipped']++;
        } catch (\Throwable $exception) {
            $repository->failOperation(
                (int)$operation['id'],
                \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_reconcile_generic')
            );
            $subscriptions->logEvent('plan_reconcile_failed', null, null, null, null, $adminId, [
                'plan_id' => $planId,
                'operation_id' => (string)$operation['operation_id'],
                'safe_error_code' => $this->errorType($exception),
            ]);
            $counts['failure']++;
        }

        return array_merge($counts, ['idle' => false, 'operation_id' => (string)$operation['operation_id']]);
    }

    private function errorType(\Throwable $exception): string
    {
        $class = get_class($exception);
        $position = strrpos($class, '\\');

        return mb_substr($position === false ? $class : substr($class, $position + 1), 0, 120);
    }
}
