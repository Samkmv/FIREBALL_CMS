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
        $lang = app()->get('lang');
        $code = is_array($lang) ? Localization::normalizeLocale((string)($lang['code'] ?? '')) : '';
        $code = $code !== '' ? $code : Localization::currentLocale();

        $layoutData = [];
        $viewData = [];
        $langFolder = '';
        $langFile = '';

        if (is_array($route)) {
            $controller_segments = explode('\\', $route[0]);
            $controller_name = array_pop($controller_segments);
            $langFolder = strtolower(str_replace('Controller', '', $controller_name));
            $langFile = (string)$route[1];
        }

        $loadedFiles = [];
        foreach (array_reverse(Localization::localeCandidates($code)) as $candidate) {
            $langLayout = APP . "/Languages/$candidate.php";
            if (file_exists($langLayout)) {
                $data = require $langLayout;
                if (is_array($data)) {
                    $layoutData = array_merge($layoutData, $data);
                    $loadedFiles[] = $langLayout;
                }
            }

            if ($langFolder !== '' && $langFile !== '') {
                $langView = APP . "/Languages/$candidate/$langFolder/$langFile.php";
                if (file_exists($langView)) {
                    $data = require $langView;
                    if (is_array($data)) {
                        $viewData = array_merge($viewData, $data);
                        $loadedFiles[] = $langView;
                    }
                }
            }
        }

        self::$lang_layout = is_array($layoutData) ? $layoutData : [];
        self::$lang_view = is_array($viewData) ? $viewData : [];
        self::$lang_data = array_merge(self::$lang_layout, self::$lang_view);
        self::loadRegisteredPluginLanguages();
        self::mergePluginTranslations();
        Localization::debug('translation_load', [
            'locale' => $code,
            'files' => $loadedFiles,
            'keys' => count(self::$lang_data),
        ]);
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
        $found = array_key_exists($key, self::$lang_data);
        Localization::debug('translation_choice', [
            'key' => (string)$key,
            'locale' => Localization::currentLocale(),
            'source' => $found ? 'loaded' : 'fallback_key',
        ]);

        return $found ? self::$lang_data[$key] : $key;
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

        $translations = [];
        foreach ($files as $file) {
            try {
                $data = require $file;
                if (is_array($data)) {
                    $translations = array_merge($translations, $data);
                }
            } catch (\Throwable $exception) {
                error_log('Plugin language file error [' . $slug . ']: ' . $exception->getMessage());
            }
        }

        self::$pluginLangData[$slug] = $translations;
    }

    protected static function resolvePluginLanguageFiles(string $langDir, array $fallbacks): array
    {
        $lang = app()->get('lang');
        $code = is_array($lang) ? (string)($lang['code'] ?? '') : '';
        $candidates = array_reverse(Localization::localeCandidates($code, $fallbacks));
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
