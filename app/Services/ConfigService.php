<?php

namespace App\Services;

final class ConfigService
{
    private const SETTING_KEY_MAP = [
        'SITE_NAME' => ['site_name', 'site_title'],
        'APP_URL' => ['app_url', 'site_url'],
        'PATH' => ['app_url', 'site_url'],
        'DEFAULT_LOCALE' => ['default_locale', 'locale'],
        'TIMEZONE' => ['timezone', 'app_timezone'],
        'APP_TIMEZONE' => ['timezone', 'app_timezone'],
    ];

    public function get(string $key, mixed $default = null): mixed
    {
        try {
            if (function_exists('site_setting')) {
                foreach ($this->settingKeys($key) as $settingKey) {
                    $value = site_setting($settingKey, '');
                    if ($value !== '') {
                        return $value;
                    }
                }
            }
        } catch (\Throwable) {
        }

        if (defined($key)) {
            return constant($key);
        }

        $local = $this->localConfig();
        if (array_key_exists($key, $local)) {
            return $local[$key];
        }

        return $default;
    }

    private function settingKeys(string $key): array
    {
        $normalized = strtoupper($key);
        $keys = self::SETTING_KEY_MAP[$normalized] ?? [];
        $keys[] = strtolower($key);

        return array_values(array_unique($keys));
    }

    public function database(): array
    {
        return defined('DB_SETTINGS') && is_array(DB_SETTINGS) ? DB_SETTINGS : [];
    }

    public function localConfig(): array
    {
        $path = CONFIG . '/config.local.php';
        if (!is_file($path)) {
            return [];
        }

        $config = require $path;

        return is_array($config) ? $config : [];
    }
}
