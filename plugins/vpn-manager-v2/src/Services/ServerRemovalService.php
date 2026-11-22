<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\Exceptions\ValidationException;
use Fireball\VpnManagerV2\Repositories\ServerRepository;
use Fireball\VpnManagerV2\Repositories\SubscriptionRepository;
use Fireball\VpnManagerV2\Support\ServerConfigurationSignature;

/** Retire a lost server locally, keeping historical records and subscription identities. */
final class ServerRemovalService
{
    public static function fingerprint(array $server): string
    {
        return hash('sha256', $server['id'] . '|' . ($server['updated_at'] ?? '') . '|'
            . ServerConfigurationSignature::hash($server));
    }

    public function preview(int $id): array
    {
        $server = (new ServerRepository())->find($id);
        if (!$server) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_server_not_found'));
        }
        $counts = db()->query('SELECT
            (SELECT COUNT(*) FROM vpn_v2_plan_nodes WHERE server_id = ?) AS plan_nodes,
            (SELECT COUNT(DISTINCT preset_id) FROM vpn_v2_server_preset_nodes WHERE server_id = ?) AS presets,
            (SELECT COUNT(*) FROM vpn_v2_subscription_nodes WHERE server_id = ?) AS connections,
            (SELECT COUNT(DISTINCT subscription_id) FROM vpn_v2_subscription_nodes WHERE server_id = ?) AS subscriptions',
            [$id, $id, $id, $id])->getOne();
        return ['server' => $server, 'counts' => $counts, 'fingerprint' => self::fingerprint($server)];
    }

    public function remove(int $id, string $fingerprint, ?int $adminId): string
    {
        $db = db();
        $db->beginTransaction();
        try {
            $server = (new ServerRepository())->findForUpdate($id);
            if (!$server) {
                throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_server_not_found'));
            }
            if (!empty($server['is_enabled'])) {
                throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_remove_server_disable_first'));
            }
            if (!hash_equals(self::fingerprint($server), $fingerprint)) {
                throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_remove_server_changed'));
            }
            $now = date('Y-m-d H:i:s');
            $operations = $db->query("SELECT id, status, lease_until, updated_at FROM vpn_v2_operations
                WHERE (server_id = ? OR connection_id IN
                    (SELECT id FROM vpn_v2_subscription_nodes WHERE server_id = ?))
                  AND status IN ('pending', 'retry', 'running') ORDER BY id FOR UPDATE", [$id, $id])->get() ?: [];
            foreach ($operations as $operation) {
                $alive = !empty($operation['lease_until']) ? $operation['lease_until'] > $now
                    : (strtotime((string)($operation['updated_at'] ?? '')) ?: 0) > time() - 300;
                if ($operation['status'] === 'running'
                    && $alive) {
                    throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_remove_server_busy'));
                }
            }
            // Invalidate direct subscriptions and their consumers BEFORE archiving
            // connection links, while the complete dependency graph is available.
            $subscriptions = $db->query('SELECT DISTINCT subscription_id AS id FROM vpn_v2_subscription_nodes WHERE server_id = ?
                UNION SELECT sub.id FROM vpn_v2_subscriptions sub INNER JOIN vpn_v2_plan_nodes pn ON pn.plan_id = sub.plan_id
                WHERE pn.server_id = ? ORDER BY id', [$id, $id])->get() ?: [];
            $revisions = new VpnSubscriptionRevisionService();
            foreach ($subscriptions as $subscription) {
                $revisions->touchConfig((int)$subscription['id']);
            }
            $db->query("UPDATE vpn_v2_operations SET status = 'cancelled', idempotency_key = NULL,
                lease_until = NULL, heartbeat_at = NULL, finished_at = ?, updated_at = ?, last_error = 'server_removed_locally'
                WHERE (server_id = ? OR connection_id IN (SELECT id FROM vpn_v2_subscription_nodes WHERE server_id = ?))
                  AND status IN ('pending', 'retry', 'running')", [$now, $now, $id, $id]);
            $db->query("UPDATE vpn_v2_subscription_items SET is_enabled = 0, effective_status = 'inactive',
                inactive_reason = 'connection_deleted_remote', relation_key = NULL, deleted_at = ?, updated_at = ?
                WHERE item_type = 'connection' AND deleted_at IS NULL
                  AND connection_id IN (SELECT id FROM vpn_v2_subscription_nodes WHERE server_id = ?)", [$now, $now, $id]);
            $db->query('DELETE FROM vpn_v2_server_preset_nodes WHERE server_id = ?', [$id]);
            $db->query('DELETE FROM vpn_v2_plan_nodes WHERE server_id = ?', [$id]);
            $db->query("UPDATE vpn_v2_subscription_nodes SET status = 'deleted', desired_enabled = 0,
                is_obsolete = 1, sync_status = 'server_removed', last_error = NULL, sync_error = NULL, updated_at = ?
                WHERE server_id = ? AND status <> 'deleted'", [$now, $id]);
            $db->query("UPDATE vpn_v2_inbounds SET is_enabled = 0, status = 'archived', updated_at = ? WHERE server_id = ?", [$now, $id]);
            $db->query("UPDATE vpn_v2_sync_conflicts SET status = 'resolved', resolved_at = ?, resolved_by = ?, updated_at = ?
                WHERE status = 'open' AND (server_id = ? OR connection_id IN
                    (SELECT id FROM vpn_v2_subscription_nodes WHERE server_id = ?))", [$now, $adminId, $now, $id, $id]);
            $db->query("UPDATE vpn_v2_servers SET status = 'archived', is_enabled = 0, allow_new_connections = 0,
                encrypted_username = NULL, encrypted_password = NULL, encrypted_token = NULL, last_error = NULL, updated_at = ? WHERE id = ?", [$now, $id]);
            (new SubscriptionRepository())->logEvent('server.removed_locally', null, null, $id, null, $adminId, [
                'name' => (string)$server['name'], 'subscriptions_touched' => count($subscriptions),
                'operations_cancelled' => count($operations), 'remote_request' => false,
            ]);
            $db->commit();
            return (string)$server['name'];
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            throw $exception;
        }
    }
}
