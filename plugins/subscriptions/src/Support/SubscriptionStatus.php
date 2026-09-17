<?php

namespace Fireball\Subscriptions\Support;

/** Read-only status projection. Stored billing state is never changed while rendering. */
final class SubscriptionStatus
{
    public static function effective(array $subscription, ?string $now = null): string
    {
        $now ??= date('Y-m-d H:i:s');
        $status = (string)($subscription['status'] ?? '');
        $startsAt = $subscription['starts_at'] ?? null;
        $endsAt = $subscription['ends_at'] ?? null;

        if (in_array($status, ['active', 'cancelled'], true)) {
            if ($endsAt !== null && $endsAt <= $now) {
                return 'expired';
            }
            if ($startsAt !== null && $startsAt > $now) {
                return 'pending';
            }
        } elseif ($status === 'grace_period') {
            $graceEnd = $subscription['grace_ends_at'] ?? $endsAt;
            if ($graceEnd === null || $graceEnd <= $now) {
                return 'expired';
            }
            if ($startsAt !== null && $startsAt > $now) {
                return 'pending';
            }
        }

        return $status;
    }

    /** Use the same projection before pagination, not only on the returned rows. */
    public static function effectiveSql(string $alias = 's'): string
    {
        $prefix = self::prefix($alias);

        return "CASE
            WHEN {$prefix}status IN ('active', 'cancelled') AND {$prefix}ends_at <= NOW() THEN 'expired'
            WHEN {$prefix}status = 'grace_period' AND (COALESCE({$prefix}grace_ends_at, {$prefix}ends_at) IS NULL OR COALESCE({$prefix}grace_ends_at, {$prefix}ends_at) <= NOW()) THEN 'expired'
            WHEN {$prefix}status IN ('active', 'cancelled', 'grace_period') AND {$prefix}starts_at > NOW() THEN 'pending'
            ELSE {$prefix}status
        END";
    }

    /** Mirrors AccessService: grace dates cannot extend a non-grace subscription. */
    public static function activeSql(string $alias = 's'): string
    {
        $prefix = self::prefix($alias);

        return "({$prefix}archived_at IS NULL AND {$prefix}starts_at <= NOW() AND (
            ({$prefix}status IN ('active', 'cancelled') AND ({$prefix}ends_at IS NULL OR {$prefix}ends_at > NOW()))
            OR ({$prefix}status = 'grace_period' AND COALESCE({$prefix}grace_ends_at, {$prefix}ends_at) > NOW())
        ))";
    }

    private static function prefix(string $alias): string
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $alias)) {
            throw new \InvalidArgumentException('Invalid subscription table alias.');
        }

        return $alias . '.';
    }
}
