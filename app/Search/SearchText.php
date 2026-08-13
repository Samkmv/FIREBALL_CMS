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
        } else {
            // The block editor stores its base64 state snapshot inside a hidden
            // template next to the readable HTML. strip_tags() removes the tag
            // itself but keeps that payload as visible text, so discard hidden
            // technical elements before extracting the public search text.
            $value = preg_replace(
                '~<(script|style|template|noscript)\b[^>]*>.*?</\1\s*>~isu',
                ' ',
                $value
            ) ?? $value;
            $value = preg_replace('/<!--.*?-->/su', ' ', $value) ?? $value;
        }

        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace(
            '~(?:https?|ftp|rtsp|rtmp)://[^\s<>]+|(?:data|blob|file):[^\s<>]+|\b[^\s<>]+\.(?:m3u8|mpd)(?:\?[^\s<>]*)?~iu',
            ' ',
            $value
        ) ?? $value;
        // Also clean already indexed editor snapshots. This makes the fix
        // effective immediately, before the periodic search reindex runs.
        $value = preg_replace(
            '/(?:eyJ|W3si|Wzsi)[A-Za-z0-9+\/_=-]{36,}/',
            ' ',
            $value
        ) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    public static function excerpt(string $value, int $length = 180): string
    {
        $plain = self::plainText($value);
        if (mb_strlen($plain, 'UTF-8') <= $length) {
            return $plain;
        }

        $excerpt = rtrim(mb_substr($plain, 0, $length - 1, 'UTF-8'));
        $lastSpace = mb_strrpos($excerpt, ' ', 0, 'UTF-8');
        if ($lastSpace !== false && $lastSpace >= (int)floor($length * .65)) {
            $excerpt = rtrim(mb_substr($excerpt, 0, $lastSpace, 'UTF-8'));
        }

        return $excerpt . '…';
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
            if (is_string($key) && preg_match(
                '/(?:^|_)(?:id|type|style|class|url|uri|href|src|source|poster|stream|token|key|secret|password|hash|signature|provider_payload|config)$/i',
                $key
            )) {
                continue;
            }
            $clean = trim($item);
            if (preg_match('~^(?:(?:https?|ftp|rtsp|rtmp)://|(?:data|blob|file):)~i', $clean) === 1
                || preg_match('/^[A-Za-z0-9+\/_=-]{40,}$/', $clean) === 1) {
                continue;
            }
            $parts[] = $clean;
        }
    }
}
