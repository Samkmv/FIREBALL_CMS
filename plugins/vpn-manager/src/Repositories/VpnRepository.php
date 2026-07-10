<?php

namespace Fireball\VpnManager\Repositories;

use Fireball\VpnManager\Support\Crypto;
use Fireball\VpnManager\Support\Formatter;
use Fireball\VpnManager\Support\Schema;

final class VpnRepository
{
    public function __construct()
    {
        Schema::ensure();
    }

    public function dashboardStats(): array
    {
        return [
            'active_subscriptions' => $this->count("vpn_subscriptions WHERE status = 'active'"),
            'expires_3_days' => $this->count("vpn_subscriptions WHERE status = 'active' AND expires_at >= NOW() AND expires_at <= DATE_ADD(NOW(), INTERVAL 3 DAY)"),
            'expires_today' => $this->count("vpn_subscriptions WHERE status = 'active' AND DATE(expires_at) = CURDATE()"),
            'expired_subscriptions' => $this->count("vpn_subscriptions WHERE status = 'expired' OR (status = 'active' AND expires_at < NOW())"),
            'servers_online' => $this->count("vpn_servers WHERE status = 'online' AND is_enabled = 1"),
            'servers_error' => $this->count("vpn_servers WHERE status IN ('offline', 'error')"),
            'traffic_used' => (int)db()->query('SELECT COALESCE(SUM(traffic_used_bytes), 0) FROM vpn_subscriptions')->getColumn(),
            'sync_errors' => $this->count("vpn_subscription_nodes WHERE status IN ('sync_error', 'create_failed')") + $this->count("vpn_subscriptions WHERE status IN ('sync_error', 'provisioning_failed')"),
        ];
    }

    public function servers(): array
    {
        return db()->query('SELECT * FROM vpn_servers ORDER BY sort_order ASC, id ASC')->get() ?: [];
    }

    public function enabledServers(): array
    {
        return db()->query('SELECT * FROM vpn_servers WHERE is_enabled = 1 ORDER BY sort_order ASC, id ASC')->get() ?: [];
    }

    public function server(int $id): ?array
    {
        $row = db()->query('SELECT * FROM vpn_servers WHERE id = ? LIMIT 1', [$id])->getOne();

        return is_array($row) ? $row : null;
    }

    public function saveServer(array $data, ?int $id = null): int
    {
        $now = date('Y-m-d H:i:s');
        $name = trim((string)($data['name'] ?? ''));
        $code = make_slug((string)($data['code'] ?? $name), 'server');
        $panelUrl = rtrim(trim((string)($data['panel_url'] ?? '')), '/');
        if ($name === '' || $code === '' || $panelUrl === '') {
            throw new \RuntimeException(\FireballPluginVpnManager::t('vpn_manager_error_server_required'));
        }

        $normalized = [
            'name' => mb_substr($name, 0, 255),
            'code' => mb_substr($code, 0, 80),
            'country' => mb_substr(trim((string)($data['country'] ?? '')), 0, 120),
            'city' => mb_substr(trim((string)($data['city'] ?? '')), 0, 120),
            'panel_url' => mb_substr($panelUrl, 0, 500),
            'panel_path' => mb_substr(trim((string)($data['panel_path'] ?? '')), 0, 190),
            'api_auth_type' => in_array((string)($data['api_auth_type'] ?? 'token'), ['token', 'password'], true) ? (string)$data['api_auth_type'] : 'token',
            'is_enabled' => !empty($data['is_enabled']) ? 1 : 0,
            'sort_order' => max(0, (int)($data['sort_order'] ?? 0)),
        ];

        $secrets = [
            'api_token_encrypted' => trim((string)($data['api_token'] ?? '')),
            'username_encrypted' => trim((string)($data['username'] ?? '')),
            'password_encrypted' => trim((string)($data['password'] ?? '')),
        ];

        if ($id !== null) {
            $server = $this->server($id);
            if (!$server) {
                throw new \RuntimeException(\FireballPluginVpnManager::t('vpn_manager_error_server_not_found'));
            }

            $params = [
                $normalized['name'],
                $normalized['code'],
                $normalized['country'] ?: null,
                $normalized['city'] ?: null,
                $normalized['panel_url'],
                $normalized['panel_path'] ?: null,
                $normalized['api_auth_type'],
                $normalized['is_enabled'],
                $normalized['sort_order'],
                $now,
            ];
            $secretSql = '';
            foreach ($secrets as $field => $plain) {
                if ($plain !== '' && !str_contains($plain, '•')) {
                    $secretSql .= ', ' . $field . ' = ?';
                    $params[] = Crypto::encrypt($plain);
                }
            }
            $params[] = $id;

            db()->query(
                'UPDATE vpn_servers
                 SET name = ?, code = ?, country = ?, city = ?, panel_url = ?, panel_path = ?, api_auth_type = ?, is_enabled = ?, sort_order = ?, updated_at = ?' . $secretSql . '
                 WHERE id = ?',
                $params
            );

            $this->logEvent('server.updated', 'VPN server updated.', ['server' => $normalized['code']], serverId: $id);

            return $id;
        }

        db()->query(
            'INSERT INTO vpn_servers
                (name, code, country, city, panel_url, panel_path, api_auth_type, api_token_encrypted, username_encrypted, password_encrypted, status, is_enabled, sort_order, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $normalized['name'],
                $normalized['code'],
                $normalized['country'] ?: null,
                $normalized['city'] ?: null,
                $normalized['panel_url'],
                $normalized['panel_path'] ?: null,
                $normalized['api_auth_type'],
                Crypto::encrypt($secrets['api_token_encrypted']),
                Crypto::encrypt($secrets['username_encrypted']),
                Crypto::encrypt($secrets['password_encrypted']),
                'unchecked',
                $normalized['is_enabled'],
                $normalized['sort_order'],
                $now,
                $now,
            ]
        );

        $newId = (int)db()->getInsertId();
        $this->logEvent('server.created', 'VPN server created.', ['server' => $normalized['code']], serverId: $newId);

        return $newId;
    }

    public function updateServerCheck(int $id, bool $success, string $message = ''): void
    {
        db()->query(
            'UPDATE vpn_servers
             SET status = ?, last_check_at = ?, last_success_at = IF(? = 1, ?, last_success_at), last_error = ?, updated_at = ?
             WHERE id = ?',
            [
                $success ? 'online' : 'error',
                date('Y-m-d H:i:s'),
                $success ? 1 : 0,
                date('Y-m-d H:i:s'),
                $success ? null : mb_substr($message, 0, 1000),
                date('Y-m-d H:i:s'),
                $id,
            ]
        );

        $this->logEvent($success ? 'server.check.success' : 'server.check.failed', $message ?: 'VPN server connection checked.', [], serverId: $id);
    }

    public function toggleServer(int $id): void
    {
        db()->query('UPDATE vpn_servers SET is_enabled = IF(is_enabled = 1, 0, 1), updated_at = ? WHERE id = ?', [date('Y-m-d H:i:s'), $id]);
        $this->logEvent('server.toggled', 'VPN server enabled state changed.', [], serverId: $id);
    }

    public function deleteServer(int $id): void
    {
        db()->query('DELETE FROM vpn_servers WHERE id = ?', [$id]);
        $this->logEvent('server.deleted', 'VPN server deleted.', [], serverId: $id);
    }

    public function inbounds(): array
    {
        return db()->query(
            'SELECT i.*, s.name AS server_name
             FROM vpn_inbounds i
             LEFT JOIN vpn_servers s ON s.id = i.server_id
             ORDER BY s.sort_order ASC, i.id ASC'
        )->get() ?: [];
    }

    public function toggleInbound(int $id): void
    {
        db()->query('UPDATE vpn_inbounds SET is_enabled = IF(is_enabled = 1, 0, 1), updated_at = ? WHERE id = ?', [date('Y-m-d H:i:s'), $id]);
        $this->logEvent('inbound.toggled', 'VPN inbound enabled state changed.', ['inbound_id' => $id]);
    }

    public function syncInboundsFromRemote(int $serverId, array $remoteItems): int
    {
        $server = $this->server($serverId);
        if (!$server) {
            throw new \RuntimeException(\FireballPluginVpnManager::t('vpn_manager_error_server_not_found'));
        }

        $count = 0;
        $now = date('Y-m-d H:i:s');
        foreach ($remoteItems as $item) {
            if (!is_array($item)) {
                continue;
            }

            $remoteId = (string)($item['id'] ?? $item['remote_inbound_id'] ?? '');
            $name = trim((string)($item['remark'] ?? $item['name'] ?? ('Inbound ' . $remoteId)));
            if ($remoteId === '' || $name === '') {
                continue;
            }

            db()->query(
                'INSERT INTO vpn_inbounds
                    (server_id, remote_inbound_id, name, protocol, remark, port, is_enabled, settings_json, stream_settings_json, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    protocol = VALUES(protocol),
                    remark = VALUES(remark),
                    port = VALUES(port),
                    settings_json = VALUES(settings_json),
                    stream_settings_json = VALUES(stream_settings_json),
                    updated_at = VALUES(updated_at)',
                [
                    $serverId,
                    $remoteId,
                    mb_substr($name, 0, 255),
                    mb_substr((string)($item['protocol'] ?? ''), 0, 80) ?: null,
                    mb_substr((string)($item['remark'] ?? ''), 0, 255) ?: null,
                    isset($item['port']) ? (int)$item['port'] : null,
                    isset($item['settings']) ? (is_string($item['settings']) ? $item['settings'] : json_encode($item['settings'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) : null,
                    isset($item['streamSettings']) ? (is_string($item['streamSettings']) ? $item['streamSettings'] : json_encode($item['streamSettings'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) : null,
                    $now,
                    $now,
                ]
            );
            $count++;
        }

        $this->logEvent('inbounds.synced', 'VPN inbounds synchronized from server.', ['count' => $count], serverId: $serverId);

        return $count;
    }

    public function plans(): array
    {
        return db()->query(
            'SELECT p.*,
                    COUNT(ps.id) AS server_count
             FROM vpn_plans p
             LEFT JOIN vpn_plan_servers ps ON ps.plan_id = p.id AND ps.is_enabled = 1
             GROUP BY p.id
             ORDER BY p.sort_order ASC, p.id ASC'
        )->get() ?: [];
    }

    public function plan(int $id): ?array
    {
        $row = db()->query('SELECT * FROM vpn_plans WHERE id = ? LIMIT 1', [$id])->getOne();

        return is_array($row) ? $row : null;
    }

    public function togglePlan(int $id): void
    {
        db()->query('UPDATE vpn_plans SET is_active = IF(is_active = 1, 0, 1), updated_at = ? WHERE id = ?', [date('Y-m-d H:i:s'), $id]);
        $this->logEvent('plan.toggled', 'VPN plan active state changed.', ['plan_id' => $id]);
    }

    public function deletePlan(int $id): void
    {
        db()->query('DELETE FROM vpn_plan_servers WHERE plan_id = ?', [$id]);
        db()->query('DELETE FROM vpn_plans WHERE id = ?', [$id]);
        $this->logEvent('plan.deleted', 'VPN plan deleted.', ['plan_id' => $id]);
    }

    public function planServerIds(int $planId): array
    {
        $rows = db()->query('SELECT server_id FROM vpn_plan_servers WHERE plan_id = ? AND is_enabled = 1', [$planId])->get() ?: [];

        return array_map('intval', array_column($rows, 'server_id'));
    }

    public function savePlan(array $data, ?int $id = null): int
    {
        $now = date('Y-m-d H:i:s');
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            throw new \RuntimeException(\FireballPluginVpnManager::t('vpn_manager_error_plan_required'));
        }

        $trafficMode = (string)($data['traffic_mode'] ?? 'shared');
        if (!in_array($trafficMode, ['shared', 'per_node'], true)) {
            $trafficMode = 'shared';
        }

        $payload = [
            'name' => mb_substr($name, 0, 255),
            'description' => trim((string)($data['description'] ?? '')),
            'duration_days' => max(1, (int)($data['duration_days'] ?? 30)),
            'traffic_limit_bytes' => Formatter::gbToBytes($data['traffic_limit_gb'] ?? 0),
            'device_limit' => max(1, (int)($data['device_limit'] ?? 1)),
            'is_active' => !empty($data['is_active']) ? 1 : 0,
            'traffic_mode' => $trafficMode,
            'sort_order' => max(0, (int)($data['sort_order'] ?? 0)),
        ];

        if ($id !== null) {
            db()->query(
                'UPDATE vpn_plans
                 SET name = ?, description = ?, duration_days = ?, traffic_limit_bytes = ?, device_limit = ?, is_active = ?, traffic_mode = ?, sort_order = ?, updated_at = ?
                 WHERE id = ?',
                [
                    $payload['name'],
                    $payload['description'] ?: null,
                    $payload['duration_days'],
                    $payload['traffic_limit_bytes'],
                    $payload['device_limit'],
                    $payload['is_active'],
                    $payload['traffic_mode'],
                    $payload['sort_order'],
                    $now,
                    $id,
                ]
            );
            $planId = $id;
            $this->logEvent('plan.updated', 'VPN plan updated.', ['plan' => $payload['name']]);
        } else {
            db()->query(
                'INSERT INTO vpn_plans
                    (name, description, duration_days, traffic_limit_bytes, device_limit, is_active, traffic_mode, sort_order, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $payload['name'],
                    $payload['description'] ?: null,
                    $payload['duration_days'],
                    $payload['traffic_limit_bytes'],
                    $payload['device_limit'],
                    $payload['is_active'],
                    $payload['traffic_mode'],
                    $payload['sort_order'],
                    $now,
                    $now,
                ]
            );
            $planId = (int)db()->getInsertId();
            $this->logEvent('plan.created', 'VPN plan created.', ['plan' => $payload['name']]);
        }

        $this->syncPlanServers($planId, (array)($data['server_ids'] ?? []));

        return $planId;
    }

    public function subscriptions(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));

        return db()->query(
            "SELECT s.*,
                    u.name AS user_name,
                    u.email AS user_email,
                    p.name AS plan_name,
                    COUNT(n.id) AS node_count
             FROM vpn_subscriptions s
             LEFT JOIN users u ON u.id = s.user_id
             LEFT JOIN vpn_plans p ON p.id = s.plan_id
             LEFT JOIN vpn_subscription_nodes n ON n.subscription_id = s.id
             GROUP BY s.id
             ORDER BY s.id DESC
             LIMIT {$limit}"
        )->get() ?: [];
    }

    public function subscription(int $id): ?array
    {
        $row = db()->query(
            'SELECT s.*, u.name AS user_name, u.email AS user_email, p.name AS plan_name
             FROM vpn_subscriptions s
             LEFT JOIN users u ON u.id = s.user_id
             LEFT JOIN vpn_plans p ON p.id = s.plan_id
             WHERE s.id = ?
             LIMIT 1',
            [$id]
        )->getOne();

        return is_array($row) ? $row : null;
    }

    public function subscriptionNodes(int $subscriptionId): array
    {
        return db()->query(
            'SELECT n.*, s.name AS server_name, i.name AS inbound_name
             FROM vpn_subscription_nodes n
             LEFT JOIN vpn_servers s ON s.id = n.server_id
             LEFT JOIN vpn_inbounds i ON i.id = n.inbound_id
             WHERE n.subscription_id = ?
             ORDER BY n.id ASC',
            [$subscriptionId]
        )->get() ?: [];
    }

    public function subscriptionNotifications(int $subscriptionId): array
    {
        return db()->query(
            'SELECT * FROM vpn_notifications WHERE subscription_id = ? ORDER BY created_at DESC, id DESC LIMIT 50',
            [$subscriptionId]
        )->get() ?: [];
    }

    public function connections(): array
    {
        return db()->query(
            'SELECT n.*,
                    s.name AS server_name,
                    i.name AS inbound_name,
                    sub.user_id,
                    u.name AS user_name
             FROM vpn_subscription_nodes n
             LEFT JOIN vpn_servers s ON s.id = n.server_id
             LEFT JOIN vpn_inbounds i ON i.id = n.inbound_id
             LEFT JOIN vpn_subscriptions sub ON sub.id = n.subscription_id
             LEFT JOIN users u ON u.id = sub.user_id
             ORDER BY n.id DESC
             LIMIT 200'
        )->get() ?: [];
    }

    public function userSummaries(): array
    {
        return db()->query(
            "SELECT u.id, u.name, u.email,
                    COUNT(s.id) AS subscription_count,
                    SUM(CASE WHEN s.status = 'active' THEN 1 ELSE 0 END) AS active_count,
                    MAX(s.expires_at) AS latest_expires_at
             FROM users u
             LEFT JOIN vpn_subscriptions s ON s.user_id = u.id
             GROUP BY u.id
             HAVING subscription_count > 0
             ORDER BY latest_expires_at DESC, u.id DESC
             LIMIT 200"
        )->get() ?: [];
    }

    public function events(int $limit = 200): array
    {
        $limit = max(1, min(500, $limit));

        return db()->query(
            "SELECT e.*, u.name AS user_name, a.name AS admin_name
             FROM vpn_events e
             LEFT JOIN users u ON u.id = e.user_id
             LEFT JOIN users a ON a.id = e.admin_id
             ORDER BY e.id DESC
             LIMIT {$limit}"
        )->get() ?: [];
    }

    public function instructions(): array
    {
        return [
            ['platform' => 'iPhone / iPad', 'app' => 'Any compatible VPN client', 'status' => 'prepared'],
            ['platform' => 'Android', 'app' => 'Any compatible VPN client', 'status' => 'prepared'],
            ['platform' => 'Windows', 'app' => 'Any compatible VPN client', 'status' => 'prepared'],
            ['platform' => 'macOS', 'app' => 'Any compatible VPN client', 'status' => 'prepared'],
        ];
    }

    public function mySubscriptions(int $userId): array
    {
        return db()->query(
            'SELECT s.*, p.name AS plan_name, p.description AS plan_description
             FROM vpn_subscriptions s
             LEFT JOIN vpn_plans p ON p.id = s.plan_id
             WHERE s.user_id = ?
             ORDER BY s.id DESC',
            [$userId]
        )->get() ?: [];
    }

    public function logEvent(
        string $eventType,
        string $message,
        array $context = [],
        ?int $userId = null,
        ?int $subscriptionId = null,
        ?int $nodeId = null,
        ?int $serverId = null
    ): void {
        $admin = function_exists('get_user') ? get_user() : null;
        $adminId = is_array($admin) && check_admin() ? (int)($admin['id'] ?? 0) : null;
        $context = $this->sanitizeContext($context);
        db()->query(
            'INSERT INTO vpn_events
                (event_type, user_id, admin_id, subscription_id, node_id, server_id, message, context_json, ip_address, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                mb_substr($eventType, 0, 120),
                $userId ?: null,
                $adminId ?: null,
                $subscriptionId ?: null,
                $nodeId ?: null,
                $serverId ?: null,
                mb_substr($message, 0, 500),
                $context ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                function_exists('client_ip') ? client_ip() : null,
                date('Y-m-d H:i:s'),
            ]
        );
    }

    private function syncPlanServers(int $planId, array $serverIds): void
    {
        $serverIds = array_values(array_unique(array_filter(array_map('intval', $serverIds))));
        db()->query('UPDATE vpn_plan_servers SET is_enabled = 0, updated_at = ? WHERE plan_id = ?', [date('Y-m-d H:i:s'), $planId]);

        foreach ($serverIds as $index => $serverId) {
            $existing = db()->query(
                'SELECT id FROM vpn_plan_servers WHERE plan_id = ? AND server_id = ? AND inbound_id IS NULL LIMIT 1',
                [$planId, $serverId]
            )->getOne();

            if ($existing) {
                db()->query(
                    'UPDATE vpn_plan_servers SET sort_order = ?, is_enabled = 1, updated_at = ? WHERE id = ?',
                    [$index + 1, date('Y-m-d H:i:s'), (int)$existing['id']]
                );
                continue;
            }

            db()->query(
                'INSERT INTO vpn_plan_servers (plan_id, server_id, inbound_id, sort_order, is_enabled, created_at, updated_at)
                 VALUES (?, ?, NULL, ?, 1, ?, ?)',
                [$planId, $serverId, $index + 1, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
            );
        }
    }

    private function count(string $from): int
    {
        return (int)db()->query('SELECT COUNT(*) FROM ' . $from)->getColumn();
    }

    private function sanitizeContext(array $context): array
    {
        $blocked = ['token', 'password', 'secret', 'uuid', 'subscription', 'link'];
        foreach ($context as $key => $value) {
            $normalized = strtolower((string)$key);
            foreach ($blocked as $needle) {
                if (str_contains($normalized, $needle)) {
                    $context[$key] = '[masked]';
                    continue 2;
                }
            }
        }

        return $context;
    }
}
