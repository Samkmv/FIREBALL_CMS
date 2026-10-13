<?php
declare(strict_types=1);
namespace Fireball\ReelPlayer\Services;

/** Turning off the platform leaves the existing personal player untouched. */
final class PlatformFeatures
{
    public const FLAGS = ['public_catalog', 'discovery', 'compact_player', 'embed', 'monetization'];

    public static function enabled(string $flag): bool
    {
        if (!in_array($flag, self::FLAGS, true) || !function_exists('plugin_setting')) return false;
        $value = plugin_setting('reel-player', $flag, false);
        return $value === true || $value === 1 || $value === '1';
    }

    public static function all(): array
    {
        $flags = [];
        foreach (self::FLAGS as $flag) $flags[$flag] = self::enabled($flag);
        return $flags;
    }

    /** A checkout may arrive before the CMS has run the additive migrations. */
    public static function schemaReady(bool $withEmbed = false): bool
    {
        if (!function_exists('db')) return false;
        static $ready = [];
        $key = $withEmbed ? 'embed' : 'catalog';
        if (array_key_exists($key, $ready)) return $ready[$key];
        try {
            $sql = 'SELECT td.track_id FROM reel_track_distribution td LEFT JOIN reel_album_distribution ad ON 1=0 LEFT JOIN reel_track_distribution_plans tp ON 1=0 LEFT JOIN reel_album_distribution_plans ap ON 1=0';
            if ($withEmbed) $sql .= ' LEFT JOIN reel_embed_settings es ON 1=0 LEFT JOIN reel_embed_domains ed ON 1=0';
            db()->query($sql . ' WHERE 1=0')->get();
            return $ready[$key] = true;
        } catch (\PDOException $error) {
            if ((string)$error->getCode() === '42S02' || ((string)$error->getCode() === 'HY000' && str_contains($error->getMessage(), 'no such table:'))) return $ready[$key] = false;
            throw $error;
        }
    }

    public static function save(array $input): void
    {
        $values = [];
        foreach (self::FLAGS as $flag) {
            $value = $input[$flag] ?? false;
            if (!in_array($value, [true, false, 1, 0, '1', '0', ''], true)) throw new \InvalidArgumentException('Некорректный переключатель платформы.');
            $values[$flag] = in_array($value, [true, 1, '1'], true);
        }
        foreach ($values as $flag => $value) plugin_setting_set('reel-player', $flag, $value);
    }
}
