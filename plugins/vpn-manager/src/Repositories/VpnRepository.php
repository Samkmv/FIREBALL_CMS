<?php

namespace Fireball\VpnManager\Repositories;

use Fireball\VpnManager\Support\Crypto;
use Fireball\VpnManager\Support\Formatter;
use Fireball\VpnManager\Support\Schema;
use Fireball\VpnManager\Services\CountryFlagService;
use Fireball\VpnManager\Services\SubscriptionLinkService;

final class VpnRepository
{
    public function __construct()
    {
        Schema::ensure();
    }

    public function dashboardStats(): array
    {
        $connections = $this->count("vpn_subscription_nodes WHERE status <> 'deleted'")
            + $this->count("vpn_remote_clients WHERE status <> 'sync_missing'");

        return [
            'subscriptions' => $this->count('vpn_subscriptions'),
            'active_subscriptions' => $this->count("vpn_subscriptions WHERE status = 'active'"),
            'provisioning_failed' => $this->count("vpn_subscriptions WHERE status = 'provisioning_failed'"),
            'expires_3_days' => $this->count("vpn_subscriptions WHERE status = 'active' AND expires_at >= NOW() AND expires_at <= DATE_ADD(NOW(), INTERVAL 3 DAY)"),
            'expires_today' => $this->count("vpn_subscriptions WHERE status = 'active' AND DATE(expires_at) = CURDATE()"),
            'expired_subscriptions' => $this->count("vpn_subscriptions WHERE status = 'expired' OR (status = 'active' AND expires_at < NOW())"),
            'traffic_exceeded' => $this->count("vpn_subscriptions WHERE status = 'traffic_exceeded'"),
            'connections' => $connections,
            'vpn_users' => (int)db()->query('SELECT COUNT(DISTINCT user_id) FROM vpn_subscriptions WHERE user_id IS NOT NULL')->getColumn(),
            'servers_online' => $this->count("vpn_servers WHERE status = 'online' AND is_enabled = 1"),
            'servers_error' => $this->count("vpn_servers WHERE status IN ('offline', 'error')"),
            'traffic_used' => (int)db()->query('SELECT COALESCE(SUM(traffic_used_bytes), 0) FROM vpn_subscriptions')->getColumn(),
            'traffic_month' => (int)db()->query("SELECT COALESCE(SUM(total_bytes), 0) FROM vpn_traffic_snapshots WHERE captured_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")->getColumn(),
            'notifications_today' => $this->count("vpn_notifications WHERE status = 'sent' AND DATE(sent_at) = CURDATE()"),
            'sync_errors' => $this->count("vpn_subscription_nodes WHERE status IN ('sync_error', 'create_failed', 'sync_missing')") + $this->count("vpn_subscriptions WHERE status IN ('sync_error', 'provisioning_failed')") + $this->count("vpn_events WHERE event_type LIKE '%.failed' OR event_type LIKE '%.error'"),
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
        $flagService = new CountryFlagService();
        $countryCode = $flagService->normalizeCountryCode((string)($data['country_code'] ?? ''));
        if ($countryCode !== '' && !$flagService->isValidCountryCode($countryCode)) {
            throw new \RuntimeException(\FireballPluginVpnManager::t('vpn_manager_error_invalid_country_code'));
        }
        $countryName = trim((string)($data['country_name'] ?? $data['country'] ?? ''));
        if ($countryName === '' && $countryCode !== '') {
            $countryName = $flagService->countryName($countryCode);
        }

        $normalized = [
            'name' => mb_substr($name, 0, 255),
            'code' => mb_substr($code, 0, 80),
            'country_code' => $countryCode !== '' ? $countryCode : null,
            'country_name' => mb_substr($countryName, 0, 120) ?: null,
            'flag_emoji' => $countryCode !== '' ? $flagService->flagFromCountryCode($countryCode) : null,
            'show_flag' => !empty($data['show_flag']) ? 1 : 0,
            'country' => mb_substr($countryName, 0, 120),
            'city' => mb_substr(trim((string)($data['city'] ?? '')), 0, 120),
            'panel_url' => mb_substr($panelUrl, 0, 500),
            'public_host' => mb_substr($this->normalizePublicHost((string)($data['public_host'] ?? '')), 0, 255),
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
                $normalized['country_code'],
                $normalized['country_name'],
                $normalized['flag_emoji'],
                $normalized['show_flag'],
                $normalized['country'] ?: null,
                $normalized['city'] ?: null,
                $normalized['panel_url'],
                $normalized['public_host'] ?: null,
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
                 SET name = ?, code = ?, country_code = ?, country_name = ?, flag_emoji = ?, show_flag = ?, country = ?, city = ?, panel_url = ?, public_host = ?, panel_path = ?, api_auth_type = ?, is_enabled = ?, sort_order = ?, updated_at = ?' . $secretSql . '
                 WHERE id = ?',
                $params
            );

            $this->logEvent('server.updated', 'VPN server updated.', ['server' => $normalized['code']], serverId: $id);

            return $id;
        }

        db()->query(
            'INSERT INTO vpn_servers
                (name, code, country_code, country_name, flag_emoji, show_flag, country, city, panel_url, public_host, panel_path, api_auth_type, api_token_encrypted, username_encrypted, password_encrypted, status, is_enabled, sort_order, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $normalized['name'],
                $normalized['code'],
                $normalized['country_code'],
                $normalized['country_name'],
                $normalized['flag_emoji'],
                $normalized['show_flag'],
                $normalized['country'] ?: null,
                $normalized['city'] ?: null,
                $normalized['panel_url'],
                $normalized['public_host'] ?: null,
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

    public function inbound(int $id): ?array
    {
        $row = db()->query(
            'SELECT i.*, s.name AS server_name
             FROM vpn_inbounds i
             LEFT JOIN vpn_servers s ON s.id = i.server_id
             WHERE i.id = ?
             LIMIT 1',
            [$id]
        )->getOne();

        return is_array($row) ? $row : null;
    }

    public function activeInboundsWithServers(): array
    {
        return db()->query(
            "SELECT i.*,
                    i.id AS inbound_id,
                    s.id AS server_id,
                    s.name AS server_name,
                    s.code AS server_code,
                    s.country_code,
                    s.country_name,
                    s.flag_emoji,
                    s.show_flag,
                    s.country,
                    s.city
             FROM vpn_inbounds i
             INNER JOIN vpn_servers s ON s.id = i.server_id
             WHERE i.is_enabled = 1
               AND s.is_enabled = 1
               AND i.status = 'active'
             ORDER BY s.sort_order ASC, s.id ASC, i.id ASC"
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
        $clientCount = 0;
        $now = date('Y-m-d H:i:s');
        $seen = [];
        foreach ($remoteItems as $item) {
            if (!is_array($item)) {
                continue;
            }

            $remoteId = (string)($item['id'] ?? $item['remote_inbound_id'] ?? '');
            $name = trim((string)($item['remark'] ?? $item['name'] ?? ('Inbound ' . $remoteId)));
            if ($remoteId === '' || $name === '') {
                continue;
            }
            $seen[] = $remoteId;
            $remoteEnabled = !array_key_exists('enable', $item) || !empty($item['enable']);
            $status = $remoteEnabled ? 'active' : 'disabled';

            db()->query(
                'INSERT INTO vpn_inbounds
                    (server_id, remote_inbound_id, name, protocol, remark, port, is_enabled, status, settings_json, stream_settings_json, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    protocol = VALUES(protocol),
                    remark = VALUES(remark),
                    port = VALUES(port),
                    status = VALUES(status),
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
                    $remoteEnabled ? 1 : 0,
                    $status,
                    isset($item['settings']) ? (is_string($item['settings']) ? $item['settings'] : json_encode($item['settings'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) : null,
                    isset($item['streamSettings']) ? (is_string($item['streamSettings']) ? $item['streamSettings'] : json_encode($item['streamSettings'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) : null,
                    $now,
                    $now,
                ]
            );
            $inboundId = $this->inboundIdByRemote($serverId, $remoteId);
            if ($inboundId > 0) {
                $clientCount += $this->syncRemoteClientsFromInboundItem($serverId, $inboundId, $remoteId, $item);
            }
            $count++;
        }

        if ($seen) {
            $placeholders = implode(',', array_fill(0, count($seen), '?'));
            db()->query(
                "UPDATE vpn_inbounds
                 SET status = 'sync_missing', updated_at = ?
                 WHERE server_id = ? AND remote_inbound_id NOT IN ({$placeholders})",
                array_merge([$now, $serverId], $seen)
            );
        } else {
            db()->query(
                "UPDATE vpn_inbounds SET status = 'sync_missing', updated_at = ? WHERE server_id = ?",
                [$now, $serverId]
            );
        }

        $this->logEvent('inbounds.synced', 'VPN inbounds synchronized from server.', [
            'count' => $count,
            'remote_clients' => $clientCount,
        ], serverId: $serverId);

        return $count;
    }

    private function inboundIdByRemote(int $serverId, string $remoteId): int
    {
        return (int)db()->query(
            'SELECT id FROM vpn_inbounds WHERE server_id = ? AND remote_inbound_id = ? LIMIT 1',
            [$serverId, $remoteId]
        )->getColumn();
    }

    private function syncRemoteClientsFromInboundItem(int $serverId, int $inboundId, string $remoteInboundId, array $item): int
    {
        $settings = $this->decodeRemoteJson($item['settings'] ?? null);
        $clients = array_values(array_filter((array)($settings['clients'] ?? []), static fn($client): bool => is_array($client)));
        $now = date('Y-m-d H:i:s');
        $seen = [];
        $count = 0;

        foreach ($clients as $client) {
            $client = (array)$client;
            $uuid = trim((string)($client['id'] ?? $client['uuid'] ?? $client['password'] ?? ''));
            $email = trim((string)($client['email'] ?? ''));
            $keySource = $email !== '' ? 'email:' . $email : ($uuid !== '' ? 'uuid:' . $uuid : json_encode($client, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $clientKey = hash('sha256', (string)$keySource);
            $seen[] = $clientKey;
            $enabled = !array_key_exists('enable', $client) || !empty($client['enable']);
            $expiresAt = $this->dateTimeFromMillis((int)($client['expiryTime'] ?? $client['expiry_time'] ?? 0));
            $trafficLimit = (int)($client['totalGB'] ?? $client['total'] ?? $client['traffic_limit_bytes'] ?? 0);
            $trafficUsed = (int)($client['up'] ?? 0) + (int)($client['down'] ?? 0);

            db()->query(
                'INSERT INTO vpn_remote_clients
                    (server_id, inbound_id, remote_inbound_id, remote_client_key, client_uuid, client_email, client_remark, status, traffic_limit_bytes, traffic_used_bytes, expires_at, raw_json, last_seen_at, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    inbound_id = VALUES(inbound_id),
                    client_uuid = VALUES(client_uuid),
                    client_email = VALUES(client_email),
                    client_remark = VALUES(client_remark),
                    status = VALUES(status),
                    traffic_limit_bytes = VALUES(traffic_limit_bytes),
                    traffic_used_bytes = VALUES(traffic_used_bytes),
                    expires_at = VALUES(expires_at),
                    raw_json = VALUES(raw_json),
                    last_seen_at = VALUES(last_seen_at),
                    updated_at = VALUES(updated_at)',
                [
                    $serverId,
                    $inboundId,
                    $remoteInboundId,
                    $clientKey,
                    $uuid !== '' ? $uuid : null,
                    $email !== '' ? mb_substr($email, 0, 255) : null,
                    mb_substr((string)($client['comment'] ?? $client['remark'] ?? ''), 0, 255) ?: null,
                    $enabled ? 'active' : 'disabled',
                    max(0, $trafficLimit),
                    max(0, $trafficUsed),
                    $expiresAt,
                    json_encode($client, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    $now,
                    $now,
                    $now,
                ]
            );
            $count++;
        }

        if ($seen) {
            $placeholders = implode(',', array_fill(0, count($seen), '?'));
            db()->query(
                "UPDATE vpn_remote_clients
                 SET status = 'sync_missing', updated_at = ?
                 WHERE server_id = ? AND remote_inbound_id = ? AND remote_client_key NOT IN ({$placeholders})",
                array_merge([$now, $serverId, $remoteInboundId], $seen)
            );
        } else {
            db()->query(
                "UPDATE vpn_remote_clients SET status = 'sync_missing', updated_at = ? WHERE server_id = ? AND remote_inbound_id = ?",
                [$now, $serverId, $remoteInboundId]
            );
        }

        return $count;
    }

    private function decodeRemoteJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string)$value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function dateTimeFromMillis(int $value): ?string
    {
        if ($value <= 0) {
            return null;
        }

        $seconds = $value > 20000000000 ? (int)floor($value / 1000) : $value;

        return date('Y-m-d H:i:s', $seconds);
    }

    public function plans(): array
    {
        return db()->query(
            'SELECT p.*,
                    (SELECT COUNT(*) FROM vpn_plan_servers ps WHERE ps.plan_id = p.id AND ps.is_enabled = 1) AS server_count
             FROM vpn_plans p
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

    public function planInboundIds(int $planId): array
    {
        $rows = db()->query('SELECT inbound_id FROM vpn_plan_servers WHERE plan_id = ? AND is_enabled = 1 AND inbound_id IS NOT NULL', [$planId])->get() ?: [];

        return array_map('intval', array_column($rows, 'inbound_id'));
    }

    public function planItems(int $planId): array
    {
        return db()->query(
            "SELECT ps.*,
                    s.id AS server_id,
                    s.name AS server_name,
                    s.code AS server_code,
                    s.country_code,
                    s.country_name,
                    s.flag_emoji,
                    s.show_flag,
                    s.country,
                    s.city,
                    s.panel_url,
                    s.panel_path,
                    s.api_auth_type,
                    s.api_token_encrypted,
                    s.username_encrypted,
                    s.password_encrypted,
                    i.id AS inbound_id,
                    i.remote_inbound_id,
                    i.name AS inbound_name,
                    i.protocol,
                    i.remark,
                    i.settings_json,
                    i.stream_settings_json
             FROM vpn_plan_servers ps
             INNER JOIN vpn_servers s ON s.id = ps.server_id
             INNER JOIN vpn_inbounds i ON i.id = ps.inbound_id
             WHERE ps.plan_id = ?
               AND ps.is_enabled = 1
               AND s.is_enabled = 1
               AND i.is_enabled = 1
               AND i.status = 'active'
             ORDER BY ps.sort_order ASC, ps.id ASC",
            [$planId]
        )->get() ?: [];
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
            'traffic_limit_bytes' => Formatter::trafficToBytes($data['traffic_limit_value'] ?? ($data['traffic_limit_gb'] ?? 0), (string)($data['traffic_unit'] ?? 'gb')),
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

        $this->syncPlanInbounds($planId, (array)($data['inbound_ids'] ?? []));

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
                    (SELECT COUNT(DISTINCT n.server_id) FROM vpn_subscription_nodes n WHERE n.subscription_id = s.id AND n.status <> 'deleted') AS server_count,
                    (SELECT COUNT(*) FROM vpn_subscription_nodes n WHERE n.subscription_id = s.id AND n.status <> 'deleted') AS node_count
             FROM vpn_subscriptions s
             LEFT JOIN users u ON u.id = s.user_id
             LEFT JOIN vpn_plans p ON p.id = s.plan_id
             ORDER BY s.id DESC
             LIMIT {$limit}"
        )->get() ?: [];
    }

    public function activePlans(): array
    {
        return db()->query(
            "SELECT p.*,
                    (SELECT COUNT(*)
                     FROM vpn_plan_servers ps
                     INNER JOIN vpn_servers s ON s.id = ps.server_id
                     INNER JOIN vpn_inbounds i ON i.id = ps.inbound_id
                     WHERE ps.plan_id = p.id
                       AND ps.is_enabled = 1
                       AND s.is_enabled = 1
                       AND i.is_enabled = 1
                       AND i.status = 'active') AS active_inbound_count
             FROM vpn_plans p
             WHERE p.is_active = 1
             ORDER BY p.sort_order ASC, p.id ASC"
        )->get() ?: [];
    }

    public function usersForSubscriptionForm(): array
    {
        return db()->query('SELECT id, name, login, email FROM users ORDER BY name ASC, id ASC LIMIT 500')->get() ?: [];
    }

    public function createSubscription(array $data): int
    {
        $userId = (int)($data['user_id'] ?? 0);
        $planId = (int)($data['plan_id'] ?? 0);
        $plan = $this->plan($planId);
        $userExists = $userId > 0 && (int)db()->query('SELECT COUNT(*) FROM users WHERE id = ?', [$userId])->getColumn() > 0;
        if (!$userExists || !$plan) {
            $this->logEvent('subscription_create_failed', 'VPN subscription creation failed: user or plan is missing.', [
                'user_id' => $userId,
                'plan_id' => $planId,
                'error_code' => 'missing_user_or_plan',
                'error_message' => \FireballPluginVpnManager::t('vpn_manager_error_subscription_required'),
            ], $userId ?: null);

            throw new \RuntimeException(\FireballPluginVpnManager::t('vpn_manager_error_subscription_required'));
        }

        $items = $this->planItems($planId);
        if (!$items) {
            $this->logEvent('subscription_create_failed', 'VPN subscription creation failed: plan has no active inbounds.', [
                'user_id' => $userId,
                'plan_id' => $planId,
                'error_code' => 'plan_has_no_inbounds',
                'error_message' => \FireballPluginVpnManager::t('vpn_manager_error_plan_has_no_inbounds'),
            ], $userId);

            throw new \RuntimeException(\FireballPluginVpnManager::t('vpn_manager_error_plan_has_no_inbounds'));
        }

        $startsAt = $this->normalizeDateTime((string)($data['starts_at'] ?? '')) ?: date('Y-m-d H:i:s');
        $expiresAt = $this->normalizeDateTime((string)($data['expires_at'] ?? ''));
        if ($expiresAt === null) {
            $expiresAt = date('Y-m-d H:i:s', strtotime($startsAt . ' +' . max(1, (int)$plan['duration_days']) . ' days'));
        }

        $createClients = !empty($data['create_clients']);
        $status = (string)($data['status'] ?? 'active');
        if (!in_array($status, $this->subscriptionStatuses(), true)) {
            $status = 'active';
        }
        if ($createClients) {
            $status = 'provisioning';
        }

        $link = new SubscriptionLinkService();
        $token = $link->generateToken();
        $subscriptionUrl = $link->subscriptionUrlForToken($token);
        $admin = function_exists('get_user') ? get_user() : null;
        $now = date('Y-m-d H:i:s');
        $database = db();
        $startedTransaction = false;
        $subscriptionId = 0;
        $nodeIds = [];

        $this->logEvent('subscription_create_started', 'VPN subscription creation started.', [
            'user_id' => $userId,
            'plan_id' => $planId,
            'active_plan_items' => count($items),
            'create_clients' => $createClients,
        ], $userId);

        try {
            if (!$database->inTransaction()) {
                $database->beginTransaction();
                $startedTransaction = true;
            }

            $database->query(
                'INSERT INTO vpn_subscriptions
                    (user_id, plan_id, status, traffic_mode, traffic_limit_bytes, traffic_used_bytes, device_limit, starts_at, expires_at, created_by, source, source_order_id, subscription_token, subscription_url, subscription_token_encrypted, subscription_token_hash, subscription_token_preview, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $userId,
                    $planId,
                    $status,
                    (string)$plan['traffic_mode'],
                    (int)$plan['traffic_limit_bytes'],
                    (int)$plan['device_limit'],
                    $startsAt,
                    $expiresAt,
                    is_array($admin) ? (int)($admin['id'] ?? 0) : null,
                    'manual',
                    null,
                    $token,
                    $subscriptionUrl,
                    $link->encryptToken($token),
                    $link->tokenHash($token),
                    $link->tokenPreview($token),
                    $now,
                    $now,
                ]
            );

            $subscriptionId = (int)$database->getInsertId();
            $subscription = [
                'id' => $subscriptionId,
                'user_id' => $userId,
                'plan_id' => $planId,
                'status' => $status,
                'traffic_mode' => (string)$plan['traffic_mode'],
                'traffic_limit_bytes' => (int)$plan['traffic_limit_bytes'],
                'device_limit' => (int)$plan['device_limit'],
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'subscription_url' => $subscriptionUrl,
                'subscription_token_preview' => $link->tokenPreview($token),
            ];

            foreach ($items as $item) {
                $node = $this->ensureSubscriptionNode($subscription, $item);
                if (!empty($node['id'])) {
                    $nodeIds[] = (int)$node['id'];
                }
            }

            if ($startedTransaction) {
                $database->commit();
            }
        } catch (\Throwable $exception) {
            if ($startedTransaction && $database->inTransaction()) {
                $database->rollBack();
            }

            $this->logEvent('subscription_create_failed', 'VPN subscription creation failed.', [
                'user_id' => $userId,
                'plan_id' => $planId,
                'error_code' => get_class($exception),
                'error_message' => $exception->getMessage(),
            ], $userId, $subscriptionId ?: null);

            throw $exception;
        }

        $this->logEvent('subscription_token_created', 'VPN subscription token created.', [
            'plan_id' => $planId,
            'subscription_id' => $subscriptionId,
            'token_preview' => $link->tokenPreview($token),
        ], $userId, $subscriptionId);
        $this->logEvent('subscription_created', 'VPN subscription created.', [
            'plan_id' => $planId,
            'nodes_created' => count($nodeIds),
        ], $userId, $subscriptionId);

        foreach ($nodeIds as $nodeId) {
            $node = $this->connection($nodeId);
            $this->logEvent('node_created', 'VPN subscription node created.', [
                'node_id' => $nodeId,
                'server_id' => (int)($node['server_id'] ?? 0),
                'inbound_id' => (int)($node['inbound_id'] ?? 0),
            ], $userId, $subscriptionId, $nodeId, (int)($node['server_id'] ?? 0));
        }
        \Fireball\VpnManager\Services\SettingsService::invalidateCache();

        return $subscriptionId;
    }

    public function subscriptionStatuses(): array
    {
        return [
            'active',
            'expired',
            'suspended',
            'traffic_exceeded',
            'provisioning',
            'provisioning_failed',
            'sync_error',
            'cancelled',
            'deleting',
            'delete_failed',
            'deleted',
        ];
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

    public function subscriptionByToken(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $row = db()->query(
            'SELECT s.*, u.name AS user_name, u.email AS user_email, p.name AS plan_name
             FROM vpn_subscriptions s
             LEFT JOIN users u ON u.id = s.user_id
             LEFT JOIN vpn_plans p ON p.id = s.plan_id
             WHERE s.subscription_token_hash = ? OR s.subscription_token = ?
             LIMIT 1',
            [(new SubscriptionLinkService())->tokenHash($token), $token]
        )->getOne();

        return is_array($row) ? $row : null;
    }

    public function subscriptionNodes(int $subscriptionId): array
    {
        return db()->query(
            'SELECT n.*, s.name AS server_name, s.code AS server_code, s.country_code, s.country_name, s.flag_emoji, s.show_flag, s.country, s.city, i.name AS inbound_name
             FROM vpn_subscription_nodes n
             LEFT JOIN vpn_servers s ON s.id = n.server_id
             LEFT JOIN vpn_inbounds i ON i.id = n.inbound_id
             WHERE n.subscription_id = ?
             ORDER BY n.id ASC',
            [$subscriptionId]
        )->get() ?: [];
    }

    public function hasSubscriptionsForUser(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        return (int)db()->query('SELECT COUNT(*) FROM vpn_subscriptions WHERE user_id = ?', [$userId])->getColumn() > 0;
    }

    public function subscriptionForUser(int $subscriptionId, int $userId): ?array
    {
        if ($subscriptionId <= 0 || $userId <= 0) {
            return null;
        }

        $row = db()->query(
            'SELECT s.*, p.name AS plan_name, p.description AS plan_description
             FROM vpn_subscriptions s
             LEFT JOIN vpn_plans p ON p.id = s.plan_id
             WHERE s.id = ? AND s.user_id = ?
             LIMIT 1',
            [$subscriptionId, $userId]
        )->getOne();

        return is_array($row) ? $row : null;
    }

    public function markSubscriptionDeleting(int $subscriptionId): void
    {
        db()->query('UPDATE vpn_subscriptions SET status = "deleting", updated_at = ? WHERE id = ?', [date('Y-m-d H:i:s'), $subscriptionId]);
        db()->query('UPDATE vpn_subscription_nodes SET status = "deleting", last_error = NULL, updated_at = ? WHERE subscription_id = ? AND status <> "deleted"', [date('Y-m-d H:i:s'), $subscriptionId]);
    }

    public function markSubscriptionDeleteFailed(int $subscriptionId, string $error): void
    {
        db()->query(
            'UPDATE vpn_subscriptions SET status = "delete_failed", updated_at = ? WHERE id = ?',
            [date('Y-m-d H:i:s'), $subscriptionId]
        );
        $this->logEvent('subscription.delete_failed', 'VPN subscription permanent deletion failed.', [
            'error_message' => mb_substr($error, 0, 1000),
        ], null, $subscriptionId);
    }

    public function markNodeDeleteFailed(int $nodeId, string $error): void
    {
        db()->query(
            'UPDATE vpn_subscription_nodes SET status = "delete_failed", last_error = ?, updated_at = ? WHERE id = ?',
            [mb_substr($error, 0, 1000), date('Y-m-d H:i:s'), $nodeId]
        );
    }

    public function markNodeDeleted(int $nodeId): void
    {
        db()->query(
            'UPDATE vpn_subscription_nodes SET status = "deleted", last_error = NULL, updated_at = ? WHERE id = ?',
            [date('Y-m-d H:i:s'), $nodeId]
        );
    }

    public function deleteSubscriptionLocalData(int $subscriptionId, int $deletedNodesCount): void
    {
        $subscription = $this->subscription($subscriptionId);
        if (!$subscription) {
            return;
        }

        $admin = function_exists('get_user') ? get_user() : null;
        $adminId = is_array($admin) ? (int)($admin['id'] ?? 0) : null;
        $database = db();
        $startedTransaction = false;
        if (!$database->inTransaction()) {
            $database->beginTransaction();
            $startedTransaction = true;
        }

        try {
            $database->query(
                'UPDATE vpn_subscriptions
                 SET subscription_token = NULL,
                     subscription_token_encrypted = NULL,
                     subscription_token_hash = NULL,
                     subscription_token_preview = NULL,
                     subscription_url = NULL,
                     updated_at = ?
                 WHERE id = ?',
                [date('Y-m-d H:i:s'), $subscriptionId]
            );
            $database->query('DELETE FROM vpn_traffic_snapshots WHERE subscription_id = ?', [$subscriptionId]);
            $database->query('DELETE FROM vpn_notifications WHERE subscription_id = ?', [$subscriptionId]);
            $database->query('DELETE FROM vpn_jobs WHERE payload_json LIKE ?', ['%"subscription_id":' . $subscriptionId . '%']);
            $database->query('DELETE FROM vpn_jobs WHERE payload_json LIKE ?', ['%"subscription_id":"' . $subscriptionId . '"%']);
            $database->query('DELETE FROM vpn_jobs WHERE payload_json LIKE ? AND payload_json LIKE ?', ['%subscription_id%', '%' . $subscriptionId . '%']);
            $database->query('DELETE FROM vpn_subscription_nodes WHERE subscription_id = ?', [$subscriptionId]);
            $database->query('UPDATE vpn_events SET subscription_id = NULL WHERE subscription_id = ?', [$subscriptionId]);
            $database->query('DELETE FROM vpn_subscriptions WHERE id = ?', [$subscriptionId]);
            $database->query(
                'INSERT INTO vpn_events
                    (event_type, user_id, admin_id, subscription_id, node_id, server_id, message, context_json, ip_address, created_at)
                 VALUES (?, ?, ?, NULL, NULL, NULL, ?, ?, ?, ?)',
                [
                    'subscription_deleted',
                    (int)($subscription['user_id'] ?? 0) ?: null,
                    $adminId ?: null,
                    'VPN subscription deleted permanently.',
                    json_encode([
                        'deleted_subscription_id' => $subscriptionId,
                        'deleted_nodes_count' => $deletedNodesCount,
                        'deleted_at' => date('c'),
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    function_exists('client_ip') ? client_ip() : null,
                    date('Y-m-d H:i:s'),
                ]
            );

            if ($startedTransaction) {
                $database->commit();
            }
        } catch (\Throwable $exception) {
            if ($startedTransaction && $database->inTransaction()) {
                $database->rollBack();
            }

            throw $exception;
        }
    }

    public function subscriptionConfigNodes(int $subscriptionId): array
    {
        return db()->query(
            "SELECT n.*,
                    s.name AS server_name,
                    s.code AS server_code,
                    s.country_code,
                    s.country_name,
                    s.flag_emoji,
                    s.show_flag,
                    s.country,
                    s.city,
                    s.public_host,
                    s.panel_url,
                    i.name AS inbound_name,
                    i.protocol,
                    i.port,
                    i.settings_json,
                    i.stream_settings_json
             FROM vpn_subscription_nodes n
             INNER JOIN vpn_servers s ON s.id = n.server_id
             INNER JOIN vpn_inbounds i ON i.id = n.inbound_id
             WHERE n.subscription_id = ?
               AND n.status = 'active'
               AND n.client_uuid IS NOT NULL
               AND n.client_uuid <> ''
               AND s.is_enabled = 1
               AND i.is_enabled = 1
               AND i.status = 'active'
             ORDER BY n.id ASC",
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

    public function subscriptionEvents(int $subscriptionId): array
    {
        return db()->query(
            'SELECT * FROM vpn_events WHERE subscription_id = ? ORDER BY id DESC LIMIT 50',
            [$subscriptionId]
        )->get() ?: [];
    }

    public function subscriptionDiagnostics(int $subscriptionId): array
    {
        $subscription = $this->subscription($subscriptionId);
        if (!$subscription) {
            return [
                'subscription_created' => false,
                'plan_found' => false,
                'active_plan_items' => 0,
                'nodes_created' => 0,
                'clients_created' => 0,
                'last_error' => \FireballPluginVpnManager::t('vpn_manager_error_subscription_not_found'),
            ];
        }

        $planId = (int)($subscription['plan_id'] ?? 0);
        $planFound = $planId > 0 && $this->plan($planId) !== null;
        $nodes = $this->subscriptionNodes($subscriptionId);
        $lastError = '';
        foreach (array_reverse($nodes) as $node) {
            $error = trim((string)($node['last_error'] ?? ''));
            if ($error !== '') {
                $lastError = $error;
                break;
            }
        }

        if ($lastError === '') {
            $event = db()->query(
                "SELECT context_json, message
                 FROM vpn_events
                 WHERE subscription_id = ?
                   AND (event_type LIKE '%failed%' OR event_type LIKE '%error%')
                 ORDER BY id DESC
                 LIMIT 1",
                [$subscriptionId]
            )->getOne();
            if (is_array($event)) {
                $context = json_decode((string)($event['context_json'] ?? ''), true);
                $lastError = is_array($context) && !empty($context['error_message'])
                    ? (string)$context['error_message']
                    : (string)($event['message'] ?? '');
            }
        }

        return [
            'subscription_created' => true,
            'plan_found' => $planFound,
            'active_plan_items' => $planFound ? count($this->planItems($planId)) : 0,
            'nodes_created' => count($nodes),
            'clients_created' => count(array_filter($nodes, static fn(array $node): bool => (string)($node['status'] ?? '') === 'active' && trim((string)($node['remote_client_id'] ?? '')) !== '')),
            'last_error' => $lastError,
        ];
    }

    public function ensureSubscriptionNode(array $subscription, array $planItem): array
    {
        $subscriptionId = (int)$subscription['id'];
        $serverId = (int)$planItem['server_id'];
        $inboundId = (int)$planItem['inbound_id'];
        $existing = db()->query(
            'SELECT * FROM vpn_subscription_nodes WHERE subscription_id = ? AND server_id = ? AND inbound_id = ? LIMIT 1',
            [$subscriptionId, $serverId, $inboundId]
        )->getOne();

        if (is_array($existing)) {
            if (!in_array((string)$existing['status'], ['active', 'creating'], true)) {
                db()->query('UPDATE vpn_subscription_nodes SET status = "creating", last_error = NULL, updated_at = ? WHERE id = ?', [date('Y-m-d H:i:s'), (int)$existing['id']]);
                $existing['status'] = 'creating';
                $existing['last_error'] = null;
            }

            return $existing;
        }

        $now = date('Y-m-d H:i:s');
        db()->query(
            'INSERT INTO vpn_subscription_nodes
                (subscription_id, server_id, inbound_id, remote_client_id, client_uuid, client_email, client_remark, subscription_item_name, status, traffic_limit_bytes, traffic_used_bytes, last_sync_at, last_error, created_at, updated_at)
             VALUES (?, ?, ?, NULL, ?, NULL, ?, ?, "creating", ?, 0, NULL, NULL, ?, ?)',
            [
                $subscriptionId,
                $serverId,
                $inboundId,
                $this->uuidV4(),
                'VPN subscription #' . $subscriptionId,
                (new \Fireball\VpnManager\Services\SubscriptionLinkService())->configNameForServer($planItem, (string)($planItem['protocol'] ?? '')),
                (int)$subscription['traffic_limit_bytes'],
                $now,
                $now,
            ]
        );

        $nodeId = (int)db()->getInsertId();
        $clientEmail = 'vpn-user-' . (int)$subscription['user_id'] . '-sub-' . $subscriptionId . '-node-' . $nodeId;
        $clientRemark = 'VPN User #' . (int)$subscription['user_id'] . ' / Subscription #' . $subscriptionId . ' / Node #' . $nodeId;
        db()->query(
            'UPDATE vpn_subscription_nodes SET client_email = ?, client_remark = ?, updated_at = ? WHERE id = ?',
            [$clientEmail, $clientRemark, date('Y-m-d H:i:s'), $nodeId]
        );

        return db()->query('SELECT * FROM vpn_subscription_nodes WHERE id = ? LIMIT 1', [$nodeId])->getOne() ?: [];
    }

    public function markNodeProvisioned(int $nodeId, string $remoteClientId): void
    {
        db()->query(
            'UPDATE vpn_subscription_nodes
             SET remote_client_id = ?, status = "active", last_sync_at = ?, last_error = NULL, updated_at = ?
             WHERE id = ?',
            [$remoteClientId, date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $nodeId]
        );
    }

    public function markNodeFailed(int $nodeId, string $error): void
    {
        db()->query(
            'UPDATE vpn_subscription_nodes
             SET status = "create_failed", last_error = ?, updated_at = ?
             WHERE id = ?',
            [mb_substr($error, 0, 1000), date('Y-m-d H:i:s'), $nodeId]
        );
    }

    public function updateSubscriptionProvisioningStatus(int $subscriptionId, string $status): void
    {
        db()->query('UPDATE vpn_subscriptions SET status = ?, updated_at = ? WHERE id = ?', [$status, date('Y-m-d H:i:s'), $subscriptionId]);
    }

    public function setSubscriptionStatus(int $subscriptionId, string $status): void
    {
        if (!in_array($status, $this->subscriptionStatuses(), true)) {
            throw new \RuntimeException(\FireballPluginVpnManager::t('vpn_manager_error_invalid_status'));
        }

        db()->query('UPDATE vpn_subscriptions SET status = ?, updated_at = ? WHERE id = ?', [$status, date('Y-m-d H:i:s'), $subscriptionId]);
        $subscription = $this->subscription($subscriptionId);
        $this->logEvent('subscription.status_changed', 'VPN subscription status changed.', ['status' => $status], (int)($subscription['user_id'] ?? 0), $subscriptionId);
    }

    public function resetSubscriptionTraffic(int $subscriptionId): void
    {
        db()->query('UPDATE vpn_subscriptions SET traffic_used_bytes = 0, updated_at = ? WHERE id = ?', [date('Y-m-d H:i:s'), $subscriptionId]);
        db()->query('UPDATE vpn_subscription_nodes SET traffic_used_bytes = 0, updated_at = ? WHERE subscription_id = ?', [date('Y-m-d H:i:s'), $subscriptionId]);
        db()->query("DELETE FROM vpn_notifications WHERE subscription_id = ? AND type IN ('traffic_80', 'traffic_100')", [$subscriptionId]);
        $subscription = $this->subscription($subscriptionId);
        $this->logEvent('subscription.traffic_reset', 'VPN subscription traffic reset.', [], (int)($subscription['user_id'] ?? 0), $subscriptionId);
    }

    public function activeNodesForTrafficSync(): array
    {
        return db()->query(
            "SELECT n.*,
                    s.name AS server_name,
                    s.code AS server_code,
                    s.country_code,
                    s.country_name,
                    s.flag_emoji,
                    s.show_flag,
                    s.country,
                    s.city,
                    s.panel_url,
                    s.panel_path,
                    s.api_auth_type,
                    s.api_token_encrypted,
                    s.username_encrypted,
                    s.password_encrypted,
                    i.remote_inbound_id,
                    i.name AS inbound_name,
                    sub.user_id,
                    sub.status AS subscription_status
             FROM vpn_subscription_nodes n
             INNER JOIN vpn_subscriptions sub ON sub.id = n.subscription_id
             INNER JOIN vpn_servers s ON s.id = n.server_id
             INNER JOIN vpn_inbounds i ON i.id = n.inbound_id
             WHERE n.status = 'active'
               AND sub.status = 'active'
               AND s.is_enabled = 1
               AND i.is_enabled = 1
             ORDER BY n.id ASC"
        )->get() ?: [];
    }

    public function updateNodeTrafficDetailed(int $nodeId, int $uploadBytes, int $downloadBytes, int $totalBytes): int
    {
        $node = $this->connection($nodeId);
        if (!$node) {
            throw new \RuntimeException(\FireballPluginVpnManager::t('vpn_manager_error_connection_not_found'));
        }

        $uploadBytes = max(0, $uploadBytes);
        $downloadBytes = max(0, $downloadBytes);
        $totalBytes = max($totalBytes, $uploadBytes + $downloadBytes, 0);
        $now = date('Y-m-d H:i:s');

        db()->query(
            'UPDATE vpn_subscription_nodes
             SET traffic_used_bytes = ?, last_sync_at = ?, last_error = NULL, updated_at = ?
             WHERE id = ?',
            [$totalBytes, $now, $now, $nodeId]
        );

        $subscriptionId = (int)($node['subscription_id'] ?? 0);
        $this->insertTrafficSnapshot(
            $subscriptionId,
            $nodeId,
            (int)($node['server_id'] ?? 0),
            (int)($node['inbound_id'] ?? 0),
            $uploadBytes,
            $downloadBytes,
            $totalBytes,
            $now
        );
        $this->recalculateSubscriptionTraffic($subscriptionId);
        $this->logEvent('traffic.synced', 'VPN traffic synchronized.', [
            'node_id' => $nodeId,
            'total_bytes' => $totalBytes,
        ], (int)($node['user_id'] ?? 0), $subscriptionId, $nodeId, (int)($node['server_id'] ?? 0));

        return $subscriptionId;
    }

    public function insertTrafficSnapshot(int $subscriptionId, int $nodeId, int $serverId, int $inboundId, int $uploadBytes, int $downloadBytes, int $totalBytes, ?string $capturedAt = null): void
    {
        $capturedAt = $capturedAt ?: date('Y-m-d H:i:s');
        db()->query(
            'INSERT INTO vpn_traffic_snapshots
                (subscription_id, node_id, server_id, inbound_id, upload_bytes, download_bytes, total_bytes, traffic_used_bytes, captured_at, recorded_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $subscriptionId ?: null,
                $nodeId ?: null,
                $serverId ?: null,
                $inboundId ?: null,
                max(0, $uploadBytes),
                max(0, $downloadBytes),
                max(0, $totalBytes),
                max(0, $totalBytes),
                $capturedAt,
                $capturedAt,
                date('Y-m-d H:i:s'),
            ]
        );
    }

    public function recalculateSubscriptionTraffic(int $subscriptionId): void
    {
        if ($subscriptionId <= 0) {
            return;
        }

        db()->query(
            'UPDATE vpn_subscriptions
             SET traffic_used_bytes = (SELECT COALESCE(SUM(traffic_used_bytes), 0) FROM vpn_subscription_nodes WHERE subscription_id = ? AND status <> \'deleted\'),
                 updated_at = ?
             WHERE id = ?',
            [$subscriptionId, date('Y-m-d H:i:s'), $subscriptionId]
        );
    }

    public function expiredActiveSubscriptions(): array
    {
        return db()->query(
            "SELECT *
             FROM vpn_subscriptions
             WHERE status = 'active'
               AND expires_at IS NOT NULL
               AND expires_at <= NOW()
             ORDER BY expires_at ASC, id ASC
             LIMIT 200"
        )->get() ?: [];
    }

    public function activeSubscriptionsForTrafficLimitCheck(): array
    {
        return db()->query(
            "SELECT *
             FROM vpn_subscriptions
             WHERE status = 'active'
               AND traffic_limit_bytes > 0
             ORDER BY id ASC
             LIMIT 500"
        )->get() ?: [];
    }

    public function setSubscriptionExpired(int $subscriptionId): void
    {
        $subscription = $this->subscription($subscriptionId);
        db()->query('UPDATE vpn_subscriptions SET status = "expired", updated_at = ? WHERE id = ?', [date('Y-m-d H:i:s'), $subscriptionId]);
        $this->logEvent('subscription.expired', 'VPN subscription expired.', [], (int)($subscription['user_id'] ?? 0), $subscriptionId);
    }

    public function setSubscriptionTrafficExceeded(int $subscriptionId): void
    {
        $subscription = $this->subscription($subscriptionId);
        db()->query('UPDATE vpn_subscriptions SET status = "traffic_exceeded", updated_at = ? WHERE id = ?', [date('Y-m-d H:i:s'), $subscriptionId]);
        $this->logEvent('subscription.traffic_exceeded', 'VPN subscription traffic limit exceeded.', [], (int)($subscription['user_id'] ?? 0), $subscriptionId);
    }

    public function extendSubscription(int $subscriptionId, int $days, bool $resetTraffic): string
    {
        $subscription = $this->subscription($subscriptionId);
        if (!$subscription) {
            throw new \RuntimeException(\FireballPluginVpnManager::t('vpn_manager_error_subscription_not_found'));
        }

        $days = max(1, min(3650, $days));
        $currentExpires = strtotime((string)($subscription['expires_at'] ?? '')) ?: 0;
        $base = max($currentExpires, time());
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . $days . ' days', $base));

        db()->query(
            'UPDATE vpn_subscriptions SET status = "active", expires_at = ?, updated_at = ? WHERE id = ?',
            [$expiresAt, date('Y-m-d H:i:s'), $subscriptionId]
        );

        if ($resetTraffic) {
            $this->resetSubscriptionTraffic($subscriptionId);
        }

        $this->logEvent('subscription.extended', 'VPN subscription extended.', [
            'days' => $days,
            'reset_traffic' => $resetTraffic,
            'expires_at' => $expiresAt,
        ], (int)$subscription['user_id'], $subscriptionId);

        return $expiresAt;
    }

    public function deleteTrafficNotifications(int $subscriptionId): void
    {
        db()->query("DELETE FROM vpn_notifications WHERE subscription_id = ? AND type IN ('traffic_80', 'traffic_100')", [$subscriptionId]);
    }

    public function connection(int $nodeId): ?array
    {
        $row = db()->query(
            'SELECT n.*,
                    s.name AS server_name,
                    s.code AS server_code,
                    s.country_code,
                    s.country_name,
                    s.flag_emoji,
                    s.show_flag,
                    s.country,
                    s.city,
                    s.panel_url,
                    s.panel_path,
                    s.api_auth_type,
                    s.api_token_encrypted,
                    s.username_encrypted,
                    s.password_encrypted,
                    i.name AS inbound_name,
                    i.remote_inbound_id,
                    sub.user_id,
                    u.name AS user_name
             FROM vpn_subscription_nodes n
             LEFT JOIN vpn_servers s ON s.id = n.server_id
             LEFT JOIN vpn_inbounds i ON i.id = n.inbound_id
             LEFT JOIN vpn_subscriptions sub ON sub.id = n.subscription_id
             LEFT JOIN users u ON u.id = sub.user_id
             WHERE n.id = ?
             LIMIT 1',
            [$nodeId]
        )->getOne();

        return is_array($row) ? $row : null;
    }

    public function setNodeStatus(int $nodeId, string $status, string $eventType = 'connection.status_changed'): int
    {
        if (!in_array($status, ['active', 'disabled', 'sync_error', 'deleted'], true)) {
            throw new \RuntimeException(\FireballPluginVpnManager::t('vpn_manager_error_invalid_status'));
        }

        db()->query('UPDATE vpn_subscription_nodes SET status = ?, updated_at = ? WHERE id = ?', [$status, date('Y-m-d H:i:s'), $nodeId]);
        $node = $this->connection($nodeId);
        $subscriptionId = (int)($node['subscription_id'] ?? 0);
        $this->logEvent($eventType, 'VPN connection status changed.', ['status' => $status], (int)($node['user_id'] ?? 0), $subscriptionId, $nodeId, (int)($node['server_id'] ?? 0));

        return $subscriptionId;
    }

    public function updateNodeTraffic(int $nodeId, int $trafficUsedBytes): int
    {
        return $this->updateNodeTrafficDetailed($nodeId, 0, 0, $trafficUsedBytes);
    }

    public function markNodeError(int $nodeId, string $error): int
    {
        db()->query(
            'UPDATE vpn_subscription_nodes SET status = "sync_error", last_error = ?, updated_at = ? WHERE id = ?',
            [mb_substr($error, 0, 1000), date('Y-m-d H:i:s'), $nodeId]
        );
        $node = $this->connection($nodeId);
        $subscriptionId = (int)($node['subscription_id'] ?? 0);
        $this->logEvent('connection.sync_error', 'VPN connection action failed.', ['error' => $error], (int)($node['user_id'] ?? 0), $subscriptionId, $nodeId, (int)($node['server_id'] ?? 0));

        return $subscriptionId;
    }

    public function connections(): array
    {
        return db()->query(
            'SELECT n.*,
                    s.name AS server_name,
                    s.code AS server_code,
                    s.country_code,
                    s.country_name,
                    s.flag_emoji,
                    s.show_flag,
                    s.country,
                    s.city,
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

    public function remoteClients(int $limit = 200): array
    {
        $limit = max(1, min(500, $limit));

        return db()->query(
            "SELECT rc.*, s.name AS server_name, s.code AS server_code, s.country_code, s.country_name, s.flag_emoji, s.show_flag, s.country, s.city, i.name AS inbound_name
             FROM vpn_remote_clients rc
             LEFT JOIN vpn_servers s ON s.id = rc.server_id
             LEFT JOIN vpn_inbounds i ON i.id = rc.inbound_id
             ORDER BY rc.last_seen_at DESC, rc.id DESC
             LIMIT {$limit}"
        )->get() ?: [];
    }

    public function remoteClientSummaries(int $limit = 200): array
    {
        $limit = max(1, min(500, $limit));

        return db()->query(
            "SELECT rc.client_email,
                    MAX(rc.client_remark) AS client_remark,
                    COUNT(*) AS connection_count,
                    SUM(CASE WHEN rc.status = 'active' THEN 1 ELSE 0 END) AS active_count,
                    COALESCE(SUM(rc.traffic_used_bytes), 0) AS traffic_used_bytes,
                    MAX(rc.last_seen_at) AS last_seen_at,
                    GROUP_CONCAT(DISTINCT s.name ORDER BY s.sort_order ASC, s.id ASC SEPARATOR ', ') AS server_names
             FROM vpn_remote_clients rc
             LEFT JOIN vpn_servers s ON s.id = rc.server_id
             WHERE rc.client_email IS NOT NULL AND rc.client_email <> ''
             GROUP BY rc.client_email
             ORDER BY last_seen_at DESC
             LIMIT {$limit}"
        )->get() ?: [];
    }

    public function userSummaries(): array
    {
        return db()->query(
            "SELECT u.id, u.name, u.email, u.login, u.last_seen_at,
                    COUNT(s.id) AS subscription_count,
                    SUM(CASE WHEN s.status = 'active' THEN 1 ELSE 0 END) AS active_count,
                    SUM(CASE WHEN s.status = 'expired' OR (s.status = 'active' AND s.expires_at < NOW()) THEN 1 ELSE 0 END) AS expired_count,
                    (SELECT COUNT(*) FROM vpn_subscription_nodes n INNER JOIN vpn_subscriptions ns ON ns.id = n.subscription_id WHERE ns.user_id = u.id) AS connection_count,
                    COALESCE(SUM(s.traffic_used_bytes), 0) AS traffic_used_bytes,
                    MAX(s.expires_at) AS latest_expires_at
             FROM users u
             INNER JOIN vpn_subscriptions s ON s.user_id = u.id
             GROUP BY u.id, u.name, u.email, u.login, u.last_seen_at
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
            'SELECT s.*, p.name AS plan_name, p.description AS plan_description,
                    (SELECT GROUP_CONCAT(DISTINCT TRIM(CONCAT(
                                IF(vs.show_flag = 1 AND vs.flag_emoji IS NOT NULL AND vs.flag_emoji <> "", CONCAT(vs.flag_emoji, " "), ""),
                                COALESCE(NULLIF(vs.country_name, ""), NULLIF(vs.country, ""), vs.name),
                                IF(vs.city IS NOT NULL AND vs.city <> "", CONCAT(", ", vs.city), "")
                            )) ORDER BY vs.sort_order ASC, vs.id ASC SEPARATOR ", ")
                     FROM vpn_subscription_nodes n
                     INNER JOIN vpn_servers vs ON vs.id = n.server_id
                     WHERE n.subscription_id = s.id AND n.status <> "deleted") AS server_names,
                    (SELECT COUNT(*) FROM vpn_subscription_nodes n WHERE n.subscription_id = s.id AND n.status <> "deleted") AS node_count
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

    private function syncPlanInbounds(int $planId, array $inboundIds): void
    {
        $inboundIds = array_values(array_unique(array_filter(array_map('intval', $inboundIds))));
        if (!$inboundIds) {
            throw new \RuntimeException(\FireballPluginVpnManager::t('vpn_manager_error_plan_has_no_inbounds'));
        }

        db()->query('UPDATE vpn_plan_servers SET is_enabled = 0, updated_at = ? WHERE plan_id = ?', [date('Y-m-d H:i:s'), $planId]);

        foreach ($inboundIds as $index => $inboundId) {
            $inbound = $this->inbound($inboundId);
            if (!$inbound) {
                continue;
            }
            $serverId = (int)$inbound['server_id'];
            $existing = db()->query(
                'SELECT id FROM vpn_plan_servers WHERE plan_id = ? AND server_id = ? AND inbound_id = ? LIMIT 1',
                [$planId, $serverId, $inboundId]
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
                 VALUES (?, ?, ?, ?, 1, ?, ?)',
                [$planId, $serverId, $inboundId, $index + 1, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
            );
        }
    }

    private function count(string $from): int
    {
        return (int)db()->query('SELECT COUNT(*) FROM ' . $from)->getColumn();
    }

    private function normalizeDateTime(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;
    }

    private function normalizePublicHost(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $host = parse_url($value, PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            return strtolower($host);
        }

        return strtolower(trim($value, " \t\n\r\0\x0B/"));
    }

    private function uuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private function sanitizeContext(array $context): array
    {
        $blocked = ['token', 'password', 'secret', 'uuid', 'link'];
        $safeKeys = ['subscription_id', 'source_order_id'];
        foreach ($context as $key => $value) {
            $normalized = strtolower((string)$key);
            if (in_array($normalized, $safeKeys, true)) {
                continue;
            }
            if ($normalized === 'subscription_url') {
                $context[$key] = '[masked]';
                continue;
            }

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
