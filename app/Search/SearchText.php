<?php

namespace App\Search;

final class SearchText
{
    public static function plainText(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if ($value[0] === '{' || $value[0] === '[') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $parts = [];
                self::collectStrings($decoded, $parts);
                $value = implode(' ', $parts);
            }
        }

        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    public static function excerpt(string $value, int $length = 220): string
    {
        $plain = self::plainText($value);
        if (mb_strlen($plain, 'UTF-8') <= $length) {
            return $plain;
        }

        return rtrim(mb_substr($plain, 0, $length - 1, 'UTF-8')) . '…';
    }

    private static function collectStrings(array $value, array &$parts): void
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                self::collectStrings($item, $parts);
                continue;
            }
            if (!is_string($item) || trim($item) === '') {
                continue;
            }
            if (is_string($key) && preg_match('/(?:^|_)(?:id|type|style|class|url|href|src)$/i', $key)) {
                continue;
            }
            $parts[] = $item;
        }
    }
}
