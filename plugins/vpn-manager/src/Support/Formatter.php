<?php

namespace Fireball\VpnManager\Support;

final class Formatter
{
    public static function bytes(int|float|string|null $bytes): string
    {
        $value = max(0.0, (float)$bytes);
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $index = 0;

        while ($value >= 1024 && $index < count($units) - 1) {
            $value /= 1024;
            $index++;
        }

        return rtrim(rtrim(number_format($value, $index === 0 ? 0 : 2, '.', ' '), '0'), '.') . ' ' . $units[$index];
    }

    public static function gbToBytes(mixed $gb): int
    {
        $value = max(0.0, (float)str_replace(',', '.', (string)$gb));

        return (int)round($value * 1024 * 1024 * 1024);
    }

    public static function bytesToGb(mixed $bytes): string
    {
        $value = max(0.0, (float)$bytes) / 1024 / 1024 / 1024;

        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    public static function statusBadge(string $status): string
    {
        $class = match ($status) {
            'active', 'online', 'sent' => 'text-success bg-success-subtle',
            'expired', 'disabled', 'cancelled', 'deleted', 'offline', 'skipped' => 'text-secondary bg-secondary-subtle',
            'traffic_exceeded', 'provisioning_failed', 'sync_error', 'create_failed', 'error', 'failed' => 'text-danger bg-danger-subtle',
            'provisioning', 'creating', 'pending', 'unchecked' => 'text-warning bg-warning-subtle',
            default => 'text-info bg-info-subtle',
        };

        return '<span class="badge rounded-pill ' . htmlSC($class) . '">' . htmlSC($status) . '</span>';
    }

    public static function maskSensitive(?string $value): string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }

        return mb_substr($value, 0, 4) . '••••' . mb_substr($value, -4);
    }
}
