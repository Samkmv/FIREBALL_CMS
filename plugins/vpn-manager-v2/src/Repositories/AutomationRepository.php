<?php

namespace Fireball\VpnManagerV2\Repositories;

final class AutomationRepository
{
    public function activeNodesForTrafficSync(int $limit = 500): array
    {
        $limit = max(1, min(2000, $limit));

        return db()->query(
            "SELECT n.id, n.subscription_id, n.server_id, n.inbound_id, n.client_uuid,
                    n.client_email, n.client_sub_id, n.protocol, n.network, n.security, n.flow,
                    n.status, n.traffic_limit_bytes, n.traffic_used_bytes,
                    n.upload_bytes, n.download_bytes, n.traffic_synced_at,
                    n.traffic_sync_status, n.last_sync_at,
                    sub.user_id, sub.status AS subscription_status, sub.starts_at, sub.expires_at,
                    sub.device_limit, sub.traffic_limit_bytes AS subscription_traffic_limit_bytes,
                    i.remote_inbound_id
             FROM vpn_v2_subscription_nodes n
             INNER JOIN vpn_v2_subscriptions sub ON sub.id = n.subscription_id
             INNER JOIN vpn_v2_servers s ON s.id = n.server_id
             INNER JOIN vpn_v2_inbounds i ON i.id = n.inbound_id AND i.server_id = n.server_id
             WHERE sub.status = 'active' AND sub.starts_at <= NOW()
               AND (sub.expires_at IS NULL OR sub.expires_at > NOW())
               AND n.status = 'active' AND s.is_enabled = 1 AND i.is_enabled = 1
             ORDER BY n.id ASC
             LIMIT {$limit}"
        )->get() ?: [];
    }

    public function recordNodeTraffic(
        int $nodeId,
        int $remoteUsedBytes,
        ?int $remoteUploadBytes = null,
        ?int $remoteDownloadBytes = null
    ): array
    {
        $database = db();
        $ownsTransaction = !$database->inTransaction();
        if ($ownsTransaction) {
            $database->beginTransaction();
        }
        try {
            $row = $database->query(
                'SELECT subscription_id, traffic_used_bytes, upload_bytes, download_bytes
                 FROM vpn_v2_subscription_nodes WHERE id = ? FOR UPDATE',
                [$nodeId]
            )->getOne();
            if (!is_array($row)) {
                throw new \RuntimeException('VPN Manager V2 traffic node was not found.');
            }
            $stored = max(0, (int)($row['traffic_used_bytes'] ?? 0));
            $confirmed = max($stored, max(0, $remoteUsedBytes));
            $upload = max((int)($row['upload_bytes'] ?? 0), max(0, (int)($remoteUploadBytes ?? 0)));
            $download = max((int)($row['download_bytes'] ?? 0), max(0, (int)($remoteDownloadBytes ?? 0)));
            $now = date('Y-m-d H:i:s');
            $database->query(
                'UPDATE vpn_v2_subscription_nodes
                 SET traffic_used_bytes = ?, upload_bytes = ?, download_bytes = ?,
                     traffic_synced_at = ?, traffic_sync_status = \'synced\',
                     last_sync_at = ?, last_error = NULL, updated_at = ?
                 WHERE id = ?',
                [$confirmed, $upload, $download, $now, $now, $now, $nodeId]
            );
            if ($ownsTransaction) {
                $database->commit();
            }

            return [
                'subscription_id' => (int)$row['subscription_id'],
                'previous_bytes' => $stored,
                'remote_bytes' => max(0, $remoteUsedBytes),
                'stored_bytes' => $confirmed,
                'upload_bytes' => $upload,
                'download_bytes' => $download,
            ];
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    public function recordNodeTrafficFailure(int $nodeId, string $safeError): void
    {
        db()->query(
            "UPDATE vpn_v2_subscription_nodes
             SET traffic_sync_status = 'failed', last_error = ?, updated_at = ? WHERE id = ?",
            [mb_substr(trim($safeError), 0, 1000), date('Y-m-d H:i:s'), $nodeId]
        );
    }

    public function recalculateSubscriptionTraffic(int $subscriptionId): int
    {
        $total = (int)db()->query(
            "SELECT COALESCE(SUM(traffic_used_bytes), 0)
             FROM vpn_v2_subscription_nodes
             WHERE subscription_id = ? AND status <> 'deleted'",
            [$subscriptionId]
        )->getColumn();
        $total = max(0, $total);
        db()->query(
            'UPDATE vpn_v2_subscriptions SET traffic_used_bytes = ?, updated_at = ? WHERE id = ?',
            [$total, date('Y-m-d H:i:s'), $subscriptionId]
        );

        return $total;
    }

    public function dueSubscriptions(int $limit = 500): array
    {
        $limit = max(1, min(2000, $limit));

        return db()->query(
            "SELECT id, user_id, status, starts_at, expires_at, traffic_limit_bytes,
                    traffic_used_bytes, device_limit, revision, created_by, internal_comment
             FROM vpn_v2_subscriptions
             WHERE status = 'active' AND expires_at IS NOT NULL AND expires_at <= NOW()
             ORDER BY expires_at ASC, id ASC
             LIMIT {$limit}"
        )->get() ?: [];
    }

    public function subscriptionsForTrafficLimit(int $limit = 500): array
    {
        $limit = max(1, min(2000, $limit));

        return db()->query(
            "SELECT id, user_id, status, starts_at, expires_at, traffic_limit_bytes,
                    traffic_used_bytes, device_limit, revision, created_by, internal_comment
             FROM vpn_v2_subscriptions
             WHERE status = 'active' AND traffic_limit_bytes IS NOT NULL
               AND traffic_limit_bytes > 0 AND traffic_used_bytes >= traffic_limit_bytes
             ORDER BY id ASC
             LIMIT {$limit}"
        )->get() ?: [];
    }

    public function updateSubscriptionStatus(int $subscriptionId, string $status, ?string $safeError = null): void
    {
        if (!in_array($status, ['active', 'suspended', 'expired', 'traffic_exceeded'], true)) {
            throw new \InvalidArgumentException('Unsupported VPN Manager V2 automation status.');
        }
        db()->query(
            'UPDATE vpn_v2_subscriptions SET status = ?, last_error = ?, updated_at = ? WHERE id = ?',
            [
                $status,
                $safeError !== null ? mb_substr(trim($safeError), 0, 1000) : null,
                date('Y-m-d H:i:s'),
                $subscriptionId,
            ]
        );
    }

    public function recordAutomationNodeSuccess(
        int $nodeId,
        ?int $trafficUsedBytes,
        ?bool $desiredEnabled = null
    ): void
    {
        $status = $desiredEnabled === false ? 'disabled' : 'active';
        $now = date('Y-m-d H:i:s');
        db()->query(
            "UPDATE vpn_v2_subscription_nodes
             SET traffic_used_bytes = GREATEST(traffic_used_bytes, COALESCE(?, traffic_used_bytes)),
                 status = ?, desired_enabled = COALESCE(?, desired_enabled),
                 last_sync_at = ?, last_error = NULL, updated_at = ?
             WHERE id = ?",
            [
                $trafficUsedBytes,
                $status,
                $desiredEnabled === null ? null : ($desiredEnabled ? 1 : 0),
                $now,
                $now,
                $nodeId,
            ]
        );
    }

    public function recordAutomationNodeFailure(int $nodeId, string $safeError): void
    {
        db()->query(
            "UPDATE vpn_v2_subscription_nodes
             SET status = 'sync_error', last_error = ?, updated_at = ? WHERE id = ?",
            [mb_substr(trim($safeError), 0, 1000), date('Y-m-d H:i:s'), $nodeId]
        );
    }

    public function subscription(int $subscriptionId): ?array
    {
        $row = db()->query(
            'SELECT id, user_id, status, starts_at, expires_at, traffic_limit_bytes,
                    traffic_used_bytes, device_limit, revision, created_by, internal_comment, created_at
             FROM vpn_v2_subscriptions WHERE id = ? LIMIT 1',
            [$subscriptionId]
        )->getOne();

        return is_array($row) ? $row : null;
    }

    public function expirationNotificationCandidates(): array
    {
        return db()->query(
            "SELECT id, user_id, status, expires_at
             FROM vpn_v2_subscriptions
             WHERE expires_at IS NOT NULL
               AND (
                    (status = 'active' AND DATE(expires_at) = DATE_ADD(CURDATE(), INTERVAL 3 DAY))
                    OR (status IN ('active', 'expired') AND DATE(expires_at) = CURDATE())
               )
             ORDER BY id ASC
             LIMIT 1000"
        )->get() ?: [];
    }

    public function trafficNotificationCandidates(): array
    {
        return db()->query(
            "SELECT id, user_id, status, starts_at, traffic_limit_bytes, traffic_used_bytes
             FROM vpn_v2_subscriptions
             WHERE status IN ('active', 'traffic_exceeded')
               AND traffic_limit_bytes IS NOT NULL AND traffic_limit_bytes > 0
               AND traffic_used_bytes / traffic_limit_bytes >= 0.8
             ORDER BY id ASC
             LIMIT 1000"
        )->get() ?: [];
    }

    public function failedDeletionSubscriptions(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));

        return db()->query(
            "SELECT id FROM vpn_v2_subscriptions
             WHERE status = 'delete_failed' ORDER BY updated_at ASC, id ASC LIMIT {$limit}"
        )->get() ?: [];
    }

    public function failedProvisioningNodes(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));

        return db()->query(
            "SELECT n.id
             FROM vpn_v2_subscription_nodes n
             INNER JOIN vpn_v2_subscriptions s ON s.id = n.subscription_id
             WHERE n.status IN ('create_failed', 'sync_error')
               AND s.status IN ('provisioning', 'provisioning_failed', 'sync_error')
             ORDER BY n.updated_at ASC, n.id ASC LIMIT {$limit}"
        )->get() ?: [];
    }

    public function failedSyncNodes(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));

        return db()->query(
            "SELECT n.id
             FROM vpn_v2_subscription_nodes n
             INNER JOIN vpn_v2_subscriptions s ON s.id = n.subscription_id
             WHERE n.status = 'sync_error'
               AND s.status IN ('active', 'suspended', 'expired', 'traffic_exceeded')
             ORDER BY n.updated_at ASC, n.id ASC LIMIT {$limit}"
        )->get() ?: [];
    }
}
