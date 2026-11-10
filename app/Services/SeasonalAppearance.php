<?php

namespace App\Services;

use DateTimeImmutable;

final class SeasonalAppearance
{
    public const MODES = ['off', 'manual', 'scheduled'];

    public static function normalize(array $input): array
    {
        $settings = [];
        foreach (['seasonal_mode', 'seasonal_start', 'seasonal_end', 'seasonal_snow_motion'] as $key) {
            $value = $input[$key] ?? '';
            $settings[$key] = is_string($value) ? trim($value) : '';
        }
        $settings['seasonal_snow_motion'] = $settings['seasonal_snow_motion'] === '1' ? '1' : '0';

        return $settings;
    }

    public static function validate(array $settings): array
    {
        $errors = [];
        $mode = $settings['seasonal_mode'];
        if (!in_array($mode, self::MODES, true)) {
            $errors['seasonal_mode'][] = return_translation('admin_seasonal_invalid_mode');
        }

        if ($mode !== 'scheduled') {
            return $errors;
        }

        foreach (['seasonal_start', 'seasonal_end'] as $key) {
            if (!self::isDate($settings[$key])) {
                $errors[$key][] = return_translation('admin_seasonal_invalid_date');
            }
        }

        if ($errors === [] && $settings['seasonal_start'] > $settings['seasonal_end']) {
            $errors['seasonal_end'][] = return_translation('admin_seasonal_invalid_range');
        }

        return $errors;
    }

    public static function isActive(array $settings, ?DateTimeImmutable $now = null): bool
    {
        return match ($settings['seasonal_mode'] ?? 'off') {
            'manual' => true,
            'scheduled' => self::isWithinSchedule($settings, ($now ?? new DateTimeImmutable())->format('Y-m-d')),
            default => false,
        };
    }

    private static function isWithinSchedule(array $settings, string $today): bool
    {
        $start = $settings['seasonal_start'] ?? '';
        $end = $settings['seasonal_end'] ?? '';

        return self::isDate($start) && self::isDate($end) && $start <= $today && $today <= $end;
    }

    private static function isDate(string $value): bool
    {
        return preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $value) === 1
            && checkdate((int)substr($value, 5, 2), (int)substr($value, 8, 2), (int)substr($value, 0, 4));
    }
}
