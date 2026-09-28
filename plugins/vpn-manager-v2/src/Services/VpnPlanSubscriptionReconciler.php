<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\DTO\NodeProvisionResult;
use Fireball\VpnManagerV2\DTO\ReconcilePreview;
use Fireball\VpnManagerV2\DTO\ReconcileResult;
use Fireball\VpnManagerV2\Exceptions\ProvisioningException;
use Fireball\VpnManagerV2\Exceptions\VpnManagerV2Exception;
use Fireball\VpnManagerV2\Repositories\PlanReconciliationRepository;
use Fireball\VpnManagerV2\Repositories\SubscriptionRepository;
use Fireball\VpnManagerV2\Support\Permissions;
use Fireball\VpnManagerV2\Support\VpnReconciliationLock;

final class VpnPlanSubscriptionReconciler
{
    public const SYNC_THRESHOLD = 20;

    public function __construct(
        private readonly ?PlanReconciliationRepository $repository = null,
        private readonly ?SubscriptionRepository $subscriptions = null,
        private readonly ?SubscriptionProvisioningService $provisioning = null,
        private readonly ?RemoteClientSyncService $remoteSync = null,
        private readonly ?RemoteClientDeletionService $remoteDeletion = null,
        private readonly ?VpnSubscriptionRevisionService $revisionService = null,
        private readonly ?VpnFlowResolver $flowResolver = null,
        private readonly ?VpnReconciliationLock $lock = null,
    ) {
    }

    public function previewPlanReconciliation(int $planId): ReconcilePreview
    {
        $repository = $this->repository();
        if (!$repository->plan($planId)) {
            throw new ProvisioningException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_plan_not_found'));
        }

        $checked = $missing = $obsolete = $matching = 0;
        $parameterCounts = array_fill_keys(['limitHwid', 'limitIp', 'totalGB', 'expiryTime', 'enable', 'flow', 'email', 'reset', 'resetDay'], 0);
        $details = $errors = [];
        foreach ($repository->eligibleSubscriptions($planId) as $subscription) {
            $checked++;
            $subscriptionId = (int)$subscription['id'];
            $expected = $this->expectedSubscription($subscription);
            $types = [];
            foreach (['device_limit' => 'limitHwid', 'ip_limit' => 'limitIp', 'traffic_limit_bytes' => 'totalGB'] as $key => $field) {
                if ((int)($subscription[$key] ?? 0) !== (int)($expected[$key] ?? 0)) {
                    $types[$field] = true;
                    $details[] = ['subscription_id' => $subscriptionId, 'node_id' => null,
                        'differences' => [$field => ['actual' => (int)($subscription[$key] ?? 0), 'expected' => (int)($expected[$key] ?? 0)]],
                        'scope' => 'subscription'];
                }
            }
            $subscriptionMissing = 0;
            $subscriptionObsolete = count($this->findObsoleteNodes($subscriptionId));
            $hadError = false;
            $nodes = [];
            foreach ($repository->subscriptionNodes($subscriptionId) as $node) {
                $nodes[$this->key($node)] = $node;
            }
            foreach ($repository->activePlanNodes($planId) as $planNode) {
                $node = $nodes[$this->key($planNode)] ?? null;
                if ($node === null || in_array((string)$node['status'], ['deleted', 'deleting'], true)) {
                    $subscriptionMissing++;
                    $details[] = ['subscription_id' => $subscriptionId, 'node_id' => $node['id'] ?? null, 'missing' => true];
                    continue;
                }
                try {
                    $inspection = ($this->remoteSync ?? new RemoteClientSyncService())->inspect($node, $expected, [
                        'flow' => $this->resolveFlow($planNode), 'traffic_limit_bytes' => $expected['traffic_limit_bytes'],
                    ]);
                    if ($inspection['missing']) {
                        $subscriptionMissing++;
                    }
                    foreach ($inspection['changed_fields'] as $field) { $types[$field] = true; }
                    if ($inspection['missing'] || $inspection['changed_fields'] !== []) {
                        $details[] = array_merge(['subscription_id' => $subscriptionId, 'node_id' => (int)$node['id']], $inspection);
                    }
                } catch (\Throwable $exception) {
                    $hadError = true;
                    $errors[] = ['subscription_id' => $subscriptionId, 'node_id' => (int)$node['id'], 'error' => $this->safeError($exception)];
                }
            }
            foreach ($this->findObsoleteNodes($subscriptionId) as $node) {
                $details[] = ['subscription_id' => $subscriptionId, 'node_id' => (int)$node['id'], 'obsolete' => true];
            }
            foreach (array_keys($types) as $field) { $parameterCounts[$field]++; }
            $missing += $subscriptionMissing;
            $obsolete += $subscriptionObsolete;
            if (!$hadError && $subscriptionMissing === 0 && $subscriptionObsolete === 0 && $types === []) { $matching++; }
        }

        $unavailableServers = [];
        $disabledInbounds = [];
        foreach ($repository->allPlanNodes($planId) as $planNode) {
            if (empty($planNode['server_is_enabled'])) {
                $unavailableServers[(int)$planNode['server_id']] = (string)$planNode['server_name'];
            }
            if (empty($planNode['inbound_is_enabled']) || (string)$planNode['inbound_status'] !== 'active') {
                $disabledInbounds[(int)$planNode['inbound_id']] = (string)$planNode['inbound_name'];
            }
        }
        $conflicts = $repository->duplicateDiagnostics();
        $this->events()->logEvent('plan_reconcile_previewed', null, null, null, null, $this->adminId(), [
            'plan_id' => $planId,
            'processed_count' => $checked,
            'missing_count' => $missing,
            'obsolete_count' => $obsolete,
            'matching_count' => $matching,
            'parameter_counts' => $parameterCounts,
            'failure_count' => count($errors),
            'unavailable_server_count' => count($unavailableServers),
            'disabled_inbound_count' => count($disabledInbounds),
            'conflict_count' => count($conflicts),
        ]);

        return new ReconcilePreview(
            $planId,
            $checked,
            $missing,
            $obsolete,
            $matching,
            $unavailableServers,
            $disabledInbounds,
            $conflicts,
            $parameterCounts,
            $details,
            $errors,
            date('Y-m-d H:i:s'),
        );
    }

    public function reconcilePlan(int $planId, array $options = []): ReconcileResult
    {
        $this->authorizeDangerousOperation($options);
        $repository = $this->repository();
        if (!$repository->plan($planId)) {
            throw new ProvisioningException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_plan_not_found'));
        }
        $adminId = $this->normalizeAdminId($options['initiated_by'] ?? $this->adminId());

        return ($this->lock ?? new VpnReconciliationLock())->plan($planId, function () use ($planId, $repository, $adminId, $options): ReconcileResult {
            $this->events()->logEvent('plan_reconcile_started', null, null, null, null, $adminId, [
                'plan_id' => $planId,
            ]);
            $checked = $created = $reused = $failed = $syncErrors = $skipped = $obsolete = $changed = 0;
            foreach ($repository->eligibleSubscriptions($planId) as $subscription) {
                $result = $this->reconcileSubscription((int)$subscription['id'], [
                    'initiated_by' => $adminId,
                    'authorized' => true,
                    'provision_missing' => $options['provision_missing'] ?? true,
                    'sync_flow' => $options['sync_flow'] ?? true,
                    'sync_parameters' => $options['sync_parameters'] ?? $options['sync_flow'] ?? true,
                ]);
                $checked += $result->subscriptionsChecked;
                $created += $result->created;
                $reused += $result->reused;
                $failed += $result->failed;
                $syncErrors += $result->syncErrors;
                $skipped += $result->skipped;
                $obsolete += $result->obsolete;
                $changed += $result->changedSubscriptions;
            }

            $event = ($failed + $syncErrors) > 0
                ? 'plan_reconcile_partial'
                : 'plan_reconcile_completed';
            $this->events()->logEvent($event, null, null, null, null, $adminId, [
                'plan_id' => $planId,
                'processed_count' => $checked,
                'success_count' => $created + $reused,
                'failure_count' => $failed + $syncErrors,
                'skipped_count' => $skipped,
            ]);

            return new ReconcileResult(
                $planId,
                $checked,
                $created,
                $reused,
                $failed,
                $syncErrors,
                $skipped,
                $obsolete,
                $changed,
            );
        }, 1);
    }

    public function reconcileSubscription(int $subscriptionId, array $options = []): ReconcileResult
    {
        $this->authorizeDangerousOperation($options);
        $repository = $this->repository();
        $subscription = $repository->eligibleSubscription($subscriptionId);
        if (!$subscription) {
            $existing = $repository->subscription($subscriptionId);

            return new ReconcileResult((int)($existing['plan_id'] ?? 0), 0, skipped: 1);
        }
        $planId = (int)$subscription['plan_id'];
        $originalStatus = (string)$subscription['status'];
        $adminId = $this->normalizeAdminId($options['initiated_by'] ?? $this->adminId());
        $created = $reused = $failed = $syncErrors = $skipped = $obsolete = 0;
        $configChanged = false;
        $syncParameters = (bool)($options['sync_parameters'] ?? $options['sync_flow'] ?? true);
        if ($syncParameters) {
            $plan = $repository->plan($planId);
            if (!$plan) { throw new ProvisioningException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_plan_not_found')); }
            $configChanged = $repository->applyPlanParameters($subscription, $plan);
            $subscription = $this->expectedSubscription($subscription, $plan);
        }
        $firstError = null;
        $repository->clearCurrentPlanObsolete($subscriptionId, $planId);

        foreach ($this->findObsoleteNodes($subscriptionId) as $node) {
            if ($repository->markObsolete((int)$node['id'])) {
                $obsolete++;
                $this->events()->logEvent('obsolete_subscription_node_detected', $subscriptionId,
                    (int)$node['id'], (int)$node['server_id'], (int)$subscription['user_id'], $adminId,
                    ['plan_id' => $planId, 'inbound_id' => (int)$node['inbound_id']]);
            }
        }

        $missingNodes = !array_key_exists('provision_missing', $options) || !empty($options['provision_missing'])
            ? $this->findMissingNodes($subscriptionId)
            : [];
        foreach ($missingNodes as $missing) {
            $planNode = (array)$missing['plan_node'];
            $existingNode = is_array($missing['existing_node'] ?? null) ? $missing['existing_node'] : null;
            $this->events()->logEvent('missing_subscription_node_detected', $subscriptionId,
                $existingNode !== null ? (int)$existingNode['id'] : null, (int)$planNode['server_id'],
                (int)$subscription['user_id'], $adminId, [
                    'plan_id' => $planId,
                    'plan_node_id' => (int)$planNode['plan_node_id'],
                    'inbound_id' => (int)$planNode['inbound_id'],
                    'reason' => (string)($missing['reason'] ?? 'missing'),
                ]);
            try {
                $result = $this->provisionMissingNode($subscriptionId, (int)$planNode['plan_node_id']);
            } catch (\Throwable $exception) {
                $failed++;
                $firstError ??= $this->safeError($exception);
                continue;
            }
            if ($result->created) {
                $created++;
                $configChanged = true;
            } elseif ($result->reused) {
                $reused++;
                $configChanged = true;
            } elseif ($result->status === 'sync_error') {
                $syncErrors++;
                $firstError ??= \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_client_not_confirmed');
            } elseif (!$result->successful()) {
                $failed++;
                $firstError ??= \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_provisioning_generic');
            } else {
                $skipped++;
            }
        }

        $flowChanges = $syncParameters
            ? $this->parameterTargets($subscriptionId)
            : [];
        foreach ($flowChanges as $change) {
            try {
                if ($this->syncParameters($subscription, (array)$change['node'], (array)$change['plan_node'])) {
                    $configChanged = true;
                }
            } catch (\Throwable $exception) {
                $syncErrors++;
                $firstError ??= $this->safeError($exception);
            }
        }

        $repository->finishSubscription($subscriptionId, $originalStatus, $firstError);
        if ($configChanged) {
            ($this->revisionService ?? new VpnSubscriptionRevisionService())->touchConfig($subscriptionId);
            $this->invalidateRelatedCaches($subscription);
            $this->events()->logEvent('subscription_revision_incremented', $subscriptionId, null, null,
                (int)$subscription['user_id'], $adminId, ['plan_id' => $planId]);
            $this->events()->logEvent('subscription_cache_invalidated', $subscriptionId, null, null,
                (int)$subscription['user_id'], $adminId, ['plan_id' => $planId]);
        }

        return new ReconcileResult(
            $planId,
            1,
            $created,
            $reused,
            $failed,
            $syncErrors,
            $skipped,
            $obsolete,
            $configChanged ? 1 : 0,
        );
    }

    public function findMissingNodes(int $subscriptionId): array
    {
        $repository = $this->repository();
        $subscription = $repository->subscription($subscriptionId);
        if (!$subscription) {
            return [];
        }
        $existing = [];
        foreach ($repository->subscriptionNodes($subscriptionId) as $node) {
            $existing[$this->key($node)] = $node;
        }

        $missing = [];
        foreach ($repository->activePlanNodes((int)$subscription['plan_id']) as $planNode) {
            $node = $existing[$this->key($planNode)] ?? null;

            // Клиент, удалённый вручную непосредственно из 3x-ui, не должен
            // автоматически появляться снова при плановом reconciliation.
            if ($node !== null
                && (string)$node['status'] === 'deleted'
                && (string)($node['sync_status'] ?? '') === 'remote_deleted') {
                continue;
            }

            // FIREBALL_VPN_RENEW_SYNC_PATCH_V1: recover-sync-errors
            if ($node === null || in_array((string)$node['status'], [
                'creating', 'create_failed', 'sync_error', 'missing_remote', 'deleted', 'delete_failed',
            ], true)) {
                $missing[] = [
                    'plan_node' => $planNode,
                    'existing_node' => $node,
                    'reason' => $node === null ? 'missing' : (string)$node['status'],
                ];
            }
        }

        return $missing;
    }

    public function findObsoleteNodes(int $subscriptionId): array
    {
        $repository = $this->repository();
        $subscription = $repository->subscription($subscriptionId);
        if (!$subscription) {
            return [];
        }
        $activeTargets = [];
        foreach ($repository->activePlanNodes((int)$subscription['plan_id']) as $planNode) {
            $activeTargets[$this->key($planNode)] = true;
        }

        return array_values(array_filter(
            $repository->subscriptionNodes($subscriptionId),
            fn(array $node): bool => !isset($activeTargets[$this->key($node)])
                && !in_array((string)$node['status'], ['deleted', 'deleting'], true)
        ));
    }

    public function provisionMissingNode(int $subscriptionId, int $planNodeId): NodeProvisionResult
    {
        $repository = $this->repository();
        $subscription = $repository->eligibleSubscription($subscriptionId);
        $planNode = $repository->planNode($planNodeId);
        if (!$subscription || !$planNode || (int)$planNode['plan_id'] !== (int)$subscription['plan_id']) {
            throw new ProvisioningException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_reconcile_target'));
        }
        if (empty($planNode['is_enabled']) || empty($planNode['server_is_enabled'])
            || !empty($planNode['maintenance_mode']) || empty($planNode['allow_new_connections'])
            || empty($planNode['inbound_is_enabled']) || (string)$planNode['inbound_status'] !== 'active') {
            throw new ProvisioningException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_provisioning_inbound_unavailable'));
        }

        $flow = $this->resolveFlow($planNode);

        return ($this->lock ?? new VpnReconciliationLock())->subscriptionNode(
            $subscriptionId,
            (int)$planNode['server_id'],
            (int)$planNode['inbound_id'],
            function () use ($repository, $subscription, $planNode, $flow): NodeProvisionResult {
                $identity = (new RemoteClientIdentityService())->forSubscription($subscription, $planNode);
                $claim = $repository->createOrClaimNode($subscription, $planNode, $flow, $identity);
                $nodeId = (int)$claim['id'];
                if (empty($claim['claimed'])) {
                    return new NodeProvisionResult(
                        (int)$subscription['id'],
                        $nodeId,
                        (string)$claim['status']
                    );
                }
                $this->events()->logEvent('subscription_node_creation_started', (int)$subscription['id'],
                    $nodeId, (int)$planNode['server_id'], (int)$subscription['user_id'], $this->adminId(), [
                        'plan_id' => (int)$subscription['plan_id'],
                        'plan_node_id' => (int)$planNode['plan_node_id'],
                        'inbound_id' => (int)$planNode['inbound_id'],
                    ]);

                $outcome = ($this->provisioning ?? new SubscriptionProvisioningService())
                    ->provisionNodeForReconciliation($nodeId);
                $status = (string)($outcome['status'] ?? 'create_failed');
                $created = $status === 'created';
                $reused = $status === 'reused';
                $event = ($created || $reused)
                    ? 'subscription_node_confirmed'
                    : 'subscription_node_creation_failed';
                $this->events()->logEvent($event, (int)$subscription['id'], $nodeId,
                    (int)$planNode['server_id'], (int)$subscription['user_id'], $this->adminId(), [
                        'plan_id' => (int)$subscription['plan_id'],
                        'plan_node_id' => (int)$planNode['plan_node_id'],
                        'inbound_id' => (int)$planNode['inbound_id'],
                        'safe_error_code' => $status,
                    ]);
                if ($created) {
                    $this->events()->logEvent('subscription_node_created', (int)$subscription['id'], $nodeId,
                        (int)$planNode['server_id'], (int)$subscription['user_id'], $this->adminId(), [
                            'plan_id' => (int)$subscription['plan_id'],
                            'inbound_id' => (int)$planNode['inbound_id'],
                        ]);
                }

                return new NodeProvisionResult(
                    (int)$subscription['id'],
                    $nodeId,
                    $status,
                    $created,
                    $reused,
                    $created || $reused,
                    !empty($outcome['flow_error'])
                );
            }
        );
    }

    public function retryFailedNode(int $nodeId): NodeProvisionResult
    {
        $node = $this->repository()->node($nodeId);
        if (!$node || !in_array((string)$node['status'], ['creating', 'create_failed', 'sync_error', 'missing_remote'], true)) {
            throw new ProvisioningException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_connection_retry_status'));
        }
        $planNode = $this->repository()->planNodeForTarget(
            (int)$node['plan_id'],
            (int)$node['server_id'],
            (int)$node['inbound_id']
        );
        if (!$planNode) {
            throw new ProvisioningException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_reconcile_target'));
        }
        if ((string)$node['status'] === 'sync_error') {
            $subscription = $this->repository()->eligibleSubscription((int)$node['subscription_id']);
            if (!$subscription) {
                throw new ProvisioningException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_reconcile_target'));
            }
            $changed = $this->syncParameters($subscription, $node, $planNode);
            if ($changed) {
                ($this->revisionService ?? new VpnSubscriptionRevisionService())->touchConfig((int)$node['subscription_id']);
                $this->invalidateRelatedCaches($subscription);
            }
            $this->repository()->finishSubscription(
                (int)$node['subscription_id'],
                (string)$subscription['status'],
                null
            );

            return new NodeProvisionResult(
                (int)$node['subscription_id'],
                $nodeId,
                empty($node['desired_enabled']) ? 'disabled' : 'active',
                reused: true,
                changed: $changed
            );
        }
        $result = $this->provisionMissingNode((int)$node['subscription_id'], (int)$planNode['plan_node_id']);
        if ($result->changed) {
            ($this->revisionService ?? new VpnSubscriptionRevisionService())->touchConfig((int)$node['subscription_id']);
            $subscription = $this->repository()->subscription((int)$node['subscription_id']);
            if ($subscription) {
                $this->repository()->finishSubscription(
                    (int)$node['subscription_id'],
                    (string)$subscription['status'],
                    null
                );
                $this->invalidateRelatedCaches($subscription);
            }
        }

        return $result;
    }

    public function queuePlan(int $planId, int $initiatedBy, array $options = []): ReconcileResult
    {
        Permissions::authorize(Permissions::RECONCILE);
        if (!array_key_exists('provision_missing', $options) || !empty($options['provision_missing'])) {
            Permissions::authorize(Permissions::CREATE_CONNECTIONS);
        }
        $operationId = $this->repository()->queueOperation(
            $planId,
            $initiatedBy,
            (int)($options['batch_size'] ?? 20),
            $options
        );

        return new ReconcileResult(
            $planId,
            operationId: $operationId,
            queued: true
        );
    }

    public function queueObsoleteRemoval(int $planId, int $initiatedBy, array $options = []): ReconcileResult
    {
        Permissions::authorize(Permissions::DELETE_CONNECTIONS);
        Permissions::authorize(Permissions::RECONCILE);
        $options['remove_obsolete'] = true;
        $operationId = $this->repository()->queueOperation(
            $planId,
            $initiatedBy,
            (int)($options['batch_size'] ?? 20),
            $options
        );

        return new ReconcileResult($planId, operationId: $operationId, queued: true);
    }

    public function removeObsoleteNodes(int $planId, int $adminId): ReconcileResult
    {
        Permissions::authorize(Permissions::DELETE_CONNECTIONS);
        $repository = $this->repository();

        return ($this->lock ?? new VpnReconciliationLock())->plan($planId, function () use ($repository, $planId, $adminId): ReconcileResult {
            $checked = $removed = $failed = $changed = 0;
            foreach ($repository->obsoleteSubscriptions($planId) as $subscription) {
                $result = $this->removeObsoleteNodesForSubscription(
                    (int)$subscription['id'],
                    $adminId,
                    ['authorized' => true]
                );
                $checked += $result->subscriptionsChecked;
                $removed += $result->removed;
                $failed += $result->failed;
                $changed += $result->changedSubscriptions;
            }

            return new ReconcileResult(
                $planId,
                $checked,
                failed: $failed,
                changedSubscriptions: $changed,
                removed: $removed
            );
        }, 1);
    }

    public function removeObsoleteNodesForSubscription(
        int $subscriptionId,
        ?int $adminId,
        array $options = []
    ): ReconcileResult {
        if (empty($options['authorized'])) {
            Permissions::authorize(Permissions::DELETE_CONNECTIONS);
        }
        $repository = $this->repository();
        $subscription = $repository->subscription($subscriptionId);
        if (!$subscription) {
            return new ReconcileResult(0, 0, skipped: 1);
        }
        $planId = (int)$subscription['plan_id'];
        $removed = $failed = 0;
        foreach ($repository->obsoleteNodesForSubscription($subscriptionId) as $row) {
            $node = $repository->node((int)$row['id']);
            if (!$node) {
                continue;
            }
            try {
                ($this->remoteDeletion ?? new RemoteClientDeletionService())->delete($node);
                $this->events()->markNodeDeleted((int)$node['id']);
                $removed++;
                $this->events()->logEvent('obsolete_subscription_node_removed',
                    $subscriptionId, (int)$node['id'], (int)$node['server_id'],
                    (int)$node['user_id'], $adminId,
                    ['plan_id' => $planId, 'inbound_id' => (int)$node['inbound_id']]);
            } catch (\Throwable $exception) {
                $failed++;
                $this->events()->markNodeDeleteFailed((int)$node['id'], $this->safeError($exception));
            }
        }
        if ($removed > 0) {
            ($this->revisionService ?? new VpnSubscriptionRevisionService())->touchConfig($subscriptionId);
            $this->invalidateRelatedCaches($subscription);
            $this->events()->logEvent('subscription_revision_incremented', $subscriptionId, null, null,
                (int)$subscription['user_id'], $adminId, ['plan_id' => $planId]);
            $this->events()->logEvent('subscription_cache_invalidated', $subscriptionId, null, null,
                (int)$subscription['user_id'], $adminId, ['plan_id' => $planId]);
        }

        return new ReconcileResult(
            $planId,
            1,
            failed: $failed,
            changedSubscriptions: $removed > 0 ? 1 : 0,
            removed: $removed
        );
    }

    private function parameterTargets(int $subscriptionId): array
    {
        $repository = $this->repository();
        $subscription = $repository->subscription($subscriptionId);
        if (!$subscription) {
            return [];
        }
        $nodes = [];
        foreach ($repository->subscriptionNodes($subscriptionId) as $node) {
            $nodes[$this->key($node)] = $node;
        }
        $changes = [];
        foreach ($repository->activePlanNodes((int)$subscription['plan_id']) as $planNode) {
            $node = $nodes[$this->key($planNode)] ?? null;
            if (!is_array($node) || !in_array((string)$node['status'], ['active', 'disabled', 'sync_error'], true)) {
                continue;
            }
            $changes[] = ['node' => $node, 'plan_node' => $planNode];
        }

        return $changes;
    }

    private function syncParameters(array $subscription, array $node, array $planNode): bool
    {
        $desiredFlow = $this->resolveFlow($planNode);
        try {
            $sync = $this->remoteSync ?? new RemoteClientSyncService();
            $inspection = $sync->inspect($node, $subscription, [
                'flow' => $desiredFlow, 'traffic_limit_bytes' => $subscription['traffic_limit_bytes'] ?? null,
            ]);
            if ($inspection['missing']) {
                $this->events()->markNodeFailure((int)$node['id'], 'missing_remote', \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_client_not_confirmed'));
                $recovered = $this->provisionMissingNode((int)$subscription['id'], (int)$planNode['plan_node_id']);
                if (!$recovered->successful()) {
                    throw new ProvisioningException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_client_not_confirmed'));
                }
                return $recovered->changed;
            }
            $result = $sync->push($node, $subscription, [
                'flow' => $desiredFlow,
                'traffic_limit_bytes' => $subscription['traffic_limit_bytes'] ?? null,
            ]);
            $this->repository()->confirmNode(
                (int)$node['id'],
                $desiredFlow,
                isset($result['traffic_used_bytes']) ? (int)$result['traffic_used_bytes'] : null,
                $result
            );
            $this->events()->logEvent('subscription_node_parameters_synced', (int)$subscription['id'],
                (int)$node['id'], (int)$node['server_id'], (int)$subscription['user_id'], $this->adminId(), [
                    'plan_id' => (int)$subscription['plan_id'], 'client_reference' => 'connection#' . (int)$node['id'],
                    'changed_fields' => $result['changed_fields'], 'success' => true,
                ]);

            return $this->normalizeFlow($node['flow'] ?? null) !== $desiredFlow
                || !empty($result['remote_updated'])
                || !in_array((string)$node['status'], ['active', 'disabled'], true);
        } catch (\Throwable $exception) {
            $this->events()->markNodeFailure((int)$node['id'], 'sync_error', $this->safeError($exception));
            $this->events()->logEvent('subscription_node_parameters_failed', (int)$subscription['id'],
                (int)$node['id'], (int)$node['server_id'], (int)$subscription['user_id'], $this->adminId(), [
                    'plan_id' => (int)$subscription['plan_id'],
                    'inbound_id' => (int)$node['inbound_id'],
                    'safe_error_code' => (new \ReflectionClass($exception))->getShortName(),
                    'client_reference' => 'connection#' . (int)$node['id'], 'success' => false,
                ]);
            throw $exception;
        }
    }

    private function expectedSubscription(array $subscription, ?array $plan = null): array
    {
        $plan ??= $this->repository()->plan((int)$subscription['plan_id']);
        if (!$plan) { throw new ProvisioningException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_plan_not_found')); }
        return array_replace($subscription, array_intersect_key($plan,
            array_flip(['device_limit', 'ip_limit', 'traffic_limit_bytes'])));
    }

    private function resolveFlow(array $planNode): ?string
    {
        $resolver = $this->flowResolver ?? new VpnFlowResolver();
        $stored = $planNode['flow_override'] ?? null;
        $flow = $stored === null
            ? $resolver->resolveDefaultFlow($planNode)
            : $resolver->normalizeFlow((string)$stored);
        if (!$resolver->isFlowCompatible($flow, $planNode)) {
            throw new ProvisioningException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_plan_flow_incompatible'));
        }

        return $flow;
    }

    private function normalizeFlow(mixed $flow): ?string
    {
        return ($this->flowResolver ?? new VpnFlowResolver())->normalizeFlow((string)$flow);
    }

    private function invalidateRelatedCaches(array $subscription): void
    {
        foreach ([
            'vpn-v2:profile:user:' . (int)$subscription['user_id'],
            'vpn-v2:admin:subscription:' . (int)$subscription['id'],
            'vpn-v2:admin:connections:' . (int)$subscription['id'],
            'vpn-v2:admin:plan:' . (int)$subscription['plan_id'],
        ] as $key) {
            cache()->remove($key);
        }
    }

    private function authorizeDangerousOperation(array $options): void
    {
        if (!empty($options['authorized'])) {
            return;
        }
        Permissions::authorize(Permissions::RECONCILE);
        if (!array_key_exists('provision_missing', $options) || !empty($options['provision_missing'])) {
            Permissions::authorize(Permissions::CREATE_CONNECTIONS);
        }
    }

    private function key(array $row): string
    {
        return (int)($row['server_id'] ?? 0) . ':' . (int)($row['inbound_id'] ?? 0);
    }

    private function safeError(\Throwable $exception): string
    {
        return \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_sync_generic') . ' ('
            . (new \ReflectionClass($exception))->getShortName() . ')';
    }

    private function repository(): PlanReconciliationRepository
    {
        return $this->repository ?? new PlanReconciliationRepository();
    }

    private function events(): SubscriptionRepository
    {
        return $this->subscriptions ?? new SubscriptionRepository();
    }

    private function adminId(): ?int
    {
        $user = get_user();
        $id = is_array($user) ? (int)($user['id'] ?? 0) : 0;

        return $id > 0 ? $id : null;
    }

    private function normalizeAdminId(mixed $adminId): ?int
    {
        $id = (int)$adminId;

        return $id > 0 ? $id : null;
    }
}
