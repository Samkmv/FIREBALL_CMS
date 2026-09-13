<?php

namespace FBL;

/** Content versions persist until an explicit asset edit, update or cache clear. */
final class AssetManifest
{
    public static function version(string $path): string
    {
        $versions = cache()->get('assets:versions:v1', []);
        if (!is_array($versions)) $versions = [];
        $key = hash('sha256', $path);
        if (!isset($versions[$key]) || (defined('DEBUG') && DEBUG)) {
            if (!is_file($path)) return '';
            $hash = hash_file('sha256', $path);
            if ($hash === false) return '';
            $versions[$key] = substr($hash, 0, 20);
            cache()->set('assets:versions:v1', $versions, 31536000);
        }
        return (string)$versions[$key];
    }

    public static function url(string $url, string $path): string
    {
        $version = self::version($path);
        if ($version === '') return $url;
        $parts = explode('#', $url, 2);
        return $parts[0] . (str_contains($parts[0], '?') ? '&' : '?') . 'v=' . $version
            . (isset($parts[1]) ? '#' . $parts[1] : '');
    }

    public static function invalidate(): void
    {
        cache()->remove('assets:versions:v1');
    }
}
