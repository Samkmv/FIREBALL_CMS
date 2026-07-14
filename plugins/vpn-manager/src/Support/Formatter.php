<?php

namespace Fireball\VpnManager\Support;

final class Formatter
{
    public static function bytes(int|float|string|null $bytes): string
    {
        $value = max(0.0, (float)$bytes);
        $locale = function_exists('current_locale') ? (string)current_locale() : '';
        $units = $locale === 'ru'
            ? ['Б', 'КБ', 'МБ', 'ГБ', 'ТБ', 'ПБ']
            : ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $decimalSeparator = in_array($locale, ['ru', 'de'], true) ? ',' : '.';
        $index = 0;

        while ($value >= 1024 && $index < count($units) - 1) {
            $value /= 1024;
            $index++;
        }

        $formatted = number_format($value, $index === 0 ? 0 : 2, $decimalSeparator, ' ');
        $formatted = rtrim(rtrim($formatted, '0'), $decimalSeparator);
        if ($formatted === '') {
            $formatted = '0';
        }

        return $formatted . ' ' . $units[$index];
    }

    public static function bytesLimit(int|float|string|null $bytes): string
    {
        if ((float)$bytes <= 0) {
            return \FireballPluginVpnManager::t('vpn_manager_unlimited');
        }

        return self::bytes($bytes);
    }

    public static function traffic(int|float|string|null $used, int|float|string|null $limit): string
    {
        return self::bytes($used) . ' / ' . self::bytesLimit($limit);
    }

    public static function gbToBytes(mixed $gb): int
    {
        $value = max(0.0, (float)str_replace(',', '.', (string)$gb));

        return (int)round($value * 1024 * 1024 * 1024);
    }

    public static function trafficToBytes(mixed $value, string $unit): int
    {
        $number = max(0.0, (float)str_replace(',', '.', (string)$value));
        $multiplier = match (strtolower($unit)) {
            'mb' => 1024 * 1024,
            'tb' => 1024 * 1024 * 1024 * 1024,
            default => 1024 * 1024 * 1024,
        };

        return (int)round($number * $multiplier);
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
            'expired', 'disabled', 'cancelled', 'deleted', 'offline', 'skipped', 'sync_missing' => 'text-secondary bg-secondary-subtle',
            'traffic_exceeded', 'provisioning_failed', 'sync_error', 'create_failed', 'delete_failed', 'error', 'failed' => 'text-danger bg-danger-subtle',
            'provisioning', 'creating', 'deleting', 'pending', 'unchecked' => 'text-warning bg-warning-subtle',
            default => 'text-info bg-info-subtle',
        };

        return '<span class="badge rounded-pill ' . htmlSC($class) . '">' . htmlSC(self::statusLabel($status)) . '</span>';
    }

    public static function statusLabel(string $status): string
    {
        $key = 'vpn_manager_status_' . preg_replace('/[^a-z0-9_]/', '_', strtolower(trim($status)));
        $label = \FireballPluginVpnManager::t($key);

        return $label !== $key ? $label : $status;
    }

    public static function dateTime(?string $value, string $emptyKey = 'vpn_manager_date_never'): string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return \FireballPluginVpnManager::t($emptyKey);
        }

        $timestamp = strtotime($value);
        if (!$timestamp) {
            return $value;
        }

        $locale = function_exists('current_locale') ? (string)current_locale() : '';

        return match ($locale) {
            'ru', 'de' => date('d.m.Y H:i', $timestamp),
            default => date('Y-m-d H:i', $timestamp),
        };
    }

    public static function dateInput(?string $value): string
    {
        $timestamp = strtotime((string)$value);

        return $timestamp ? date('Y-m-d\TH:i', $timestamp) : '';
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
