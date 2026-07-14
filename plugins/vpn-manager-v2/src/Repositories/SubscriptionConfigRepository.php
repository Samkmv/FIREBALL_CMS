<?php

namespace Fireball\VpnManagerV2\Repositories;

final class SubscriptionConfigRepository
{
    public function findByToken(string $token): ?array
    {
        $row = db()->query(
            'SELECT id, user_id, plan_id, status, starts_at, expires_at, traffic_limit_bytes,
                    device_limit, subscription_token, revision, config_updated_at, created_by,
                    created_at, updated_at
             FROM vpn_v2_subscriptions WHERE subscription_token = ? LIMIT 1',
            [$token]
        )->getOne();

        return is_array($row) ? $row : null;
    }

    public function activeNodes(int $subscriptionId): array
    {
        return db()->query(
            "SELECT n.id AS node_id, n.subscription_id, n.client_uuid, n.client_email,
                    n.client_sub_id, n.flow, n.traffic_limit_bytes, n.updated_at AS node_updated_at,
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
             ORDER BY n.id ASC",
            [$subscriptionId]
        )->get() ?: [];
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
