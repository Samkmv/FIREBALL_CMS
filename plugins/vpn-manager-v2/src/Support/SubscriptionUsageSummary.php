<?php

namespace Fireball\VpnManagerV2\Support;

final class SubscriptionUsageSummary
{
    public static function from(array $subscription, array $nodes, ?int $now = null): array
    {
        $nodes = array_values(array_filter($nodes, static fn(array $node): bool =>
            (int)($node['subscription_id'] ?? 0) === (int)($subscription['id'] ?? 0)
            && (string)($node['status'] ?? '') !== 'deleted'
        ));
        $used = max(0, (int)($subscription['traffic_used_bytes'] ?? 0));
        $limit = max(0, (int)($subscription['traffic_limit_bytes'] ?? 0));
        $timestamps = [];
        $upload = 0;
        $download = 0;
        $partial = $nodes === [];
        foreach ($nodes as $node) {
            $upload = self::addBytes($upload, (int)($node['upload_bytes'] ?? 0));
            $download = self::addBytes($download, (int)($node['download_bytes'] ?? 0));
            $timestamp = strtotime((string)($node['traffic_synced_at'] ?? ''));
            if ($timestamp !== false) { $timestamps[] = $timestamp; }
            if ($timestamp === false || (string)($node['traffic_sync_status'] ?? '') !== 'synced') { $partial = true; }
        }
        $known = array_key_exists('traffic_used_bytes', $subscription) && ($used > 0 || $timestamps !== []);
        $expires = trim((string)($subscription['expires_at'] ?? ''));
        $expiration = $expires !== '' ? strtotime($expires) : false;
        return [
            'used' => $used, 'limit' => $limit,
            'remaining' => $limit > 0 ? max(0, $limit - $used) : null,
            'percent' => $known && $limit > 0 ? min(100, round($used / $limit * 100, 1)) : null,
            'known' => $known, 'partial' => $partial,
            'upload' => $upload, 'download' => $download,
            // Show the oldest included sample, not a misleading newest-only timestamp.
            'checked_at' => $timestamps !== [] ? date('Y-m-d H:i:s', min($timestamps)) : null,
            'days_remaining' => $expiration !== false ? max(0, (int)ceil(($expiration - ($now ?? time())) / 86400)) : null,
            'lifetime' => $expires === '',
            'connections' => count($nodes),
            'active_connections' => count(array_filter($nodes, static fn(array $node): bool => (string)($node['status'] ?? '') === 'active')),
        ];
    }

    private static function addBytes(int $total, int $value): int
    {
        $value = max(0, $value);
        return $total > PHP_INT_MAX - $value ? PHP_INT_MAX : $total + $value;
    }
}
