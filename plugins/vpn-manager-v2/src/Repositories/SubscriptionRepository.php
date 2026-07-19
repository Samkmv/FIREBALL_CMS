<?php

namespace Fireball\VpnManagerV2\Repositories;

use Fireball\VpnManagerV2\Services\RemoteClientCredentialService;

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

    /**
     * Keeps legacy credentials stable when a logical profile is introduced for an
     * existing user. New connections inherit the oldest confirmed credential;
     * existing remote clients are never rewritten just to homogenize UUIDs.
     */
    public function stableCredentialForUser(int $userId): ?string
    {
        $credential = db()->query(
            "SELECT n.client_uuid
             FROM vpn_v2_subscription_nodes n
             INNER JOIN vpn_v2_subscriptions s ON s.id = n.subscription_id
             WHERE s.user_id = ? AND n.client_uuid <> ''
               AND n.protocol IN ('vless', 'vmess')
               AND n.status NOT IN ('deleted', 'deleting')
             ORDER BY CASE n.status WHEN 'active' THEN 0 WHEN 'disabled' THEN 1 ELSE 2 END,
                      n.id ASC
             LIMIT 1",
            [$userId]
        )->getColumn();

        $credential = is_scalar($credential) ? trim((string)$credential) : '';

        return $credential !== '' ? $credential : null;
    }

    public function assignProfile(int $subscriptionId, int $profileId): void
    {
        if ($subscriptionId <= 0 || $profileId <= 0) {
            return;
        }
        db()->query(
            'UPDATE vpn_v2_subscriptions SET profile_id = COALESCE(profile_id, ?), updated_at = ? WHERE id = ?',
            [$profileId, date('Y-m-d H:i:s'), $subscriptionId]
        );
    }

    public function hasOverlappingSubscription(int $userId, string $startsAt, string $expiresAt): bool
    {
        return (bool)db()->query(
            "SELECT id FROM vpn_v2_subscriptions
             WHERE user_id = ?
               AND status IN ('active', 'provisioning', 'provisioning_failed', 'partial_sync', 'sync_error', 'suspended')
               AND starts_at < ? AND (expires_at IS NULL OR expires_at > ?)
             LIMIT 1",
            [$userId, $expiresAt, $startsAt]
        )->getOne();
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
                    s.country_code, s.maintenance_mode, s.allow_new_connections,
                    i.remote_inbound_id, i.name AS inbound_name, i.protocol, i.network, i.security,
                    i.default_flow, i.status AS inbound_status, i.is_enabled AS inbound_is_enabled
             FROM vpn_v2_plan_nodes n
             INNER JOIN vpn_v2_servers s ON s.id = n.server_id
             INNER JOIN vpn_v2_inbounds i ON i.id = n.inbound_id AND i.server_id = n.server_id
             WHERE n.plan_id = ? AND n.is_enabled = 1
               AND s.is_enabled = 1 AND s.maintenance_mode = 0 AND s.allow_new_connections = 1
               AND i.is_enabled = 1 AND i.status = 'active'
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
                    (user_id, profile_id, plan_id, status, starts_at, expires_at, traffic_limit_bytes, device_limit,
                     subscription_token, subscription_token_hash, revision, config_updated_at,
                     created_by, last_error, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NULL, ?, NULL, ?, ?)',
                [
                    $subscription['user_id'],
                    (int)($subscription['profile_id'] ?? 0) > 0 ? (int)$subscription['profile_id'] : null,
                    $subscription['plan_id'],
                    'provisioning',
                    $subscription['starts_at'],
                    $subscription['expires_at'],
                    $subscription['traffic_limit_bytes'],
                    $subscription['device_limit'],
                    $subscription['subscription_token'],
                    hash('sha256', (string)$subscription['subscription_token']),
                    $subscription['created_by'],
                    $now,
                    $now,
                ]
            );
            $subscriptionId = (int)$database->getInsertId();

            foreach ($nodes as $node) {
                $remoteName = (string)$node['remote_client_name'];
                $database->query(
                    'INSERT INTO vpn_v2_subscription_nodes
                        (subscription_id, server_id, inbound_id, sort_order, remote_client_id, client_uuid, encrypted_client_credential, client_email,
                         remote_client_name, country_code, client_sub_id, protocol, network, security, flow,
                         status, sync_status, traffic_limit_bytes, traffic_used_bytes,
                         last_sync_at, last_error, created_at, updated_at)
                     VALUES (?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'pending\', ?, 0, NULL, NULL, ?, ?)',
                    [
                        $subscriptionId,
                        $node['server_id'],
                        $node['inbound_id'],
                        max(0, (int)($node['sort_order'] ?? 0)),
                        $node['client_uuid'],
                        (new RemoteClientCredentialService())->encryptForStorage(
                            (string)$node['protocol'],
                            isset($node['client_credential']) ? (string)$node['client_credential'] : null
                        ),
                        $remoteName,
                        $remoteName,
                        $node['country_code'],
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
                    s.created_by, s.internal_comment, s.last_error, s.created_at, s.updated_at,
                    u.name AS user_name, u.login AS user_login, u.email AS user_email, p.name AS plan_name,
                    COUNT(n.id) AS node_count,
                    SUM(CASE WHEN n.status = "active" THEN 1 ELSE 0 END) AS active_node_count
             FROM vpn_v2_subscriptions s
             INNER JOIN users u ON u.id = s.user_id
             INNER JOIN vpn_v2_plans p ON p.id = s.plan_id
             LEFT JOIN vpn_v2_subscription_nodes n ON n.subscription_id = s.id
             GROUP BY s.id, s.user_id, s.plan_id, s.status, s.starts_at, s.expires_at,
                      s.traffic_limit_bytes, s.device_limit, s.revision, s.config_updated_at,
                      s.created_by, s.internal_comment, s.last_error, s.created_at, s.updated_at,
                      u.name, u.login, u.email, p.name
             ORDER BY s.id ASC'
        )->get() ?: [];
    }

    public function find(int $id): ?array
    {
        $row = db()->query(
            "SELECT s.id, s.user_id, s.plan_id, s.status, s.starts_at, s.expires_at,
                    s.traffic_limit_bytes, s.device_limit, s.revision, s.config_updated_at,
                    s.created_by, s.internal_comment, s.last_error, s.created_at, s.updated_at,
                    CONCAT(LEFT(s.subscription_token, 4), '…', RIGHT(s.subscription_token, 4)) AS token_preview,
                    u.name AS user_name, u.login AS user_login, u.email AS user_email, p.name AS plan_name
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
                    device_limit, revision, created_by, internal_comment, last_error, created_at, updated_at
             FROM vpn_v2_subscriptions WHERE id = ? LIMIT 1',
            [$id]
        )->getOne();

        return is_array($row) ? $row : null;
    }

    public function findForDeletion(int $id): ?array
    {
        $row = db()->query(
            'SELECT id, user_id, plan_id, status, starts_at, expires_at, traffic_limit_bytes,
                    device_limit, subscription_token, revision, config_updated_at, created_by,
                    internal_comment, last_error, created_at, updated_at
             FROM vpn_v2_subscriptions WHERE id = ? LIMIT 1',
            [$id]
        )->getOne();

        return is_array($row) ? $row : null;
    }

    public function nodesForSubscription(int $subscriptionId): array
    {
        return db()->query(
            'SELECT n.id, n.subscription_id, n.server_id, n.inbound_id, n.sort_order,
                    CASE WHEN n.remote_client_id IS NULL OR n.remote_client_id = "" THEN NULL
                         ELSE CONCAT(LEFT(n.remote_client_id, 8), "…", RIGHT(n.remote_client_id, 4)) END AS remote_client_preview,
                    n.client_email, n.remote_client_name, n.country_code,
                    n.protocol, n.network, n.security, n.flow,
                    n.status, n.sync_status, n.sync_error, n.desired_enabled, n.is_obsolete,
                    n.traffic_limit_bytes, n.traffic_used_bytes, n.last_sync_at,
                    n.last_error, n.created_at, n.updated_at,
                    s.name AS server_name, s.code AS server_code, i.name AS inbound_name,
                    i.remote_inbound_id, u.name AS user_name, u.email AS user_email
             FROM vpn_v2_subscription_nodes n
             INNER JOIN vpn_v2_subscriptions sub ON sub.id = n.subscription_id
             INNER JOIN users u ON u.id = sub.user_id
             INNER JOIN vpn_v2_servers s ON s.id = n.server_id
             INNER JOIN vpn_v2_inbounds i ON i.id = n.inbound_id
             WHERE n.subscription_id = ?
             ORDER BY n.sort_order ASC, n.id ASC',
            [$subscriptionId]
        )->get() ?: [];
    }

    public function connections(): array
    {
        return db()->query(
            'SELECT n.id, n.subscription_id, n.server_id, n.inbound_id,
                    CASE WHEN n.remote_client_id IS NULL OR n.remote_client_id = "" THEN NULL
                         ELSE CONCAT(LEFT(n.remote_client_id, 8), "…", RIGHT(n.remote_client_id, 4)) END AS remote_client_preview,
                    n.client_email, n.remote_client_name, n.country_code,
                    n.protocol, n.network, n.security, n.flow, n.status, n.sync_status, n.sync_error,
                    n.desired_enabled, n.is_obsolete,
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
                    n.client_email, n.remote_client_name, n.country_code,
                    n.protocol, n.network, n.security, n.flow,
                    n.status, n.sync_status, n.sync_error, n.last_seen_remote_at,
                    n.lkg_snapshot_version, n.lkg_validity, n.desired_enabled, n.is_obsolete,
                    n.traffic_limit_bytes, n.traffic_used_bytes, n.last_sync_at,
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
                    n.client_uuid, n.encrypted_client_credential, n.client_email, n.remote_client_name, n.country_code,
                    n.client_sub_id, n.protocol, n.network,
                    n.security, n.flow, n.status, n.desired_enabled, n.is_obsolete,
                    n.traffic_limit_bytes, n.traffic_used_bytes,
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

    public function nodeIdsForSubscription(int $subscriptionId): array
    {
        $rows = db()->query(
            'SELECT id FROM vpn_v2_subscription_nodes WHERE subscription_id = ? ORDER BY id ASC',
            [$subscriptionId]
        )->get() ?: [];

        return array_values(array_filter(array_map(
            static fn(array $row): int => (int)($row['id'] ?? 0),
            $rows
        )));
    }

    /**
     * Persists the complete visible connection sequence and bumps the subscription
     * revision in the same transaction so an old cached body can never outlive it.
     */
    public function reorderNodes(int $subscriptionId, array $orderedNodeIds, int $adminId): bool
    {
        $database = db();
        $database->beginTransaction();
        try {
            $subscription = $database->query(
                'SELECT id, user_id FROM vpn_v2_subscriptions WHERE id = ? FOR UPDATE',
                [$subscriptionId]
            )->getOne();
            if (!is_array($subscription)) {
                throw new \InvalidArgumentException('subscription_not_found');
            }

            $rows = $database->query(
                "SELECT id, sort_order FROM vpn_v2_subscription_nodes
                 WHERE subscription_id = ? AND status NOT IN ('deleted', 'deleting')
                 ORDER BY sort_order ASC, id ASC FOR UPDATE",
                [$subscriptionId]
            )->get() ?: [];
            $current = array_map(static fn(array $row): int => (int)$row['id'], $rows);
            $submitted = array_values(array_map('intval', $orderedNodeIds));
            $currentSet = $current;
            $submittedSet = $submitted;
            sort($currentSet);
            sort($submittedSet);
            if ($submitted === [] || count(array_unique($submitted)) !== count($submitted)
                || $currentSet !== $submittedSet) {
                throw new \InvalidArgumentException('connection_order_invalid');
            }

            foreach ($submitted as $index => $nodeId) {
                $database->query(
                    'UPDATE vpn_v2_subscription_nodes SET sort_order = ?, updated_at = ?
                     WHERE id = ? AND subscription_id = ?',
                    [($index + 1) * 10, date('Y-m-d H:i:s'), $nodeId, $subscriptionId]
                );
            }
            $changed = $current !== $submitted;
            if ($changed) {
                $now = date('Y-m-d H:i:s');
                $database->query(
                    'UPDATE vpn_v2_subscriptions
                     SET revision = revision + 1, config_updated_at = ?, updated_at = ? WHERE id = ?',
                    [$now, $now, $subscriptionId]
                );
                $this->logEvent(
                    'subscription.connection_order_changed',
                    $subscriptionId,
                    null,
                    null,
                    (int)$subscription['user_id'],
                    $adminId > 0 ? $adminId : null,
                    ['connection_ids' => $submitted]
                );
            }
            $database->commit();

            return $changed;
        } catch (\Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
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

    public function markNodeActive(int $id, ?string $remoteClientId, string $status = 'active'): void
    {
        $status = $status === 'disabled' ? 'disabled' : 'active';
        $now = date('Y-m-d H:i:s');
        db()->query(
            "UPDATE vpn_v2_subscription_nodes
             SET remote_client_id = ?, status = ?, is_obsolete = 0,
                 sync_status = 'synced', sync_error = NULL,
                 last_sync_at = ?, last_error = NULL, updated_at = ? WHERE id = ?",
            [$remoteClientId, $status, $now, $now, $id]
        );
    }

    public function markNodeFailure(int $id, string $status, string $safeError): void
    {
        $status = in_array($status, ['create_failed', 'sync_error'], true) ? $status : 'create_failed';
        db()->query(
            'UPDATE vpn_v2_subscription_nodes
             SET status = ?, sync_status = ?, last_error = ?, sync_error = ?, updated_at = ? WHERE id = ?',
            [
                $status,
                $status === 'sync_error' ? 'failed' : 'pending',
                mb_substr(trim($safeError), 0, 1000),
                mb_substr(trim($safeError), 0, 1000),
                date('Y-m-d H:i:s'),
                $id,
            ]
        );
    }

    public function updateNodeConfirmed(
        int $id,
        ?string $flow,
        ?int $trafficLimitBytes,
        ?int $trafficUsedBytes = null,
        string $status = 'active',
        ?bool $desiredEnabled = null
    ): void {
        $status = $status === 'disabled' ? 'disabled' : 'active';
        $now = date('Y-m-d H:i:s');
        db()->query(
            "UPDATE vpn_v2_subscription_nodes
             SET flow = ?, traffic_limit_bytes = ?,
                 traffic_used_bytes = COALESCE(?, traffic_used_bytes), status = ?,
                 desired_enabled = COALESCE(?, desired_enabled), is_obsolete = 0,
                 sync_status = 'synced', sync_error = NULL,
                 last_sync_at = ?, last_error = NULL, updated_at = ?
             WHERE id = ?",
            [
                $flow,
                $trafficLimitBytes,
                $trafficUsedBytes,
                $status,
                $desiredEnabled === null ? null : ($desiredEnabled ? 1 : 0),
                $now,
                $now,
                $id,
            ]
        );
    }

    public function updateSubscriptionConfirmed(int $id, array $data, ?string $safeError = null): void
    {
        $status = in_array((string)($data['status'] ?? ''), ['active', 'suspended', 'expired'], true)
            ? (string)$data['status']
            : 'suspended';
        db()->query(
            'UPDATE vpn_v2_subscriptions
             SET expires_at = ?, traffic_limit_bytes = ?, status = ?, internal_comment = ?,
                 last_error = ?, updated_at = ?
             WHERE id = ?',
            [
                $data['expires_at'] ?? null,
                $data['traffic_limit_bytes'] ?? null,
                $status,
                $data['internal_comment'] ?? null,
                $safeError !== null ? mb_substr(trim($safeError), 0, 1000) : null,
                date('Y-m-d H:i:s'),
                $id,
            ]
        );
    }

    public function updateInternalComment(int $id, ?string $comment): void
    {
        db()->query(
            'UPDATE vpn_v2_subscriptions SET internal_comment = ?, updated_at = ? WHERE id = ?',
            [$comment, date('Y-m-d H:i:s'), $id]
        );
    }

    public function markDeleting(int $id): bool
    {
        $database = db();
        $database->beginTransaction();
        try {
            $now = date('Y-m-d H:i:s');
            $database->query(
                "UPDATE vpn_v2_subscriptions
                 SET status = 'deleting', last_error = NULL, updated_at = ? WHERE id = ?",
                [$now, $id]
            );
            if ($database->rowCount() !== 1) {
                $database->rollBack();
                return false;
            }
            $database->query(
                "UPDATE vpn_v2_subscription_nodes
                 SET status = 'deleting', last_error = NULL, updated_at = ?
                 WHERE subscription_id = ? AND status <> 'deleted'",
                [$now, $id]
            );
            $database->commit();

            return true;
        } catch (\Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    public function markNodeDeleted(int $id): void
    {
        $now = date('Y-m-d H:i:s');
        db()->query(
            "UPDATE vpn_v2_subscription_nodes
             SET status = 'deleted', last_sync_at = ?, last_error = NULL, updated_at = ? WHERE id = ?",
            [$now, $now, $id]
        );
    }

    public function markNodeDeleteFailed(int $id, string $safeError): void
    {
        db()->query(
            "UPDATE vpn_v2_subscription_nodes
             SET status = 'delete_failed', last_error = ?, updated_at = ? WHERE id = ?",
            [mb_substr(trim($safeError), 0, 1000), date('Y-m-d H:i:s'), $id]
        );
    }

    public function markNodePendingRemoteDelete(int $id, string $safeError): void
    {
        db()->query(
            "UPDATE vpn_v2_subscription_nodes
             SET status = 'pending_remote_delete', sync_status = 'pending',
                 last_error = ?, updated_at = ? WHERE id = ?",
            [mb_substr(trim($safeError), 0, 1000), date('Y-m-d H:i:s'), $id]
        );
    }

    public function markSubscriptionPendingRemoteDelete(int $id, string $safeError): void
    {
        db()->query(
            "UPDATE vpn_v2_subscriptions
             SET status = 'pending_remote_delete', last_error = ?, updated_at = ? WHERE id = ?",
            [mb_substr(trim($safeError), 0, 1000), date('Y-m-d H:i:s'), $id]
        );
    }

    public function markSubscriptionDeleteFailed(int $id, string $safeError): void
    {
        db()->query(
            "UPDATE vpn_v2_subscriptions
             SET status = 'delete_failed', last_error = ?, updated_at = ? WHERE id = ?",
            [mb_substr(trim($safeError), 0, 1000), date('Y-m-d H:i:s'), $id]
        );
    }

    public function allNodesDeleted(int $subscriptionId): bool
    {
        return (int)db()->query(
            "SELECT COUNT(*) FROM vpn_v2_subscription_nodes
             WHERE subscription_id = ? AND status <> 'deleted'",
            [$subscriptionId]
        )->getColumn() === 0;
    }

    public function finalizeDeletion(int $subscriptionId, string $revokedToken): bool
    {
        $database = db();
        $database->beginTransaction();
        try {
            $subscription = $database->query(
                'SELECT id FROM vpn_v2_subscriptions WHERE id = ? FOR UPDATE',
                [$subscriptionId]
            )->getOne();
            if (!$subscription) {
                $database->rollBack();
                return true;
            }
            $nodes = $database->query(
                'SELECT id, status FROM vpn_v2_subscription_nodes WHERE subscription_id = ? FOR UPDATE',
                [$subscriptionId]
            )->get() ?: [];
            foreach ($nodes as $node) {
                if ((string)($node['status'] ?? '') !== 'deleted') {
                    throw new \RuntimeException('Unconfirmed remote clients remain.');
                }
            }

            $database->query(
                "UPDATE vpn_v2_subscriptions
                 SET subscription_token = ?, subscription_token_hash = ?,
                     status = 'deleting', updated_at = ? WHERE id = ?",
                [$revokedToken, hash('sha256', $revokedToken), date('Y-m-d H:i:s'), $subscriptionId]
            );
            $database->query('DELETE FROM vpn_v2_subscription_nodes WHERE subscription_id = ?', [$subscriptionId]);
            $database->query('DELETE FROM vpn_v2_subscriptions WHERE id = ?', [$subscriptionId]);
            if ($database->rowCount() !== 1) {
                throw new \RuntimeException('Subscription row was not deleted.');
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

    public function claimRetry(int $id): bool
    {
        db()->query(
            "UPDATE vpn_v2_subscription_nodes
             SET status = 'creating', last_error = NULL, updated_at = ?
             WHERE id = ? AND status IN ('create_failed', 'sync_error', 'missing_remote')",
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
