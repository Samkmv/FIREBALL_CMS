<?php

namespace Fireball\VpnManagerV2\Repositories;

final class SubscriptionItemRepository
{
    public function subscription(int $id): ?array
    {
        $row = db()->query(
            'SELECT s.id, s.user_id, s.plan_id, s.status, s.starts_at, s.expires_at,
                    s.traffic_limit_bytes, s.traffic_used_bytes, s.device_limit, s.revision,
                    s.subscription_token, s.config_updated_at, s.created_at, s.updated_at,
                    u.name AS user_name, u.login AS user_login, u.email AS user_email,
                    p.name AS plan_name
             FROM vpn_v2_subscriptions s
             INNER JOIN users u ON u.id = s.user_id
             INNER JOIN vpn_v2_plans p ON p.id = s.plan_id
             WHERE s.id = ? LIMIT 1',
            [$id]
        )->getOne();

        return is_array($row) ? $row : null;
    }

    public function connection(int $id): ?array
    {
        $row = db()->query(
            'SELECT n.id, n.subscription_id, n.server_id, n.inbound_id, n.status,
                    n.desired_enabled, n.protocol, n.client_email, n.client_uuid,
                    n.encrypted_client_credential, n.traffic_limit_bytes,
                    sub.user_id, sub.status AS subscription_status,
                    sub.starts_at, sub.expires_at, sub.traffic_limit_bytes AS subscription_traffic_limit_bytes,
                    sub.traffic_used_bytes AS subscription_traffic_used_bytes,
                    s.name AS server_name, i.name AS inbound_name
             FROM vpn_v2_subscription_nodes n
             INNER JOIN vpn_v2_subscriptions sub ON sub.id = n.subscription_id
             INNER JOIN vpn_v2_servers s ON s.id = n.server_id
             INNER JOIN vpn_v2_inbounds i ON i.id = n.inbound_id
             WHERE n.id = ? LIMIT 1',
            [$id]
        )->getOne();

        return is_array($row) ? $row : null;
    }

    public function itemsForParent(int $parentId, bool $includeDisabled = true): array
    {
        $enabledSql = $includeDisabled ? '' : ' AND item.is_enabled = 1';

        return db()->query(
            "SELECT item.*,
                    child.user_id AS child_user_id, child.status AS child_status,
                    child.starts_at AS child_starts_at, child.expires_at AS child_expires_at,
                    child.traffic_limit_bytes AS child_traffic_limit_bytes,
                    child.traffic_used_bytes AS child_traffic_used_bytes,
                    child_user.name AS child_user_name, child_user.email AS child_user_email,
                    child_plan.name AS child_plan_name,
                    connection.subscription_id AS connection_subscription_id,
                    connection.status AS connection_status,
                    connection.desired_enabled AS connection_desired_enabled,
                    connection.protocol AS connection_protocol,
                    connection.server_id AS connection_server_id,
                    connection.inbound_id AS connection_inbound_id,
                    server.name AS connection_server_name,
                    inbound.name AS connection_inbound_name,
                    source_sub.user_id AS connection_user_id,
                    source_sub.status AS connection_subscription_status,
                    source_sub.starts_at AS connection_starts_at,
                    source_sub.expires_at AS connection_expires_at,
                    source_user.name AS connection_user_name,
                    source_user.email AS connection_user_email,
                    (SELECT COUNT(*) FROM vpn_v2_subscription_nodes child_node
                     WHERE child_node.subscription_id = child.id
                       AND child_node.status NOT IN ('deleted', 'deleting')) AS child_connection_count
             FROM vpn_v2_subscription_items item
             LEFT JOIN vpn_v2_subscriptions child ON child.id = item.child_subscription_id
             LEFT JOIN users child_user ON child_user.id = child.user_id
             LEFT JOIN vpn_v2_plans child_plan ON child_plan.id = child.plan_id
             LEFT JOIN vpn_v2_subscription_nodes connection ON connection.id = item.connection_id
             LEFT JOIN vpn_v2_subscriptions source_sub ON source_sub.id = connection.subscription_id
             LEFT JOIN users source_user ON source_user.id = source_sub.user_id
             LEFT JOIN vpn_v2_servers server ON server.id = connection.server_id
             LEFT JOIN vpn_v2_inbounds inbound ON inbound.id = connection.inbound_id
             WHERE item.parent_subscription_id = ? AND item.deleted_at IS NULL" . $enabledSql . "
             ORDER BY item.sort_order ASC, item.id ASC",
            [$parentId]
        )->get() ?: [];
    }

    public function find(int $parentId, int $itemId): ?array
    {
        $row = db()->query(
            'SELECT * FROM vpn_v2_subscription_items
             WHERE id = ? AND parent_subscription_id = ? AND deleted_at IS NULL LIMIT 1',
            [$itemId, $parentId]
        )->getOne();

        return is_array($row) ? $row : null;
    }

    public function create(
        int $parentId,
        string $type,
        int $targetId,
        string $ownership,
        int $adminId
    ): int {
        $relationKey = $type . ':' . $targetId;
        $now = date('Y-m-d H:i:s');
        $database = db();
        $database->beginTransaction();
        try {
            $duplicate = $database->query(
                'SELECT id FROM vpn_v2_subscription_items
                 WHERE parent_subscription_id = ? AND relation_key = ? LIMIT 1 FOR UPDATE',
                [$parentId, $relationKey]
            )->getOne();
            if (is_array($duplicate)) {
                throw new \InvalidArgumentException('dependency_duplicate');
            }
            $nextOrder = (int)$database->query(
                'SELECT COALESCE(MAX(sort_order), 0) + 10 FROM vpn_v2_subscription_items
                 WHERE parent_subscription_id = ? AND deleted_at IS NULL FOR UPDATE',
                [$parentId]
            )->getColumn();
            $database->query(
                'INSERT INTO vpn_v2_subscription_items
                    (parent_subscription_id, item_type, child_subscription_id, connection_id,
                     ownership_type, is_enabled, sort_order, effective_status, inactive_reason,
                     relation_key, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, 1, ?, \'inactive\', \'not_evaluated\', ?, ?, ?)',
                [
                    $parentId,
                    $type,
                    $type === 'subscription' ? $targetId : null,
                    $type === 'connection' ? $targetId : null,
                    $ownership,
                    max(10, $nextOrder),
                    $relationKey,
                    $now,
                    $now,
                ]
            );
            $id = (int)$database->getInsertId();
            $database->commit();

            return $id;
        } catch (\Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    public function setEnabled(int $parentId, int $itemId, bool $enabled): bool
    {
        db()->query(
            'UPDATE vpn_v2_subscription_items
             SET is_enabled = ?, updated_at = ?
             WHERE id = ? AND parent_subscription_id = ? AND deleted_at IS NULL',
            [$enabled ? 1 : 0, date('Y-m-d H:i:s'), $itemId, $parentId]
        );

        return db()->rowCount() === 1;
    }

    public function detach(int $parentId, int $itemId): ?array
    {
        $database = db();
        $database->beginTransaction();
        try {
            $item = $database->query(
                'SELECT * FROM vpn_v2_subscription_items
                 WHERE id = ? AND parent_subscription_id = ? AND deleted_at IS NULL FOR UPDATE',
                [$itemId, $parentId]
            )->getOne();
            if (!is_array($item)) {
                $database->rollBack();
                return null;
            }
            $now = date('Y-m-d H:i:s');
            $database->query(
                "UPDATE vpn_v2_subscription_items
                 SET is_enabled = 0, effective_status = 'inactive', inactive_reason = 'detached',
                     relation_key = NULL, deleted_at = ?, updated_at = ? WHERE id = ?",
                [$now, $now, $itemId]
            );
            $database->commit();

            return $item;
        } catch (\Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    public function reorder(int $parentId, array $itemIds): bool
    {
        $database = db();
        $database->beginTransaction();
        try {
            $rows = $database->query(
                'SELECT id FROM vpn_v2_subscription_items
                 WHERE parent_subscription_id = ? AND deleted_at IS NULL
                 ORDER BY sort_order ASC, id ASC FOR UPDATE',
                [$parentId]
            )->get() ?: [];
            $current = array_map(static fn(array $row): int => (int)$row['id'], $rows);
            $submitted = array_values(array_map('intval', $itemIds));
            $left = $current;
            $right = $submitted;
            sort($left);
            sort($right);
            if ($submitted === [] || count($submitted) !== count(array_unique($submitted)) || $left !== $right) {
                throw new \InvalidArgumentException('dependency_order_invalid');
            }
            if ($current === $submitted) {
                $database->commit();
                return false;
            }
            $now = date('Y-m-d H:i:s');
            foreach ($submitted as $index => $id) {
                $database->query(
                    'UPDATE vpn_v2_subscription_items SET sort_order = ?, updated_at = ?
                     WHERE id = ? AND parent_subscription_id = ?',
                    [($index + 1) * 10, $now, $id, $parentId]
                );
            }
            $database->commit();

            return true;
        } catch (\Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    public function updateEffectiveStatus(int $itemId, string $status, ?string $reason): void
    {
        db()->query(
            'UPDATE vpn_v2_subscription_items
             SET effective_status = ?, inactive_reason = ?, last_evaluated_at = ?, updated_at = ?
             WHERE id = ? AND deleted_at IS NULL',
            [$status, $reason, date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $itemId]
        );
    }

    public function parentIdsForChildSubscription(int $subscriptionId): array
    {
        return $this->ids(
            'SELECT parent_subscription_id AS id FROM vpn_v2_subscription_items
             WHERE child_subscription_id = ? AND item_type = \'subscription\'
               AND is_enabled = 1 AND deleted_at IS NULL',
            [$subscriptionId]
        );
    }

    public function parentIdsForConnection(int $connectionId): array
    {
        return $this->ids(
            'SELECT parent_subscription_id AS id FROM vpn_v2_subscription_items
             WHERE connection_id = ? AND item_type = \'connection\'
               AND is_enabled = 1 AND deleted_at IS NULL',
            [$connectionId]
        );
    }

    public function parentIdsForConnectionsInSubscription(int $subscriptionId): array
    {
        return $this->ids(
            "SELECT DISTINCT item.parent_subscription_id AS id
             FROM vpn_v2_subscription_items item
             INNER JOIN vpn_v2_subscription_nodes n ON n.id = item.connection_id
             WHERE n.subscription_id = ? AND item.item_type = 'connection'
               AND item.is_enabled = 1 AND item.deleted_at IS NULL",
            [$subscriptionId]
        );
    }

    public function consumerLinksForConnection(int $connectionId): array
    {
        return db()->query(
            "SELECT DISTINCT item.id, item.parent_subscription_id, item.item_type,
                    item.ownership_type, item.is_enabled
             FROM vpn_v2_subscription_items item
             LEFT JOIN vpn_v2_subscription_nodes n ON n.id = ?
             WHERE item.deleted_at IS NULL AND item.is_enabled = 1
               AND (
                   (item.item_type = 'connection' AND item.connection_id = ?)
                   OR
                   (item.item_type = 'subscription' AND item.child_subscription_id = n.subscription_id)
               )",
            [$connectionId, $connectionId]
        )->get() ?: [];
    }

    public function activeParentIds(): array
    {
        return $this->ids(
            'SELECT DISTINCT parent_subscription_id AS id FROM vpn_v2_subscription_items
             WHERE is_enabled = 1 AND deleted_at IS NULL ORDER BY parent_subscription_id ASC',
            []
        );
    }

    public function isDependentChild(int $subscriptionId): bool
    {
        return (bool)db()->query(
            'SELECT id FROM vpn_v2_subscription_items
             WHERE child_subscription_id = ? AND item_type = \'subscription\'
               AND deleted_at IS NULL LIMIT 1',
            [$subscriptionId]
        )->getOne();
    }

    public function exclusiveParentForConnection(int $connectionId): ?int
    {
        $value = db()->query(
            "SELECT parent_subscription_id FROM vpn_v2_subscription_items
             WHERE connection_id = ? AND item_type = 'connection' AND ownership_type = 'exclusive'
               AND deleted_at IS NULL
             ORDER BY id ASC LIMIT 1",
            [$connectionId]
        )->getColumn();

        return (int)$value > 0 ? (int)$value : null;
    }

    public function archiveRelationsForSubscription(int $subscriptionId): int
    {
        $now = date('Y-m-d H:i:s');
        db()->query(
            "UPDATE vpn_v2_subscription_items
             SET is_enabled = 0, effective_status = 'inactive',
                 inactive_reason = 'parent_subscription_deleted', relation_key = NULL,
                 deleted_at = ?, updated_at = ?
             WHERE deleted_at IS NULL
               AND (parent_subscription_id = ? OR child_subscription_id = ?)",
            [$now, $now, $subscriptionId, $subscriptionId]
        );

        return db()->rowCount();
    }

    public function archiveExclusiveSubscription(int $subscriptionId): bool
    {
        db()->query(
            "UPDATE vpn_v2_subscriptions
             SET status = 'suspended', last_error = NULL, updated_at = ?
             WHERE id = ? AND status NOT IN ('deleted', 'deleting')",
            [date('Y-m-d H:i:s'), $subscriptionId]
        );

        return db()->rowCount() === 1;
    }

    public function archiveExclusiveConnection(int $connectionId): bool
    {
        db()->query(
            "UPDATE vpn_v2_subscription_nodes
             SET status = 'disabled', desired_enabled = 0, updated_at = ?
             WHERE id = ? AND status NOT IN ('deleted', 'deleting')",
            [date('Y-m-d H:i:s'), $connectionId]
        );

        return db()->rowCount() === 1;
    }

    public function subscriptionCandidates(int $parentId): array
    {
        $parent = $this->subscription($parentId);
        if (!$parent) {
            return [];
        }

        return db()->query(
            "SELECT s.id, s.status, s.expires_at, u.name AS user_name, p.name AS plan_name
             FROM vpn_v2_subscriptions s
             INNER JOIN users u ON u.id = s.user_id
             INNER JOIN vpn_v2_plans p ON p.id = s.plan_id
             WHERE s.user_id = ? AND s.id <> ? AND s.status NOT IN ('deleting', 'deleted')
               AND NOT EXISTS (
                   SELECT 1 FROM vpn_v2_subscription_items item
                   WHERE item.parent_subscription_id = ? AND item.child_subscription_id = s.id
                     AND item.deleted_at IS NULL
               )
             ORDER BY s.id ASC",
            [(int)$parent['user_id'], $parentId, $parentId]
        )->get() ?: [];
    }

    public function connectionCandidates(int $parentId): array
    {
        $parent = $this->subscription($parentId);
        if (!$parent) {
            return [];
        }

        return db()->query(
            "SELECT n.id, n.protocol, n.status, s.name AS server_name, i.name AS inbound_name,
                    source.id AS subscription_id
             FROM vpn_v2_subscription_nodes n
             INNER JOIN vpn_v2_subscriptions source ON source.id = n.subscription_id
             INNER JOIN vpn_v2_servers s ON s.id = n.server_id
             INNER JOIN vpn_v2_inbounds i ON i.id = n.inbound_id
             WHERE source.user_id = ? AND n.status NOT IN ('deleted', 'deleting')
               AND n.subscription_id <> ?
               AND NOT EXISTS (
                   SELECT 1 FROM vpn_v2_subscription_items item
                   WHERE item.parent_subscription_id = ? AND item.connection_id = n.id
                     AND item.deleted_at IS NULL
               )
             ORDER BY n.id ASC",
            [(int)$parent['user_id'], $parentId, $parentId]
        )->get() ?: [];
    }

    public function recordNodeSync(int $nodeId, bool $success, ?string $error = null): void
    {
        $now = date('Y-m-d H:i:s');
        db()->query(
            'UPDATE vpn_v2_subscription_nodes
             SET sync_status = ?, sync_error = ?, last_sync_at = ?, last_error = ?, updated_at = ?
             WHERE id = ?',
            [
                $success ? 'synced' : 'failed',
                $success ? null : mb_substr(trim((string)$error), 0, 1000),
                $success ? $now : null,
                $success ? null : mb_substr(trim((string)$error), 0, 1000),
                $now,
                $nodeId,
            ]
        );
    }

    private function ids(string $sql, array $params): array
    {
        $rows = db()->query($sql, $params)->get() ?: [];

        return array_values(array_unique(array_filter(array_map(
            static fn(array $row): int => (int)($row['id'] ?? 0),
            $rows
        ))));
    }
}
