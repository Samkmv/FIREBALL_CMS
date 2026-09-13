<?php

namespace App\Services;

/** Existing templates opt in explicitly or through their rendered HTML markers. */
final class FrontendAssets
{
    public static function requirements(string $html, array $requested = []): array
    {
        $assets = array_fill_keys(array_filter($requested, 'is_string'), true);
        foreach ([
            'choices' => '/\\bdata-select(?:[\\s=>])|\\bdata-choices(?:[\\s=>])/i',
            'simplebar' => '/\\bdata-simplebar(?:[\\s=>])/i',
            'swiper' => '/\\bswiper(?:[\\s"\x27-])|\\bdata-swiper/i',
            'highlight' => '/<pre\\b|\\blanguage-[a-z0-9]+/i',
            'player' => '/<(?:video|audio)\\b|\\bdata-(?:fire-player|fireplayer|plyr)/i',
        ] as $name => $pattern) {
            if (preg_match($pattern, $html)) $assets[$name] = true;
        }
        return $assets;
    }
}
