<?php
declare(strict_types=1);
namespace Fireball\ReelPlayer\Services;

use Fireball\ReelPlayer\Repositories\PlayerPreferences;

/** Versioned static resources and a public configuration with no private library. */
final class PlatformAssets
{
    public const SCRIPTS = ['media-session.js','chapters.js','transport.js','meters.js','quality.js','images.js','playback-core.js','preferences.js','compact.js','public-playback.js','public.js','navigation.js'];
    public const STYLES = ['reel.css','public.css'];

    public static function url(string $file): string
    {
        if (!preg_match('/^[a-z0-9.-]+$/D', $file)) throw new \InvalidArgumentException('Invalid music resource.');
        $path = dirname(__DIR__, 2) . '/assets/' . $file;
        if (!is_file($path)) return '';
        return base_href('/plugins/reel-player/assets/' . $file . '?v=' . substr(hash_file('sha256', $path), 0, 16));
    }

    public static function artwork(): array
    {
        $result = ['defaultCovers'=>[], 'defaultCoverVariants'=>[], 'defaultArtists'=>[], 'defaultArtistVariants'=>[]];
        foreach (['cover','artist'] as $kind) foreach (['light','dark'] as $theme) {
            $key = $kind === 'cover' ? 'defaultCovers' : 'defaultArtists';
            $variantKey = $kind === 'cover' ? 'defaultCoverVariants' : 'defaultArtistVariants';
            $result[$key][$theme] = self::url($kind . '-' . $theme . '.png');
            foreach ([96,320,640] as $size) $result[$variantKey][$theme][$size] = self::url($kind . '-' . $theme . '-' . $size . '.webp');
        }
        $result['defaultCover'] = $result['defaultCovers']['light'];
        $result['defaultArtwork'] = $result['defaultCoverVariants']['light'][640];
        return $result;
    }

    public static function publicConfig(array $config = []): array
    {
        $embed = !empty($config['embed']);
        $viewer = !$embed && !empty($config['viewer']);
        $user = $viewer && function_exists('get_user') ? get_user() : null;
        $id = $user ? (int)$user['id'] : 0;
        $flags = function_exists('plugin_setting') ? PlatformFeatures::all() : array_fill_keys(PlatformFeatures::FLAGS, false);
        $preferences = PlayerPreferences::DEFAULTS;
        $initialized = false;
        if ($id) {
            $repository = new PlayerPreferences($id);
            $preferences = $repository->get();
            $initialized = $repository->hasStored();
        }
        return [
            ...self::artwork(), ...$config,
            'surface'=>'public', 'flags'=>$flags,
            'features'=>['compact'=>$embed || $flags['compact_player'], 'sitewide'=>self::supportsSitewide()],
            'authenticated'=>$viewer, 'viewer'=>$viewer, 'csrf'=>$viewer ? (string)($config['csrf'] ?? '') : '',
            'preferencesUrl'=>$viewer ? base_href('/music/api/preferences') : '',
            'listenUrl'=>$viewer ? base_href('/music/api/listen') : '',
            'storageKey'=>$embed ? 'fireball-tape-room:embed' : 'fireball-tape-room:public:' . ($id ?: 'guest'),
            'commonPlaybackKey'=>$embed ? '' : 'fireball-tape-room:session:' . ($id ?: 'guest'),
            'acceptPrivateSources'=>!$embed && $viewer && function_exists('check_admin') && check_admin(),
            'preferences'=>$preferences, 'preferencesInitialized'=>$initialized,
            'state'=>$config['state'] ?? ['tracks'=>[]], 'embed'=>$embed,
        ];
    }

    public static function privateConfig(array $config): array
    {
        $flags = function_exists('plugin_setting') ? PlatformFeatures::all() : array_fill_keys(PlatformFeatures::FLAGS, false);
        $id = (int)(function_exists('get_user') ? (get_user()['id'] ?? 0) : 0);
        $config['flags'] = $flags;
        $config['features'] = ['compact'=>$flags['compact_player'], 'sitewide'=>self::supportsSitewide()];
        $config['authenticated'] = $id > 0;
        $config['commonPlaybackKey'] = 'fireball-tape-room:session:' . ($id ?: 'guest');
        $config['acceptPrivateSources'] = $id > 0 && function_exists('check_admin') && check_admin();
        $config['preferencesUrl'] = $id ? base_href('/music/api/preferences') : '';
        if ($id) {
            $repository = new PlayerPreferences($id);
            $preferences = $repository->get();
            if ($repository->hasStored()) {
                $config['preferences'] = $preferences;
                $config['preferencesInitialized'] = true;
            }
        }
        return $config;
    }

    public static function supportsSitewide(): bool
    {
        return method_exists(\FBL\ThemeManager::class, 'supportsPersistentComponents');
    }

    public static function tags(bool $scripts = true): string
    {
        $html = '';
        foreach ($scripts ? self::SCRIPTS : self::STYLES as $file) {
            $url = self::url($file);
            if ($url === '') continue;
            $html .= $scripts ? '<script src="' . htmlSC($url) . '" defer data-tr-platform-asset></script>' : '<link rel="stylesheet" href="' . htmlSC($url) . '" data-tr-platform-asset>';
        }
        return $html;
    }
}
