<?php

namespace Fireball\VpnManagerV2\Repositories;

final class SubscriptionRepository
{
    public function usersForForm(): array
    {
        return db()->query(
            'SELECT id, name, login, email, role FROM users ORDER BY id ASC'
        )->get() ?: [];
    }

    public function findUser(int $id): ?array
    {
        $row = db()->query(
            'SELECT id, name, login, email, role FROM users WHERE id = ? LIMIT 1',
            [$id]
        )->getOne();

        return is_array($row) ? $row : null;
    }

    public function activePlansForForm(): array
    {
        return db()->query(
            'SELECT p.id, p.name, p.duration_days, p.traffic_limit_bytes, p.device_limit,
                    COUNT(n.id) AS node_count
             FROM vpn_v2_plans p
             LEFT JOIN vpn_v2_plan_nodes n ON n.plan_id = p.id AND n.is_enabled = 1
             WHERE p.is_active = 1
             GROUP BY p.id, p.name, p.duration_days, p.traffic_limit_bytes, p.device_limit
             HAVING COUNT(n.id) > 0
             ORDER BY p.id ASC'
        )->get() ?: [];
    }

    public function activePlan(int $id): ?array
    {
        $row = db()->query(
            'SELECT id, name, description, duration_days, traffic_limit_bytes, device_limit, is_active
             FROM vpn_v2_plans WHERE id = ? AND is_active = 1 LIMIT 1',
            [$id]
        )->getOne();

        return is_array($row) ? $row : null;
    }

    public function activePlanNodes(int $planId): array
    {
        return db()->query(
            "SELECT n.id AS plan_node_id, n.plan_id, n.server_id, n.inbound_id, n.flow_override,
                    n.sort_order, s.name AS server_name, s.code AS server_code,
                    s.status AS server_status, s.is_enabled AS server_is_enabled,
                    i.remote_inbound_id, i.name AS inbound_name, i.protocol, i.network, i.security,
                    i.default_flow, i.status AS inbound_status, i.is_enabled AS inbound_is_enabled
             FROM vpn_v2_plan_nodes n
             INNER JOIN vpn_v2_servers s ON s.id = n.server_id
             INNER JOIN vpn_v2_inbounds i ON i.id = n.inbound_id AND i.server_id = n.server_id
             WHERE n.plan_id = ? AND n.is_enabled = 1
               AND s.is_enabled = 1 AND i.is_enabled = 1 AND i.status = 'active'
             ORDER BY n.sort_order ASC, n.id ASC",
            [$planId]
        )->get() ?: [];
    }

    public function tokenExists(string $token): bool
    {
        return (bool)db()->query(
            'SELECT id FROM vpn_v2_subscriptions WHERE subscription_token = ? LIMIT 1',
            [$token]
        )->getOne();
    }

    public function createLocal(array $subscription, array $nodes): int
    {
        $database = db();
        $database->beginTransaction();
        try {
            $now = date('Y-m-d H:i:s');
            $database->query(
                'INSERT INTO vpn_v2_subscriptions
                    (user_id, plan_id, status, starts_at, expires_at, traffic_limit_bytes, device_limit,
                     subscription_token, revision, config_updated_at, created_by, last_error, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NULL, ?, NULL, ?, ?)',
                [
                    $subscription['user_id'],
                    $subscription['plan_id'],
                    'provisioning',
                    $subscription['starts_at'],
                    $subscription['expires_at'],
                    $subscription['traffic_limit_bytes'],
                    $subscription['device_limit'],
                    $subscription['subscription_token'],
                    $subscription['created_by'],
                    $now,
                    $now,
                ]
            );
            $subscriptionId = (int)$database->getInsertId();

            foreach ($nodes as $node) {
                $email = 'vpn-v2-u' . (int)$subscription['user_id']
                    . '-s' . $subscriptionId
                    . '-p' . (int)$node['plan_node_id'];
                $database->query(
                    'INSERT INTO vpn_v2_subscription_nodes
                        (subscription_id, server_id, inbound_id, remote_client_id, client_uuid, client_email,
                         client_sub_id, protocol, network, security, flow, status, traffic_limit_bytes,
                         traffic_used_bytes, last_sync_at, last_error, created_at, updated_at)
                     VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NULL, NULL, ?, ?)',
                    [
                        $subscriptionId,
                        $node['server_id'],
                        $node['inbound_id'],
                        $node['client_uuid'],
                        $email,
                        $node['client_sub_id'],
                        $node['protocol'],
                        $node['network'],
                        $node['security'],
                        $node['flow'],
                        'creating',
                        $subscription['traffic_limit_bytes'],
                        $now,
                        $now,
                    ]
                );
            }

            $this->logEvent(
                'subscription.local_created',
                $subscriptionId,
                null,
                null,
                (int)$subscription['user_id'],
                (int)$subscription['created_by'],
                ['plan_id' => (int)$subscription['plan_id'], 'node_count' => count($nodes)]
            );
            $database->commit();

            return $subscriptionId;
        } catch (\Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    public function all(): array
    {
        return db()->query(
            'SELECT s.id, s.user_id, s.plan_id, s.status, s.starts_at, s.expires_at,
                    s.traffic_limit_bytes, s.device_limit, s.revision, s.config_updated_at,
                    s.created_by, s.last_error, s.created_at, s.updated_at,
                    u.name AS user_name, u.email AS user_email, p.name AS plan_name,
                    COUNT(n.id) AS node_count,
                    SUM(CASE WHEN n.status = "active" THEN 1 ELSE 0 END) AS active_node_count
             FROM vpn_v2_subscriptions s
             INNER JOIN users u ON u.id = s.user_id
             INNER JOIN vpn_v2_plans p ON p.id = s.plan_id
             LEFT JOIN vpn_v2_subscription_nodes n ON n.subscription_id = s.id
             GROUP BY s.id, s.user_id, s.plan_id, s.status, s.starts_at, s.expires_at,
                      s.traffic_limit_bytes, s.device_limit, s.revision, s.config_updated_at,
                      s.created_by, s.last_error, s.created_at, s.updated_at,
                      u.name, u.email, p.name
             ORDER BY s.id ASC'
        )->get() ?: [];
    }

    public function find(int $id): ?array
    {
        $row = db()->query(
            "SELECT s.id, s.user_id, s.plan_id, s.status, s.starts_at, s.expires_at,
                    s.traffic_limit_bytes, s.device_limit, s.revision, s.config_updated_at,
                    s.created_by, s.last_error, s.created_at, s.updated_at,
                    CONCAT(LEFT(s.subscription_token, 4), '…', RIGHT(s.subscription_token, 4)) AS token_preview,
                    u.name AS user_name, u.email AS user_email, p.name AS plan_name
             FROM vpn_v2_subscriptions s
             INNER JOIN users u ON u.id = s.user_id
             INNER JOIN vpn_v2_plans p ON p.id = s.plan_id
             WHERE s.id = ? LIMIT 1",
            [$id]
        )->getOne();

        return is_array($row) ? $row : null;
    }

    public function findForProvisioning(int $id): ?array
    {
        $row = db()->query(
            'SELECT id, user_id, plan_id, status, starts_at, expires_at, traffic_limit_bytes,
                    device_limit, revision, created_by, last_error, created_at, updated_at
             FROM vpn_v2_subscriptions WHERE id = ? LIMIT 1',
            [$id]
        )->getOne();

        return is_array($row) ? $row : null;
    }

    public function nodesForSubscription(int $subscriptionId): array
    {
        return db()->query(
            'SELECT n.id, n.subscription_id, n.server_id, n.inbound_id,
                    CASE WHEN n.remote_client_id IS NULL OR n.remote_client_id = "" THEN NULL
                         ELSE CONCAT(LEFT(n.remote_client_id, 8), "…", RIGHT(n.remote_client_id, 4)) END AS remote_client_preview,
                    n.client_email, n.protocol, n.network, n.security, n.flow,
                    n.status, n.traffic_limit_bytes, n.traffic_used_bytes, n.last_sync_at,
                    n.last_error, n.created_at, n.updated_at,
                    s.name AS server_name, s.code AS server_code, i.name AS inbound_name,
                    i.remote_inbound_id, u.name AS user_name, u.email AS user_email
             FROM vpn_v2_subscription_nodes n
             INNER JOIN vpn_v2_subscriptions sub ON sub.id = n.subscription_id
             INNER JOIN users u ON u.id = sub.user_id
             INNER JOIN vpn_v2_servers s ON s.id = n.server_id
             INNER JOIN vpn_v2_inbounds i ON i.id = n.inbound_id
             WHERE n.subscription_id = ?
             ORDER BY n.id ASC',
            [$subscriptionId]
        )->get() ?: [];
    }

    public function connections(): array
    {
        return db()->query(
            'SELECT n.id, n.subscription_id, n.server_id, n.inbound_id,
                    CASE WHEN n.remote_client_id IS NULL OR n.remote_client_id = "" THEN NULL
                         ELSE CONCAT(LEFT(n.remote_client_id, 8), "…", RIGHT(n.remote_client_id, 4)) END AS remote_client_preview,
                    n.client_email, n.protocol, n.network, n.security, n.flow, n.status,
                    n.traffic_limit_bytes, n.traffic_used_bytes, n.last_sync_at, n.last_error,
                    n.created_at, n.updated_at, sub.user_id, sub.plan_id,
                    u.name AS user_name, u.email AS user_email, p.name AS plan_name,
                    s.name AS server_name, s.code AS server_code, i.name AS inbound_name,
                    i.remote_inbound_id
             FROM vpn_v2_subscription_nodes n
             INNER JOIN vpn_v2_subscriptions sub ON sub.id = n.subscription_id
             INNER JOIN users u ON u.id = sub.user_id
             INNER JOIN vpn_v2_plans p ON p.id = sub.plan_id
             INNER JOIN vpn_v2_servers s ON s.id = n.server_id
             INNER JOIN vpn_v2_inbounds i ON i.id = n.inbound_id
             ORDER BY n.id ASC'
        )->get() ?: [];
    }

    public function connection(int $id): ?array
    {
        $row = db()->query(
            'SELECT n.id, n.subscription_id, n.server_id, n.inbound_id,
                    CASE WHEN n.remote_client_id IS NULL OR n.remote_client_id = "" THEN NULL
                         ELSE CONCAT(LEFT(n.remote_client_id, 8), "…", RIGHT(n.remote_client_id, 4)) END AS remote_client_preview,
                    n.client_email, n.protocol, n.network, n.security, n.flow,
                    n.status, n.traffic_limit_bytes, n.traffic_used_bytes, n.last_sync_at,
                    n.last_error, n.created_at, n.updated_at, sub.user_id, sub.plan_id,
                    sub.status AS subscription_status, sub.starts_at, sub.expires_at,
                    sub.device_limit, sub.traffic_limit_bytes AS subscription_traffic_limit_bytes,
                    sub.created_by, u.name AS user_name, u.email AS user_email,
                    p.name AS plan_name, s.name AS server_name, s.code AS server_code,
                    s.is_enabled AS server_is_enabled, i.name AS inbound_name,
                    i.remote_inbound_id, i.status AS inbound_status,
                    i.is_enabled AS inbound_is_enabled
             FROM vpn_v2_subscription_nodes n
             INNER JOIN vpn_v2_subscriptions sub ON sub.id = n.subscription_id
             INNER JOIN users u ON u.id = sub.user_id
             INNER JOIN vpn_v2_plans p ON p.id = sub.plan_id
             INNER JOIN vpn_v2_servers s ON s.id = n.server_id
             INNER JOIN vpn_v2_inbounds i ON i.id = n.inbound_id
             WHERE n.id = ? LIMIT 1',
            [$id]
        )->getOne();

        return is_array($row) ? $row : null;
    }

    public function connectionForProvisioning(int $id): ?array
    {
        $row = db()->query(
            'SELECT n.id, n.subscription_id, n.server_id, n.inbound_id, n.remote_client_id,
                    n.client_uuid, n.client_email, n.client_sub_id, n.protocol, n.network,
                    n.security, n.flow, n.status, n.traffic_limit_bytes, n.traffic_used_bytes,
                    n.last_sync_at, n.last_error, sub.user_id, sub.plan_id,
                    sub.status AS subscription_status, sub.starts_at, sub.expires_at,
                    sub.device_limit, sub.traffic_limit_bytes AS subscription_traffic_limit_bytes,
                    sub.created_by
             FROM vpn_v2_subscription_nodes n
             INNER JOIN vpn_v2_subscriptions sub ON sub.id = n.subscription_id
             WHERE n.id = ? LIMIT 1',
            [$id]
        )->getOne();

        return is_array($row) ? $row : null;
    }

    public function inbound(int $id): ?array
    {
        $row = db()->query(
            'SELECT id, server_id, remote_inbound_id, name, protocol, port, network, security,
                    default_flow, status, is_enabled
             FROM vpn_v2_inbounds WHERE id = ? LIMIT 1',
            [$id]
        )->getOne();

        return is_array($row) ? $row : null;
    }

    public function markNodeActive(int $id, string $remoteClientId): void
    {
        $now = date('Y-m-d H:i:s');
        db()->query(
            "UPDATE vpn_v2_subscription_nodes
             SET remote_client_id = ?, status = 'active', last_sync_at = ?, last_error = NULL, updated_at = ?
             WHERE id = ?",
            [$remoteClientId, $now, $now, $id]
        );
    }

    public function markNodeFailure(int $id, string $status, string $safeError): void
    {
        $status = in_array($status, ['create_failed', 'sync_error'], true) ? $status : 'create_failed';
        db()->query(
            'UPDATE vpn_v2_subscription_nodes SET status = ?, last_error = ?, updated_at = ? WHERE id = ?',
            [$status, mb_substr(trim($safeError), 0, 1000), date('Y-m-d H:i:s'), $id]
        );
    }

    public function claimRetry(int $id): bool
    {
        db()->query(
            "UPDATE vpn_v2_subscription_nodes
             SET status = 'creating', last_error = NULL, updated_at = ?
             WHERE id = ? AND status IN ('create_failed', 'sync_error')",
            [date('Y-m-d H:i:s'), $id]
        );

        return db()->rowCount() === 1;
    }

    public function setSubscriptionProvisioning(int $id): void
    {
        db()->query(
            "UPDATE vpn_v2_subscriptions SET status = 'provisioning', last_error = NULL, updated_at = ? WHERE id = ?",
            [date('Y-m-d H:i:s'), $id]
        );
    }

    public function recalculateSubscriptionStatus(int $id): string
    {
        $summary = db()->query(
            "SELECT COUNT(*) AS total,
                    SUM(status = 'active') AS active_count,
                    SUM(status = 'sync_error') AS sync_error_count,
                    SUM(status = 'create_failed') AS failed_count,
                    SUM(status = 'creating') AS creating_count
             FROM vpn_v2_subscription_nodes WHERE subscription_id = ?",
            [$id]
        )->getOne() ?: [];
        $total = (int)($summary['total'] ?? 0);
        $active = (int)($summary['active_count'] ?? 0);
        $syncErrors = (int)($summary['sync_error_count'] ?? 0);
        $failed = (int)($summary['failed_count'] ?? 0);

        if ($total > 0 && $active === $total) {
            $status = 'active';
            $lastError = null;
            $configUpdatedAt = date('Y-m-d H:i:s');
        } elseif ($syncErrors > 0) {
            $status = 'sync_error';
            $lastError = $this->firstNodeError($id);
            $configUpdatedAt = null;
        } elseif ($failed > 0) {
            $status = 'provisioning_failed';
            $lastError = $this->firstNodeError($id);
            $configUpdatedAt = null;
        } else {
            $status = 'provisioning';
            $lastError = null;
            $configUpdatedAt = null;
        }

        db()->query(
            'UPDATE vpn_v2_subscriptions
             SET status = ?, last_error = ?, config_updated_at = COALESCE(?, config_updated_at), updated_at = ?
             WHERE id = ?',
            [$status, $lastError, $configUpdatedAt, date('Y-m-d H:i:s'), $id]
        );

        return $status;
    }

    public function logEvent(
        string $eventType,
        ?int $subscriptionId,
        ?int $nodeId,
        ?int $serverId,
        ?int $userId,
        ?int $adminId,
        array $context = []
    ): void {
        $safeContext = $this->sanitizeContext($context);
        $json = $safeContext !== []
            ? json_encode($safeContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null;
        db()->query(
            'INSERT INTO vpn_v2_events
                (event_type, subscription_id, node_id, server_id, user_id, admin_id, context_json, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$eventType, $subscriptionId, $nodeId, $serverId, $userId, $adminId, $json ?: null, date('Y-m-d H:i:s')]
        );
    }

    private function firstNodeError(int $subscriptionId): ?string
    {
        $value = db()->query(
            'SELECT last_error FROM vpn_v2_subscription_nodes
             WHERE subscription_id = ? AND last_error IS NOT NULL AND last_error <> ""
             ORDER BY id ASC LIMIT 1',
            [$subscriptionId]
        )->getColumn();
        $value = mb_substr(trim((string)$value), 0, 1000);

        return $value !== '' ? $value : null;
    }

    private function sanitizeContext(array $context): array
    {
        $safe = [];
        foreach ($context as $key => $value) {
            $normalized = strtolower((string)$key);
            if (str_contains($normalized, 'token')
                || str_contains($normalized, 'password')
                || str_contains($normalized, 'cookie')
                || str_contains($normalized, 'authorization')
                || str_contains($normalized, 'uuid')) {
                $safe[$key] = '[redacted]';
                continue;
            }
            $safe[$key] = is_array($value) ? $this->sanitizeContext($value) : $value;
        }

        return $safe;
    }
}
