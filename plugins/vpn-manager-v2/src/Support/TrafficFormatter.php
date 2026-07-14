<?php

namespace Fireball\VpnManagerV2\Support;

final class TrafficFormatter
{
    public static function limit(?int $bytes): string
    {
        if ($bytes === null || $bytes <= 0) {
            return \FireballPluginVpnManagerV2::t('vpn_manager_v2_unlimited');
        }

        $value = (float)$bytes;
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $unit = 0;
        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        $formatted = number_format($value, $unit === 0 ? 0 : 2, '.', '');
        if ($unit !== 0) {
            $formatted = rtrim(rtrim($formatted, '0'), '.');
        }

        return $formatted . ' ' . $units[$unit];
    }

    public static function inputParts(?int $bytes): array
    {
        if ($bytes === null || $bytes <= 0) {
            return ['value' => '0', 'unit' => 'gb'];
        }

        $units = [
            'tb' => 1024 ** 4,
            'gb' => 1024 ** 3,
            'mb' => 1024 ** 2,
        ];
        foreach ($units as $unit => $multiplier) {
            if ($bytes >= $multiplier && $bytes % $multiplier === 0) {
                return ['value' => (string)($bytes / $multiplier), 'unit' => $unit];
            }
        }

        $value = $bytes / (1024 ** 2);

        return ['value' => rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.'), 'unit' => 'mb'];
    }

    private function __construct()
    {
    }
}
