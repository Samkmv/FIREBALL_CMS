<?php

namespace Fireball\VpnManagerV2\Repositories;

final class ServerHealthRepository
{
    public function nextDue(): ?array
    {
        $row = db()->query("SELECT s.id, COALESCE(h.consecutive_failures, 0) AS consecutive_failures,
                h.last_success_at
            FROM vpn_v2_servers s LEFT JOIN vpn_v2_server_health h ON h.server_id = s.id
            WHERE s.is_enabled = 1 AND s.maintenance_mode = 0
              AND (h.server_id IS NULL OR h.next_check_at <= NOW())
            ORDER BY COALESCE(h.next_check_at, '1970-01-01') ASC, s.id ASC LIMIT 1")->getOne();
        return is_array($row) ? $row : null;
    }

    public function inbounds(int $serverId): array
    {
        return db()->query("SELECT id, port, network, security FROM vpn_v2_inbounds
            WHERE server_id = ? AND is_enabled = 1 AND status = 'active' ORDER BY id LIMIT 8", [$serverId])->get() ?: [];
    }

    public function save(int $serverId, array $snapshot, int $failures, int $delay): void
    {
        $json = json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $now = date('Y-m-d H:i:s');
        $success = $snapshot['state'] === 'healthy' ? $now : null;
        db()->query('INSERT INTO vpn_v2_server_health
            (server_id, state, consecutive_failures, last_check_at, last_success_at, next_check_at, snapshot_json)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE state = VALUES(state), consecutive_failures = VALUES(consecutive_failures),
                last_check_at = VALUES(last_check_at), last_success_at = COALESCE(VALUES(last_success_at), last_success_at),
                next_check_at = VALUES(next_check_at), snapshot_json = VALUES(snapshot_json)',
            [$serverId, $snapshot['state'], $failures, $now, $success, date('Y-m-d H:i:s', time() + $delay), $json]);
        db()->query('INSERT INTO vpn_v2_server_health_checks (server_id, state, checked_at, snapshot_json) VALUES (?, ?, ?, ?)',
            [$serverId, $snapshot['state'], $now, $json]);
        $cutoff = (int)db()->query('SELECT id FROM vpn_v2_server_health_checks WHERE server_id = ? ORDER BY id DESC LIMIT 1 OFFSET 49', [$serverId])->getColumn();
        if ($cutoff > 0) {
            db()->query('DELETE FROM vpn_v2_server_health_checks WHERE server_id = ? AND id < ?', [$serverId, $cutoff]);
        }
    }

    public function all(): array
    {
        return db()->query('SELECT h.*, s.name AS server_name FROM vpn_v2_server_health h
            INNER JOIN vpn_v2_servers s ON s.id = h.server_id ORDER BY s.id')->get() ?: [];
    }
}
