<?php

namespace App\Models;

use FBL\Model;
use FBL\Pagination;

/**
 * Отвечает за публичную работу с постами блога, категориями и SEO-подготовкой данных.
 */
class Post extends Model
{

    protected string $table = 'posts';
    public bool $timestamps = false;
    protected string $categoriesTable = 'post_categories';
    protected bool $schemaReady = false;

    /**
     * Возвращает опубликованные посты с пагинацией и фильтром по категории.
     */
    public function getPaginatedPosts(?string $category = null, int $perPage = PAGINATION_SETTINGS['perPage']): array
    {
        $this->ensureSchema();
        $category = $this->normalizeCategoryFilter($category);
        $total = $this->countPublished($category);
        $pagination = new Pagination($total, $perPage);
        $offset = $pagination->getOffset();
        $limit = (int)$perPage;
        $offset = (int)$offset;
        [$whereSql, $params] = $this->buildPublishedWhereClause($category);
        $categoryNameSql = $this->categoryNameSql('c');
        $posts = db()->query(
            "SELECT p.id, p.title, p.slug, p.excerpt, p.content, p.image, p.seo_title, p.seo_description, p.seo_keywords, p.seo_image, p.hide_placeholder_image, p.author_name, p.author_role, p.published_at, p.views_count,
                    COALESCE({$categoryNameSql}, p.category, 'General') AS category,
                    c.slug AS category_slug,
                    c.seo_title AS category_seo_title,
                    c.seo_description AS category_seo_description,
                    c.seo_keywords AS category_seo_keywords,
                    c.seo_image AS category_seo_image
             FROM {$this->table} p
             LEFT JOIN {$this->categoriesTable} c ON c.id = p.category_id
             {$whereSql}
             ORDER BY p.published_at DESC, p.id DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        )->get() ?: [];

        return [
            'posts' => $this->normalizePosts($posts),
            'total' => $total,
            'pagination' => $pagination,
            'current_category' => $category,
            'current_category_label' => $category ? $this->getCategoryNameBySlug($category) : null,
            'current_category_meta' => $category ? $this->getCategoryMetaBySlug($category) : null,
        ];
    }

    /**
     * Ищет один опубликованный пост по slug.
     */
    public function findPublishedBySlug(string $slug): array|false
    {
        $this->ensureSchema();
        $categoryNameSql = $this->categoryNameSql('c');
        $post = db()->query(
            "SELECT p.id, p.title, p.slug, p.excerpt, p.content, p.image, p.seo_title, p.seo_description, p.seo_keywords, p.seo_image, p.hide_placeholder_image, p.author_name, p.author_role, p.published_at, p.views_count,
                    COALESCE({$categoryNameSql}, p.category, 'General') AS category,
                    c.slug AS category_slug,
                    c.seo_title AS category_seo_title,
                    c.seo_description AS category_seo_description,
                    c.seo_keywords AS category_seo_keywords,
                    c.seo_image AS category_seo_image
             FROM {$this->table} p
             LEFT JOIN {$this->categoriesTable} c ON c.id = p.category_id
             WHERE p.slug = ? AND p.is_published = 1
             LIMIT 1",
            [$slug]
        )->getOne();

        if (!$post) {
            return false;
        }

        return $this->normalizePost($post);
    }

    /**
     * Увеличивает счётчик просмотров опубликованного поста.
     */
    public function incrementViews(int $id): void
    {
        $this->ensureSchema();
        db()->query(
            "UPDATE {$this->table}
             SET views_count = views_count + 1
             WHERE id = ? AND is_published = 1",
            [$id]
        );
    }

    /**
     * Возвращает данные для сайдбара блога: категории и популярные посты.
     */
    public function getSidebarData(?string $excludeSlug = null): array
    {
        return [
            'categories' => $this->getCategories(),
            'trending_posts' => $this->getTrendingPosts(3, $excludeSlug),
        ];
    }

    /**
     * Возвращает посты, отмеченные для показа на главной странице.
     */
    public function getHomeFeaturedPosts(int $limit = 8): array
    {
        $this->ensureSchema();
        $limit = max(1, (int)$limit);
        $categoryNameSql = $this->categoryNameSql('c');
        $posts = db()->query(
            "SELECT p.id, p.title, p.slug, p.excerpt, p.content, p.image, p.seo_title, p.seo_description, p.seo_keywords, p.seo_image, p.hide_placeholder_image, p.show_on_home, p.author_name, p.author_role, p.published_at, p.views_count,
                    COALESCE({$categoryNameSql}, p.category, 'General') AS category,
                    c.slug AS category_slug,
                    c.seo_title AS category_seo_title,
                    c.seo_description AS category_seo_description,
                    c.seo_keywords AS category_seo_keywords,
                    c.seo_image AS category_seo_image
             FROM {$this->table} p
             LEFT JOIN {$this->categoriesTable} c ON c.id = p.category_id
             WHERE p.is_published = 1
               AND p.show_on_home = 1
             ORDER BY p.published_at DESC, p.id DESC
             LIMIT {$limit}"
        )->get() ?: [];

        return $this->normalizePosts($posts);
    }

    /**
     * Возвращает категории для навигации по блогу.
     */
    public function getNavigationCategories(): array
    {
        $this->ensureSchema();
        $categoryNameSql = $this->categoryNameSql('c');

        $categories = db()->query(
            "SELECT c.id, {$categoryNameSql} AS name, c.name_ru, c.name_en, c.slug, COUNT(p.id) AS total
             FROM {$this->categoriesTable} c
             LEFT JOIN {$this->table} p ON p.category_id = c.id AND p.is_published = 1
             GROUP BY c.id, c.name, c.name_ru, c.name_en, c.slug
             ORDER BY c.name ASC"
        )->get() ?: [];

        return array_map(static function (array $category): array {
            return [
                'id' => (int)$category['id'],
                'name' => (string)$category['name'],
                'slug' => (string)$category['slug'],
                'label' => (string)$category['name'],
                'total' => (int)$category['total'],
            ];
        }, $categories);
    }

    /**
     * Выполняет быстрый поиск по опубликованным постам без пагинации.
     */
    public function searchPublished(string $query, int $limit = 8): array
    {
        $this->ensureSchema();
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        $like = '%' . $query . '%';
        $limit = (int)$limit;
        $categoryNameSql = $this->categoryNameSql('c');
        $posts = db()->query(
            "SELECT p.id, p.title, p.slug, p.excerpt, p.content, p.image, p.seo_title, p.seo_description, p.seo_keywords, p.seo_image, p.hide_placeholder_image, p.author_name, p.author_role, p.published_at, p.views_count,
                    COALESCE({$categoryNameSql}, p.category, 'General') AS category,
                    c.slug AS category_slug,
                    c.seo_title AS category_seo_title,
                    c.seo_description AS category_seo_description,
                    c.seo_keywords AS category_seo_keywords,
                    c.seo_image AS category_seo_image
             FROM {$this->table} p
             LEFT JOIN {$this->categoriesTable} c ON c.id = p.category_id
             WHERE p.is_published = 1
               AND (
                   p.title LIKE ?
                   OR c.name LIKE ?
                   OR c.name_ru LIKE ?
                   OR c.name_en LIKE ?
                   OR p.excerpt LIKE ?
                   OR p.content LIKE ?
               )
             ORDER BY p.published_at DESC, p.id DESC
             LIMIT {$limit}",
            [$like, $like, $like, $like, $like, $like]
        )->get() ?: [];

        return $this->normalizePosts($posts);
    }

    /**
     * Выполняет поиск по опубликованным постам с пагинацией.
     */
    public function searchPublishedPaginated(string $query, int $perPage = PAGINATION_SETTINGS['perPage'], string $pageParam = 'page'): array
    {
        $this->ensureSchema();
        $query = trim($query);

        if ($query === '') {
            return [
                'items' => [],
                'total' => 0,
                'pagination' => null,
            ];
        }

        $total = $this->countSearchPublished($query);
        $pagination = new Pagination($total, $perPage, PAGINATION_SETTINGS['midSize'], PAGINATION_SETTINGS['maxPages'], PAGINATION_SETTINGS['tpl'], $pageParam);
        $offset = $pagination->getOffset();
        $limit = (int)$perPage;
        $like = '%' . $query . '%';
        $categoryNameSql = $this->categoryNameSql('c');

        $posts = db()->query(
            "SELECT p.id, p.title, p.slug, p.excerpt, p.content, p.image, p.seo_title, p.seo_description, p.seo_keywords, p.seo_image, p.hide_placeholder_image, p.author_name, p.author_role, p.published_at, p.views_count,
                    COALESCE({$categoryNameSql}, p.category, 'General') AS category,
                    c.slug AS category_slug,
                    c.seo_title AS category_seo_title,
                    c.seo_description AS category_seo_description,
                    c.seo_keywords AS category_seo_keywords,
                    c.seo_image AS category_seo_image
             FROM {$this->table} p
             LEFT JOIN {$this->categoriesTable} c ON c.id = p.category_id
             WHERE p.is_published = 1
               AND (
                   p.title LIKE ?
                   OR c.name LIKE ?
                   OR c.name_ru LIKE ?
                   OR c.name_en LIKE ?
                   OR p.excerpt LIKE ?
                   OR p.content LIKE ?
               )
             ORDER BY p.published_at DESC, p.id DESC
             LIMIT {$limit} OFFSET {$offset}",
            [$like, $like, $like, $like, $like, $like]
        )->get() ?: [];

        return [
            'items' => $this->normalizePosts($posts),
            'total' => $total,
            'pagination' => $total > $perPage ? $pagination : null,
        ];
    }

    /**
     * Создаёт таблицы блога и приводит старую схему к актуальному виду.
     */
    protected function ensureSchema(): void
    {
        if ($this->schemaReady) {
            return;
        }

        db()->query(
            "CREATE TABLE IF NOT EXISTS {$this->categoriesTable} (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(150) NOT NULL,
                name_ru VARCHAR(150) NULL,
                name_en VARCHAR(150) NULL,
                slug VARCHAR(180) NOT NULL,
                seo_title VARCHAR(255) NULL,
                seo_description TEXT NULL,
                seo_keywords TEXT NULL,
                seo_image VARCHAR(255) NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY slug (slug),
                UNIQUE KEY name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->ensureCategoryTranslationColumns();
        $this->ensureCategorySeoColumns();

        db()->query(
            "CREATE TABLE IF NOT EXISTS {$this->table} (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                category VARCHAR(150) NOT NULL,
                excerpt TEXT NULL,
                content MEDIUMTEXT NOT NULL,
                image VARCHAR(255) NULL,
                seo_title VARCHAR(255) NULL,
                seo_description TEXT NULL,
                seo_keywords TEXT NULL,
                seo_image VARCHAR(255) NULL,
                hide_placeholder_image TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
                show_on_home TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
                author_id INT(10) UNSIGNED NULL,
                author_name VARCHAR(100) NULL,
                author_role VARCHAR(20) NOT NULL DEFAULT 'user',
                views_count INT(10) UNSIGNED NOT NULL DEFAULT 0,
                published_at DATETIME NOT NULL,
                is_published TINYINT(3) UNSIGNED NOT NULL DEFAULT 1,
                PRIMARY KEY (id),
                UNIQUE KEY slug (slug),
                KEY published_at (published_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $categoryIdExists = (bool)db()->query("SHOW COLUMNS FROM {$this->table} LIKE 'category_id'")->getColumn();
        if (!$categoryIdExists) {
            db()->query("ALTER TABLE {$this->table} ADD COLUMN category_id INT(10) UNSIGNED NULL AFTER slug");
            db()->query("ALTER TABLE {$this->table} ADD KEY category_id (category_id)");
        }

        $this->ensureAuthorColumns();
        $this->ensureViewsColumn();
        $this->ensurePostSeoColumns();
        $this->ensureHidePlaceholderImageColumn();
        $this->ensureShowOnHomeColumn();

        $oldCategoryExists = (bool)db()->query("SHOW COLUMNS FROM {$this->table} LIKE 'category'")->getColumn();
        if ($oldCategoryExists) {
            $categories = db()->query(
                "SELECT DISTINCT category FROM {$this->table}
                 WHERE category IS NOT NULL
                   AND category != ''
                   AND (category_id IS NULL OR category_id = 0)"
            )->get() ?: [];

            foreach ($categories as $category) {
                $name = trim((string)$category['category']);
                if ($name === '') {
                    continue;
                }

                $slug = $this->makeSlug($name);
                db()->query(
                    "INSERT IGNORE INTO {$this->categoriesTable} (name, name_ru, name_en, slug, created_at)
                     VALUES (?, ?, ?, ?, ?)",
                    [$name, $name, $name, $slug, date('Y-m-d H:i:s')]
                );
                db()->query(
                    "UPDATE {$this->table} p
                     INNER JOIN {$this->categoriesTable} c ON c.name = p.category
                     SET p.category_id = c.id
                     WHERE (p.category_id IS NULL OR p.category_id = 0)
                       AND p.category = ?",
                    [$name]
                );
            }
        }

        db()->query(
            "UPDATE {$this->table} p
             INNER JOIN (
                SELECT id FROM {$this->categoriesTable} ORDER BY id ASC LIMIT 1
             ) c ON 1 = 1
             SET p.category_id = c.id
             WHERE p.category_id IS NULL OR p.category_id = 0"
        );

        $this->schemaReady = true;
    }

    /**
     * Возвращает количество опубликованных постов с учётом фильтра по категории.
     */
    protected function countPublished(?string $category = null): int
    {
        [$whereSql, $params] = $this->buildPublishedWhereClause($category);
        return (int)db()->query(
            "SELECT COUNT(*)
             FROM {$this->table} p
             LEFT JOIN {$this->categoriesTable} c ON c.id = p.category_id
             {$whereSql}",
            $params
        )->getColumn();
    }

    /**
     * Возвращает категории, в которых есть опубликованные посты.
     */
    protected function getCategories(): array
    {
        $categoryNameSql = $this->categoryNameSql('c');
        $categories = db()->query(
            "SELECT c.id, {$categoryNameSql} AS name, c.name_ru, c.name_en, c.slug, COUNT(p.id) AS total
             FROM {$this->categoriesTable} c
             LEFT JOIN {$this->table} p ON p.category_id = c.id AND p.is_published = 1
             GROUP BY c.id, c.name, c.name_ru, c.name_en, c.slug
             HAVING total > 0
             ORDER BY total DESC, c.name ASC"
        )->get() ?: [];

        if (!empty($categories)) {
            return array_map(static function (array $category): array {
                return [
                    'id' => (int)$category['id'],
                    'name' => (string)$category['name'],
                    'slug' => (string)$category['slug'],
                    'label' => (string)$category['name'],
                    'total' => (int)$category['total'],
                ];
            }, $categories);
        }

        // Fallback for legacy schemas where category_id is not linked yet.
        $legacyCategories = db()->query(
            "SELECT p.category AS name, COUNT(*) AS total
             FROM {$this->table} p
             WHERE p.is_published = 1
               AND p.category IS NOT NULL
               AND p.category != ''
             GROUP BY p.category
             ORDER BY total DESC, p.category ASC"
        )->get() ?: [];

        return array_map(function (array $category): array {
            $name = (string)$category['name'];
            return [
                'id' => 0,
                'name' => $name,
                'slug' => $this->makeSlug($name),
                'label' => $name,
                'total' => (int)$category['total'],
            ];
        }, $legacyCategories);
    }

    /**
     * Возвращает список актуальных постов для блока рекомендаций.
     */
    protected function getTrendingPosts(int $limit = 3, ?string $excludeSlug = null): array
    {
        $limit = (int)$limit;
        $params = [];
        $where = 'WHERE p.is_published = 1';

        if ($excludeSlug) {
            $where .= ' AND p.slug != ?';
            $params[] = $excludeSlug;
        }

        $categoryNameSql = $this->categoryNameSql('c');
        $posts = db()->query(
            "SELECT p.id, p.title, p.slug, p.excerpt, p.content, p.image, p.seo_title, p.seo_description, p.seo_keywords, p.seo_image, p.hide_placeholder_image, p.author_name, p.author_role, p.published_at, p.views_count,
                    COALESCE({$categoryNameSql}, p.category, 'General') AS category,
                    c.slug AS category_slug,
                    c.seo_title AS category_seo_title,
                    c.seo_description AS category_seo_description,
                    c.seo_keywords AS category_seo_keywords,
                    c.seo_image AS category_seo_image
             FROM {$this->table} p
             LEFT JOIN {$this->categoriesTable} c ON c.id = p.category_id
             {$where}
             ORDER BY p.published_at DESC, p.id DESC
             LIMIT {$limit}",
            $params
        )->get() ?: [];

        return $this->normalizePosts($posts);
    }

    /**
     * Возвращает количество опубликованных постов, найденных по поисковому запросу.
     */
    protected function countSearchPublished(string $query): int
    {
        $like = '%' . trim($query) . '%';

        return (int)db()->query(
            "SELECT COUNT(*)
             FROM {$this->table} p
             LEFT JOIN {$this->categoriesTable} c ON c.id = p.category_id
             WHERE p.is_published = 1
               AND (
                   p.title LIKE ?
                   OR c.name LIKE ?
                   OR c.name_ru LIKE ?
                   OR c.name_en LIKE ?
                   OR p.excerpt LIKE ?
                   OR p.content LIKE ?
               )",
            [$like, $like, $like, $like, $like, $like]
        )->getColumn();
    }

    /**
     * Строит SQL-условие выборки опубликованных постов.
     */
    protected function buildPublishedWhereClause(?string $category = null): array
    {
        $params = [];
        $where = 'WHERE p.is_published = 1';

        if ($category !== null && $category !== '') {
            $where .= ' AND (c.slug = ? OR (c.id IS NULL AND p.category = ?))';
            $params[] = $category;
            $params[] = $this->getCategoryNameBySlug($category);
        }

        return [$where, $params];
    }

    /**
     * Нормализует slug категории из параметров фильтра.
     */
    protected function normalizeCategoryFilter(?string $category): ?string
    {
        $category = trim((string)$category);
        return $category !== '' ? $category : null;
    }

    /**
     * Возвращает отображаемое имя категории по её slug.
     */
    protected function getCategoryNameBySlug(string $slug): string
    {
        $categoryNameSql = $this->categoryNameSql();
        $name = db()->query(
            "SELECT {$categoryNameSql} FROM {$this->categoriesTable} WHERE slug = ? LIMIT 1",
            [$slug]
        )->getColumn();

        if ($name) {
            return (string)$name;
        }

        $legacyCategories = db()->query(
            "SELECT DISTINCT category
             FROM {$this->table}
             WHERE category IS NOT NULL AND category != ''"
        )->get() ?: [];

        foreach ($legacyCategories as $legacyCategory) {
            $legacyName = (string)$legacyCategory['category'];
            if ($this->makeSlug($legacyName) === $slug) {
                return $legacyName;
            }
        }

        return $slug;
    }

    /**
     * Возвращает SEO-метаданные категории по её slug.
     */
    protected function getCategoryMetaBySlug(string $slug): ?array
    {
        $categoryNameSql = $this->categoryNameSql();
        $category = db()->query(
            "SELECT {$categoryNameSql} AS name, slug, seo_title, seo_description, seo_keywords, seo_image
             FROM {$this->categoriesTable}
             WHERE slug = ?
             LIMIT 1",
            [$slug]
        )->getOne();

        if (!$category) {
            return null;
        }

        return [
            'name' => trim((string)($category['name'] ?? '')),
            'slug' => trim((string)($category['slug'] ?? '')),
            'seo_title' => trim((string)($category['seo_title'] ?? '')),
            'seo_description' => trim((string)($category['seo_description'] ?? '')),
            'seo_keywords' => trim((string)($category['seo_keywords'] ?? '')),
            'seo_image' => trim((string)($category['seo_image'] ?? '')),
        ];
    }

    /**
     * Нормализует массив постов перед передачей в представление.
     */
    protected function normalizePosts(array $posts): array
    {
        return array_map(fn(array $post): array => $this->normalizePost($post), $posts);
    }

    /**
     * Подготавливает один пост к выводу на сайте.
     */
    protected function normalizePost(array $post): array
    {
        $post['title'] = trim((string)($post['title'] ?? 'Untitled'));
        $post['slug'] = trim((string)($post['slug'] ?? ''));
        $post['category'] = trim((string)($post['category'] ?? 'General'));
        $post['category_slug'] = trim((string)($post['category_slug'] ?? $this->makeSlug($post['category'])));
        $post['category_label'] = $post['category'];
        $post['original_image'] = ltrim((string)($post['image'] ?? ''), '/');
        $post['has_image'] = $post['original_image'] !== '';
        $post['hide_placeholder_image'] = max(0, (int)($post['hide_placeholder_image'] ?? 0));
        $post['show_post_image'] = $post['hide_placeholder_image'] !== 1;
        $post['show_on_home'] = max(0, (int)($post['show_on_home'] ?? 0));
        $post['image'] = $post['has_image'] ? $post['original_image'] : 'assets/img/no-image.png';
        $post['seo_title'] = trim((string)($post['seo_title'] ?? ''));
        $post['seo_description'] = trim((string)($post['seo_description'] ?? ''));
        $post['seo_keywords'] = trim((string)($post['seo_keywords'] ?? ''));
        $post['seo_image'] = trim((string)($post['seo_image'] ?? ''));
        $post['category_seo_title'] = trim((string)($post['category_seo_title'] ?? ''));
        $post['category_seo_description'] = trim((string)($post['category_seo_description'] ?? ''));
        $post['category_seo_keywords'] = trim((string)($post['category_seo_keywords'] ?? ''));
        $post['category_seo_image'] = trim((string)($post['category_seo_image'] ?? ''));
        $post['published_at'] = (string)($post['published_at'] ?? date('Y-m-d'));
        $post['author_name'] = trim((string)($post['author_name'] ?? 'Fireball'));
        $post['author_role'] = trim((string)($post['author_role'] ?? 'admin')) ?: 'user';
        $post['author_label'] = $this->formatAuthorLabel($post['author_name'], $post['author_role']);
        $post['views_count'] = max(0, (int)($post['views_count'] ?? 0));

        $excerpt = trim((string)($post['excerpt'] ?? ''));
        $content = trim((string)($post['content'] ?? ''));

        if ($content === '') {
            $content = '<p>' . htmlSC($excerpt ?: $post['title']) . '</p>';
        } elseif ($content === strip_tags($content)) {
            $content = '<p>' . nl2br(htmlSC($content)) . '</p>';
        }

        $post['excerpt'] = $excerpt;
        $post['content'] = $content;
        $post['seo_description'] = $post['seo_description'] !== ''
            ? $post['seo_description']
            : $this->buildSeoDescription($excerpt, $content, $post['title']);
        $post['seo_image'] = $post['seo_image'] !== '' ? ltrim($post['seo_image'], '/') : $post['original_image'];

        return $post;
    }

    /**
     * Преобразует строку в slug для публичных сущностей блога.
     */
    protected function makeSlug(string $value): string
    {
        return make_slug($value, 'general');
    }

    /**
     * Добавляет языковые поля категорий и заполняет их в старых схемах.
     */
    protected function ensureCategoryTranslationColumns(): void
    {
        $nameRuExists = (bool)db()->query("SHOW COLUMNS FROM {$this->categoriesTable} LIKE 'name_ru'")->getColumn();
        if (!$nameRuExists) {
            db()->query("ALTER TABLE {$this->categoriesTable} ADD COLUMN name_ru VARCHAR(150) NULL AFTER name");
        }

        $nameEnExists = (bool)db()->query("SHOW COLUMNS FROM {$this->categoriesTable} LIKE 'name_en'")->getColumn();
        if (!$nameEnExists) {
            db()->query("ALTER TABLE {$this->categoriesTable} ADD COLUMN name_en VARCHAR(150) NULL AFTER name_ru");
        }

        db()->query(
            "UPDATE {$this->categoriesTable}
             SET name_ru = COALESCE(NULLIF(name_ru, ''), name),
                 name_en = COALESCE(NULLIF(name_en, ''), name)
             WHERE name_ru IS NULL OR name_ru = '' OR name_en IS NULL OR name_en = ''"
        );
    }

    /**
     * Добавляет SEO-поля категорий в таблицу, если их нет.
     */
    protected function ensureCategorySeoColumns(): void
    {
        $seoTitleExists = (bool)db()->query("SHOW COLUMNS FROM {$this->categoriesTable} LIKE 'seo_title'")->getColumn();
        if (!$seoTitleExists) {
            db()->query("ALTER TABLE {$this->categoriesTable} ADD COLUMN seo_title VARCHAR(255) NULL AFTER slug");
        }

        $seoDescriptionExists = (bool)db()->query("SHOW COLUMNS FROM {$this->categoriesTable} LIKE 'seo_description'")->getColumn();
        if (!$seoDescriptionExists) {
            db()->query("ALTER TABLE {$this->categoriesTable} ADD COLUMN seo_description TEXT NULL AFTER seo_title");
        }

        $seoKeywordsExists = (bool)db()->query("SHOW COLUMNS FROM {$this->categoriesTable} LIKE 'seo_keywords'")->getColumn();
        if (!$seoKeywordsExists) {
            db()->query("ALTER TABLE {$this->categoriesTable} ADD COLUMN seo_keywords TEXT NULL AFTER seo_description");
        }

        $seoImageExists = (bool)db()->query("SHOW COLUMNS FROM {$this->categoriesTable} LIKE 'seo_image'")->getColumn();
        if (!$seoImageExists) {
            db()->query("ALTER TABLE {$this->categoriesTable} ADD COLUMN seo_image VARCHAR(255) NULL AFTER seo_keywords");
        }
    }

    /**
     * Возвращает SQL-выражение для выбора названия категории в текущем языке.
     */
    protected function categoryNameSql(string $alias = ''): string
    {
        $prefix = $alias !== '' ? "{$alias}." : '';
        $column = (app()->get('lang')['code'] ?? 'ru') === 'en' ? 'name_en' : 'name_ru';

        return "COALESCE(NULLIF({$prefix}{$column}, ''), {$prefix}name)";
    }

    /**
     * Добавляет и инициализирует авторские поля в таблице постов.
     */
    protected function ensureAuthorColumns(): void
    {
        $authorIdExists = (bool)db()->query("SHOW COLUMNS FROM {$this->table} LIKE 'author_id'")->getColumn();
        if (!$authorIdExists) {
            db()->query("ALTER TABLE {$this->table} ADD COLUMN author_id INT(10) UNSIGNED NULL AFTER image");
        }

        $authorNameExists = (bool)db()->query("SHOW COLUMNS FROM {$this->table} LIKE 'author_name'")->getColumn();
        if (!$authorNameExists) {
            db()->query("ALTER TABLE {$this->table} ADD COLUMN author_name VARCHAR(100) NULL AFTER author_id");
        }

        $authorRoleExists = (bool)db()->query("SHOW COLUMNS FROM {$this->table} LIKE 'author_role'")->getColumn();
        if (!$authorRoleExists) {
            db()->query("ALTER TABLE {$this->table} ADD COLUMN author_role VARCHAR(20) NOT NULL DEFAULT 'user' AFTER author_name");
        }

        db()->query(
            "UPDATE {$this->table}
             SET author_name = COALESCE(NULLIF(author_name, ''), 'Fireball'),
                 author_role = COALESCE(NULLIF(author_role, ''), 'admin')
             WHERE author_name IS NULL OR author_name = '' OR author_role IS NULL OR author_role = ''"
        );
    }

    /**
     * Добавляет поле счётчика просмотров.
     */
    protected function ensureViewsColumn(): void
    {
        $viewsCountExists = (bool)db()->query("SHOW COLUMNS FROM {$this->table} LIKE 'views_count'")->getColumn();
        if (!$viewsCountExists) {
            db()->query("ALTER TABLE {$this->table} ADD COLUMN views_count INT(10) UNSIGNED NOT NULL DEFAULT 0 AFTER author_role");
        }
    }

    /**
     * Добавляет SEO-поля постов в таблицу.
     */
    protected function ensurePostSeoColumns(): void
    {
        $seoTitleExists = (bool)db()->query("SHOW COLUMNS FROM {$this->table} LIKE 'seo_title'")->getColumn();
        if (!$seoTitleExists) {
            db()->query("ALTER TABLE {$this->table} ADD COLUMN seo_title VARCHAR(255) NULL AFTER image");
        }

        $seoDescriptionExists = (bool)db()->query("SHOW COLUMNS FROM {$this->table} LIKE 'seo_description'")->getColumn();
        if (!$seoDescriptionExists) {
            db()->query("ALTER TABLE {$this->table} ADD COLUMN seo_description TEXT NULL AFTER seo_title");
        }

        $seoKeywordsExists = (bool)db()->query("SHOW COLUMNS FROM {$this->table} LIKE 'seo_keywords'")->getColumn();
        if (!$seoKeywordsExists) {
            db()->query("ALTER TABLE {$this->table} ADD COLUMN seo_keywords TEXT NULL AFTER seo_description");
        }

        $seoImageExists = (bool)db()->query("SHOW COLUMNS FROM {$this->table} LIKE 'seo_image'")->getColumn();
        if (!$seoImageExists) {
            db()->query("ALTER TABLE {$this->table} ADD COLUMN seo_image VARCHAR(255) NULL AFTER seo_keywords");
        }
    }

    /**
     * Добавляет флаг скрытия изображения-заглушки.
     */
    protected function ensureHidePlaceholderImageColumn(): void
    {
        $columnExists = (bool)db()->query("SHOW COLUMNS FROM {$this->table} LIKE 'hide_placeholder_image'")->getColumn();
        if (!$columnExists) {
            db()->query("ALTER TABLE {$this->table} ADD COLUMN hide_placeholder_image TINYINT(3) UNSIGNED NOT NULL DEFAULT 0 AFTER image");
        }
    }

    /**
     * Добавляет флаг показа поста на главной странице.
     */
    protected function ensureShowOnHomeColumn(): void
    {
        $columnExists = (bool)db()->query("SHOW COLUMNS FROM {$this->table} LIKE 'show_on_home'")->getColumn();
        if (!$columnExists) {
            db()->query("ALTER TABLE {$this->table} ADD COLUMN show_on_home TINYINT(3) UNSIGNED NOT NULL DEFAULT 0 AFTER hide_placeholder_image");
        }
    }

    /**
     * Формирует подпись автора поста с учётом роли.
     */
    protected function formatAuthorLabel(string $name, string $role): string
    {
        $roleLabel = get_user_role_label($role);
        return trim($roleLabel . ($name !== '' ? ': ' . $name : ''));
    }

    /**
     * Извлекает краткий текстовый фрагмент из HTML-содержимого.
     */
    protected function excerptFromHtml(string $content, int $limit = 180): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($content)));
        if (mb_strlen($text) <= $limit) {
            return $text;
        }
        return rtrim(mb_substr($text, 0, $limit - 1)) . '...';
    }

    /**
     * Формирует SEO-описание на основе excerpt, content или заголовка.
     */
    protected function buildSeoDescription(string $excerpt, string $content, string $title): string
    {
        $excerpt = trim(preg_replace('/\s+/', ' ', strip_tags($excerpt)));
        if ($excerpt !== '') {
            return $this->excerptFromHtml($excerpt, 160);
        }

        $contentExcerpt = $this->excerptFromHtml($content, 160);
        if ($contentExcerpt !== '') {
            return $contentExcerpt;
        }

        return $title;
    }

}
