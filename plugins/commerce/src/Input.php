<?php

namespace Fireball\Commerce;

use FireballPluginCommerce as Plugin;

final class Input
{
    public static function text(mixed $value, int $limit, bool $required = false): string
    {
        $text = is_string($value) ? trim($value) : '';
        if (($required && $text === '') || !mb_check_encoding($text, 'UTF-8') || mb_strlen($text) > $limit
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $text)) {
            throw new ValidationError(Plugin::t('error_fields'));
        }
        return $text;
    }

    public static function query(mixed $value, int $limit = 100): string
    {
        return is_string($value) && mb_check_encoding($value, 'UTF-8') ? mb_substr(trim($value), 0, $limit) : '';
    }

    public static function page(mixed $value): int
    {
        return is_scalar($value) ? min(10000, max(1, (int)$value)) : 1;
    }

    public static function number(mixed $value, int $maximum): int
    {
        $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => $maximum]]);
        if ($number === false) {
            throw new ValidationError(Plugin::t('error_fields'));
        }
        return $number;
    }

    public static function price(mixed $value): int
    {
        $value = is_string($value) || is_int($value) ? trim((string)$value) : '';
        if (!preg_match('/\A(\d{1,8})(?:[.,](\d{1,2}))?\z/', $value, $match)) {
            throw new ValidationError(Plugin::t('error_price'));
        }
        return (int)$match[1] * 100 + (int)str_pad($match[2] ?? '', 2, '0');
    }

    public static function money(int $minor, bool $form = false): string
    {
        if ($form) {
            return sprintf('%d.%02d', intdiv($minor, 100), $minor % 100);
        }
        return number_format(intdiv($minor, 100), 0, ',', ' ')
            . ($minor % 100 > 0 ? sprintf(',%02d', $minor % 100) : '') . ' ₽';
    }

    public static function slug(mixed $value, string $name): string
    {
        $value = strtolower(self::text($value, 160));
        if ($value === '') {
            $latin = function_exists('transliterator_transliterate') ? transliterator_transliterate('Any-Latin; Latin-ASCII', $name) : $name;
            $value = strtolower($latin ?: $name);
            $value = trim(substr((string)preg_replace('/[^a-z0-9]+/', '-', $value), 0, 130), '-');
            $value = ($value !== '' ? $value : 'item') . '-' . bin2hex(random_bytes(4));
        }
        if (!preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $value)) {
            throw new ValidationError(Plugin::t('error_slug'));
        }
        return $value;
    }
}
