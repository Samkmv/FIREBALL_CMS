<?php

use App\Models\Analytics;
use App\Models\ChatMessage;
use App\Models\ContactRequest;
use App\Models\Post;
use App\Models\SiteSetting;
use App\Models\User;

$now = date('Y-m-d H:i:s');
$adminPassword = password_hash('admin', PASSWORD_DEFAULT);
$demoUserPassword = password_hash('user', PASSWORD_DEFAULT);

// Legacy shop tables used by the home controller.
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

// CMS tables from current models.
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
    "INSERT INTO user_roles (name, slug, is_system, created_at)
     VALUES
        (?, ?, ?, ?),
        (?, ?, ?, ?)",
    [
        'Admin',
        'admin',
        1,
        $now,
        'User',
        'user',
        1,
        $now,
    ]
);

db()->query(
    "INSERT INTO users (name, login, email, password, avatar, role, last_seen_at, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?),
            (?, ?, ?, ?, ?, ?, ?, ?)",
    [
        'Administrator',
        'admin',
        'admin@admin.com',
        $adminPassword,
        null,
        'admin',
        $now,
        $now,
        'Demo User',
        'user',
        'user@example.com',
        $demoUserPassword,
        null,
        'user',
        $now,
        $now,
    ]
);

$siteSettings = [
    'site_title' => 'FIREBALL CMS',
    'site_description' => 'Базовая установка FIREBALL CMS с демо-контентом для сайта и админки.',
    'seo_home_title' => 'FIREBALL CMS',
    'seo_default_title_suffix' => ' | FIREBALL CMS',
    'seo_meta_description' => 'Стартовый демо-сайт на FIREBALL CMS.',
    'seo_meta_keywords' => 'cms, fireball, demo',
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

foreach ($siteSettings as $key => $value) {
    db()->query(
        "INSERT INTO site_settings (setting_key, setting_value, updated_at)
         VALUES (?, ?, ?)",
        [$key, $value, $now]
    );
}

$shopCategories = [
    ['title' => 'Женская обувь', 'slug' => 'zhenskaya-obuv', 'parent_id' => 0, 'image' => 'assets/img/categories/1.png'],
    ['title' => 'Мужская обувь', 'slug' => 'muzhskaya-obuv', 'parent_id' => 0, 'image' => 'assets/img/categories/2.png'],
    ['title' => 'Женская одежда', 'slug' => 'zhenskaya-odezhda', 'parent_id' => 0, 'image' => 'assets/img/categories/3.png'],
    ['title' => 'Мужская одежда', 'slug' => 'muzhskaya-odezhda', 'parent_id' => 0, 'image' => 'assets/img/categories/4.png'],
];

foreach ($shopCategories as $category) {
    db()->query(
        "INSERT INTO categories (title, slug, parent_id, image)
         VALUES (?, ?, ?, ?)",
        [$category['title'], $category['slug'], $category['parent_id'], $category['image']]
    );
}

$products = [
    [
        'title' => 'Кожаные кеды Nova',
        'slug' => 'kozhanye-kedy-nova',
        'category_id' => 1,
        'price' => 8900,
        'old_price' => 9900,
        'excerpt' => 'Базовая демо-модель для главной страницы и поиска.',
        'content' => '<p>Универсальные кожаные кеды для демонстрации каталога FIREBALL CMS.</p>',
        'image' => 'assets/img/products/1.jpg',
        'gallery' => json_encode(['assets/img/products/1.jpg', 'assets/img/products/2.jpg'], JSON_UNESCAPED_SLASHES),
        'is_sale' => 1,
        'in_stock' => 1,
    ],
    [
        'title' => 'Замшевые ботинки Atlas',
        'slug' => 'zamhevye-botinki-atlas',
        'category_id' => 2,
        'price' => 12400,
        'old_price' => 0,
        'excerpt' => 'Демо-товар для мужского раздела магазина.',
        'content' => '<p>Плотные ботинки с лаконичным описанием для наполнения демо-каталога.</p>',
        'image' => 'assets/img/products/3.jpg',
        'gallery' => json_encode(['assets/img/products/3.jpg'], JSON_UNESCAPED_SLASHES),
        'is_sale' => 0,
        'in_stock' => 1,
    ],
    [
        'title' => 'Пальто Aurora',
        'slug' => 'palto-aurora',
        'category_id' => 3,
        'price' => 15900,
        'old_price' => 17900,
        'excerpt' => 'Демо-товар для блока акций на главной.',
        'content' => '<p>Тёплое пальто для демонстрации карточек товаров и акционных цен.</p>',
        'image' => 'assets/img/products/4.jpg',
        'gallery' => json_encode(['assets/img/products/4.jpg', 'assets/img/products/5.jpg'], JSON_UNESCAPED_SLASHES),
        'is_sale' => 1,
        'in_stock' => 1,
    ],
    [
        'title' => 'Худи Motion',
        'slug' => 'hoodie-motion',
        'category_id' => 4,
        'price' => 5200,
        'old_price' => 0,
        'excerpt' => 'Базовый демо-товар для мужской одежды.',
        'content' => '<p>Худи для наполнения демо-каталога и проверки поиска по товарам.</p>',
        'image' => 'assets/img/products/6.jpg',
        'gallery' => json_encode(['assets/img/products/6.jpg'], JSON_UNESCAPED_SLASHES),
        'is_sale' => 0,
        'in_stock' => 1,
    ],
];

foreach ($products as $product) {
    db()->query(
        "INSERT INTO products (title, slug, category_id, price, old_price, excerpt, content, image, gallery, is_sale, in_stock)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $product['title'],
            $product['slug'],
            $product['category_id'],
            $product['price'],
            $product['old_price'],
            $product['excerpt'],
            $product['content'],
            $product['image'],
            $product['gallery'],
            $product['is_sale'],
            $product['in_stock'],
        ]
    );
}

db()->query(
    "INSERT INTO post_categories (name, name_ru, name_en, slug, seo_title, seo_description, seo_keywords, seo_image, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?),
            (?, ?, ?, ?, ?, ?, ?, ?, ?)",
    [
        'Новости',
        'Новости',
        'News',
        'news',
        'Новости FIREBALL CMS',
        'Демо-рубрика новостей FIREBALL CMS.',
        'fireball cms, новости',
        '',
        $now,
        'Гайды',
        'Гайды',
        'Guides',
        'guides',
        'Гайды FIREBALL CMS',
        'Демо-рубрика для обучающих материалов.',
        'fireball cms, гайды',
        '',
        $now,
    ]
);

$newsCategoryId = (int)db()->getInsertId();
$guidesCategoryId = $newsCategoryId + 1;

db()->query(
    "INSERT INTO posts (
        title,
        slug,
        category_id,
        category,
        excerpt,
        content,
        image,
        hide_placeholder_image,
        show_on_home,
        seo_title,
        seo_description,
        seo_keywords,
        seo_image,
        author_id,
        author_name,
        author_role,
        views_count,
        published_at,
        is_published
     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?),
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
    [
        'Добро пожаловать!',
        'dobro-pozhalovat',
        $newsCategoryId,
        'Новости',
        'Первая запись после установки FIREBALL CMS.',
        '<p>Добро пожаловать в FIREBALL CMS.</p><p>Это стартовая запись, созданная сидером полной базы данных.</p>',
        null,
        0,
        1,
        'Добро пожаловать в FIREBALL CMS',
        'Стартовая запись FIREBALL CMS после полной инициализации базы.',
        'fireball cms, старт',
        '',
        1,
        'Administrator',
        'admin',
        0,
        $now,
        1,
        'Как устроена админка',
        'kak-ustroena-adminka',
        $guidesCategoryId,
        'Гайды',
        'Короткий обзор разделов административной панели.',
        '<p>В админке доступны посты, категории, пользователи, роли, чат, файловый менеджер и центр обновлений.</p>',
        null,
        0,
        1,
        'Как устроена админка FIREBALL CMS',
        'Краткий обзор разделов административной панели FIREBALL CMS.',
        'fireball cms, админка, гайд',
        '',
        1,
        'Administrator',
        'admin',
        0,
        $now,
        1,
    ]
);

db()->query(
    "INSERT INTO contact_requests (name, email, subject, message, is_viewed, created_at)
     VALUES (?, ?, ?, ?, ?, ?)",
    [
        'Иван Клиент',
        'client@example.com',
        'Демо-заявка',
        'Это тестовая заявка, созданная сидером полной базы данных.',
        0,
        $now,
    ]
);

db()->query(
    "INSERT INTO chat_messages (sender_id, receiver_id, message_ciphertext, attachment_path, attachment_name, attachment_type, attachment_size, is_read, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
    [
        2,
        1,
        \App\Services\ChatCipher::encrypt('Здравствуйте! Это демо-сообщение для проверки чата и уведомлений.'),
        null,
        null,
        null,
        null,
        0,
        $now,
    ]
);

db()->query(
    "INSERT INTO site_metrics (metric_key, metric_value, updated_at)
     VALUES (?, ?, ?),
            (?, ?, ?)",
    [
        'site_visits',
        0,
        $now,
        'page_views',
        0,
        $now,
    ]
);

return [
    'status' => 'ok',
    'admin_login' => 'admin',
    'admin_email' => 'admin@admin.com',
    'admin_password' => 'admin',
    'demo_user_login' => 'user',
    'demo_user_email' => 'user@example.com',
    'demo_user_password' => 'user',
    'category_slug' => 'news',
    'post_slug' => 'dobro-pozhalovat',
];
