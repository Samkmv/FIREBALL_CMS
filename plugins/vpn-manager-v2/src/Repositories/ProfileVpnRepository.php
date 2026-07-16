<?php

namespace Fireball\VpnManagerV2\Repositories;

final class ProfileVpnRepository
{
    public function hasSubscriptionsForUser(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        return (bool)db()->query(
            'SELECT id FROM vpn_v2_subscriptions WHERE user_id = ? LIMIT 1',
            [$userId]
        )->getOne();
    }

    public function subscriptionsForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        return db()->query(
            'SELECT sub.id, sub.user_id, sub.plan_id, sub.status, sub.starts_at, sub.expires_at,
                    sub.traffic_limit_bytes, sub.device_limit, sub.revision,
                    p.name AS plan_name, p.description AS plan_description,
                    COUNT(CASE WHEN n.status <> \'deleted\' THEN 1 END) AS connection_count,
                    COALESCE(SUM(CASE WHEN n.status <> \'deleted\' THEN n.traffic_used_bytes ELSE 0 END), 0)
                        AS traffic_used_bytes
             FROM vpn_v2_subscriptions sub
             INNER JOIN vpn_v2_plans p ON p.id = sub.plan_id
             LEFT JOIN vpn_v2_subscription_nodes n ON n.subscription_id = sub.id
             WHERE sub.user_id = ?
             GROUP BY sub.id, sub.user_id, sub.plan_id, sub.status, sub.starts_at, sub.expires_at,
                      sub.traffic_limit_bytes, sub.device_limit, sub.revision,
                      p.name, p.description
             ORDER BY sub.id DESC',
            [$userId]
        )->get() ?: [];
    }

    public function subscriptionForUser(int $subscriptionId, int $userId): ?array
    {
        if ($subscriptionId <= 0 || $userId <= 0) {
            return null;
        }

        $row = db()->query(
            'SELECT sub.id, sub.user_id, sub.plan_id, sub.status, sub.starts_at, sub.expires_at,
                    sub.traffic_limit_bytes, sub.device_limit, sub.revision,
                    p.name AS plan_name, p.description AS plan_description,
                    COUNT(CASE WHEN n.status <> \'deleted\' THEN 1 END) AS connection_count,
                    COALESCE(SUM(CASE WHEN n.status <> \'deleted\' THEN n.traffic_used_bytes ELSE 0 END), 0)
                        AS traffic_used_bytes
             FROM vpn_v2_subscriptions sub
             INNER JOIN vpn_v2_plans p ON p.id = sub.plan_id
             LEFT JOIN vpn_v2_subscription_nodes n ON n.subscription_id = sub.id
             WHERE sub.id = ? AND sub.user_id = ?
             GROUP BY sub.id, sub.user_id, sub.plan_id, sub.status, sub.starts_at, sub.expires_at,
                      sub.traffic_limit_bytes, sub.device_limit, sub.revision,
                      p.name, p.description
             LIMIT 1',
            [$subscriptionId, $userId]
        )->getOne();

        return is_array($row) ? $row : null;
    }

    public function serversForUserSubscription(int $subscriptionId, int $userId): array
    {
        if ($subscriptionId <= 0 || $userId <= 0) {
            return [];
        }

        return db()->query(
            'SELECT s.name, s.country_code, s.country_name, s.city, s.show_flag,
                    MIN(n.id) AS first_node_id
             FROM vpn_v2_subscription_nodes n
             INNER JOIN vpn_v2_subscriptions sub
                ON sub.id = n.subscription_id AND sub.user_id = ?
             INNER JOIN vpn_v2_servers s ON s.id = n.server_id
             WHERE n.subscription_id = ? AND n.status <> \'deleted\'
             GROUP BY s.id, s.name, s.country_code, s.country_name, s.city, s.show_flag
             ORDER BY first_node_id ASC',
            [$userId, $subscriptionId]
        )->get() ?: [];
    }

    public function tokenForUserSubscription(int $subscriptionId, int $userId): ?string
    {
        if ($subscriptionId <= 0 || $userId <= 0) {
            return null;
        }

        $token = db()->query(
            'SELECT subscription_token
             FROM vpn_v2_subscriptions
             WHERE id = ? AND user_id = ?
             LIMIT 1',
            [$subscriptionId, $userId]
        )->getColumn();
        $token = trim((string)$token);

        return $token !== '' ? $token : null;
    }
}
