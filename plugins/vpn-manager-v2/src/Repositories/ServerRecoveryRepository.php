<?php

namespace Fireball\VpnManagerV2\Repositories;

use Fireball\VpnManagerV2\Exceptions\ValidationException;

final class ServerRecoveryRepository
{
    public function targets(int $serverId): array
    {
        return db()->query(
            "SELECT i.*, COUNT(DISTINCT pn.plan_id) AS plan_count
             FROM vpn_v2_inbounds i
             INNER JOIN vpn_v2_plan_nodes pn ON pn.inbound_id = i.id AND pn.server_id = i.server_id
             WHERE i.server_id = ? AND pn.is_enabled = 1
             GROUP BY i.id ORDER BY i.id",
            [$serverId]
        )->get() ?: [];
    }

    public function subscriptions(int $serverId): array
    {
        return db()->query(
            "SELECT sub.id, sub.status, sub.plan_id, p.name AS plan_name,
                    COALESCE(u.name, sub.manual_customer_name) AS customer_name,
                    sub.last_error,
                    (SELECT COUNT(*) FROM vpn_v2_subscription_nodes n
                     WHERE n.subscription_id = sub.id AND n.server_id = ?
                       AND n.status IN ('active', 'disabled') AND n.sync_status = 'synced') AS ready_count
             FROM vpn_v2_subscriptions sub
             INNER JOIN vpn_v2_plans p ON p.id = sub.plan_id AND p.deleted_at IS NULL
             LEFT JOIN users u ON u.id = sub.user_id
             WHERE sub.status IN ('active', 'provisioning', 'provisioning_failed', 'partial_sync', 'sync_error', 'suspended')
               AND sub.starts_at <= NOW() AND (sub.expires_at IS NULL OR sub.expires_at > NOW())
               AND EXISTS (SELECT 1 FROM vpn_v2_plan_nodes pn
                           WHERE pn.plan_id = sub.plan_id AND pn.server_id = ? AND pn.is_enabled = 1)
             ORDER BY sub.id",
            [$serverId, $serverId]
        )->get() ?: [];
    }

    /** Validate every mapping before changing topology; never merge two credentials. */
    public function remap(int $serverId, array $mapping): void
    {
        $database = db();
        $database->beginTransaction();
        try {
            $database->query('SELECT id FROM vpn_v2_servers WHERE id = ? FOR UPDATE', [$serverId])->getOne();
            $targets = $this->targets($serverId);
            $inbounds = [];
            foreach ((new InboundRepository())->forServer($serverId) as $inbound) {
                $inbounds[(int)$inbound['id']] = $inbound;
            }
            $selected = [];
            foreach ($targets as $target) {
                $oldId = (int)$target['id'];
                $newId = (int)($mapping[$oldId] ?? 0);
                $new = $inbounds[$newId] ?? null;
                if (!$new || $new['status'] !== 'active' || empty($new['is_enabled'])
                    || strtolower((string)$target['protocol']) !== strtolower((string)$new['protocol'])
                    || isset($selected[$newId])) {
                    throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_recovery_mapping_error'));
                }
                $selected[$newId] = true;
                if ($oldId === $newId) {
                    continue;
                }
                // A target already used by any subscription cannot silently absorb another identity.
                $collision = $database->query(
                    'SELECT a.id FROM vpn_v2_subscription_nodes a
                     INNER JOIN vpn_v2_subscription_nodes b ON b.subscription_id = a.subscription_id
                     WHERE a.server_id = ? AND a.inbound_id = ? AND b.server_id = ? AND b.inbound_id = ? LIMIT 1',
                    [$serverId, $newId, $serverId, $oldId]
                )->getOne();
                if ($collision) {
                    throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_recovery_mapping_error'));
                }
                $planCollision = $database->query(
                    'SELECT a.id FROM vpn_v2_plan_nodes a INNER JOIN vpn_v2_plan_nodes b ON b.plan_id = a.plan_id
                     WHERE a.server_id = ? AND a.inbound_id = ? AND b.server_id = ? AND b.inbound_id = ? LIMIT 1',
                    [$serverId, $newId, $serverId, $oldId]
                )->getOne();
                if ($planCollision) {
                    throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_recovery_mapping_error'));
                }
            }
            foreach ($targets as $target) {
                $oldId = (int)$target['id'];
                $newId = (int)$mapping[$oldId];
                if ($oldId === $newId) {
                    continue;
                }
                $database->query(
                    'UPDATE vpn_v2_plan_nodes SET inbound_id = ?, updated_at = ? WHERE server_id = ? AND inbound_id = ?',
                    [$newId, date('Y-m-d H:i:s'), $serverId, $oldId]
                );
                // Preserve UUID/password, subId, URL, counters and the local node id.
                $database->query(
                    'UPDATE vpn_v2_subscription_nodes SET inbound_id = ?, network = ?, security = ?,
                         sync_status = CASE WHEN status = \'deleted\' AND sync_status = \'remote_deleted\'
                                            THEN sync_status ELSE \'pending\' END, updated_at = ?
                     WHERE server_id = ? AND inbound_id = ? AND status NOT IN (\'deleting\', \'pending_remote_delete\')',
                    [$newId, $inbounds[$newId]['network'], $inbounds[$newId]['security'],
                        date('Y-m-d H:i:s'), $serverId, $oldId]
                );
            }
            $database->commit();
        } catch (\Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    public function operations(int $serverId): array
    {
        return db()->query(
            "SELECT operation_id, subscription_id, status, processed_count, total_count, last_error, updated_at,
                    payload_json
             FROM vpn_v2_operations
             WHERE server_id = ? AND operation_type = 'sync_subscription' AND source = 'manual_sync'
             ORDER BY id DESC",
            [$serverId]
        )->get() ?: [];
    }

    public function operation(int $serverId, string $uuid): ?array
    {
        $row = db()->query(
            "SELECT * FROM vpn_v2_operations WHERE server_id = ? AND operation_id = ?
             AND operation_type = 'sync_subscription' AND source = 'manual_sync' LIMIT 1",
            [$serverId, $uuid]
        )->getOne();
        $payload = json_decode((string)($row['payload_json'] ?? ''), true);
        return is_array($row) && (int)($payload['repair_server_id'] ?? 0) === $serverId ? $row : null;
    }
}
