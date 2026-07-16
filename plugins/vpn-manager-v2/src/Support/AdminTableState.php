<?php

namespace Fireball\VpnManagerV2\Support;

final class AdminTableState
{
    private const ALLOWED_KEYS = ['page', 'search', 'status', 'sort', 'direction', 'filters', 'filter', 'per_page'];

    public static function capture(): string
    {
        $query = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY) ?? '');

        return self::sanitize($query);
    }

    public static function sanitize(mixed $query): string
    {
        if (!is_scalar($query) || trim((string)$query) === '') {
            return '';
        }
        parse_str(mb_substr((string)$query, 0, 4000), $input);
        $clean = [];
        foreach (self::ALLOWED_KEYS as $key) {
            if (!array_key_exists($key, $input)) {
                continue;
            }
            $value = self::cleanValue($input[$key]);
            if ($value !== null && $value !== '' && $value !== []) {
                $clean[$key] = $value;
            }
        }

        return http_build_query($clean, '', '&', PHP_QUERY_RFC3986);
    }

    public static function append(string $path, string $query): string
    {
        $url = base_href($path);
        $query = self::sanitize($query);

        return $url . ($query !== '' ? '?' . $query : '');
    }

    public static function asParameter(string $path, string $query): string
    {
        $query = self::sanitize($query);
        $url = base_href($path);

        return $url . ($query !== '' ? '?return_query=' . rawurlencode($query) : '');
    }

    private static function cleanValue(mixed $value, int $depth = 0): array|string|null
    {
        if ($depth > 2) {
            return null;
        }
        if (is_array($value)) {
            $clean = [];
            foreach (array_slice($value, 0, 30, true) as $key => $item) {
                $key = preg_replace('/[^a-zA-Z0-9_.-]/', '', (string)$key) ?? '';
                if ($key === '') {
                    continue;
                }
                $item = self::cleanValue($item, $depth + 1);
                if ($item !== null && $item !== '' && $item !== []) {
                    $clean[$key] = $item;
                }
            }

            return $clean;
        }
        if (!is_scalar($value) && $value !== null) {
            return null;
        }

        return mb_substr(trim((string)$value), 0, 255);
    }

    private function __construct()
    {
    }
}
