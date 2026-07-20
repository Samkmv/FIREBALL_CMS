<?php

namespace Fireball\VpnManagerV2\Repositories;

use Fireball\VpnManagerV2\Support\Uuid;
use Fireball\VpnManagerV2\Services\RemoteClientCredentialService;

final class PlanReconciliationRepository
{
    public const ELIGIBLE_STATUSES = [
        'active',
        'provisioning',
        'provisioning_failed',
        'partial_sync',
        'sync_error',
        'suspended',
    ];

    public function plan(int $planId): ?array
    {
        $row = db()->query(
            'SELECT id, name, is_active FROM vpn_v2_plans WHERE id = ? AND deleted_at IS NULL LIMIT 1',
            [$planId]
        )->getOne();

        return is_array($row) ? $row : null;
    }

    public function activePlanNodes(int $planId): array
    {
        return db()->query(
            "SELECT pn.id AS plan_node_id, pn.plan_id, pn.server_id, pn.inbound_id,
                    pn.flow_override, pn.is_enabled, pn.sort_order,
                    s.name AS server_name, s.country_code, s.is_enabled AS server_is_enabled,
                    s.maintenance_mode, s.allow_new_connections,
                    i.name AS inbound_name, i.remote_inbound_id, i.protocol, i.network,
                    i.security, i.default_flow, i.stream_settings_json,
                    i.is_enabled AS inbound_is_enabled, i.status AS inbound_status
             FROM vpn_v2_plan_nodes pn
             INNER JOIN vpn_v2_servers s ON s.id = pn.server_id
             INNER JOIN vpn_v2_inbounds i ON i.id = pn.inbound_id AND i.server_id = pn.server_id
             WHERE pn.plan_id = ? AND pn.is_enabled = 1
               AND s.is_enabled = 1 AND s.maintenance_mode = 0 AND s.allow_new_connections = 1
               AND i.is_enabled = 1 AND i.status = 'active'
             ORDER BY pn.sort_order ASC, pn.id ASC",
            [$planId]
        )->get() ?: [];
    }

    public function allPlanNodes(int $planId): array
    {
        return db()->query(
            'SELECT pn.id AS plan_node_id, pn.plan_id, pn.server_id, pn.inbound_id,
                    pn.flow_override, pn.is_enabled, s.name AS server_name, s.country_code,
                    s.is_enabled AS server_is_enabled, s.maintenance_mode, s.allow_new_connections,
                    i.name AS inbound_name,
                    i.is_enabled AS inbound_is_enabled, i.status AS inbound_status
             FROM vpn_v2_plan_nodes pn
             INNER JOIN vpn_v2_servers s ON s.id = pn.server_id
             INNER JOIN vpn_v2_inbounds i ON i.id = pn.inbound_id AND i.server_id = pn.server_id
             WHERE pn.plan_id = ? ORDER BY pn.id ASC',
            [$planId]
        )->get() ?: [];
    }

    public function planNode(int $planNodeId): ?array
    {
        $row = db()->query(
            "SELECT pn.id AS plan_node_id, pn.plan_id, pn.server_id, pn.inbound_id,
                    pn.flow_override, pn.is_enabled, pn.sort_order,
                    s.name AS server_name, s.country_code, s.is_enabled AS server_is_enabled,
                    s.maintenance_mode, s.allow_new_connections,
                    i.name AS inbound_name, i.remote_inbound_id, i.protocol, i.network,
                    i.security, i.default_flow, i.stream_settings_json,
                    i.is_enabled AS inbound_is_enabled, i.status AS inbound_status
             FROM vpn_v2_plan_nodes pn
             INNER JOIN vpn_v2_servers s ON s.id = pn.server_id
             INNER JOIN vpn_v2_inbounds i ON i.id = pn.inbound_id AND i.server_id = pn.server_id
             WHERE pn.id = ? LIMIT 1",
            [$planNodeId]
        )->getOne();

        return is_array($row) ? $row : null;
    }

    public function planNodeForTarget(int $planId, int $serverId, int $inboundId): ?array
    {
        $row = db()->query(
            "SELECT id AS plan_node_id FROM vpn_v2_plan_nodes
             WHERE plan_id = ? AND server_id = ? AND inbound_id = ? AND is_enabled = 1 LIMIT 1",
            [$planId, $serverId, $inboundId]
        )->getOne();
        if (!is_array($row)) {
            return null;
        }

        return $this->planNode((int)$row['plan_node_id']);
    }

    public function eligibleSubscriptionCount(int $planId): int
    {
        [$statusSql, $params] = $this->statusClause(self::ELIGIBLE_STATUSES);

        return (int)db()->query(
            "SELECT COUNT(*) FROM vpn_v2_subscriptions
             WHERE plan_id = ? AND status IN ({$statusSql}) AND starts_at <= NOW()
               AND (expires_at IS NULL OR expires_at > NOW())",
            array_merge([$planId], $params)
        )->getColumn();
    }

    public function mismatchSubscriptionCount(int $planId): int
    {
        [$statusSql, $params] = $this->statusClause(self::ELIGIBLE_STATUSES);

        return (int)db()->query(
            "SELECT COUNT(*)
             FROM vpn_v2_subscriptions sub
             WHERE sub.plan_id = ? AND sub.status IN ({$statusSql}) AND sub.starts_at <= NOW()
               AND (sub.expires_at IS NULL OR sub.expires_at > NOW())
               AND (
                    EXISTS (
                        SELECT 1
                        FROM vpn_v2_plan_nodes pn
                        INNER JOIN vpn_v2_servers srv ON srv.id = pn.server_id AND srv.is_enabled = 1
                            AND srv.maintenance_mode = 0 AND srv.allow_new_connections = 1
                        INNER JOIN vpn_v2_inbounds ib ON ib.id = pn.inbound_id
                            AND ib.server_id = pn.server_id AND ib.is_enabled = 1 AND ib.status = 'active'
                        WHERE pn.plan_id = sub.plan_id AND pn.is_enabled = 1
                          AND NOT EXISTS (
                              SELECT 1 FROM vpn_v2_subscription_nodes sn
                              WHERE sn.subscription_id = sub.id
                                AND sn.server_id = pn.server_id AND sn.inbound_id = pn.inbound_id
                                AND sn.status IN ('active', 'disabled')
                          )
                    )
                    OR EXISTS (
                        SELECT 1 FROM vpn_v2_subscription_nodes sn
                        WHERE sn.subscription_id = sub.id AND sn.status NOT IN ('deleted', 'deleting')
                          AND NOT EXISTS (
                              SELECT 1 FROM vpn_v2_plan_nodes pn
                              WHERE pn.plan_id = sub.plan_id AND pn.is_enabled = 1
                                AND pn.server_id = sn.server_id AND pn.inbound_id = sn.inbound_id
                          )
                    )
                    OR EXISTS (
                        SELECT 1
                        FROM vpn_v2_plan_nodes pn
                        INNER JOIN vpn_v2_servers srv ON srv.id = pn.server_id AND srv.is_enabled = 1
                            AND srv.maintenance_mode = 0 AND srv.allow_new_connections = 1
                        INNER JOIN vpn_v2_inbounds ib ON ib.id = pn.inbound_id
                            AND ib.server_id = pn.server_id AND ib.is_enabled = 1 AND ib.status = 'active'
                        INNER JOIN vpn_v2_subscription_nodes sn
                            ON sn.server_id = pn.server_id
                           AND sn.inbound_id = pn.inbound_id AND sn.status IN ('active', 'disabled', 'sync_error')
                        WHERE pn.plan_id = sub.plan_id AND pn.is_enabled = 1
                          AND sn.subscription_id = sub.id
                          AND LOWER(TRIM(COALESCE(sn.flow, ''))) <> LOWER(TRIM(
                              CASE
                                  WHEN pn.flow_override IS NOT NULL THEN pn.flow_override
                                  WHEN LOWER(ib.protocol) = 'vless'
                                   AND LOWER(COALESCE(NULLIF(ib.network, ''), 'tcp')) IN ('tcp', 'raw')
                                   AND LOWER(COALESCE(NULLIF(ib.security, ''), 'none')) = 'reality'
                                      THEN 'xtls-rprx-vision'
                                  ELSE ''
                              END
                          ))
                    )
               )",
            array_merge([$planId], $params)
        )->getColumn();
    }

    public function missingNodeCountForPlan(int $planId): int
    {
        [$statusSql, $params] = $this->statusClause(self::ELIGIBLE_STATUSES);

        return (int)db()->query(
            "SELECT COUNT(*)
             FROM vpn_v2_subscriptions sub
             INNER JOIN vpn_v2_plan_nodes pn ON pn.plan_id = sub.plan_id AND pn.is_enabled = 1
             INNER JOIN vpn_v2_servers srv ON srv.id = pn.server_id AND srv.is_enabled = 1
                AND srv.maintenance_mode = 0 AND srv.allow_new_connections = 1
             INNER JOIN vpn_v2_inbounds ib ON ib.id = pn.inbound_id
                AND ib.server_id = pn.server_id AND ib.is_enabled = 1 AND ib.status = 'active'
             WHERE sub.plan_id = ? AND sub.status IN ({$statusSql}) AND sub.starts_at <= NOW()
               AND (sub.expires_at IS NULL OR sub.expires_at > NOW())
               AND NOT EXISTS (
                   SELECT 1 FROM vpn_v2_subscription_nodes sn
                   WHERE sn.subscription_id = sub.id
                     AND sn.server_id = pn.server_id AND sn.inbound_id = pn.inbound_id
                     AND sn.status IN ('active', 'disabled')
               )",
            array_merge([$planId], $params)
        )->getColumn();
    }

    public function eligibleSubscriptions(int $planId, int $afterId = 0, int $limit = 0): array
    {
        [$statusSql, $params] = $this->statusClause(self::ELIGIBLE_STATUSES);
        $limitSql = $limit > 0 ? ' LIMIT ' . max(1, min(500, $limit)) : '';

        return db()->query(
            "SELECT id, user_id, plan_id, status, starts_at, expires_at, traffic_limit_bytes,
                    device_limit, revision, created_by, last_error, created_at, updated_at
             FROM vpn_v2_subscriptions
             WHERE plan_id = ? AND id > ? AND status IN ({$statusSql}) AND starts_at <= NOW()
               AND (expires_at IS NULL OR expires_at > NOW())
             ORDER BY id ASC{$limitSql}",
            array_merge([$planId, max(0, $afterId)], $params)
        )->get() ?: [];
    }

    public function eligibleSubscription(int $subscriptionId): ?array
    {
        [$statusSql, $params] = $this->statusClause(self::ELIGIBLE_STATUSES);
        $row = db()->query(
            "SELECT id, user_id, plan_id, status, starts_at, expires_at, traffic_limit_bytes,
                    device_limit, revision, created_by, last_error, created_at, updated_at
             FROM vpn_v2_subscriptions WHERE id = ? AND status IN ({$statusSql}) AND starts_at <= NOW()
               AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1",
            array_merge([$subscriptionId], $params)
        )->getOne();

        return is_array($row) ? $row : null;
    }

    public function subscription(int $subscriptionId): ?array
    {
        $row = db()->query(
            'SELECT id, user_id, plan_id, status, starts_at, expires_at, traffic_limit_bytes,
                    device_limit, revision, created_by, last_error, created_at, updated_at
             FROM vpn_v2_subscriptions WHERE id = ? LIMIT 1',
            [$subscriptionId]
        )->getOne();

        return is_array($row) ? $row : null;
    }

    public function subscriptionNodes(int $subscriptionId): array
    {
        return db()->query(
            'SELECT id, subscription_id, server_id, inbound_id, remote_client_id, client_uuid,
                    encrypted_client_credential,
                    client_email, client_sub_id, protocol, network, security, flow, status,
                    desired_enabled, is_obsolete, traffic_limit_bytes, traffic_used_bytes,
                    upload_bytes, download_bytes, traffic_synced_at, traffic_sync_status,
                    last_sync_at, last_error, created_at, updated_at
             FROM vpn_v2_subscription_nodes WHERE subscription_id = ? ORDER BY id ASC',
            [$subscriptionId]
        )->get() ?: [];
    }

    public function node(int $nodeId): ?array
    {
        $row = db()->query(
            'SELECT n.id, n.subscription_id, n.server_id, n.inbound_id, n.remote_client_id,
                    n.client_uuid, n.encrypted_client_credential, n.client_email, n.client_sub_id, n.protocol, n.network,
                    n.security, n.flow, n.status, n.desired_enabled, n.is_obsolete,
                    n.traffic_limit_bytes, n.traffic_used_bytes, n.last_sync_at, n.last_error,
                    sub.user_id, sub.plan_id, sub.status AS subscription_status,
                    sub.starts_at, sub.expires_at, sub.device_limit,
                    sub.traffic_limit_bytes AS subscription_traffic_limit_bytes, sub.created_by
             FROM vpn_v2_subscription_nodes n
             INNER JOIN vpn_v2_subscriptions sub ON sub.id = n.subscription_id
             WHERE n.id = ? LIMIT 1',
            [$nodeId]
        )->getOne();

        return is_array($row) ? $row : null;
    }

    public function createOrClaimNode(array $subscription, array $planNode, ?string $flow, array $identity = []): array
    {
        $database = db();
        $database->beginTransaction();
        try {
            $database->query(
                'SELECT id FROM vpn_v2_subscriptions WHERE id = ? FOR UPDATE',
                [(int)$subscription['id']]
            )->getOne();
            $existing = $database->query(
                'SELECT id, status, is_obsolete FROM vpn_v2_subscription_nodes
                 WHERE subscription_id = ? AND server_id = ? AND inbound_id = ? FOR UPDATE',
                [(int)$subscription['id'], (int)$planNode['server_id'], (int)$planNode['inbound_id']]
            )->getOne();
            $desiredEnabled = (string)$subscription['status'] === 'suspended' ? 0 : 1;
            $now = date('Y-m-d H:i:s');
            if (is_array($existing)) {
                $status = (string)$existing['status'];
                if (in_array($status, ['active', 'disabled'], true)) {
                    $database->query(
                        'UPDATE vpn_v2_subscription_nodes
                         SET is_obsolete = 0, desired_enabled = ?, updated_at = ? WHERE id = ?',
                        [$desiredEnabled, $now, (int)$existing['id']]
                    );
                    $database->commit();

                    return ['id' => (int)$existing['id'], 'claimed' => false, 'status' => $status, 'existing' => true];
                }

                $database->query(
                    "UPDATE vpn_v2_subscription_nodes
                     SET protocol = ?, network = ?, security = ?, flow = ?, status = 'creating',
                         desired_enabled = ?, is_obsolete = 0, expires_at = ?, device_limit = ?,
                         traffic_limit_bytes = ?, last_error = NULL, updated_at = ?
                     WHERE id = ?",
                    [
                        strtolower((string)$planNode['protocol']),
                        $this->nullable($planNode['network'] ?? null),
                        $this->nullable($planNode['security'] ?? null),
                        $flow,
                        $desiredEnabled,
                        $subscription['expires_at'] ?? null,
                        (int)$subscription['device_limit'],
                        $subscription['traffic_limit_bytes'] !== null ? (int)$subscription['traffic_limit_bytes'] : null,
                        $now,
                        (int)$existing['id'],
                    ]
                );
                $database->commit();

                return ['id' => (int)$existing['id'], 'claimed' => true, 'status' => 'creating', 'existing' => true];
            }

            $uuid = trim((string)($identity['client_uuid'] ?? ''));
            $email = trim((string)($identity['remote_client_name'] ?? ''));
            if ($uuid === '' || $email === '') {
                throw new \RuntimeException('A stable VPN client identity is required.');
            }
            $protocol = strtolower(trim((string)$planNode['protocol']));
            $subId = $protocol === 'vless' ? bin2hex(random_bytes(8)) : null;
            $sortOrder = (int)$database->query(
                'SELECT COALESCE(MAX(sort_order), 0) + 10
                 FROM vpn_v2_subscription_nodes WHERE subscription_id = ?',
                [(int)$subscription['id']]
            )->getColumn();
            $database->query(
                'INSERT INTO vpn_v2_subscription_nodes
                    (subscription_id, server_id, inbound_id, sort_order, remote_client_id, client_uuid, encrypted_client_credential,
                     client_email, remote_client_name, country_code, client_sub_id,
                     protocol, network, security, flow, status,
                     desired_enabled, is_obsolete, expires_at, device_limit,
                     traffic_limit_bytes, traffic_used_bytes,
                     upload_bytes, download_bytes, traffic_synced_at, traffic_sync_status, sync_status,
                     last_sync_at, last_error, created_at, updated_at)
                 VALUES (?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'creating\', ?, 0, ?, ?, ?, 0,
                         0, 0, NULL, \'pending\', \'pending\', NULL, NULL, ?, ?)',
                [
                    (int)$subscription['id'],
                    (int)$planNode['server_id'],
                    (int)$planNode['inbound_id'],
                    max(10, $sortOrder),
                    $uuid,
                    (new RemoteClientCredentialService())->encryptForStorage(
                        $protocol,
                        isset($identity['client_password']) ? (string)$identity['client_password'] : null
                    ),
                    $email,
                    $email,
                    strtoupper((string)($identity['country_code'] ?? $planNode['country_code'] ?? '')),
                    $subId,
                    $protocol,
                    $this->nullable($planNode['network'] ?? null),
                    $this->nullable($planNode['security'] ?? null),
                    $flow,
                    $desiredEnabled,
                    $subscription['expires_at'] ?? null,
                    (int)$subscription['device_limit'],
                    $subscription['traffic_limit_bytes'] !== null ? (int)$subscription['traffic_limit_bytes'] : null,
                    $now,
                    $now,
                ]
            );
            $id = (int)$database->getInsertId();
            $database->commit();

            return ['id' => $id, 'claimed' => true, 'status' => 'creating', 'existing' => false];
        } catch (\Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    public function markObsolete(int $nodeId): bool
    {
        db()->query(
            'UPDATE vpn_v2_subscription_nodes SET is_obsolete = 1, updated_at = ?
             WHERE id = ? AND is_obsolete = 0',
            [date('Y-m-d H:i:s'), $nodeId]
        );

        return db()->rowCount() === 1;
    }

    public function markTargetObsolete(int $planId, int $serverId, int $inboundId): int
    {
        db()->query(
            'UPDATE vpn_v2_subscription_nodes sn
             INNER JOIN vpn_v2_subscriptions sub ON sub.id = sn.subscription_id AND sub.plan_id = ?
             SET sn.is_obsolete = 1, sn.updated_at = ?
             WHERE sn.server_id = ? AND sn.inbound_id = ?
               AND sn.status NOT IN (\'deleted\', \'deleting\') AND sn.is_obsolete = 0',
            [$planId, date('Y-m-d H:i:s'), $serverId, $inboundId]
        );

        return db()->rowCount();
    }

    public function clearCurrentPlanObsolete(int $subscriptionId, int $planId): int
    {
        db()->query(
            'UPDATE vpn_v2_subscription_nodes sn
             INNER JOIN vpn_v2_plan_nodes pn
                ON pn.plan_id = ? AND pn.server_id = sn.server_id
               AND pn.inbound_id = sn.inbound_id AND pn.is_enabled = 1
             SET sn.is_obsolete = 0, sn.updated_at = ?
             WHERE sn.subscription_id = ? AND sn.is_obsolete = 1',
            [$planId, date('Y-m-d H:i:s'), $subscriptionId]
        );

        return db()->rowCount();
    }

    public function confirmNode(int $nodeId, ?string $flow, ?int $trafficUsedBytes = null): void
    {
        $node = $this->node($nodeId);
        if (!is_array($node)) {
            throw new \RuntimeException('VPN subscription node not found: ' . $nodeId);
        }
        $status = !empty($node['desired_enabled']) ? 'active' : 'disabled';
        $now = date('Y-m-d H:i:s');
        db()->query(
            'UPDATE vpn_v2_subscription_nodes
             SET flow = ?, status = ?, is_obsolete = 0,
                 traffic_used_bytes = COALESCE(?, traffic_used_bytes),
                 last_sync_at = ?, last_error = NULL, updated_at = ? WHERE id = ?',
            [$flow, $status, $trafficUsedBytes, $now, $now, $nodeId]
        );
    }

    public function finishSubscription(int $subscriptionId, string $originalStatus, ?string $error): void
    {
        $status = $error === null
            ? ($originalStatus === 'suspended' ? 'suspended' : 'active')
            : ($originalStatus === 'suspended' ? 'suspended' : 'partial_sync');
        db()->query(
            'UPDATE vpn_v2_subscriptions SET status = ?, last_error = ?, updated_at = ? WHERE id = ?',
            [$status, $error !== null ? mb_substr($error, 0, 1000) : null, date('Y-m-d H:i:s'), $subscriptionId]
        );
    }

    public function obsoleteNodesForPlan(int $planId): array
    {
        return db()->query(
            "SELECT n.id FROM vpn_v2_subscription_nodes n
             INNER JOIN vpn_v2_subscriptions sub ON sub.id = n.subscription_id
             WHERE sub.plan_id = ? AND n.is_obsolete = 1
               AND n.status NOT IN ('deleted', 'deleting') ORDER BY n.id ASC",
            [$planId]
        )->get() ?: [];
    }

    public function obsoleteNodeCount(int $planId): int
    {
        return (int)db()->query(
            "SELECT COUNT(*) FROM vpn_v2_subscription_nodes n
             INNER JOIN vpn_v2_subscriptions sub ON sub.id = n.subscription_id
             WHERE sub.plan_id = ? AND n.is_obsolete = 1
               AND n.status NOT IN ('deleted', 'deleting')",
            [$planId]
        )->getColumn();
    }

    public function obsoleteSubscriptionCount(int $planId): int
    {
        return (int)db()->query(
            "SELECT COUNT(DISTINCT n.subscription_id)
             FROM vpn_v2_subscription_nodes n
             INNER JOIN vpn_v2_subscriptions sub ON sub.id = n.subscription_id
             WHERE sub.plan_id = ? AND n.is_obsolete = 1
               AND n.status NOT IN ('deleted', 'deleting')",
            [$planId]
        )->getColumn();
    }

    public function obsoleteTargetsForPlan(int $planId): array
    {
        return db()->query(
            "SELECT n.server_id, n.inbound_id, srv.name AS server_name, ib.name AS inbound_name,
                    COUNT(*) AS connection_count, COUNT(DISTINCT n.subscription_id) AS subscription_count
             FROM vpn_v2_subscription_nodes n
             INNER JOIN vpn_v2_subscriptions sub ON sub.id = n.subscription_id
             INNER JOIN vpn_v2_servers srv ON srv.id = n.server_id
             INNER JOIN vpn_v2_inbounds ib ON ib.id = n.inbound_id
             WHERE sub.plan_id = ? AND n.is_obsolete = 1
               AND n.status NOT IN ('deleted', 'deleting')
             GROUP BY n.server_id, n.inbound_id, srv.name, ib.name
             ORDER BY n.server_id, n.inbound_id",
            [$planId]
        )->get() ?: [];
    }

    public function obsoleteSubscriptions(int $planId, int $afterId = 0, int $limit = 0): array
    {
        $limitSql = $limit > 0 ? ' LIMIT ' . max(1, min(500, $limit)) : '';

        return db()->query(
            "SELECT DISTINCT sub.id, sub.user_id, sub.plan_id, sub.status, sub.revision
             FROM vpn_v2_subscriptions sub
             INNER JOIN vpn_v2_subscription_nodes n ON n.subscription_id = sub.id
             WHERE sub.plan_id = ? AND sub.id > ? AND n.is_obsolete = 1
               AND n.status NOT IN ('deleted', 'deleting')
             ORDER BY sub.id ASC{$limitSql}",
            [$planId, max(0, $afterId)]
        )->get() ?: [];
    }

    public function obsoleteNodesForSubscription(int $subscriptionId): array
    {
        return db()->query(
            "SELECT id FROM vpn_v2_subscription_nodes
             WHERE subscription_id = ? AND is_obsolete = 1
               AND status NOT IN ('deleted', 'deleting') ORDER BY id ASC",
            [$subscriptionId]
        )->get() ?: [];
    }

    public function duplicateDiagnostics(): array
    {
        return db()->query(
            'SELECT subscription_id, server_id, inbound_id, COUNT(*) AS duplicate_count,
                    GROUP_CONCAT(id ORDER BY id) AS node_ids
             FROM vpn_v2_subscription_nodes
             GROUP BY subscription_id, server_id, inbound_id HAVING COUNT(*) > 1'
        )->get() ?: [];
    }

    public function queueOperation(int $planId, int $initiatedBy, int $batchSize = 20, array $options = []): string
    {
        $operationId = Uuid::v4();
        $now = date('Y-m-d H:i:s');
        $json = json_encode($options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        db()->query(
            'INSERT INTO vpn_v2_reconcile_operations
                (operation_id, plan_id, status, initiated_by, last_subscription_id, batch_size,
                 options_json, total_count, processed_count, success_count, failure_count,
                 skipped_count, started_at, finished_at, last_error, created_at, updated_at)
             VALUES (?, ?, \'pending\', ?, 0, ?, ?, ?, 0, 0, 0, 0, NULL, NULL, NULL, ?, ?)',
            [
                $operationId,
                $planId,
                $initiatedBy > 0 ? $initiatedBy : null,
                max(1, min(100, $batchSize)),
                $json ?: null,
                !empty($options['remove_obsolete'])
                    ? $this->obsoleteSubscriptionCount($planId)
                    : $this->eligibleSubscriptionCount($planId),
                $now,
                $now,
            ]
        );

        return $operationId;
    }

    public function nextOperation(?string $operationId = null): ?array
    {
        $row = $operationId !== null && trim($operationId) !== ''
            ? db()->query(
                "SELECT * FROM vpn_v2_reconcile_operations
                 WHERE operation_id = ? AND status = 'pending' LIMIT 1",
                [trim($operationId)]
            )->getOne()
            : db()->query(
                "SELECT * FROM vpn_v2_reconcile_operations
                 WHERE status = 'pending' ORDER BY id ASC LIMIT 1"
            )->getOne();

        return is_array($row) ? $row : null;
    }

    public function claimOperation(int $id): ?array
    {
        $database = db();
        $database->beginTransaction();
        try {
            $row = $database->query(
                'SELECT * FROM vpn_v2_reconcile_operations WHERE id = ? FOR UPDATE',
                [$id]
            )->getOne();
            if (!is_array($row) || (string)$row['status'] !== 'pending') {
                $database->rollBack();
                return null;
            }
            $now = date('Y-m-d H:i:s');
            $database->query(
                "UPDATE vpn_v2_reconcile_operations
                 SET status = 'running', started_at = COALESCE(started_at, ?), updated_at = ? WHERE id = ?",
                [$now, $now, $id]
            );
            $database->commit();
            $row['status'] = 'running';

            return $row;
        } catch (\Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    public function advanceOperation(int $id, int $cursor, array $counts, bool $finished): void
    {
        $failures = max(0, (int)($counts['failure'] ?? 0));
        $previousFailures = (int)db()->query(
            'SELECT failure_count FROM vpn_v2_reconcile_operations WHERE id = ? LIMIT 1',
            [$id]
        )->getColumn();
        $status = $finished
            ? (($previousFailures + $failures) > 0 ? 'completed_with_errors' : 'completed')
            : 'pending';
        $now = date('Y-m-d H:i:s');
        db()->query(
            'UPDATE vpn_v2_reconcile_operations
             SET status = ?, last_subscription_id = ?, processed_count = processed_count + ?,
                 success_count = success_count + ?, failure_count = failure_count + ?,
                 skipped_count = skipped_count + ?, finished_at = ?, updated_at = ? WHERE id = ?',
            [
                $status,
                max(0, $cursor),
                max(0, (int)($counts['processed'] ?? 0)),
                max(0, (int)($counts['success'] ?? 0)),
                $failures,
                max(0, (int)($counts['skipped'] ?? 0)),
                $finished ? $now : null,
                $now,
                $id,
            ]
        );
    }

    public function failOperation(int $id, string $safeError): void
    {
        $now = date('Y-m-d H:i:s');
        db()->query(
            "UPDATE vpn_v2_reconcile_operations
             SET status = 'failed', last_error = ?, finished_at = ?, updated_at = ? WHERE id = ?",
            [mb_substr($safeError, 0, 1000), $now, $now, $id]
        );
    }

    public function releaseOperation(int $id): void
    {
        db()->query(
            "UPDATE vpn_v2_reconcile_operations
             SET status = 'pending', updated_at = ? WHERE id = ? AND status = 'running'",
            [date('Y-m-d H:i:s'), $id]
        );
    }

    public function latestOperation(int $planId): ?array
    {
        $row = db()->query(
            'SELECT operation_id, status, total_count, processed_count, success_count,
                    failure_count, skipped_count, started_at, finished_at, created_at
             FROM vpn_v2_reconcile_operations WHERE plan_id = ? ORDER BY id DESC LIMIT 1',
            [$planId]
        )->getOne();

        return is_array($row) ? $row : null;
    }

    public function operationProgress(int $operationId): ?array
    {
        $row = db()->query(
            'SELECT operation_id, status, total_count, processed_count, success_count,
                    failure_count, skipped_count, started_at, finished_at, created_at
             FROM vpn_v2_reconcile_operations WHERE id = ? LIMIT 1',
            [$operationId]
        )->getOne();

        return is_array($row) ? $row : null;
    }

    private function statusClause(array $statuses): array
    {
        return [implode(',', array_fill(0, count($statuses), '?')), array_values($statuses)];
    }

    private function nullable(mixed $value): ?string
    {
        $value = strtolower(trim((string)$value));

        return $value !== '' ? mb_substr($value, 0, 40) : null;
    }
}
