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

        $defaults = $this->defaults();
        if ($defaults !== []) {
            $placeholders = [];
            $params = [];
            $now = date('Y-m-d H:i:s');

            foreach ($defaults as $key => $value) {
                $placeholders[] = '(?, ?, ?)';
                array_push($params, $key, $value, $now);
            }

            db()->query(
                "INSERT IGNORE INTO {$this->table} (setting_key, setting_value, updated_at) VALUES "
                . implode(', ', $placeholders),
                $params
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

        $cached = cache()->get('site_settings:all');
        if (is_array($cached)) {
            return self::$cache = $cached;
        }

        $this->ensureTableExists();
        $rows = db()->query("SELECT setting_key, setting_value FROM {$this->table}")->get() ?: [];

        self::$cache = [];
        foreach ($rows as $row) {
            self::$cache[$row['setting_key']] = (string)($row['setting_value'] ?? '');
        }
        cache()->set('site_settings:all', self::$cache, 600);

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

        self::clearPublicCache();
    }

    public static function clearPublicCache(): void
    {
        self::$cache = null;
        cache()->remove('site_settings:all');

        if (class_exists(\App\Widgets\Menu\Menu::class)) {
            \App\Widgets\Menu\Menu::clearCache();
        }
    }

    /**
     * Возвращает предустановленный набор настроек сайта.
     */
    protected function defaults(): array
    {
        return [
            'site_title' => SITE_NAME,
            'site_description' => '',
            'site_favicon' => '',
            'site_url' => PATH,
            'default_locale' => DEFAULT_LOCALE,
            'timezone' => APP_TIMEZONE,
            'admin_session_lifetime_hours' => '12',
            'active_theme' => 'default',
            'social_links' => '[]',
            'social_telegram' => '',
            'social_instagram' => '',
            'social_facebook' => '',
            'social_youtube' => '',
            'contacts_page_heading' => '',
            'contacts_page_subheading' => '',
            'contacts_page_image' => '',
            'contacts_phone_customers' => '',
            'contacts_phone_support' => '',
            'contacts_phone_franchise' => '',
            'contacts_email_customers' => '',
            'contacts_email_support' => '',
            'contacts_email_franchise' => '',
            'contacts_location_city' => '',
            'contacts_location_address' => '',
            'contacts_hours_weekdays' => '',
            'contacts_hours_weekends' => '',
            'contacts_support_title' => '',
            'contacts_support_text' => '',
            'contacts_form_subjects' => '[]',
            'contact_subjects_migrated' => '0',
            'support_public_enabled' => '1',
            'support_notification_email' => '',
            'support_autoreply_enabled' => '0',
            'support_autoreply_subject' => '',
            'support_autoreply_message' => '',
            'support_spam_protection' => '1',
            'support_notify_new_requests' => '1',
            'support_notify_status_changes' => '0',
            'seo_home_title' => '',
            'seo_default_title_suffix' => '',
            'seo_meta_description' => '',
            'seo_meta_keywords' => '',
            'seo_meta_author' => '',
            'seo_robots' => 'index,follow',
            'seo_og_image' => '',
            'seo_twitter_card' => 'summary_large_image',
            'homepage_type' => 'default',
            'homepage_page_id' => '',
            'posts_per_page' => '10',
            'cookie_enabled' => '0',
            'cookie_message' => 'Мы используем файлы cookie для корректной работы сайта. Продолжая пользоваться сайтом, вы соглашаетесь с их использованием.',
            'cookie_button_text' => 'Принять',
            'cookie_policy_page_id' => '0',
            'cookie_policy_use_on_registration' => '0',
            'cookie_position' => 'bottom_right',
            'cookie_style' => 'card',
            'cookie_expiration_days' => '365',
            'cookie_consent_categories' => '["necessary"]',
            'updater_github_repository' => '',
            'updater_github_branch' => 'main',
            'updater_github_token' => '',
            'update_channel' => 'stable',
            'updater_last_check_payload' => '',
            'updater_last_checked_at' => '',
            'updater_last_updated_at' => '',
            'updater_last_installed_commit' => '',
            'updater_rollback_commit' => '',
            'cms_version' => (string)((require CONFIG . '/version.php')['version'] ?? ''),
            'mail_enabled' => '0',
            'mail_host' => '',
            'mail_auth' => '',
            'mail_username' => '',
            'mail_password' => '',
            'mail_encryption' => 'none',
            'mail_secure' => '',
            'mail_port' => '',
            'mail_from_email' => '',
            'mail_from_name' => '',
            'mail_reply_to_email' => '',
            'mail_is_html' => '',
            'mail_charset' => '',
            'mail_debug' => '',
            'allow_email_password_reset' => '1',
            'allow_2fa_email_recovery' => '1',
            'allow_admin_reset_user_2fa' => '1',
            'require_admin_password_for_2fa_reset' => '1',
            'require_admin_2fa_for_2fa_reset' => '1',
            'notify_user_after_admin_2fa_reset' => '1',
        ];
    }

}
