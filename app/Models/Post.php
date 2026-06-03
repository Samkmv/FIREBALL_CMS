<?php

namespace App\Models;

use App\Modules\BlockEditor\BlockRenderer;
use App\Services\PostSeo;
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
    protected Category $categories;
    protected PostSeo $seo;
    protected static bool $schemaReady = false;
    protected static array $runtimeCache = [];

    public function __construct()
    {
        $this->categories = new Category();
        $this->seo = new PostSeo();
    }

    public static function clearPublicCache(): void
    {
        self::$runtimeCache = [];
        cache()->set('posts:public_version', (string)microtime(true), 31536000);

        foreach ([
            'posts:navigation_categories',
            'posts:home_featured:8',
            'posts:sidebar_categories',
        ] as $key) {
            cache()->remove($key);
        }
    }

    protected function publicCacheKey(string $name): string
    {
        return 'posts:v' . $this->publicCacheVersion() . ':' . $name;
    }

    protected function publicCacheVersion(): string
    {
        return (string)cache()->get('posts:public_version', '1');
    }

    protected function publicPostSelectColumns(): string
    {
        $categoryNameSql = $this->categories->nameSql('c');

        return "p.id, p.title, p.slug, p.excerpt, p.content, p.image,
                p.seo_title, p.seo_description, p.seo_keywords, p.seo_image,
                p.hide_placeholder_image, p.show_on_home, p.priority,
                p.author_name, p.author_role, p.published_at, p.views_count,
                COALESCE({$categoryNameSql}, p.category, 'General') AS category,
                c.slug AS category_slug,
                c.seo_title AS category_seo_title,
                c.seo_description AS category_seo_description,
                c.seo_keywords AS category_seo_keywords,
                c.seo_image AS category_seo_image";
    }

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
        $posts = db()->query(
            "SELECT {$this->publicPostSelectColumns()}
             FROM {$this->table} p
             LEFT JOIN {$this->categoriesTable} c ON c.id = p.category_id
             {$whereSql}
             ORDER BY p.priority DESC, p.published_at DESC, p.id DESC
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
        $post = db()->query(
            "SELECT {$this->publicPostSelectColumns()}
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
     * Ищет запись по ID для админского предпросмотра, включая черновики.
     */
    public function findByIdForPreview(int $id): array|false
    {
        if ($id <= 0) {
            return false;
        }

        $this->ensureSchema();
        $post = db()->query(
            "SELECT {$this->publicPostSelectColumns()}
             FROM {$this->table} p
             LEFT JOIN {$this->categoriesTable} c ON c.id = p.category_id
             WHERE p.id = ?
             LIMIT 1",
            [$id]
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
        $cacheKey = 'sidebar:' . ($excludeSlug ?: 'all');
        if (array_key_exists($cacheKey, self::$runtimeCache)) {
            return self::$runtimeCache[$cacheKey];
        }

        return self::$runtimeCache[$cacheKey] = [
            'categories' => $this->getCategories(),
            'trending_posts' => $this->getTrendingPosts(6, $excludeSlug),
        ];
    }

    /**
     * Возвращает посты, отмеченные для показа на главной странице.
     */
    public function getHomeFeaturedPosts(int $limit = 8): array
    {
        $this->ensureSchema();
        $limit = max(1, (int)$limit);
        $cacheKey = $this->publicCacheKey('home_featured:' . $limit);
        $cached = cache()->get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $posts = db()->query(
            "SELECT {$this->publicPostSelectColumns()}
             FROM {$this->table} p
             LEFT JOIN {$this->categoriesTable} c ON c.id = p.category_id
             WHERE p.is_published = 1
               AND p.show_on_home = 1
             ORDER BY p.priority DESC, p.published_at DESC, p.id DESC
             LIMIT {$limit}"
        )->get() ?: [];

        $items = $this->normalizePosts($posts);
        cache()->set($cacheKey, $items, 300);

        return $items;
    }

    /**
     * Returns latest published posts without using the legacy "show on home" flag.
     */
    public function getLatestPublishedPosts(int $limit = 10): array
    {
        $this->ensureSchema();
        $limit = max(1, min(100, (int)$limit));
        $cacheKey = $this->publicCacheKey('latest:' . $limit);
        $cached = cache()->get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $posts = db()->query(
            "SELECT {$this->publicPostSelectColumns()}
             FROM {$this->table} p
             LEFT JOIN {$this->categoriesTable} c ON c.id = p.category_id
             WHERE p.is_published = 1
             ORDER BY p.published_at DESC, p.id DESC
             LIMIT {$limit}"
        )->get() ?: [];

        $items = $this->normalizePosts($posts);
        cache()->set($cacheKey, $items, 300);

        return $items;
    }

    /**
     * Возвращает самые просматриваемые опубликованные посты.
     */
    public function getPopularPosts(int $limit = 6, ?string $excludeSlug = null): array
    {
        $this->ensureSchema();
        $limit = max(1, (int)$limit);
        $excludeSlug = trim((string)$excludeSlug);
        $cacheKey = $this->publicCacheKey('popular:' . $limit . ':' . md5($excludeSlug));
        $cached = cache()->get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $params = [];
        $where = 'WHERE p.is_published = 1';

        if ($excludeSlug) {
            $where .= ' AND p.slug != ?';
            $params[] = $excludeSlug;
        }

        $posts = db()->query(
            "SELECT {$this->publicPostSelectColumns()}
             FROM {$this->table} p
             LEFT JOIN {$this->categoriesTable} c ON c.id = p.category_id
             {$where}
             ORDER BY p.views_count DESC, p.priority DESC, p.published_at DESC, p.id DESC
             LIMIT {$limit}",
            $params
        )->get() ?: [];

        $items = $this->normalizePosts($posts);
        cache()->set($cacheKey, $items, 300);

        return $items;
    }

    /**
     * Возвращает категории для навигации по блогу.
     */
    public function getNavigationCategories(): array
    {
        $this->ensureSchema();
        $cacheKey = $this->publicCacheKey('navigation_categories');
        $cached = cache()->get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $items = $this->categories->getNavigationCategories($this->table);
        cache()->set($cacheKey, $items, 600);

        return $items;
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
        $posts = db()->query(
            "SELECT {$this->publicPostSelectColumns()}
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
             ORDER BY p.priority DESC, p.published_at DESC, p.id DESC
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
        $posts = db()->query(
            "SELECT {$this->publicPostSelectColumns()}
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
             ORDER BY p.priority DESC, p.published_at DESC, p.id DESC
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
        if (self::$schemaReady) {
            return;
        }

        $this->categories->ensureSchema();

        db()->query(
            "CREATE TABLE IF NOT EXISTS {$this->table} (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                category_id INT(10) UNSIGNED NULL,
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
                priority INT(10) UNSIGNED NOT NULL DEFAULT 0,
                author_id INT(10) UNSIGNED NULL,
                author_name VARCHAR(100) NULL,
                author_role VARCHAR(20) NOT NULL DEFAULT 'user',
                views_count INT(10) UNSIGNED NOT NULL DEFAULT 0,
                published_at DATETIME NOT NULL,
                is_published TINYINT(3) UNSIGNED NOT NULL DEFAULT 1,
                PRIMARY KEY (id),
                UNIQUE KEY slug (slug),
                KEY category_id (category_id),
                KEY published_at (published_at),
                KEY published_lookup (is_published, published_at, id),
                KEY category_published (category_id, is_published, published_at),
                KEY show_on_home (show_on_home),
                KEY priority (priority),
                KEY home_featured (is_published, show_on_home, priority, published_at),
                KEY popular_published (is_published, views_count, priority, published_at)
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
        $this->ensurePriorityColumn();
        $this->ensurePostIndexes();

        $this->categories->syncLegacyPostCategories($this->table);

        self::$schemaReady = true;
    }

    protected function ensurePostIndexes(): void
    {
        $this->ensureIndex('category_id', ['category_id'], "ALTER TABLE {$this->table} ADD KEY category_id (category_id)");
        $this->ensureIndex('published_lookup', ['is_published', 'published_at', 'id'], "ALTER TABLE {$this->table} ADD KEY published_lookup (is_published, published_at, id)");
        $this->ensureIndex('category_published', ['category_id', 'is_published', 'published_at'], "ALTER TABLE {$this->table} ADD KEY category_published (category_id, is_published, published_at)");
        $this->ensureIndex('show_on_home', ['show_on_home'], "ALTER TABLE {$this->table} ADD KEY show_on_home (show_on_home)");
        $this->ensureIndex('priority', ['priority'], "ALTER TABLE {$this->table} ADD KEY priority (priority)");
        $this->ensureIndex('home_featured', ['is_published', 'show_on_home', 'priority', 'published_at'], "ALTER TABLE {$this->table} ADD KEY home_featured (is_published, show_on_home, priority, published_at)");
        $this->ensureIndex('popular_published', ['is_published', 'views_count', 'priority', 'published_at'], "ALTER TABLE {$this->table} ADD KEY popular_published (is_published, views_count, priority, published_at)");
    }

    protected function ensureIndex(string $name, array $columns, string $sql): void
    {
        $exists = (bool)db()->query("SHOW INDEX FROM {$this->table} WHERE Key_name = ?", [$name])->getOne();
        if (!$exists) {
            foreach ($columns as $column) {
                if (!$this->columnExists($column)) {
                    return;
                }
            }

            db()->query($sql);
        }
    }

    protected function columnExists(string $column): bool
    {
        return (bool)db()->query("SHOW COLUMNS FROM {$this->table} LIKE ?", [$column])->getColumn();
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
        $cacheKey = $this->publicCacheKey('sidebar_categories');
        $cached = cache()->get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $items = $this->categories->getSidebarCategories($this->table);
        cache()->set($cacheKey, $items, 600);

        return $items;
    }

    /**
     * Возвращает список актуальных постов для блока рекомендаций.
     */
    protected function getTrendingPosts(int $limit = 3, ?string $excludeSlug = null): array
    {
        $limit = (int)$limit;
        $excludeSlug = trim((string)$excludeSlug);
        $cacheKey = $this->publicCacheKey('trending:' . $limit . ':' . md5($excludeSlug));
        $cached = cache()->get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $params = [];
        $where = 'WHERE p.is_published = 1';

        if ($excludeSlug) {
            $where .= ' AND p.slug != ?';
            $params[] = $excludeSlug;
        }

        $posts = db()->query(
            "SELECT {$this->publicPostSelectColumns()}
             FROM {$this->table} p
             LEFT JOIN {$this->categoriesTable} c ON c.id = p.category_id
             {$where}
             ORDER BY p.published_at DESC, p.id DESC
             LIMIT {$limit}",
            $params
        )->get() ?: [];

        $items = $this->normalizePosts($posts);
        cache()->set($cacheKey, $items, 300);

        return $items;
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
        $cacheKey = $this->publicCacheKey('category_name:' . md5($slug));
        $cached = cache()->get($cacheKey);
        if (is_string($cached)) {
            return $cached;
        }

        $value = $this->categories->getNameBySlug($slug, $this->table);
        cache()->set($cacheKey, $value, 600);

        return $value;
    }

    /**
     * Возвращает SEO-метаданные категории по её slug.
     */
    protected function getCategoryMetaBySlug(string $slug): ?array
    {
        $cacheKey = $this->publicCacheKey('category_meta:' . md5($slug));
        $cached = cache()->get($cacheKey);
        if (is_array($cached)) {
            return $cached ?: null;
        }

        $meta = $this->categories->getMetaBySlug($slug);
        if ($meta === null) {
            cache()->set($cacheKey, [], 600);
            return null;
        }

        cache()->set($cacheKey, $meta, 600);

        return $meta;
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
        $post['priority'] = max(0, (int)($post['priority'] ?? 0));
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

        if ($content !== '' && $content[0] === '{') {
            $content = (new BlockRenderer())->renderPublicContent($content);
        }

        if ($content !== '' && $content === strip_tags($content)) {
            $content = '<p>' . nl2br(htmlSC($content)) . '</p>';
        }

        $post['excerpt'] = $excerpt;
        $post['content'] = $content;
        $post['seo_description'] = $post['seo_description'] !== ''
            ? $post['seo_description']
            : $this->seo->description($excerpt, $content, $post['title']);
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
     * Добавляет поле приоритета поста.
     */
    protected function ensurePriorityColumn(): void
    {
        $columnExists = (bool)db()->query("SHOW COLUMNS FROM {$this->table} LIKE 'priority'")->getColumn();
        if (!$columnExists) {
            db()->query("ALTER TABLE {$this->table} ADD COLUMN priority INT(10) UNSIGNED NOT NULL DEFAULT 0 AFTER show_on_home");
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

}
