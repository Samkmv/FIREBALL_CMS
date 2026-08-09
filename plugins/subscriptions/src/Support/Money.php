<?php

namespace Fireball\Subscriptions\Support;

final class Money
{
    public static function toMinor(string|int $value): int
    {
        $value = trim((string)$value);
        if ($value === '') {
            return 0;
        }

        $value = str_replace(["\xc2\xa0", ' ', ','], ['', '', '.'], $value);
        if (preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $value, $matches) !== 1) {
            throw new \InvalidArgumentException('Invalid money value.');
        }

        $whole = (int)$matches[1];
        $fraction = str_pad((string)($matches[2] ?? ''), 2, '0');
        if ($whole > intdiv(PHP_INT_MAX - (int)$fraction, 100)) {
            throw new \OverflowException('Money value is too large.');
        }

        return $whole * 100 + (int)$fraction;
    }

    public static function decimal(int|string $minor): string
    {
        $minor = max(0, (int)$minor);

        return intdiv($minor, 100) . '.' . str_pad((string)($minor % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function display(int|string $minor, string $currency = 'RUB'): string
    {
        $minor = max(0, (int)$minor);
        $whole = number_format(intdiv($minor, 100), 0, ',', ' ');
        $fraction = $minor % 100;
        $number = $fraction === 0 ? $whole : $whole . ',' . str_pad((string)$fraction, 2, '0', STR_PAD_LEFT);

        return $number . ' ' . strtoupper(trim($currency));
    }
}
