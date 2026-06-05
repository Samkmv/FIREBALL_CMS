INSERT INTO post_categories (name, name_ru, name_en, slug, seo_title, seo_description, seo_keywords, seo_image, created_at)
VALUES ('Москва', 'Москва', 'Moscow', 'moscow', 'Записи FIREBALL CMS в Москве', 'Демо-категория записей FIREBALL CMS.', 'fireball cms, demo', '', :now)
ON DUPLICATE KEY UPDATE name = VALUES(name), name_ru = VALUES(name_ru), name_en = VALUES(name_en);

INSERT INTO posts (title, slug, category, category_id, excerpt, content, image, seo_title, seo_description, seo_keywords, seo_image, hide_placeholder_image, show_on_home, priority, author_id, author_name, author_role, views_count, published_at, is_published, created_at)
SELECT 'Демо-запись FIREBALL CMS', 'demo-post', 'Москва', id, 'Одна демонстрационная запись для проверки блога.', '<p>Это демонстрационная запись FIREBALL CMS.</p>', '', 'Демо-запись FIREBALL CMS', 'Одна демонстрационная запись FIREBALL CMS.', 'fireball cms, demo, post', '', 0, 1, 10, 1, 'Creator', 'creator', 0, :now, 1, :now
FROM post_categories WHERE slug = 'moscow'
ON DUPLICATE KEY UPDATE title = VALUES(title), category_id = VALUES(category_id);
