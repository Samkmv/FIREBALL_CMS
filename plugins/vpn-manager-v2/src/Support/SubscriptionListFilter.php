<?php

namespace Fireball\VpnManagerV2\Support;

final class SubscriptionListFilter
{
    public const STATUSES = ['active', 'inactive', 'expired', 'suspended', 'traffic_exceeded',
        'cancelled', 'provisioning', 'provisioning_failed', 'partial_sync', 'sync_error',
        'disabled', 'deleting', 'delete_failed', 'pending_remote_delete', 'missing_remote', 'invalid_snapshot'];
    public const INACTIVE_STATUSES = ['inactive', 'suspended', 'disabled', 'cancelled', 'traffic_exceeded'];

    public static function normalize(mixed $status): string
    {
        return is_string($status) && in_array($status, self::STATUSES, true) ? $status : '';
    }

    public static function inactiveCount(array $counts): int
    {
        return array_sum(array_map(static fn(string $status): int => (int)($counts[$status] ?? 0), self::INACTIVE_STATUSES));
    }
}
