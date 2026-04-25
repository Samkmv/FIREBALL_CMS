<?php

namespace App\Models;

use FBL\Pagination;

/**
 * Предоставляет административные операции для постов, категорий и общей статистики.
 */
class Admin
{

    protected string $postsTable = 'posts';
    protected string $categoriesTable = 'post_categories';
    protected bool $schemaReady = false;

    /**
     * Создаёт таблицы блога и подтягивает недостающие поля старых схем.
     */
    public function ensureSchema(): void
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
            "CREATE TABLE IF NOT EXISTS {$this->postsTable} (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                category VARCHAR(150) NOT NULL,
                category_id INT(10) UNSIGNED NULL,
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
                KEY published_at (published_at),
                KEY category_id (category_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $categoryIdExists = (bool)db()->query("SHOW COLUMNS FROM {$this->postsTable} LIKE 'category_id'")->getColumn();
        if (!$categoryIdExists) {
            db()->query("ALTER TABLE {$this->postsTable} ADD COLUMN category_id INT(10) UNSIGNED NULL AFTER slug");
            db()->query("ALTER TABLE {$this->postsTable} ADD KEY category_id (category_id)");
        }

        $this->ensureAuthorColumns();
        $this->ensureViewsColumn();
        $this->ensurePostSeoColumns();
        $this->ensureHidePlaceholderImageColumn();
        $this->ensureShowOnHomeColumn();
        $this->syncLegacyCategoryColumn();
        $this->schemaReady = true;
    }

    /**
     * Возвращает сводную статистику для административной панели.
     */
    public function getStats(): array
    {
        $this->ensureSchema();

        return [
            'posts' => (int)db()->query("SELECT COUNT(*) FROM {$this->postsTable}")->getColumn(),
            'categories' => (int)db()->query("SELECT COUNT(*) FROM {$this->categoriesTable}")->getColumn(),
            'users' => (int)db()->query("SELECT COUNT(*) FROM users")->getColumn(),
        ];
    }

    /**
     * Возвращает все посты вместе с названием категории.
     */
    public function getPosts(): array
    {
        $this->ensureSchema();

        return db()->query(
            "SELECT p.*, c.name AS category_name
             FROM {$this->postsTable} p
             LEFT JOIN {$this->categoriesTable} c ON c.id = p.category_id
             ORDER BY p.id DESC"
        )->get() ?: [];
    }

    /**
     * Возвращает посты для административной таблицы с поиском и пагинацией.
     */
    public function getPaginatedPosts(array $options = []): array
    {
        $this->ensureSchema();

        $perPage = max(1, (int)($options['per_page'] ?? 15));
        $search = trim((string)($options['search'] ?? ''));
        $sort = (string)($options['sort'] ?? 'published_at');
        $direction = strtolower((string)($options['direction'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

        $sortMap = [
            'id' => 'p.id',
            'title' => 'p.title',
            'category' => 'category_name',
            'author' => 'p.author_name',
            'views' => 'p.views_count',
            'status' => 'p.is_published',
            'published_at' => 'p.published_at',
        ];

        $orderBy = $sortMap[$sort] ?? 'p.published_at';
        $where = '';
        $params = [];

        if ($search !== '') {
            $where = "WHERE p.title LIKE ? OR p.slug LIKE ? OR COALESCE(c.name, p.category) LIKE ? OR p.author_name LIKE ?";
            $searchLike = '%' . $search . '%';
            $params = [$searchLike, $searchLike, $searchLike, $searchLike];
        }

        $total = (int)db()->query(
            "SELECT COUNT(*)
             FROM {$this->postsTable} p
             LEFT JOIN {$this->categoriesTable} c ON c.id = p.category_id
             {$where}",
            $params
        )->getColumn();

        $pagination = new Pagination($total, $perPage);
        $offset = $pagination->getOffset();

        $items = db()->query(
            "SELECT p.*, c.name AS category_name
             FROM {$this->postsTable} p
             LEFT JOIN {$this->categoriesTable} c ON c.id = p.category_id
             {$where}
             ORDER BY {$orderBy} {$direction}, p.id DESC
             LIMIT {$offset}, {$perPage}",
            $params
        )->get() ?: [];

        return [
            'items' => $items,
            'total' => $total,
            'pagination' => $pagination,
            'search' => $search,
            'sort' => $sort,
            'direction' => strtolower($direction),
            'per_page' => $perPage,
        ];
    }

    /**
     * Ищет пост по идентификатору вместе с категорией.
     */
    public function findPostById(int $id): array|false
    {
        $this->ensureSchema();

        return db()->query(
            "SELECT p.*, c.name AS category_name
             FROM {$this->postsTable} p
             LEFT JOIN {$this->categoriesTable} c ON c.id = p.category_id
             WHERE p.id = ?
             LIMIT 1",
            [$id]
        )->getOne();
    }

    /**
     * Создаёт новый пост в административной части.
     */
    public function createPost(array $data): int
    {
        $this->ensureSchema();
        $slugSource = (string)($data['slug'] ?: $data['title']);
        $slug = $this->makeUniquePostSlug($this->makeSlug($slugSource));

        db()->query(
            "INSERT INTO {$this->postsTable}
             (title, slug, category, category_id, excerpt, content, image, seo_title, seo_description, seo_keywords, seo_image, hide_placeholder_image, show_on_home, author_id, author_name, author_role, published_at, is_published)
             VALUES (:title, :slug, :category, :category_id, :excerpt, :content, :image, :seo_title, :seo_description, :seo_keywords, :seo_image, :hide_placeholder_image, :show_on_home, :author_id, :author_name, :author_role, :published_at, :is_published)",
            [
                'title' => trim((string)$data['title']),
                'slug' => $slug,
                'category' => trim((string)$data['category_name']),
                'category_id' => (int)$data['category_id'],
                'excerpt' => trim((string)$data['excerpt']),
                'content' => trim((string)$data['content']),
                'image' => trim((string)$data['image']),
                'seo_title' => trim((string)($data['seo_title'] ?? '')),
                'seo_description' => trim((string)($data['seo_description'] ?? '')),
                'seo_keywords' => trim((string)($data['seo_keywords'] ?? '')),
                'seo_image' => trim((string)($data['seo_image'] ?? '')),
                'hide_placeholder_image' => !empty($data['hide_placeholder_image']) ? 1 : 0,
                'show_on_home' => !empty($data['show_on_home']) ? 1 : 0,
                'author_id' => $data['author_id'] ? (int)$data['author_id'] : null,
                'author_name' => trim((string)$data['author_name']),
                'author_role' => trim((string)$data['author_role']) ?: 'user',
                'published_at' => $this->normalizePublishedAt((string)$data['published_at']),
                'is_published' => !empty($data['is_published']) ? 1 : 0,
            ]
        );

        return (int)db()->getInsertId();
    }

    /**
     * Обновляет существующий пост.
     */
    public function updatePost(int $id, array $data): void
    {
        $this->ensureSchema();
        $slugSource = (string)($data['slug'] ?: $data['title']);
        $slug = $this->makeUniquePostSlug($this->makeSlug($slugSource), $id);

        db()->query(
            "UPDATE {$this->postsTable}
             SET title = :title,
                 slug = :slug,
                 category = :category,
                 category_id = :category_id,
                 excerpt = :excerpt,
                 content = :content,
                 image = :image,
                 seo_title = :seo_title,
                 seo_description = :seo_description,
                 seo_keywords = :seo_keywords,
                 seo_image = :seo_image,
                 hide_placeholder_image = :hide_placeholder_image,
                 show_on_home = :show_on_home,
                 published_at = :published_at,
                 is_published = :is_published
             WHERE id = :id",
            [
                'id' => $id,
                'title' => trim((string)$data['title']),
                'slug' => $slug,
                'category' => trim((string)$data['category_name']),
                'category_id' => (int)$data['category_id'],
                'excerpt' => trim((string)$data['excerpt']),
                'content' => trim((string)$data['content']),
                'image' => trim((string)$data['image']),
                'seo_title' => trim((string)($data['seo_title'] ?? '')),
                'seo_description' => trim((string)($data['seo_description'] ?? '')),
                'seo_keywords' => trim((string)($data['seo_keywords'] ?? '')),
                'seo_image' => trim((string)($data['seo_image'] ?? '')),
                'hide_placeholder_image' => !empty($data['hide_placeholder_image']) ? 1 : 0,
                'show_on_home' => !empty($data['show_on_home']) ? 1 : 0,
                'published_at' => $this->normalizePublishedAt((string)$data['published_at']),
                'is_published' => !empty($data['is_published']) ? 1 : 0,
            ]
        );
    }

    /**
     * Удаляет пост по идентификатору.
     */
    public function deletePost(int $id): void
    {
        $this->ensureSchema();
        db()->query("DELETE FROM {$this->postsTable} WHERE id = ?", [$id]);
    }

    /**
     * Возвращает список всех категорий с количеством постов.
     */
    public function getCategories(): array
    {
        $this->ensureSchema();

        return db()->query(
            "SELECT c.id, c.name, c.name_ru, c.name_en, c.slug, COUNT(p.id) AS posts_count
             FROM {$this->categoriesTable} c
             LEFT JOIN {$this->postsTable} p ON p.category_id = c.id
             GROUP BY c.id, c.name, c.name_ru, c.name_en, c.slug
             ORDER BY c.id DESC"
        )->get() ?: [];
    }

    /**
     * Возвращает категории для административной таблицы с пагинацией.
     */
    public function getPaginatedCategories(array $options = []): array
    {
        $this->ensureSchema();

        $perPage = max(1, (int)($options['per_page'] ?? 15));
        $search = trim((string)($options['search'] ?? ''));
        $sort = (string)($options['sort'] ?? 'id');
        $direction = strtolower((string)($options['direction'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

        $sortMap = [
            'id' => 'c.id',
            'name_ru' => 'c.name_ru',
            'name_en' => 'c.name_en',
            'slug' => 'c.slug',
            'posts_count' => 'posts_count',
        ];

        $orderBy = $sortMap[$sort] ?? 'c.id';
        $where = '';
        $params = [];

        if ($search !== '') {
            $where = "WHERE c.name LIKE ? OR c.name_ru LIKE ? OR c.name_en LIKE ? OR c.slug LIKE ?";
            $searchLike = '%' . $search . '%';
            $params = [$searchLike, $searchLike, $searchLike, $searchLike];
        }

        $total = (int)db()->query(
            "SELECT COUNT(*)
             FROM {$this->categoriesTable} c
             {$where}",
            $params
        )->getColumn();

        $pagination = new Pagination($total, $perPage);
        $offset = $pagination->getOffset();

        $items = db()->query(
            "SELECT c.id, c.name, c.name_ru, c.name_en, c.slug, COUNT(p.id) AS posts_count
             FROM {$this->categoriesTable} c
             LEFT JOIN {$this->postsTable} p ON p.category_id = c.id
             {$where}
             GROUP BY c.id, c.name, c.name_ru, c.name_en, c.slug
             ORDER BY {$orderBy} {$direction}, c.id DESC
             LIMIT {$offset}, {$perPage}",
            $params
        )->get() ?: [];

        return [
            'items' => $items,
            'total' => $total,
            'pagination' => $pagination,
            'search' => $search,
            'sort' => $sort,
            'direction' => strtolower($direction),
            'per_page' => $perPage,
        ];
    }

    /**
     * Ищет категорию по идентификатору.
     */
    public function findCategoryById(int $id): array|false
    {
        $this->ensureSchema();
        return db()->findOne($this->categoriesTable, $id);
    }

    /**
     * Создаёт новую категорию блога.
     */
    public function createCategory(string $nameRu, string $nameEn, string $slug, array $seo = []): int
    {
        $this->ensureSchema();
        $slug = $this->makeUniqueCategorySlug($this->makeSlug($slug));
        $nameRu = trim($nameRu);
        $nameEn = trim($nameEn);
        $name = $nameRu ?: $nameEn;

        db()->query(
            "INSERT INTO {$this->categoriesTable} (name, name_ru, name_en, slug, seo_title, seo_description, seo_keywords, seo_image, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $name,
                $nameRu,
                $nameEn,
                $slug,
                trim((string)($seo['seo_title'] ?? '')),
                trim((string)($seo['seo_description'] ?? '')),
                trim((string)($seo['seo_keywords'] ?? '')),
                trim((string)($seo['seo_image'] ?? '')),
                date('Y-m-d H:i:s'),
            ]
        );

        return (int)db()->getInsertId();
    }

    /**
     * Обновляет категорию и синхронизирует её в связанных постах.
     */
    public function updateCategory(int $id, string $nameRu, string $nameEn, string $slug, array $seo = []): void
    {
        $this->ensureSchema();
        $slug = $this->makeUniqueCategorySlug($this->makeSlug($slug), $id);
        $nameRu = trim($nameRu);
        $nameEn = trim($nameEn);
        $name = $nameRu ?: $nameEn;

        db()->query(
            "UPDATE {$this->categoriesTable} SET name = ?, name_ru = ?, name_en = ?, slug = ?, seo_title = ?, seo_description = ?, seo_keywords = ?, seo_image = ? WHERE id = ?",
            [
                $name,
                $nameRu,
                $nameEn,
                $slug,
                trim((string)($seo['seo_title'] ?? '')),
                trim((string)($seo['seo_description'] ?? '')),
                trim((string)($seo['seo_keywords'] ?? '')),
                trim((string)($seo['seo_image'] ?? '')),
                $id,
            ]
        );

        db()->query(
            "UPDATE {$this->postsTable} SET category = ?, category_id = ? WHERE category_id = ?",
            [$name, $id, $id]
        );
    }

    /**
     * Удаляет категорию, если в ней нет постов.
     */
    public function deleteCategory(int $id): bool
    {
        $this->ensureSchema();
        $category = $this->findCategoryById($id);
        if (!$category) {
            return false;
        }

        $postCount = (int)db()->query(
            "SELECT COUNT(*)
             FROM {$this->postsTable}
             WHERE category_id = ?
                OR ((category_id IS NULL OR category_id = 0) AND category = ?)",
            [$id, (string)$category['name']]
        )->getColumn();
        if ($postCount > 0) {
            return false;
        }

        db()->query("DELETE FROM {$this->categoriesTable} WHERE id = ?", [$id]);
        return true;
    }

    /**
     * Возвращает список пользователей для административного обзора.
     */
    public function getUsers(): array
    {
        return db()->query("SELECT id, name, email, role, created_at FROM users ORDER BY id DESC")->get() ?: [];
    }

    /**
     * Связывает старое текстовое поле категории с новой таблицей категорий.
     */
    protected function syncLegacyCategoryColumn(): void
    {
        $legacyExists = (bool)db()->query("SHOW COLUMNS FROM {$this->postsTable} LIKE 'category'")->getColumn();
        if (!$legacyExists) {
            return;
        }

        $categories = db()->query(
            "SELECT DISTINCT category FROM {$this->postsTable}
             WHERE category IS NOT NULL
               AND category != ''
               AND (category_id IS NULL OR category_id = 0)"
        )->get() ?: [];

        foreach ($categories as $row) {
            $name = trim((string)$row['category']);
            if ($name === '') {
                continue;
            }

            $slug = $this->makeUniqueCategorySlug($this->makeSlug($name));
            db()->query(
                "INSERT IGNORE INTO {$this->categoriesTable} (name, name_ru, name_en, slug, created_at)
                 VALUES (?, ?, ?, ?, ?)",
                [$name, $name, $name, $slug, date('Y-m-d H:i:s')]
            );
        }

        db()->query(
            "UPDATE {$this->postsTable} p
             INNER JOIN {$this->categoriesTable} c ON c.name = p.category
             SET p.category_id = c.id
             WHERE p.category_id IS NULL OR p.category_id = 0"
        );
    }

    /**
     * Добавляет и заполняет языковые поля категорий в старых схемах.
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
     * Добавляет SEO-поля категорий, если их ещё нет в таблице.
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
     * Добавляет авторские поля в таблицу постов и заполняет их значениями по умолчанию.
     */
    protected function ensureAuthorColumns(): void
    {
        $authorIdExists = (bool)db()->query("SHOW COLUMNS FROM {$this->postsTable} LIKE 'author_id'")->getColumn();
        if (!$authorIdExists) {
            db()->query("ALTER TABLE {$this->postsTable} ADD COLUMN author_id INT(10) UNSIGNED NULL AFTER image");
        }

        $authorNameExists = (bool)db()->query("SHOW COLUMNS FROM {$this->postsTable} LIKE 'author_name'")->getColumn();
        if (!$authorNameExists) {
            db()->query("ALTER TABLE {$this->postsTable} ADD COLUMN author_name VARCHAR(100) NULL AFTER author_id");
        }

        $authorRoleExists = (bool)db()->query("SHOW COLUMNS FROM {$this->postsTable} LIKE 'author_role'")->getColumn();
        if (!$authorRoleExists) {
            db()->query("ALTER TABLE {$this->postsTable} ADD COLUMN author_role VARCHAR(20) NOT NULL DEFAULT 'user' AFTER author_name");
        }

        db()->query(
            "UPDATE {$this->postsTable}
             SET author_name = COALESCE(NULLIF(author_name, ''), 'Fireball'),
                 author_role = COALESCE(NULLIF(author_role, ''), 'admin')
             WHERE author_name IS NULL OR author_name = '' OR author_role IS NULL OR author_role = ''"
        );
    }

    /**
     * Добавляет счётчик просмотров в таблицу постов.
     */
    protected function ensureViewsColumn(): void
    {
        $viewsCountExists = (bool)db()->query("SHOW COLUMNS FROM {$this->postsTable} LIKE 'views_count'")->getColumn();
        if (!$viewsCountExists) {
            db()->query("ALTER TABLE {$this->postsTable} ADD COLUMN views_count INT(10) UNSIGNED NOT NULL DEFAULT 0 AFTER author_role");
        }
    }

    /**
     * Добавляет SEO-поля постов в старых схемах.
     */
    protected function ensurePostSeoColumns(): void
    {
        $seoTitleExists = (bool)db()->query("SHOW COLUMNS FROM {$this->postsTable} LIKE 'seo_title'")->getColumn();
        if (!$seoTitleExists) {
            db()->query("ALTER TABLE {$this->postsTable} ADD COLUMN seo_title VARCHAR(255) NULL AFTER image");
        }

        $seoDescriptionExists = (bool)db()->query("SHOW COLUMNS FROM {$this->postsTable} LIKE 'seo_description'")->getColumn();
        if (!$seoDescriptionExists) {
            db()->query("ALTER TABLE {$this->postsTable} ADD COLUMN seo_description TEXT NULL AFTER seo_title");
        }

        $seoKeywordsExists = (bool)db()->query("SHOW COLUMNS FROM {$this->postsTable} LIKE 'seo_keywords'")->getColumn();
        if (!$seoKeywordsExists) {
            db()->query("ALTER TABLE {$this->postsTable} ADD COLUMN seo_keywords TEXT NULL AFTER seo_description");
        }

        $seoImageExists = (bool)db()->query("SHOW COLUMNS FROM {$this->postsTable} LIKE 'seo_image'")->getColumn();
        if (!$seoImageExists) {
            db()->query("ALTER TABLE {$this->postsTable} ADD COLUMN seo_image VARCHAR(255) NULL AFTER seo_keywords");
        }
    }

    /**
     * Добавляет флаг скрытия заглушки изображения у постов.
     */
    protected function ensureHidePlaceholderImageColumn(): void
    {
        $columnExists = (bool)db()->query("SHOW COLUMNS FROM {$this->postsTable} LIKE 'hide_placeholder_image'")->getColumn();
        if (!$columnExists) {
            db()->query("ALTER TABLE {$this->postsTable} ADD COLUMN hide_placeholder_image TINYINT(3) UNSIGNED NOT NULL DEFAULT 0 AFTER image");
        }
    }

    /**
     * Добавляет флаг показа поста на главной странице.
     */
    protected function ensureShowOnHomeColumn(): void
    {
        $columnExists = (bool)db()->query("SHOW COLUMNS FROM {$this->postsTable} LIKE 'show_on_home'")->getColumn();
        if (!$columnExists) {
            db()->query("ALTER TABLE {$this->postsTable} ADD COLUMN show_on_home TINYINT(3) UNSIGNED NOT NULL DEFAULT 0 AFTER hide_placeholder_image");
        }
    }

    /**
     * Приводит дату публикации к формату базы данных.
     */
    protected function normalizePublishedAt(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return date('Y-m-d H:i:s');
        }

        $timestamp = strtotime($value);
        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : date('Y-m-d H:i:s');
    }

    /**
     * Генерирует уникальный slug для поста.
     */
    protected function makeUniquePostSlug(string $slug, ?int $ignoreId = null): string
    {
        $base = $slug ?: 'post';
        $candidate = $base;
        $counter = 1;

        while ($this->postSlugExists($candidate, $ignoreId)) {
            $candidate = $base . '-' . $counter;
            $counter++;
        }

        return $candidate;
    }

    /**
     * Генерирует уникальный slug для категории.
     */
    protected function makeUniqueCategorySlug(string $slug, ?int $ignoreId = null): string
    {
        $base = $slug ?: 'category';
        $candidate = $base;
        $counter = 1;

        while ($this->categorySlugExists($candidate, $ignoreId)) {
            $candidate = $base . '-' . $counter;
            $counter++;
        }

        return $candidate;
    }

    /**
     * Проверяет, занят ли slug поста другим постом.
     */
    protected function postSlugExists(string $slug, ?int $ignoreId = null): bool
    {
        if ($ignoreId) {
            return (int)db()->query(
                "SELECT COUNT(*) FROM {$this->postsTable} WHERE slug = ? AND id != ?",
                [$slug, $ignoreId]
            )->getColumn() > 0;
        }

        return (int)db()->query("SELECT COUNT(*) FROM {$this->postsTable} WHERE slug = ?", [$slug])->getColumn() > 0;
    }

    /**
     * Проверяет, занят ли slug категории другой категорией.
     */
    protected function categorySlugExists(string $slug, ?int $ignoreId = null): bool
    {
        if ($ignoreId) {
            return (int)db()->query(
                "SELECT COUNT(*) FROM {$this->categoriesTable} WHERE slug = ? AND id != ?",
                [$slug, $ignoreId]
            )->getColumn() > 0;
        }

        return (int)db()->query("SELECT COUNT(*) FROM {$this->categoriesTable} WHERE slug = ?", [$slug])->getColumn() > 0;
    }

    /**
     * Нормализует строку в slug административной сущности.
     */
    protected function makeSlug(string $value): string
    {
        return make_slug($value, 'item');
    }

}
