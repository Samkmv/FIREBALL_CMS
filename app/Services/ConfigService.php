<?php

namespace App\Services;

use App\Models\SiteSetting;

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
        foreach ($this->settingKeys($key) as $settingKey) {
            $value = $this->site($settingKey, '');
            if ($value !== '') {
                return $value;
            }
        }

        $local = $this->localConfig();
        if (array_key_exists($key, $local)) {
            return $local[$key];
        }

        if (defined($key)) {
            return constant($key);
        }

        return $default;
    }

    public function site(string $key, string $default = ''): string
    {
        try {
            if (function_exists('db') && app()->db !== null) {
                $settings = (new SiteSetting())->all();
                if (array_key_exists($key, $settings) && (string)$settings[$key] !== '') {
                    return (string)$settings[$key];
                }
            }
        } catch (\Throwable) {
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

    public function mail(): array
    {
        $settings = defined('MAIL_SETTINGS') && is_array(MAIL_SETTINGS) ? MAIL_SETTINGS : [];
        foreach (array_keys($settings) as $key) {
            $databaseValue = $this->site('mail_' . $key, '');
            if ($databaseValue === '') {
                continue;
            }
            $settings[$key] = match ($key) {
                'auth', 'is_html' => filter_var($databaseValue, FILTER_VALIDATE_BOOLEAN),
                'port', 'debug' => (int)$databaseValue,
                default => $databaseValue,
            };
        }

        return $settings;
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
