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
        $used = 0;
        $limit = max(0, (int)($subscription['traffic_limit_bytes'] ?? 0));
        $timestamps = [];
        $upload = 0;
        $download = 0;
        $known = false;
        $missing = 0;
        $failed = false;
        // Use exactly the same owned counters as the server table, not a stale
        // subscription-level accounting value. Viewing never changes accounting.
        foreach (self::byServer($subscription, $nodes) as $server) {
            $used = self::addBytes($used, $server['used']);
            $upload = self::addBytes($upload, $server['upload']);
            $download = self::addBytes($download, $server['download']);
            $known = $known || $server['known'];
            $missing += $server['missing'];
            $failed = $failed || $server['failed'];
            $timestamp = strtotime((string)($server['checked_at'] ?? ''));
            if ($timestamp !== false) { $timestamps[] = $timestamp; }
        }
        $expires = trim((string)($subscription['expires_at'] ?? ''));
        $expiration = $expires !== '' ? strtotime($expires) : false;
        return [
            'used' => $used, 'limit' => $limit,
            'remaining' => $limit > 0 ? max(0, $limit - $used) : null,
            'remaining_known' => $known && $missing === 0,
            'percent' => $known && $missing === 0 && $limit > 0 ? min(100, round($used / $limit * 100, 1)) : null,
            'known' => $known, 'partial' => $missing > 0 || $failed,
            'missing' => $missing, 'failed' => $failed,
            'upload' => $upload, 'download' => $download,
            // Show the oldest included sample, not a misleading newest-only timestamp.
            'checked_at' => $timestamps !== [] ? date('Y-m-d H:i:s', min($timestamps)) : null,
            'days_remaining' => $expiration !== false ? max(0, (int)ceil(($expiration - ($now ?? time())) / 86400)) : null,
            'lifetime' => $expires === '',
            'connections' => count($nodes),
            'active_connections' => count(array_filter($nodes, static fn(array $node): bool => (string)($node['status'] ?? '') === 'active')),
        ];
    }

    public static function byServer(array $subscription, array $nodes): array
    {
        $servers = [];
        foreach ($nodes as $node) {
            if ((int)($node['subscription_id'] ?? 0) !== (int)($subscription['id'] ?? 0)
                || (string)($node['status'] ?? '') === 'deleted') { continue; }
            $id = (int)($node['server_id'] ?? 0);
            if ($id <= 0) continue;
            $servers[$id] ??= ['id' => $id, 'name' => (string)($node['server_name'] ?? ('#' . $id)),
                'used' => 0, 'upload' => 0, 'download' => 0, 'known' => false,
                'sample_known' => false, 'partial' => false, 'missing' => 0, 'failed' => false,
                'connections' => 0, 'timestamps' => []];
            $server = &$servers[$id];
            $server['connections']++;
            $used = max(0, (int)($node['traffic_used_bytes'] ?? 0));
            $timestamp = strtotime((string)($node['traffic_synced_at'] ?? ''));
            $server['used'] = self::addBytes($server['used'], $used);
            $server['known'] = $server['known'] || $used > 0 || $timestamp !== false;
            if ($timestamp !== false) {
                $server['sample_known'] = true;
                $server['timestamps'][] = $timestamp;
                $server['upload'] = self::addBytes($server['upload'], (int)($node['upload_bytes'] ?? 0));
                $server['download'] = self::addBytes($server['download'], (int)($node['download_bytes'] ?? 0));
            }
            if ($timestamp === false) { $server['missing']++; }
            $server['failed'] = $server['failed'] || (string)($node['traffic_sync_status'] ?? '') === 'failed';
            $server['partial'] = $server['missing'] > 0 || $server['failed'];
            unset($server);
        }
        foreach ($servers as &$server) {
            $server['checked_at'] = $server['timestamps'] === [] ? null : date('Y-m-d H:i:s', min($server['timestamps']));
            unset($server['timestamps']);
        }
        unset($server);
        return array_values($servers);
    }

    private static function addBytes(int $total, int $value): int
    {
        $value = max(0, $value);
        return $total > PHP_INT_MAX - $value ? PHP_INT_MAX : $total + $value;
    }
}
