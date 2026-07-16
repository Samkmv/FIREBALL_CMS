<?php

namespace Fireball\VpnManagerV2\Repositories;

final class SettingsRepository
{
    private const SLUG = 'vpn-manager-v2';
    private const CACHE_KEY = 'vpn-v2:settings:v1';

    public function read(array $defaults, bool $useCache = true): array
    {
        if ($useCache && function_exists('cache')) {
            $cached = cache()->get(self::CACHE_KEY);
            if (is_array($cached)) {
                return array_replace($defaults, $cached);
            }
        }

        $settings = array_replace($defaults, $this->stored());
        if ($useCache && function_exists('cache')) {
            $ttl = max(30, min(1800, (int)($settings['settings_cache_ttl_seconds'] ?? 300)));
            cache()->set(self::CACHE_KEY, $settings, $ttl);
        }

        return $settings;
    }

    public function stored(bool $forUpdate = false): array
    {
        $rows = db()->query(
            'SELECT setting_key, setting_value
             FROM plugin_settings
             WHERE plugin_slug = ?' . ($forUpdate ? ' FOR UPDATE' : ''),
            [self::SLUG]
        )->get() ?: [];
        $settings = [];
        foreach ($rows as $row) {
            $key = trim((string)($row['setting_key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $raw = (string)($row['setting_value'] ?? '');
            $decoded = json_decode($raw, true);
            $settings[$key] = json_last_error() === JSON_ERROR_NONE ? $decoded : $raw;
        }

        return $settings;
    }

    public function write(array $settings): void
    {
        $now = date('Y-m-d H:i:s');
        foreach ($settings as $key => $value) {
            if (!is_string($key) || preg_match('/^[a-z][a-z0-9_]{0,189}$/', $key) !== 1) {
                throw new \InvalidArgumentException('Invalid VPN Manager V2 setting key.');
            }
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded === false) {
                throw new \RuntimeException('VPN Manager V2 setting value cannot be encoded.');
            }
            db()->query(
                'INSERT INTO plugin_settings (plugin_slug, setting_key, setting_value, updated_at)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)',
                [self::SLUG, $key, $encoded, $now]
            );
        }
    }

    public function missing(array $defaults): array
    {
        $stored = $this->stored();

        return array_diff_key($defaults, $stored);
    }

    public function assertStorageReady(): void
    {
        $table = (int)db()->query(
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plugin_settings'"
        )->getColumn();
        $plugin = (int)db()->query(
            'SELECT COUNT(*) FROM plugins WHERE slug = ?',
            [self::SLUG]
        )->getColumn();
        if ($table !== 1 || $plugin !== 1) {
            throw new \RuntimeException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_settings_storage'));
        }
    }

    public function invalidateCache(): void
    {
        if (function_exists('cache')) {
            cache()->remove(self::CACHE_KEY);
        }
    }

    public function cacheKey(): string
    {
        return self::CACHE_KEY;
    }
}
