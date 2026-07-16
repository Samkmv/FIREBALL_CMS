<?php

namespace Fireball\VpnManagerV2\Support;

final class ProfileVpnFormatter
{
    public static function effectiveStatus(array $subscription, ?int $now = null): string
    {
        $status = trim((string)($subscription['status'] ?? ''));
        $now ??= time();
        if ($status === 'active') {
            $startsAt = self::timestamp($subscription['starts_at'] ?? null);
            $expiresAt = self::timestamp($subscription['expires_at'] ?? null);
            if ($expiresAt !== null && $expiresAt <= $now) {
                return 'expired';
            }
            if ($startsAt !== null && $startsAt > $now) {
                return 'provisioning';
            }
        }

        return $status !== '' ? $status : 'unknown';
    }

    public static function date(mixed $value): string
    {
        $timestamp = self::timestamp($value);

        return $timestamp !== null ? date('d.m.Y H:i', $timestamp) : '—';
    }

    public static function remaining(mixed $expiresAt, ?int $now = null): string
    {
        $expires = self::timestamp($expiresAt);
        if ($expires === null) {
            return \FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_remaining_unlimited');
        }
        $seconds = $expires - ($now ?? time());
        if ($seconds <= 0) {
            return \FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_remaining_expired');
        }
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $parts = [];
        if ($days > 0) {
            $parts[] = $days . ' ' . \FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_unit_day');
        }
        if ($hours > 0 && count($parts) < 2) {
            $parts[] = $hours . ' ' . \FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_unit_hour');
        }
        if ($minutes > 0 && count($parts) < 2) {
            $parts[] = $minutes . ' ' . \FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_unit_minute');
        }

        return $parts !== []
            ? implode(' ', $parts)
            : \FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_less_minute');
    }

    public static function bytes(mixed $bytes): string
    {
        $value = max(0, (int)$bytes);
        if ($value === 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $number = (float)$value;
        $unit = 0;
        while ($number >= 1024 && $unit < count($units) - 1) {
            $number /= 1024;
            $unit++;
        }
        $formatted = number_format($number, $unit === 0 ? 0 : 2, '.', '');
        if ($unit !== 0) {
            $formatted = rtrim(rtrim($formatted, '0'), '.');
        }

        return $formatted . ' ' . $units[$unit];
    }

    private static function timestamp(mixed $value): ?int
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }
        $timestamp = strtotime($value);

        return $timestamp !== false ? $timestamp : null;
    }

    private function __construct()
    {
    }
}
