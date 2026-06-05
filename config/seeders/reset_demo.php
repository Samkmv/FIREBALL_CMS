<?php

use App\Services\DatabaseMaintenanceService;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$service = new DatabaseMaintenanceService();
$result = $service->run('full_reset', [
    'id' => 0,
    'name' => 'CLI',
], '127.0.0.1');

if (($result['status'] ?? 'error') !== 'success') {
    return $result;
}

$now = date('Y-m-d H:i:s');
db()->query(
    "INSERT INTO post_categories (name, name_ru, name_en, slug, seo_title, seo_description, seo_keywords, seo_image, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
    [
        'Москва',
        'Москва',
        'Moscow',
        'moscow',
        'Записи FIREBALL CMS в Москве',
        'Демо-категория записей FIREBALL CMS.',
        'fireball cms, demo',
        '',
        $now,
    ]
);

$categoryId = (int)db()->getInsertId();
db()->query(
    "INSERT INTO posts
     (title, slug, category, category_id, excerpt, content, image, seo_title, seo_description, seo_keywords, seo_image, hide_placeholder_image, show_on_home, priority, author_id, author_name, author_role, views_count, published_at, is_published)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
    [
        'Демо-запись FIREBALL CMS',
        'demo-post',
        'Москва',
        $categoryId,
        'Одна демонстрационная запись для проверки блога.',
        '<p>Это единственная демо-запись после сброса базы. Ее можно редактировать, скрывать блоки и проверять публикацию.</p>',
        '',
        'Демо-запись FIREBALL CMS',
        'Одна демонстрационная запись FIREBALL CMS.',
        'fireball cms, demo, post',
        '',
        0,
        1,
        10,
        1,
        'Creator',
        'creator',
        0,
        $now,
        1,
    ]
);

return array_merge($result, [
    'demo' => [
        'category_slug' => 'moscow',
        'post_slug' => 'demo-post',
    ],
]);
