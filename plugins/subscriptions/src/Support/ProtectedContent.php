<?php

namespace Fireball\Subscriptions\Support;

final class ProtectedContent
{
    public static function replaceVideos(string $html, string $replacement): string
    {
        $html = preg_replace('/<video\b[^>]*>.*?<\/video\s*>/is', $replacement, $html) ?? $html;

        return preg_replace_callback(
            '/<iframe\b[^>]*>.*?<\/iframe\s*>/is',
            static function (array $matches) use ($replacement): string {
                $iframe = (string)$matches[0];
                if (preg_match('/\bsrc\s*=\s*(["\'])(.*?)\1/is', $iframe, $source) !== 1) {
                    return $iframe;
                }
                $host = strtolower((string)(parse_url(html_entity_decode((string)$source[2]), PHP_URL_HOST) ?: ''));
                foreach (['youtube.com', 'youtube-nocookie.com', 'youtu.be', 'vimeo.com'] as $videoHost) {
                    if ($host === $videoHost || str_ends_with($host, '.' . $videoHost)) {
                        return $replacement;
                    }
                }

                return $iframe;
            },
            $html
        ) ?? $html;
    }
}
