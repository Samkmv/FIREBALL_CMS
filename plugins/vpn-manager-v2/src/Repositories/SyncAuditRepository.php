<?php

namespace Fireball\VpnManagerV2\Repositories;

final class SyncAuditRepository
{
    public function log(array $data): void
    {
        $changedFields = array_values(array_filter(array_map(
            static fn(mixed $field): string => mb_substr(trim((string)$field), 0, 80),
            (array)($data['changed_fields'] ?? [])
        )));
        db()->query(
            'INSERT INTO vpn_v2_sync_logs
                (operation_id, operation_type, source, server_id, subscription_id, user_id,
                 connection_id, previous_hash, new_hash, changed_fields_json, status,
                 error_code, safe_error, duration_ms, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $this->nullable($data['operation_id'] ?? null, 36),
                $this->nullable($data['operation_type'] ?? 'sync_client', 60) ?? 'sync_client',
                $this->nullable($data['source'] ?? 'reconciliation', 40) ?? 'reconciliation',
                $this->positive($data['server_id'] ?? null),
                $this->positive($data['subscription_id'] ?? null),
                $this->positive($data['user_id'] ?? null),
                $this->positive($data['connection_id'] ?? null),
                $this->hash($data['previous_hash'] ?? null),
                $this->hash($data['new_hash'] ?? null),
                $changedFields !== [] ? json_encode($changedFields, JSON_UNESCAPED_SLASHES) : null,
                $this->nullable($data['status'] ?? 'completed', 40) ?? 'completed',
                $this->nullable($data['error_code'] ?? null, 120),
                $this->safeError($data['safe_error'] ?? null),
                isset($data['duration_ms']) ? max(0, (int)$data['duration_ms']) : null,
                date('Y-m-d H:i:s'),
            ]
        );
    }

    public function recent(int $limit = 200): array
    {
        $limit = max(1, min(500, $limit));

        return db()->query(
            'SELECT l.id, l.operation_id, l.operation_type, l.source, l.server_id,
                    l.subscription_id, l.user_id, l.connection_id, l.previous_hash, l.new_hash,
                    l.changed_fields_json, l.status, l.error_code, l.safe_error, l.duration_ms,
                    l.created_at, s.name AS server_name, u.name AS user_name
             FROM vpn_v2_sync_logs l
             LEFT JOIN vpn_v2_servers s ON s.id = l.server_id
             LEFT JOIN users u ON u.id = l.user_id
             ORDER BY l.id DESC LIMIT ' . $limit
        )->get() ?: [];
    }

    public function conflicts(int $limit = 200): array
    {
        $limit = max(1, min(500, $limit));

        return db()->query(
            'SELECT c.id, c.conflict_type, c.server_id, c.subscription_id, c.connection_id,
                    c.local_value, c.remote_value, c.recommended_action, c.status,
                    c.operation_id, c.detected_at, c.resolved_at, c.created_at,
                    s.name AS server_name
             FROM vpn_v2_sync_conflicts c
             LEFT JOIN vpn_v2_servers s ON s.id = c.server_id
             ORDER BY (c.status = \'open\') DESC, c.id DESC LIMIT ' . $limit
        )->get() ?: [];
    }

    public function conflict(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        $type = $this->nullable($data['conflict_type'] ?? 'ambiguous_match', 60) ?? 'ambiguous_match';
        $serverId = $this->positive($data['server_id'] ?? null);
        $subscriptionId = $this->positive($data['subscription_id'] ?? null);
        $connectionId = $this->positive($data['connection_id'] ?? null);
        $existing = db()->query(
            "SELECT id FROM vpn_v2_sync_conflicts
             WHERE conflict_type = ? AND server_id <=> ? AND subscription_id <=> ?
               AND connection_id <=> ? AND status = 'open' LIMIT 1",
            [$type, $serverId, $subscriptionId, $connectionId]
        )->getOne();
        if (is_array($existing)) {
            db()->query(
                'UPDATE vpn_v2_sync_conflicts
                 SET remote_value = ?, recommended_action = ?, operation_id = ?, detected_at = ?, updated_at = ?
                 WHERE id = ?',
                [
                    $this->safeValue($data['remote_value'] ?? null),
                    $this->nullable($data['recommended_action'] ?? null, 255),
                    $this->nullable($data['operation_id'] ?? null, 36),
                    $now,
                    $now,
                    (int)$existing['id'],
                ]
            );

            return (int)$existing['id'];
        }
        db()->query(
            'INSERT INTO vpn_v2_sync_conflicts
                (conflict_type, server_id, subscription_id, connection_id, local_value,
                 remote_value, recommended_action, status, operation_id, detected_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, \'open\', ?, ?, ?, ?)',
            [
                $type,
                $serverId,
                $subscriptionId,
                $connectionId,
                $this->safeValue($data['local_value'] ?? null),
                $this->safeValue($data['remote_value'] ?? null),
                $this->nullable($data['recommended_action'] ?? null, 255),
                $this->nullable($data['operation_id'] ?? null, 36),
                $now,
                $now,
                $now,
            ]
        );

        return (int)db()->getInsertId();
    }

    public function resolveForConnection(int $connectionId, ?int $adminId): int
    {
        $now = date('Y-m-d H:i:s');
        db()->query(
            "UPDATE vpn_v2_sync_conflicts
             SET status = 'resolved', resolved_at = ?, resolved_by = ?, updated_at = ?
             WHERE connection_id = ? AND status = 'open'",
            [$now, $adminId !== null && $adminId > 0 ? $adminId : null, $now, $connectionId]
        );

        return db()->rowCount();
    }

    private function positive(mixed $value): ?int
    {
        $value = (int)$value;

        return $value > 0 ? $value : null;
    }

    private function nullable(mixed $value, int $length): ?string
    {
        $value = mb_substr(trim((string)$value), 0, $length);

        return $value !== '' ? $value : null;
    }

    private function hash(mixed $value): ?string
    {
        $value = strtolower(trim((string)$value));

        return preg_match('/^[a-f0-9]{64}$/', $value) === 1 ? $value : null;
    }

    private function safeError(mixed $value): ?string
    {
        return $this->nullable($value, 1000);
    }

    private function safeValue(mixed $value): ?string
    {
        $value = is_scalar($value) ? (string)$value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $value = trim((string)$value);

        return $value !== '' ? mb_substr($value, 0, 1000) : null;
    }
}
