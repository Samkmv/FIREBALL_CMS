<?php

namespace App\Search;

final class SearchNormalizer
{
    public static function normalize(string $value): string
    {
        $value = mb_strtolower($value, 'UTF-8');
        $value = str_replace('ё', 'е', $value);
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    /**
     * @return list<string>
     */
    public static function tokens(string $value, int $minimumLength = 1): array
    {
        $minimumLength = max(1, $minimumLength);
        $normalized = self::normalize($value);
        if ($normalized === '') {
            return [];
        }

        $tokens = [];
        foreach (explode(' ', $normalized) as $token) {
            $isNumber = preg_match('/^\p{N}+$/u', $token) === 1;
            if (!$isNumber && mb_strlen($token, 'UTF-8') < $minimumLength) {
                continue;
            }
            $tokens[$token] = true;
        }

        return array_keys($tokens);
    }

    /**
     * Returns full and conservative word-form prefixes for partial matching.
     * For example, both "камера" and "камеры" share the prefix "камер".
     *
     * @return list<string>
     */
    public static function matchingPrefixes(string $token, int $minimumLength = 3): array
    {
        $token = str_replace(' ', '', self::normalize($token));
        if ($token === '') {
            return [];
        }

        $prefixes = [$token];
        $length = mb_strlen($token, 'UTF-8');
        if (
            $length >= max(5, $minimumLength + 2)
            && preg_match('/^\p{L}+$/u', $token) === 1
        ) {
            $prefixes[] = mb_substr($token, 0, $length - 1, 'UTF-8');
        }

        return array_values(array_unique($prefixes));
    }
}
