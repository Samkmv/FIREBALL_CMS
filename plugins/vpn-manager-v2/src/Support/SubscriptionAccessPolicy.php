<?php

namespace Fireball\VpnManagerV2\Support;

final class SubscriptionAccessPolicy
{
    public static function enabled(array $subscription, array $node = [], ?int $now = null): bool
    {
        $now ??= time();
        if (!in_array(strtolower(trim((string)($subscription['status'] ?? 'active'))), [
            'active', 'provisioning', 'provisioning_failed', 'partial_sync', 'sync_error',
        ], true)) {
            return false;
        }
        foreach (['starts_at', 'expires_at'] as $field) {
            $value = trim((string)($subscription[$field] ?? ''));
            if ($value === '') {
                continue;
            }
            $timestamp = strtotime($value);
            if ($timestamp === false || ($field === 'expires_at' ? $timestamp <= $now : $timestamp > $now)) {
                return false;
            }
        }
        $limit = max(0, (int)($subscription['traffic_limit_bytes'] ?? 0));
        if ($limit > 0 && (int)($subscription['traffic_used_bytes'] ?? 0) >= $limit) {
            return false;
        }

        // A node can restrict access, but cannot override expiry or suspension.
        return !array_key_exists('desired_enabled', $node)
            || filter_var($node['desired_enabled'], FILTER_VALIDATE_BOOL) === true;
    }
}
