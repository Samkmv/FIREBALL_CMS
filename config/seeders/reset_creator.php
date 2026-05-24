<?php

use App\Models\Analytics;
use App\Models\ChatMessage;
use App\Models\ContactRequest;
use App\Models\Post;
use App\Models\SiteSetting;
use App\Models\User;

$now = date('Y-m-d H:i:s');
$creatorPasswordPlain = 'creator';
$creatorPasswordHash = password_hash($creatorPasswordPlain, PASSWORD_DEFAULT);

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

(new User())->ensureUsersTableExists();
(new SiteSetting())->ensureTableExists();
(new ContactRequest())->ensureTableExists();
(new ChatMessage())->ensureTableExists();
(new Post())->getNavigationCategories();
(new Analytics())->getStats();

db()->query("SET FOREIGN_KEY_CHECKS = 0");
db()->query("TRUNCATE TABLE chat_messages");
db()->query("TRUNCATE TABLE contact_requests");
db()->query("TRUNCATE TABLE password_resets");
db()->query("TRUNCATE TABLE posts");
db()->query("TRUNCATE TABLE post_categories");
db()->query("TRUNCATE TABLE users");
db()->query("TRUNCATE TABLE user_roles");
db()->query("TRUNCATE TABLE site_settings");
db()->query("TRUNCATE TABLE products");
db()->query("TRUNCATE TABLE categories");
db()->query("TRUNCATE TABLE site_metrics");
db()->query("SET FOREIGN_KEY_CHECKS = 1");

db()->query(
    "INSERT INTO user_roles (id, name, slug, is_system, created_at)
     VALUES
        (?, ?, ?, ?, ?),
        (?, ?, ?, ?, ?),
        (?, ?, ?, ?, ?)",
    [
        1, 'Creator', 'creator', 1, $now,
        2, 'Admin', 'admin', 1, $now,
        3, 'User', 'user', 1, $now,
    ]
);

db()->query(
    "INSERT INTO users (name, login, email, password, avatar, role, last_seen_at, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
    [
        'Creator',
        'creator',
        'creator@admin.com',
        $creatorPasswordHash,
        null,
        'creator',
        $now,
        $now,
    ]
);

db()->query(
    "INSERT INTO site_settings (setting_key, setting_value, updated_at)
     VALUES (?, ?, ?),
            (?, ?, ?)",
    [
        'site_title', 'FIREBALL CMS', $now,
        'site_description', 'Чистая установка FIREBALL CMS после сброса базы.', $now,
    ]
);

db()->query(
    "INSERT INTO site_metrics (metric_key, metric_value, updated_at)
     VALUES (?, ?, ?),
            (?, ?, ?)",
    [
        'site_visits', 0, $now,
        'page_views', 0, $now,
    ]
);

return [
    'status' => 'ok',
    'message' => 'Database cleared. Creator user created.',
    'creator_login' => 'creator',
    'creator_email' => 'creator@admin.com',
    'creator_password' => $creatorPasswordPlain,
];
