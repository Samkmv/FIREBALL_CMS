<?php

namespace App\Services;

/** Existing templates opt in explicitly or through their rendered HTML markers. */
final class FrontendAssets
{
    public static function requirements(string $html, array $requested = []): array
    {
        $assets = array_fill_keys(array_filter($requested, 'is_string'), true);
        // Native/background media has its own lifecycle and does not need FirePlayer.
        // Explicit player requirements and all legacy unmarked media still work.
        $playerHtml = preg_replace('/<(?:video|audio)\b(?=[^>]*\sdata-player-native(?:\s|=|>))[^>]*>/i', '', $html) ?? $html;
        foreach ([
            'choices' => '/\\bdata-select(?:[\\s=>])|\\bdata-choices(?:[\\s=>])/i',
            'simplebar' => '/\\bdata-simplebar(?:[\\s=>])/i',
            'swiper' => '/\\bswiper(?:[\\s"\x27-])|\\bdata-swiper/i',
            'highlight' => '/<pre\\b|\\blanguage-[a-z0-9]+/i',
            'player' => '/<(?:video|audio)\\b|\\bdata-(?:fire-player|fireplayer|plyr)/i',
        ] as $name => $pattern) {
            if (preg_match($pattern, $name === 'player' ? $playerHtml : $html)) $assets[$name] = true;
        }
        if (!empty($assets['player'])) {
            $all = ['player_video'=>true, 'player_audio'=>true, 'player_hls'=>true, 'player_live'=>true];
            if (in_array('player', $requested, true)) return $assets + $all;
            preg_match_all('/<(video|audio)\b([^>]*)(?:>(.*?)<\/\1\s*>|>)/is', $playerHtml, $media, PREG_SET_ORDER);
            // Manual/dynamic players and unclassified legacy wrappers retain the full bundle.
            if (!$media || preg_match('/data-fire-player-manual/i', $playerHtml)) return $assets + $all;
            $outsideMedia = str_replace(array_column($media, 0), '', $playerHtml);
            if (preg_match('/data-(?:fire-player|fireplayer|plyr)|class=["\'][^"\']*fire-player/i', $outsideMedia)) return $assets + $all;
            foreach ($media as $element) {
                $markup = $element[0];
                if (preg_match('/data-plyr-options/i', $markup)) return $assets + $all;
                $assets['player_' . strtolower($element[1])] = true;
                if (preg_match('/\.m3u8\b|mpegurl|data-hls-src|data-protocol=["\']hls/i', $markup)) {
                    $assets['player_hls'] = true;
                    // Auto-detected HLS can be live; only explicit VOD can omit live recovery.
                    if (!preg_match('/data-mode=["\']vod["\']/i', $markup)) $assets['player_live'] = true;
                } elseif (!preg_match('/\.(?:mp4|webm|ogv|mov|mp3|m4a|ogg|oga|wav|flac|aac)\b|type=["\'](?:video|audio)\//i', $markup)) {
                    return $assets + $all;
                }
            }
        }
        return $assets;
    }
}
