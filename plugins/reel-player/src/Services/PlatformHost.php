<?php
declare(strict_types=1);
namespace Fireball\ReelPlayer\Services;

/** Mounted outside the page fragment; normal navigation never claims seamless audio. */
final class PlatformHost
{
    public static function markup(array $config = []): string
    {
        return plugin_view('reel-player', 'components/host', ['config'=>PlatformAssets::publicConfig($config)], false);
    }

    public static function inject(string $html, array $context = []): string
    {
        if (($context['surface'] ?? '') !== 'theme' || !PlatformFeatures::enabled('public_catalog') || !PlatformFeatures::enabled('compact_player')) return $html;
        $path = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
        if (preg_match('~/admin(?:/|$)|/music/(?:api|media|cover|embed|download)(?:/|$)~', $path)) return $html;
        if (!str_contains($html, '</body>')) return $html;
        if (!preg_match('/<[a-z][^>]*\bdata-tape-room-host(?:\s|=|>)/i', $html)) {
            $viewer = function_exists('check_auth') && check_auth();
            $config = ['api'=>base_href('/music/api'),'base'=>base_href('/music'),'viewer'=>$viewer,'csrf'=>$viewer ? (string)session()->get('needCSRFToken','') : ''];
            $html = str_ireplace('</body>', self::markup($config) . PlatformAssets::tags() . '</body>', $html);
        }
        if (!str_contains($html, 'data-tr-platform-style')) $html = str_ireplace('</head>', str_replace('data-tr-platform-asset', 'data-tr-platform-style', PlatformAssets::tags(false)) . '</head>', $html);
        return $html;
    }
}
