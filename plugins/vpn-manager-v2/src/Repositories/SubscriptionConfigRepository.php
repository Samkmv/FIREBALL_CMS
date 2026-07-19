<?php

namespace Fireball\VpnManagerV2\Repositories;

final class SubscriptionConfigRepository
{
    public function findByToken(string $token): ?array
    {
        $rows = db()->query(
            'SELECT id, user_id, plan_id, status, starts_at, expires_at, traffic_limit_bytes,
                    device_limit, subscription_token, revision, config_updated_at, created_by,
                    created_at, updated_at
             FROM vpn_v2_subscriptions WHERE subscription_token_hash = ? LIMIT 3',
            [hash('sha256', $token)]
        )->get() ?: [];
        foreach ($rows as $row) {
            if (is_array($row) && hash_equals((string)($row['subscription_token'] ?? ''), $token)) {
                return $row;
            }
        }

        return null;
    }

    public function activeNodes(int $subscriptionId): array
    {
        $rows = db()->query(
            "SELECT n.id AS node_id, n.subscription_id, n.sort_order, n.client_uuid, n.encrypted_client_credential,
                    n.client_email,
                    n.client_sub_id, n.flow, n.traffic_limit_bytes, n.sync_status,
                    n.lkg_snapshot_json, n.lkg_snapshot_hash, n.lkg_validity,
                    n.updated_at AS node_updated_at,
                    s.id AS server_id, s.name AS server_name, s.code AS server_code,
                    s.panel_url, s.country_code, s.country_name, s.city, s.show_flag,
                    s.updated_at AS server_updated_at,
                    i.id AS inbound_id, i.remote_inbound_id, i.name AS inbound_name,
                    i.remark AS inbound_remark, i.protocol, i.port, i.network, i.security,
                    i.settings_json, i.stream_settings_json, i.synced_at,
                    i.updated_at AS inbound_updated_at
             FROM vpn_v2_subscription_nodes n
             INNER JOIN vpn_v2_servers s ON s.id = n.server_id
             INNER JOIN vpn_v2_inbounds i ON i.id = n.inbound_id AND i.server_id = n.server_id
             WHERE n.subscription_id = ? AND n.status = 'active'
               AND s.is_enabled = 1 AND i.is_enabled = 1 AND i.status = 'active'
             ORDER BY n.sort_order ASC, n.id ASC",
            [$subscriptionId]
        )->get() ?: [];

        $nodes = [];
        foreach ($rows as $row) {
            $snapshot = [];
            if ((string)($row['lkg_validity'] ?? '') === 'valid'
                && trim((string)($row['lkg_snapshot_json'] ?? '')) !== '') {
                $decoded = json_decode((string)$row['lkg_snapshot_json'], true);
                $snapshot = is_array($decoded) ? $decoded : [];
            }
            unset($row['lkg_snapshot_json']);
            $nodes[] = $snapshot !== [] ? array_replace($row, $snapshot) : $row;
        }

        return $nodes;
    }

    public function revisionMetadata(int $subscriptionId): ?array
    {
        $row = db()->query(
            'SELECT id, subscription_token, revision, config_updated_at, updated_at
             FROM vpn_v2_subscriptions WHERE id = ? LIMIT 1',
            [$subscriptionId]
        )->getOne();

        return is_array($row) ? $row : null;
    }

    public function incrementRevision(int $subscriptionId, bool $touchConfig): bool
    {
        $now = date('Y-m-d H:i:s');
        $configSql = $touchConfig ? ', config_updated_at = ?' : '';
        $params = $touchConfig ? [$now, $now, $subscriptionId] : [$now, $subscriptionId];
        db()->query(
            'UPDATE vpn_v2_subscriptions
             SET revision = revision + 1, updated_at = ?' . $configSql . '
             WHERE id = ?',
            $params
        );

        return db()->rowCount() === 1;
    }

    public function subscriptionIdsForServer(int $serverId): array
    {
        $rows = db()->query(
            "SELECT DISTINCT sub.id
             FROM vpn_v2_subscriptions sub
             INNER JOIN vpn_v2_subscription_nodes n ON n.subscription_id = sub.id
             WHERE n.server_id = ? AND sub.status = 'active'
               AND sub.starts_at <= NOW()
               AND (sub.expires_at IS NULL OR sub.expires_at > NOW())",
            [$serverId]
        )->get() ?: [];

        return array_values(array_filter(array_map(
            static fn(array $row): int => (int)($row['id'] ?? 0),
            $rows
        )));
    }

    public function activeRevisionMetadata(): array
    {
        return db()->query(
            "SELECT id, subscription_token, revision, config_updated_at, updated_at
             FROM vpn_v2_subscriptions
             WHERE status = 'active'
             ORDER BY id ASC"
        )->get() ?: [];
    }

    public function subscriptionIdsForGlobalConfig(): array
    {
        return array_values(array_filter(array_map(
            static fn(array $row): int => (int)($row['id'] ?? 0),
            $this->activeRevisionMetadata()
        )));
    }

    public function tokenForSubscription(int $subscriptionId): ?string
    {
        $token = db()->query(
            'SELECT subscription_token FROM vpn_v2_subscriptions WHERE id = ? LIMIT 1',
            [$subscriptionId]
        )->getColumn();
        $token = trim((string)$token);

        return $token !== '' ? $token : null;
    }
}
