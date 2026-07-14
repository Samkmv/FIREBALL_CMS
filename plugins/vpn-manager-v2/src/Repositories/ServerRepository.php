<?php

namespace Fireball\VpnManagerV2\Repositories;

final class ServerRepository
{
    private const PUBLIC_COLUMNS = 'id, name, code, panel_url, panel_path, auth_type, country_code, country_name, city,
        show_flag, status, is_enabled, last_check_at, last_success_at, last_error, created_at, updated_at,
        CASE WHEN encrypted_username IS NOT NULL AND encrypted_username <> "" THEN 1 ELSE 0 END AS has_username,
        CASE WHEN encrypted_password IS NOT NULL AND encrypted_password <> "" THEN 1 ELSE 0 END AS has_password,
        CASE WHEN encrypted_token IS NOT NULL AND encrypted_token <> "" THEN 1 ELSE 0 END AS has_token';

    public function all(): array
    {
        return db()->query('SELECT ' . self::PUBLIC_COLUMNS . ' FROM vpn_v2_servers ORDER BY id ASC')->get() ?: [];
    }

    public function find(int $id): ?array
    {
        $row = db()->query(
            'SELECT ' . self::PUBLIC_COLUMNS . ' FROM vpn_v2_servers WHERE id = ? LIMIT 1',
            [$id]
        )->getOne();

        return is_array($row) ? $row : null;
    }

    public function findWithSecrets(int $id): ?array
    {
        $row = db()->query(
            'SELECT *,
                CASE WHEN encrypted_username IS NOT NULL AND encrypted_username <> "" THEN 1 ELSE 0 END AS has_username,
                CASE WHEN encrypted_password IS NOT NULL AND encrypted_password <> "" THEN 1 ELSE 0 END AS has_password,
                CASE WHEN encrypted_token IS NOT NULL AND encrypted_token <> "" THEN 1 ELSE 0 END AS has_token
             FROM vpn_v2_servers WHERE id = ? LIMIT 1',
            [$id]
        )->getOne();

        return is_array($row) ? $row : null;
    }

    public function codeExists(string $code, ?int $exceptId = null): bool
    {
        $sql = 'SELECT id FROM vpn_v2_servers WHERE code = ?';
        $params = [$code];
        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }

        return (bool)db()->query($sql . ' LIMIT 1', $params)->getOne();
    }

    public function create(array $data, array $encryptedSecrets): int
    {
        $now = date('Y-m-d H:i:s');
        db()->query(
            'INSERT INTO vpn_v2_servers
                (name, code, panel_url, panel_path, auth_type, encrypted_username, encrypted_password, encrypted_token,
                 country_code, country_name, city, show_flag, status, is_enabled, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['name'],
                $data['code'],
                $data['panel_url'],
                $data['panel_path'],
                $data['auth_type'],
                $encryptedSecrets['encrypted_username'] ?: null,
                $encryptedSecrets['encrypted_password'] ?: null,
                $encryptedSecrets['encrypted_token'] ?: null,
                $data['country_code'],
                $data['country_name'],
                $data['city'],
                $data['show_flag'],
                'unchecked',
                $data['is_enabled'],
                $now,
                $now,
            ]
        );

        $id = (int)db()->getInsertId();
        $this->logEvent('server.created', $id, ['code' => $data['code'], 'auth_type' => $data['auth_type']]);

        return $id;
    }

    public function update(int $id, array $data, array $encryptedSecrets): void
    {
        $sql = 'UPDATE vpn_v2_servers
                SET name = ?, code = ?, panel_url = ?, panel_path = ?, auth_type = ?, country_code = ?,
                    country_name = ?, city = ?, show_flag = ?, is_enabled = ?, updated_at = ?';
        $params = [
            $data['name'],
            $data['code'],
            $data['panel_url'],
            $data['panel_path'],
            $data['auth_type'],
            $data['country_code'],
            $data['country_name'],
            $data['city'],
            $data['show_flag'],
            $data['is_enabled'],
            date('Y-m-d H:i:s'),
        ];

        foreach (['encrypted_username', 'encrypted_password', 'encrypted_token'] as $column) {
            if (!array_key_exists($column, $encryptedSecrets)) {
                continue;
            }
            $sql .= ', ' . $column . ' = ?';
            $params[] = $encryptedSecrets[$column] !== '' ? $encryptedSecrets[$column] : null;
        }

        $params[] = $id;
        db()->query($sql . ' WHERE id = ?', $params);
        $this->logEvent('server.updated', $id, ['code' => $data['code'], 'auth_type' => $data['auth_type']]);
    }

    public function toggle(int $id): bool
    {
        db()->query(
            'UPDATE vpn_v2_servers SET is_enabled = IF(is_enabled = 1, 0, 1), updated_at = ? WHERE id = ?',
            [date('Y-m-d H:i:s'), $id]
        );
        $row = $this->find($id);
        $enabled = !empty($row['is_enabled']);
        $this->logEvent('server.toggled', $id, ['is_enabled' => $enabled]);

        return $enabled;
    }

    public function recordConnectionSuccess(int $id, int $inboundCount): void
    {
        $now = date('Y-m-d H:i:s');
        db()->query(
            'UPDATE vpn_v2_servers
             SET status = ?, last_check_at = ?, last_success_at = ?, last_error = NULL, updated_at = ?
             WHERE id = ?',
            ['online', $now, $now, $now, $id]
        );
        $this->logEvent('server.check.success', $id, ['inbound_count' => $inboundCount]);
    }

    public function recordConnectionFailure(int $id, string $status, string $safeMessage, string $errorType): void
    {
        $status = in_array($status, ['offline', 'error'], true) ? $status : 'error';
        $safeMessage = mb_substr(trim($safeMessage), 0, 1000);
        $now = date('Y-m-d H:i:s');
        db()->query(
            'UPDATE vpn_v2_servers
             SET status = ?, last_check_at = ?, last_error = ?, updated_at = ?
             WHERE id = ?',
            [$status, $now, $safeMessage, $now, $id]
        );
        $this->logEvent('server.check.failed', $id, [
            'status' => $status,
            'error_type' => $errorType,
        ]);
    }

    private function logEvent(string $eventType, int $serverId, array $context = []): void
    {
        $user = get_user();
        $adminId = is_array($user) ? (int)($user['id'] ?? 0) : 0;
        $contextJson = $context !== []
            ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null;

        db()->query(
            'INSERT INTO vpn_v2_events (event_type, server_id, admin_id, context_json, created_at)
             VALUES (?, ?, ?, ?, ?)',
            [$eventType, $serverId, $adminId > 0 ? $adminId : null, $contextJson ?: null, date('Y-m-d H:i:s')]
        );
    }
}
