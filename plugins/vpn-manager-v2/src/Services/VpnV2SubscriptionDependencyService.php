<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\Exceptions\VpnManagerV2Exception;
use Fireball\VpnManagerV2\Repositories\OperationQueueRepository;
use Fireball\VpnManagerV2\Repositories\SubscriptionConfigRepository;
use Fireball\VpnManagerV2\Repositories\SubscriptionItemRepository;
use Fireball\VpnManagerV2\Repositories\SubscriptionRepository;

final class VpnV2SubscriptionDependencyService
{
    private const ACCESSIBLE_STATUSES = ['active', 'partial_sync', 'sync_error'];
    private const MAX_DEPTH = 32;

    public function __construct(
        private readonly ?SubscriptionItemRepository $items = null,
        private readonly ?SubscriptionRepository $subscriptions = null,
        private readonly ?SubscriptionConfigRepository $config = null,
        private readonly ?VpnSubscriptionRevisionService $revisions = null,
        private readonly ?RemoteClientSyncService $remoteSync = null,
        private readonly ?OperationQueueRepository $operations = null,
    ) {
    }

    public function attachSubscription(
        int $parentId,
        int $childId,
        string $ownership = 'shared',
        ?int $adminId = null
    ): int {
        $this->validateDependency($parentId, 'subscription', $childId);
        $id = $this->repository()->create(
            $parentId,
            'subscription',
            $childId,
            $this->ownership($ownership),
            (int)$adminId
        );
        $this->recalculateEffectiveStatuses($parentId);
        $this->touch($parentId);
        $this->cascadeItem($parentId, $id, true, null, $adminId);
        $parent = $this->requiredSubscription($parentId);
        $this->events()->logEvent(
            'subscription.dependency_attached',
            $parentId,
            null,
            null,
            (int)$parent['user_id'],
            $adminId,
            ['item_id' => $id, 'item_type' => 'subscription', 'child_subscription_id' => $childId,
                'ownership_type' => $ownership]
        );

        return $id;
    }

    public function attachConnection(
        int $parentId,
        int $connectionId,
        string $ownership = 'shared',
        ?int $adminId = null
    ): int {
        $this->validateDependency($parentId, 'connection', $connectionId);
        $id = $this->repository()->create(
            $parentId,
            'connection',
            $connectionId,
            $this->ownership($ownership),
            (int)$adminId
        );
        $this->recalculateEffectiveStatuses($parentId);
        $this->touch($parentId);
        $this->cascadeItem($parentId, $id, true, null, $adminId);
        $parent = $this->requiredSubscription($parentId);
        $this->events()->logEvent(
            'subscription.dependency_attached',
            $parentId,
            $connectionId,
            null,
            (int)$parent['user_id'],
            $adminId,
            ['item_id' => $id, 'item_type' => 'connection', 'ownership_type' => $ownership]
        );

        return $id;
    }

    public function detachItem(int $parentId, int $itemId, ?int $adminId = null): bool
    {
        $item = $this->repository()->detach($parentId, $itemId);
        if (!$item) {
            return false;
        }
        $this->touch($parentId);
        $parent = $this->requiredSubscription($parentId);
        $this->events()->logEvent(
            'subscription.dependency_detached',
            $parentId,
            isset($item['connection_id']) ? (int)$item['connection_id'] : null,
            null,
            (int)$parent['user_id'],
            $adminId,
            ['item_id' => $itemId, 'item_type' => (string)$item['item_type'],
                'ownership_type' => (string)$item['ownership_type']]
        );

        return true;
    }

    public function setItemEnabled(int $parentId, int $itemId, bool $enabled, ?int $adminId = null): bool
    {
        $item = $this->repository()->find($parentId, $itemId);
        if (!$item) {
            return false;
        }
        if ((bool)$item['is_enabled'] === $enabled) {
            return true;
        }
        if (!$this->repository()->setEnabled($parentId, $itemId, $enabled)) {
            return false;
        }
        $this->recalculateEffectiveStatuses($parentId);
        $this->touch($parentId);
        $cascade = $this->cascadeItem(
            $parentId,
            $itemId,
            $enabled,
            $enabled ? null : 'dependency_item_disabled',
            $adminId
        );
        $parent = $this->requiredSubscription($parentId);
        $this->events()->logEvent(
            $enabled ? 'subscription.dependency_enabled' : 'subscription.dependency_disabled',
            $parentId,
            isset($item['connection_id']) ? (int)$item['connection_id'] : null,
            null,
            (int)$parent['user_id'],
            $adminId,
            ['item_id' => $itemId, 'item_type' => (string)$item['item_type'],
                'cascade_status' => (string)($cascade['status'] ?? 'inactive'),
                'cascade_failed' => (int)($cascade['failed'] ?? 0)]
        );

        return true;
    }

    public function reorderItems(int $parentId, array $itemIds, ?int $adminId = null): bool
    {
        $changed = $this->repository()->reorder($parentId, $itemIds);
        if (!$changed) {
            return false;
        }
        $this->touch($parentId);
        $parent = $this->requiredSubscription($parentId);
        $this->events()->logEvent(
            'subscription.dependency_order_changed',
            $parentId,
            null,
            null,
            (int)$parent['user_id'],
            $adminId,
            ['item_ids' => array_values(array_map('intval', $itemIds))]
        );

        return true;
    }

    public function validateDependency(int $parentId, string $type, int $targetId): void
    {
        $type = strtolower(trim($type));
        if (!in_array($type, ['subscription', 'connection'], true) || $targetId <= 0) {
            throw new \InvalidArgumentException('dependency_target_invalid');
        }
        $parent = $this->requiredSubscription($parentId);
        if ($type === 'subscription') {
            if ($parentId === $targetId) {
                throw new \InvalidArgumentException('dependency_cycle');
            }
            $child = $this->repository()->subscription($targetId);
            if (!$child || in_array((string)$child['status'], ['deleting', 'deleted'], true)) {
                throw new \InvalidArgumentException('dependency_target_missing');
            }
            if ((int)$child['user_id'] !== (int)$parent['user_id']) {
                throw new \InvalidArgumentException('dependency_user_forbidden');
            }
            if ($this->detectCycle($parentId, $targetId)) {
                throw new \InvalidArgumentException('dependency_cycle');
            }

            return;
        }

        $connection = $this->repository()->connection($targetId);
        if (!$connection || in_array((string)$connection['status'], ['deleted', 'deleting'], true)) {
            throw new \InvalidArgumentException('dependency_target_missing');
        }
        if ((int)$connection['user_id'] !== (int)$parent['user_id']) {
            throw new \InvalidArgumentException('dependency_user_forbidden');
        }
        if ((int)$connection['subscription_id'] === $parentId) {
            throw new \InvalidArgumentException('dependency_duplicate_own_connection');
        }
    }

    public function detectCycle(int $parentId, int $childId): bool
    {
        $stack = [[$childId, 0]];
        $visited = [];
        while ($stack !== []) {
            [$current, $depth] = array_pop($stack);
            if ($current === $parentId) {
                return true;
            }
            if ($depth >= self::MAX_DEPTH) {
                throw new \InvalidArgumentException('dependency_depth_exceeded');
            }
            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;
            foreach ($this->repository()->itemsForParent($current) as $item) {
                if ((string)$item['item_type'] === 'subscription' && (int)$item['child_subscription_id'] > 0) {
                    $stack[] = [(int)$item['child_subscription_id'], $depth + 1];
                }
            }
        }

        return false;
    }

    public function calculateEffectiveStatus(array $own, ?array $parent = null): array
    {
        $ownState = $this->subscriptionState($own, false);
        if ($parent === null) {
            return [
                'own_status' => (string)($own['status'] ?? 'missing'),
                'parent_status' => null,
                'effective_status' => $ownState['active'] ? 'active' : 'inactive',
                'inactive_reason' => $ownState['reason'],
            ];
        }
        $parentState = $this->subscriptionState($parent, true);
        if (!$parentState['active']) {
            return [
                'own_status' => (string)($own['status'] ?? 'missing'),
                'parent_status' => (string)($parent['status'] ?? 'missing'),
                'effective_status' => 'inactive',
                'inactive_reason' => $parentState['reason'],
            ];
        }

        return [
            'own_status' => (string)($own['status'] ?? 'missing'),
            'parent_status' => (string)($parent['status'] ?? 'missing'),
            'effective_status' => $ownState['active'] ? 'active' : 'inactive',
            'inactive_reason' => $ownState['reason'],
        ];
    }

    public function effectiveStatusForSubscription(int $subscriptionId): array
    {
        $subscription = $this->requiredSubscription($subscriptionId);

        return $this->calculateEffectiveStatus($subscription);
    }

    public function isDependentChild(int $subscriptionId): bool
    {
        return $this->repository()->isDependentChild($subscriptionId);
    }

    public function collectEffectiveConnections(array|int $parent): array
    {
        $parent = is_array($parent) ? $parent : $this->requiredSubscription($parent);
        if ($this->repository()->isDependentChild((int)($parent['id'] ?? 0))) {
            return [];
        }
        $root = $this->calculateEffectiveStatus($parent);
        if ($root['effective_status'] !== 'active') {
            return [];
        }
        $visited = [];
        $nodes = $this->collectRecursive($parent, $visited, 0);
        $unique = [];
        foreach ($nodes as $node) {
            $key = $this->technicalKey($node);
            if (!isset($unique[$key])) {
                $unique[$key] = $node;
            }
        }

        return array_values($unique);
    }

    public function collectEffectiveSubscriptionIds(array|int $parent): array
    {
        $parent = is_array($parent) ? $parent : $this->requiredSubscription($parent);
        if ($this->repository()->isDependentChild((int)($parent['id'] ?? 0))
            || $this->calculateEffectiveStatus($parent)['effective_status'] !== 'active') {
            return [];
        }
        $visited = [];

        return $this->collectSubscriptionIdsRecursive($parent, $visited, 0);
    }

    public function recalculateEffectiveStatuses(int $parentId): array
    {
        $parent = $this->requiredSubscription($parentId);
        $result = ['active' => 0, 'inactive' => 0];
        foreach ($this->repository()->itemsForParent($parentId) as $item) {
            $status = $this->itemStatus($item, $parent);
            $this->repository()->updateEffectiveStatus(
                (int)$item['id'],
                (string)$status['effective_status'],
                $status['inactive_reason']
            );
            $result[$status['effective_status']]++;
        }

        return $result;
    }

    public function recalculateAll(): array
    {
        $result = ['parents' => 0, 'active' => 0, 'inactive' => 0, 'errors' => 0];
        foreach ($this->repository()->activeParentIds() as $parentId) {
            try {
                $state = $this->recalculateEffectiveStatuses($parentId);
                $result['parents']++;
                $result['active'] += $state['active'];
                $result['inactive'] += $state['inactive'];
            } catch (\Throwable) {
                $result['errors']++;
            }
        }

        return $result;
    }

    public function cascadeDisable(int $parentId, string $reason = 'parent_subscription_inactive', ?int $adminId = null): array
    {
        return $this->cascade($parentId, false, $reason, $adminId, true);
    }

    public function cascadeEnable(int $parentId, ?int $adminId = null): array
    {
        $parent = $this->requiredSubscription($parentId);
        if ($this->calculateEffectiveStatus($parent)['effective_status'] !== 'active') {
            return ['processed' => 0, 'synced' => 0, 'failed' => 0, 'skipped_shared' => 0,
                'status' => 'inactive'];
        }

        return $this->cascade($parentId, true, null, $adminId, true);
    }

    public function processQueuedCascade(
        int $parentId,
        bool $enable,
        array $nodeIds = [],
        ?int $itemId = null
    ): array
    {
        $parentActive = $this->effectiveStatusForSubscription($parentId)['effective_status'] === 'active';
        if ($itemId !== null && $itemId > 0) {
            $item = $this->repository()->find($parentId, $itemId);
            if (!$item || (bool)$item['is_enabled'] !== $enable || ($enable && !$parentActive)) {
                return $this->inactiveCascadeResult();
            }
        } elseif ($parentActive !== $enable) {
            return $this->inactiveCascadeResult();
        }
        $nodeIds = array_values(array_unique(array_filter(array_map('intval', $nodeIds))));
        $nodes = $nodeIds === [] ? null : array_fill_keys(
            $nodeIds,
            ['ownership_type' => 'shared']
        );
        $result = $this->cascade(
            $parentId,
            $enable,
            $enable ? null : 'parent_subscription_inactive',
            null,
            false,
            $nodes,
            $itemId
        );
        if ((int)($result['failed'] ?? 0) > 0) {
            throw new \RuntimeException('Dependent 3x-ui clients were only partially synchronized.');
        }

        return $result;
    }

    public function countActiveConsumers(int $connectionId, ?int $excludingParentId = null): int
    {
        $connection = $this->repository()->connection($connectionId);
        if (!$connection) {
            return 0;
        }
        $consumerIds = [];
        foreach ($this->repository()->consumerLinksForConnection($connectionId) as $link) {
            $parentId = (int)$link['parent_subscription_id'];
            if ($parentId <= 0 || $parentId === $excludingParentId || isset($consumerIds[$parentId])) {
                continue;
            }
            $parent = $this->repository()->subscription($parentId);
            if (!$parent || $this->calculateEffectiveStatus($parent)['effective_status'] !== 'active') {
                continue;
            }
            foreach ($this->repository()->itemsForParent($parentId, false) as $item) {
                if ((int)$item['id'] === (int)$link['id']
                    && $this->itemStatus($item, $parent)['effective_status'] === 'active') {
                    $consumerIds[$parentId] = true;
                    break;
                }
            }
        }
        $sourceId = (int)$connection['subscription_id'];
        if ($sourceId !== $excludingParentId
            && !$this->repository()->isDependentChild($sourceId)
            && $this->repository()->exclusiveParentForConnection($connectionId) === null) {
            $source = $this->repository()->subscription($sourceId);
            if ($source && $this->calculateEffectiveStatus($source)['effective_status'] === 'active') {
                $consumerIds[$sourceId] = true;
            }
        }

        return count($consumerIds);
    }

    public function archiveForDeletion(int $subscriptionId): int
    {
        foreach ($this->repository()->itemsForParent($subscriptionId) as $item) {
            if ((string)$item['ownership_type'] !== 'exclusive') {
                continue;
            }
            if ((string)$item['item_type'] === 'connection') {
                $connectionId = (int)$item['connection_id'];
                if ($this->countActiveConsumers($connectionId, $subscriptionId) === 0) {
                    $this->repository()->archiveExclusiveConnection($connectionId);
                    ($this->revisions ?? new VpnSubscriptionRevisionService())->touchConnection($connectionId);
                }
                continue;
            }
            $childId = (int)$item['child_subscription_id'];
            if ($this->countActiveSubscriptionConsumers($childId, $subscriptionId) === 0
                && $this->repository()->archiveExclusiveSubscription($childId)) {
                $this->touch($childId);
            }
        }

        return $this->repository()->archiveRelationsForSubscription($subscriptionId);
    }

    private function countActiveSubscriptionConsumers(int $childId, int $excludingParentId): int
    {
        $count = 0;
        foreach ($this->repository()->parentIdsForChildSubscription($childId) as $parentId) {
            if ($parentId === $excludingParentId) {
                continue;
            }
            $parent = $this->repository()->subscription($parentId);
            if ($parent && $this->calculateEffectiveStatus($parent)['effective_status'] === 'active') {
                $count++;
            }
        }

        return $count;
    }

    private function collectRecursive(array $parent, array &$visited, int $depth): array
    {
        $parentId = (int)$parent['id'];
        if ($depth > self::MAX_DEPTH || isset($visited[$parentId])) {
            return [];
        }
        $visited[$parentId] = true;
        $nodes = array_values(array_filter(
            $this->configuration()->activeNodes($parentId),
            function (array $node) use ($parentId): bool {
                $exclusiveParent = $this->repository()->exclusiveParentForConnection((int)($node['node_id'] ?? 0));

                return $exclusiveParent === null || $exclusiveParent === $parentId;
            }
        ));
        foreach ($this->repository()->itemsForParent($parentId, false) as $item) {
            $status = $this->itemStatus($item, $parent);
            $this->repository()->updateEffectiveStatus(
                (int)$item['id'],
                (string)$status['effective_status'],
                $status['inactive_reason']
            );
            if ($status['effective_status'] !== 'active') {
                continue;
            }
            if ((string)$item['item_type'] === 'connection') {
                $node = $this->configuration()->activeNode((int)$item['connection_id']);
                if ($node) {
                    $nodes[] = $node;
                }
                continue;
            }
            $child = $this->repository()->subscription((int)$item['child_subscription_id']);
            if ($child) {
                array_push($nodes, ...$this->collectRecursive($child, $visited, $depth + 1));
            }
        }
        unset($visited[$parentId]);

        return $nodes;
    }

    private function collectSubscriptionIdsRecursive(array $parent, array &$visited, int $depth): array
    {
        $parentId = (int)$parent['id'];
        if ($depth > self::MAX_DEPTH || isset($visited[$parentId])) {
            return [];
        }
        $visited[$parentId] = true;
        $ids = [$parentId];
        foreach ($this->repository()->itemsForParent($parentId, false) as $item) {
            if ((string)$item['item_type'] !== 'subscription'
                || $this->itemStatus($item, $parent)['effective_status'] !== 'active') {
                continue;
            }
            $child = $this->repository()->subscription((int)$item['child_subscription_id']);
            if ($child) {
                array_push($ids, ...$this->collectSubscriptionIdsRecursive($child, $visited, $depth + 1));
            }
        }
        unset($visited[$parentId]);

        return array_values(array_unique($ids));
    }

    private function itemStatus(array $item, array $parent): array
    {
        $parentState = $this->subscriptionState($parent, true);
        if (!$parentState['active']) {
            return ['own_status' => $this->itemOwnStatus($item), 'parent_status' => (string)$parent['status'],
                'effective_status' => 'inactive', 'inactive_reason' => $parentState['reason']];
        }
        if (empty($item['is_enabled'])) {
            return ['own_status' => $this->itemOwnStatus($item), 'parent_status' => (string)$parent['status'],
                'effective_status' => 'inactive', 'inactive_reason' => 'item_disabled'];
        }
        if ((string)$item['item_type'] === 'subscription') {
            $child = $this->repository()->subscription((int)$item['child_subscription_id']);
            if (!$child) {
                return ['own_status' => 'missing', 'parent_status' => (string)$parent['status'],
                    'effective_status' => 'inactive', 'inactive_reason' => 'child_subscription_deleted'];
            }

            return $this->calculateEffectiveStatus($child, $parent);
        }
        $connection = $this->repository()->connection((int)$item['connection_id']);
        if (!$connection) {
            return ['own_status' => 'missing', 'parent_status' => (string)$parent['status'],
                'effective_status' => 'inactive', 'inactive_reason' => 'connection_deleted'];
        }
        if ((string)$connection['status'] !== 'active' || empty($connection['desired_enabled'])) {
            return ['own_status' => (string)$connection['status'], 'parent_status' => (string)$parent['status'],
                'effective_status' => 'inactive', 'inactive_reason' => 'connection_inactive'];
        }
        $source = $this->repository()->subscription((int)$connection['subscription_id']);
        if (!$source) {
            return ['own_status' => (string)$connection['status'], 'parent_status' => (string)$parent['status'],
                'effective_status' => 'inactive', 'inactive_reason' => 'connection_source_deleted'];
        }
        if ((string)$source['status'] !== 'deleted') {
            $sourceState = $this->subscriptionState($source, false);
            if (!$sourceState['active']) {
                return ['own_status' => (string)$connection['status'], 'parent_status' => (string)$parent['status'],
                    'effective_status' => 'inactive', 'inactive_reason' => $sourceState['reason']];
            }
        } elseif ((string)$item['ownership_type'] !== 'shared') {
            return ['own_status' => (string)$connection['status'], 'parent_status' => (string)$parent['status'],
                'effective_status' => 'inactive', 'inactive_reason' => 'connection_source_deleted'];
        }
        if (!$this->configuration()->activeNode((int)$connection['id'])) {
            return ['own_status' => (string)$connection['status'], 'parent_status' => (string)$parent['status'],
                'effective_status' => 'inactive', 'inactive_reason' => 'connection_technically_unavailable'];
        }

        return ['own_status' => (string)$connection['status'], 'parent_status' => (string)$parent['status'],
            'effective_status' => 'active', 'inactive_reason' => null];
    }

    private function cascade(
        int $parentId,
        bool $enable,
        ?string $reason,
        ?int $adminId,
        bool $enqueueFailure,
        ?array $nodeOverride = null,
        ?int $itemId = null
    ): array {
        $parent = $this->requiredSubscription($parentId);
        $nodes = $nodeOverride ?? $this->dependencyNodesForCascade($parentId, $enable);
        $result = ['processed' => 0, 'synced' => 0, 'failed' => 0, 'skipped_shared' => 0,
            'status' => 'synced'];
        foreach ($nodes as $nodeId => $descriptor) {
            $node = $this->events()->connectionForProvisioning((int)$nodeId);
            if (!$node || in_array((string)$node['status'], ['deleted', 'deleting'], true)) {
                continue;
            }
            $result['processed']++;
            if (!$enable && $this->countActiveConsumers((int)$nodeId, $parentId) > 0) {
                $result['skipped_shared']++;
                continue;
            }
            if ($enable && ((string)$node['status'] !== 'active' || empty($node['desired_enabled']))) {
                continue;
            }
            $source = $this->repository()->subscription((int)$node['subscription_id']);
            if (!$source) {
                $result['failed']++;
                continue;
            }
            try {
                ($this->remoteSync ?? new RemoteClientSyncService())->push($node, $source, [
                    'desired_enabled' => $enable,
                ]);
                $this->repository()->recordNodeSync((int)$nodeId, true);
                if ($enable) {
                    ($this->operations ?? new OperationQueueRepository())->enqueue(
                        'sync_client',
                        'reconciliation',
                        (int)$node['server_id'],
                        $parentId,
                        (int)$nodeId,
                        ['dependency_parent_id' => $parentId],
                        $adminId
                    );
                }
                $result['synced']++;
            } catch (\Throwable $exception) {
                $result['failed']++;
                $this->repository()->recordNodeSync((int)$nodeId, false, $this->safeError($exception));
            }
        }
        $this->recalculateEffectiveStatuses($parentId);
        $this->touch($parentId);
        if ($result['failed'] > 0) {
            $result['status'] = 'partially_synced';
            if ($enqueueFailure) {
                ($this->operations ?? new OperationQueueRepository())->enqueue(
                    $enable ? 'cascade_enable_children' : 'cascade_disable_children',
                    'retry',
                    null,
                    $parentId,
                    null,
                    [
                        'reason' => $reason ?? ($enable ? 'parent_reactivated' : 'parent_subscription_inactive'),
                        'node_ids' => $nodeOverride === null ? [] : array_values(array_map('intval', array_keys($nodes))),
                        'item_id' => $itemId,
                    ],
                    $adminId
                );
            }
        }
        $this->events()->logEvent(
            $enable ? 'subscription.cascade_enable_children' : 'subscription.cascade_disable_children',
            $parentId,
            null,
            null,
            (int)$parent['user_id'],
            $adminId,
            $result + ['inactive_reason' => $reason]
        );

        return $result;
    }

    private function cascadeItem(
        int $parentId,
        int $itemId,
        bool $enable,
        ?string $reason,
        ?int $adminId
    ): array {
        $parent = $this->requiredSubscription($parentId);
        $item = null;
        foreach ($this->repository()->itemsForParent($parentId) as $candidate) {
            if ((int)$candidate['id'] === $itemId) {
                $item = $candidate;
                break;
            }
        }
        if ($item === null) {
            return ['processed' => 0, 'synced' => 0, 'failed' => 0, 'skipped_shared' => 0,
                'status' => 'inactive'];
        }
        if ($enable && $this->itemStatus($item, $parent)['effective_status'] !== 'active') {
            return ['processed' => 0, 'synced' => 0, 'failed' => 0, 'skipped_shared' => 0,
                'status' => 'inactive'];
        }
        $nodes = $this->nodesForItem($item, $enable);

        return $this->cascade($parentId, $enable, $reason, $adminId, true, $nodes, $itemId);
    }

    private function inactiveCascadeResult(): array
    {
        return ['processed' => 0, 'synced' => 0, 'failed' => 0, 'skipped_shared' => 0,
            'status' => 'inactive'];
    }

    private function nodesForItem(array $item, bool $effectiveOnly): array
    {
        $ownership = (string)($item['ownership_type'] ?? 'shared');
        if ((string)$item['item_type'] === 'connection') {
            return [(int)$item['connection_id'] => ['ownership_type' => $ownership]];
        }
        $childId = (int)$item['child_subscription_id'];
        $nodes = [];
        foreach ($this->events()->nodeIdsForSubscription($childId) as $nodeId) {
            $nodes[$nodeId] = ['ownership_type' => $ownership];
        }
        $visited = [];
        foreach ($this->dependencyNodes($childId, $visited, 1, $effectiveOnly) as $nodeId => $descriptor) {
            $nodes[$nodeId] = $descriptor;
        }

        return $nodes;
    }

    private function dependencyNodesForCascade(int $parentId, bool $effectiveOnly): array
    {
        $visited = [];

        return $this->dependencyNodes($parentId, $visited, 0, $effectiveOnly);
    }

    private function dependencyNodes(
        int $parentId,
        array &$visited,
        int $depth,
        bool $effectiveOnly
    ): array
    {
        if ($depth > self::MAX_DEPTH || isset($visited[$parentId])) {
            return [];
        }
        $visited[$parentId] = true;
        $nodes = [];
        $parent = $this->repository()->subscription($parentId);
        if (!$parent) {
            unset($visited[$parentId]);
            return [];
        }
        foreach ($this->repository()->itemsForParent($parentId, false) as $item) {
            if ($effectiveOnly && $this->itemStatus($item, $parent)['effective_status'] !== 'active') {
                continue;
            }
            $ownership = (string)$item['ownership_type'];
            if ((string)$item['item_type'] === 'connection') {
                $nodes[(int)$item['connection_id']] = ['ownership_type' => $ownership];
                continue;
            }
            $childId = (int)$item['child_subscription_id'];
            foreach ($this->events()->nodeIdsForSubscription($childId) as $nodeId) {
                $nodes[$nodeId] = ['ownership_type' => $ownership];
            }
            foreach ($this->dependencyNodes(
                $childId,
                $visited,
                $depth + 1,
                $effectiveOnly
            ) as $nodeId => $descriptor) {
                $nodes[$nodeId] = $descriptor;
            }
        }
        unset($visited[$parentId]);

        return $nodes;
    }

    private function subscriptionState(array $subscription, bool $asParent): array
    {
        $status = strtolower(trim((string)($subscription['status'] ?? 'missing')));
        $prefix = $asParent ? 'parent_' : '';
        if (!in_array($status, self::ACCESSIBLE_STATUSES, true)) {
            $reason = match ($status) {
                'expired' => $prefix . 'subscription_expired',
                'suspended' => $prefix . 'subscription_suspended',
                'deleting', 'deleted', 'pending_remote_delete', 'delete_failed' => $prefix . 'subscription_deleted',
                'traffic_exceeded', 'limit_exceeded' => $prefix . 'subscription_limit_exceeded',
                default => $prefix . 'subscription_inactive',
            };

            return ['active' => false, 'reason' => $reason];
        }
        $startsAt = strtotime((string)($subscription['starts_at'] ?? ''));
        if ($startsAt !== false && $startsAt > time()) {
            return ['active' => false, 'reason' => $prefix . 'subscription_not_started'];
        }
        $expiresAt = trim((string)($subscription['expires_at'] ?? ''));
        $expires = $expiresAt !== '' ? strtotime($expiresAt) : false;
        if ($expires !== false && $expires <= time()) {
            return ['active' => false, 'reason' => $prefix . 'subscription_expired'];
        }
        $limit = isset($subscription['traffic_limit_bytes']) ? (int)$subscription['traffic_limit_bytes'] : 0;
        $used = isset($subscription['traffic_used_bytes']) ? (int)$subscription['traffic_used_bytes'] : 0;
        if ($limit > 0 && $used >= $limit) {
            return ['active' => false, 'reason' => $prefix . 'subscription_limit_exceeded'];
        }

        return ['active' => true, 'reason' => null];
    }

    private function itemOwnStatus(array $item): string
    {
        return (string)($item['item_type'] === 'subscription'
            ? ($item['child_status'] ?? 'missing')
            : ($item['connection_status'] ?? 'missing'));
    }

    private function technicalKey(array $node): string
    {
        $credential = (new RemoteClientCredentialService())->credential($node);
        if ($credential === '') {
            $credential = trim((string)($node['remote_client_id'] ?? ''));
        }
        $host = strtolower(trim((string)(parse_url((string)($node['panel_url'] ?? ''), PHP_URL_HOST) ?: '')));

        return hash('sha256', implode('|', [
            (int)($node['server_id'] ?? 0),
            (int)($node['inbound_id'] ?? 0),
            strtolower((string)($node['protocol'] ?? '')),
            hash('sha256', $credential),
            $host,
            (int)($node['port'] ?? 0),
        ]));
    }

    private function requiredSubscription(int $id): array
    {
        $subscription = $this->repository()->subscription($id);
        if (!$subscription) {
            throw new \InvalidArgumentException('subscription_not_found');
        }

        return $subscription;
    }

    private function ownership(string $ownership): string
    {
        $ownership = strtolower(trim($ownership));
        if (!in_array($ownership, ['exclusive', 'shared'], true)) {
            throw new \InvalidArgumentException('dependency_ownership_invalid');
        }

        return $ownership;
    }

    private function touch(int $subscriptionId): void
    {
        ($this->revisions ?? new VpnSubscriptionRevisionService())->touchConfig($subscriptionId);
    }

    private function safeError(\Throwable $exception): string
    {
        return $exception instanceof VpnManagerV2Exception
            ? mb_substr(trim($exception->getMessage()), 0, 1000)
            : \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_sync_generic');
    }

    private function repository(): SubscriptionItemRepository
    {
        return $this->items ?? new SubscriptionItemRepository();
    }

    private function configuration(): SubscriptionConfigRepository
    {
        return $this->config ?? new SubscriptionConfigRepository();
    }

    private function events(): SubscriptionRepository
    {
        return $this->subscriptions ?? new SubscriptionRepository();
    }
}
