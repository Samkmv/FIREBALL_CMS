<?php

use App\Models\Admin;
use App\Models\Analytics;
use App\Models\ChatMessage;
use App\Models\ContactRequest;
use App\Models\SiteSetting;
use App\Models\User;

$now = date('Y-m-d H:i:s');

$ensureShopTables = static function (): void {
    db()->query(
        "CREATE TABLE IF NOT EXISTS categories (
            id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) DEFAULT NULL,
            parent_id INT(10) UNSIGNED NOT NULL DEFAULT 0,
            image VARCHAR(255) DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    db()->query(
        "CREATE TABLE IF NOT EXISTS products (
            id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) DEFAULT NULL,
            category_id INT(10) UNSIGNED NOT NULL,
            price INT(10) UNSIGNED NOT NULL,
            old_price INT(10) UNSIGNED NOT NULL DEFAULT 0,
            excerpt VARCHAR(255) DEFAULT NULL,
            content TEXT NOT NULL,
            image VARCHAR(255) DEFAULT NULL,
            gallery TEXT DEFAULT NULL,
            is_sale TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
            in_stock TINYINT(3) UNSIGNED NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY category_id (category_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
};

$tableExists = static function (string $table): bool {
    return (int)db()->query(
        "SELECT COUNT(*)
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?",
        [$table]
    )->getColumn() > 0;
};

$truncateTables = static function () use ($tableExists): void {
    $tables = [
        'chat_audit_logs',
        'chat_messages',
        'contact_requests',
        'password_resets',
        'posts',
        'post_categories',
        'users',
        'user_roles',
        'site_settings',
        'products',
        'categories',
        'site_metrics',
    ];

    db()->query("SET FOREIGN_KEY_CHECKS = 0");
    foreach ($tables as $table) {
        if ($tableExists($table)) {
            db()->query("TRUNCATE TABLE {$table}");
        }
    }
    db()->query("SET FOREIGN_KEY_CHECKS = 1");
};

$insertRoles = static function (string $now): void {
    db()->query(
        "INSERT INTO user_roles (id, name, slug, is_system, created_at)
         VALUES
            (?, ?, ?, ?, ?),
            (?, ?, ?, ?, ?),
            (?, ?, ?, ?, ?),
            (?, ?, ?, ?, ?)",
        [
            1, 'Creator', 'creator', 1, $now,
            2, 'Admin', 'admin', 1, $now,
            3, 'Moderator', 'moderator', 1, $now,
            4, 'User', 'user', 1, $now,
        ]
    );
};

$insertCreator = static function (string $now): void {
    db()->query(
        "INSERT INTO users (name, login, email, password, avatar, role, last_seen_at, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        [
            'Creator',
            'creator',
            'creator@admin.com',
            password_hash('creator', PASSWORD_DEFAULT),
            null,
            'creator',
            null,
            $now,
        ]
    );
};

$insertSiteSettings = static function (string $now): void {
    $settings = [
        'site_title' => 'FIREBALL CMS',
        'site_description' => 'Чистая установка FIREBALL CMS после сброса базы.',
        'seo_home_title' => 'FIREBALL CMS',
        'seo_default_title_suffix' => ' | FIREBALL CMS',
        'seo_meta_description' => 'Чистая установка FIREBALL CMS после сброса базы.',
        'seo_meta_keywords' => 'fireball cms',
        'seo_meta_author' => 'FIREBALL CMS',
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

    foreach ($settings as $key => $value) {
        db()->query(
            "INSERT INTO site_settings (setting_key, setting_value, updated_at)
             VALUES (?, ?, ?)",
            [$key, $value, $now]
        );
    }
};

$insertMetrics = static function (string $now): void {
    db()->query(
        "INSERT INTO site_metrics (metric_key, metric_value, updated_at)
         VALUES (?, ?, ?),
                (?, ?, ?)",
        [
            'site_visits', 0, $now,
            'page_views', 0, $now,
        ]
    );
};

$ensureShopTables();
(new User())->ensureUsersTableExists();
(new SiteSetting())->ensureTableExists();
(new ContactRequest())->ensureTableExists();
(new ChatMessage())->ensureTableExists();
(new Admin())->ensureSchema();
(new Analytics())->getStats();

$truncateTables();
$insertRoles($now);
$insertCreator($now);
$insertSiteSettings($now);
$insertMetrics($now);

return [
    'status' => 'ok',
    'message' => 'Database cleared. Creator account created.',
    'account' => [
        'role' => 'creator',
        'login' => 'creator',
        'email' => 'creator@admin.com',
        'password' => 'creator',
    ],
];
