<?php

namespace App\Models;

/**
 * Хранит и отдаёт настройки сайта с кэшированием в памяти процесса.
 */
class SiteSetting
{

    protected string $table = 'site_settings';
    protected static ?array $cache = null;
    protected static bool $schemaReady = false;

    /**
     * Создаёт таблицу настроек и заполняет её значениями по умолчанию.
     */
    public function ensureTableExists(): void
    {
        if (self::$schemaReady) {
            return;
        }

        db()->query(
            "CREATE TABLE IF NOT EXISTS {$this->table} (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                setting_key VARCHAR(100) NOT NULL,
                setting_value TEXT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY setting_key (setting_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        foreach ($this->defaults() as $key => $value) {
            db()->query(
                "INSERT IGNORE INTO {$this->table} (setting_key, setting_value, updated_at) VALUES (?, ?, ?)",
                [$key, $value, date('Y-m-d H:i:s')]
            );
        }

        self::$schemaReady = true;
    }

    /**
     * Возвращает все настройки сайта в виде ассоциативного массива.
     */
    public function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $this->ensureTableExists();
        $rows = db()->query("SELECT setting_key, setting_value FROM {$this->table}")->get() ?: [];

        self::$cache = [];
        foreach ($rows as $row) {
            self::$cache[$row['setting_key']] = (string)($row['setting_value'] ?? '');
        }

        return self::$cache;
    }

    /**
     * Возвращает значение одной настройки или значение по умолчанию.
     */
    public function get(string $key, string $default = ''): string
    {
        $settings = $this->all();

        if (!array_key_exists($key, $settings)) {
            return $default;
        }

        $value = (string)$settings[$key];

        return $value !== '' ? $value : $default;
    }

    /**
     * Сохраняет набор настроек и сбрасывает внутренний кэш.
     */
    public function setMany(array $settings): void
    {
        $this->ensureTableExists();

        foreach ($settings as $key => $value) {
            db()->query(
                "INSERT INTO {$this->table} (setting_key, setting_value, updated_at)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)",
                [
                    $key,
                    trim((string)$value),
                    date('Y-m-d H:i:s'),
                ]
            );
        }

        self::$cache = null;
    }

    /**
     * Возвращает предустановленный набор настроек сайта.
     */
    protected function defaults(): array
    {
        return [
            'site_title' => SITE_NAME,
            'site_description' => '',
            'social_telegram' => '',
            'social_instagram' => '',
            'social_facebook' => '',
            'social_youtube' => '',
            'seo_home_title' => '',
            'seo_default_title_suffix' => '',
            'seo_meta_description' => '',
            'seo_meta_keywords' => '',
            'seo_meta_author' => '',
            'seo_robots' => 'index,follow',
            'seo_og_image' => '',
            'seo_twitter_card' => 'summary_large_image',
            'updater_github_repository' => '',
            'updater_github_branch' => 'main',
            'updater_github_token' => '',
            'updater_last_check_payload' => '',
            'updater_last_checked_at' => '',
            'updater_last_updated_at' => '',
        ];
    }

}
