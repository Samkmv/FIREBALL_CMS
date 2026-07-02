<?php

namespace FBL;

/**
 * Загружает языковые файлы макета и конкретного представления.
 */
class Language
{

    public static array $lang_data = [];
    protected static array $lang_layout = [];
    protected static array $lang_view = [];
    protected static array $pluginLangPaths = [];
    protected static array $pluginLangData = [];

    /**
     * Подгружает переводы для текущего языка и выбранного маршрута.
     */
    public static function load($route)
    {
        $code = app()->get('lang')['code'];

        $lang_layout = APP . "/Languages/$code.php";
        $lang_view = '';
        $layoutData = [];
        $viewData = [];

        if (is_array($route)) {
            $controller_segments = explode('\\', $route[0]);
            $controller_name = array_pop($controller_segments);
            $lang_folder = strtolower(str_replace('Controller', '', $controller_name));
            $lang_file = $route[1];
            $lang_view = APP . "/Languages/$code/$lang_folder/$lang_file.php";
        }

        if (file_exists($lang_layout)) {
            $layoutData = require $lang_layout;
        }

        if ($lang_view && file_exists($lang_view)) {
            $viewData = require $lang_view;
        }

        self::$lang_layout = is_array($layoutData) ? $layoutData : [];
        self::$lang_view = is_array($viewData) ? $viewData : [];
        self::$lang_data = array_merge(self::$lang_layout, self::$lang_view);
        self::loadRegisteredPluginLanguages();
        self::mergePluginTranslations();
    }

    public static function registerPluginLanguage(string $slug, string $langDir, array $fallbacks = ['ru', 'en']): void
    {
        $slug = trim($slug);
        if ($slug === '' || !is_dir($langDir)) {
            return;
        }

        self::$pluginLangPaths[$slug] = [
            'dir' => rtrim($langDir, '/'),
            'fallbacks' => $fallbacks,
        ];

        self::loadRegisteredPluginLanguage($slug);
        self::mergePluginTranslations();
    }

    /**
     * Возвращает перевод по ключу или сам ключ, если перевод не найден.
     */
    public static function get($key)
    {
        return self::$lang_data[$key] ?? $key;
    }

    protected static function loadRegisteredPluginLanguages(): void
    {
        foreach (array_keys(self::$pluginLangPaths) as $slug) {
            self::loadRegisteredPluginLanguage($slug);
        }
    }

    protected static function loadRegisteredPluginLanguage(string $slug): void
    {
        $config = self::$pluginLangPaths[$slug] ?? null;
        if (!$config) {
            return;
        }

        $files = self::resolvePluginLanguageFiles((string)$config['dir'], (array)$config['fallbacks']);
        if (!$files) {
            self::$pluginLangData[$slug] = [];
            return;
        }

        foreach ($files as $file) {
            try {
                $data = require $file;
                if (is_array($data)) {
                    self::$pluginLangData[$slug] = $data;
                    return;
                }
            } catch (\Throwable $exception) {
                error_log('Plugin language file error [' . $slug . ']: ' . $exception->getMessage());
            }
        }

        self::$pluginLangData[$slug] = [];
    }

    protected static function resolvePluginLanguageFiles(string $langDir, array $fallbacks): array
    {
        $lang = app()->get('lang');
        $code = is_array($lang) ? (string)($lang['code'] ?? '') : '';
        $candidates = array_values(array_unique(array_filter(array_merge([$code], $fallbacks))));
        $files = [];

        foreach ($candidates as $candidate) {
            $file = $langDir . '/' . basename($candidate) . '.php';
            if (is_file($file)) {
                $files[] = $file;
            }
        }

        return $files;
    }

    protected static function mergePluginTranslations(): void
    {
        foreach (self::$pluginLangData as $translations) {
            foreach ($translations as $key => $value) {
                if (!array_key_exists($key, self::$lang_data)) {
                    self::$lang_data[$key] = $value;
                }
            }
        }
    }

}
