INSERT INTO post_categories (name, name_ru, name_en, slug, seo_title, seo_description, seo_keywords, seo_image, created_at)
VALUES ('Демо', 'Демо', 'Demo', 'demo', 'Демо FIREBALL CMS', 'Минимальная демонстрационная категория FIREBALL CMS.', 'fireball cms, demo', '', :now)
ON DUPLICATE KEY UPDATE name = VALUES(name), name_ru = VALUES(name_ru), name_en = VALUES(name_en);

INSERT INTO posts (title, slug, category, category_id, excerpt, content, image, seo_title, seo_description, seo_keywords, seo_image, hide_placeholder_image, show_on_home, priority, author_id, author_name, author_role, views_count, published_at, is_published)
SELECT 'Добро пожаловать в FIREBALL CMS', 'welcome-to-fireball-cms', 'Демо', id, 'Минимальная демонстрационная запись для проверки сайта после сброса.', '<p>Это минимальный демо-контент FIREBALL CMS. Отредактируйте или удалите запись перед запуском сайта.</p>', '', 'Добро пожаловать в FIREBALL CMS', 'Минимальная демонстрационная запись для проверки сайта после сброса.', 'fireball cms, demo, welcome', '', 0, 1, 10, 1, 'Creator', 'creator', 0, :now, 1
FROM post_categories WHERE slug = 'demo'
ON DUPLICATE KEY UPDATE title = VALUES(title), category_id = VALUES(category_id);
