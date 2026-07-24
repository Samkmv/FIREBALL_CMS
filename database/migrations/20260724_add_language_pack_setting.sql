INSERT INTO site_settings (setting_key, setting_value, updated_at)
SELECT
    'language_pack',
    CASE
        WHEN EXISTS (
            SELECT 1
            FROM site_settings
            WHERE setting_key IN ('site_title', 'seo_home_title', 'pwa_app_name', 'pwa_short_name')
              AND LOWER(setting_value) LIKE '%maxipapa%'
        ) THEN 'maxipapa'
        ELSE 'cms'
    END,
    NOW()
ON DUPLICATE KEY UPDATE
    setting_value = IF(setting_value = '', VALUES(setting_value), setting_value);
