<?php

namespace App\Models;

use App\Modules\BlockEditor\BlockRenderer;
use FBL\Model;
use FBL\Pagination;

/**
 * Manages CMS pages and navigation placement.
 */
class Page extends Model
{

    protected string $table = 'pages';
    public bool $timestamps = false;
    protected static bool $schemaReady = false;
    protected static array $runtimeCache = [];

    protected array $reservedSlugs = [
        'admin',
        'add-to-cart',
        'chat',
        'contacts',
        'forgot-password',
        'login',
        'logout',
        'notifications',
        'posts',
        'product',
        'profile',
        'register',
        'remove-from-cart',
        'reset-password',
        'search',
    ];

    public static function clearPublicCache(): void
    {
        self::$runtimeCache = [];
        cache()->set('pages:public_version', (string)microtime(true), 31536000);
        cache()->remove('pages:menu:header');
        cache()->remove('pages:menu:footer');
        cache()->remove('pages:menu:legal_information');
    }

    protected function publicCacheKey(string $name): string
    {
        return 'pages:v' . $this->publicCacheVersion() . ':' . $name;
    }

    protected function publicCacheVersion(): string
    {
        return (string)cache()->get('pages:public_version', '1');
    }

    /**
     * Creates the pages table when it is missing and keeps older local schemas usable.
     */
    public function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }

        db()->query(
            "CREATE TABLE IF NOT EXISTS {$this->table} (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                title VARCHAR(255) NOT NULL,
                menu_title VARCHAR(255) NULL,
                slug VARCHAR(255) NOT NULL,
                content MEDIUMTEXT NOT NULL,
                meta_title VARCHAR(255) NULL,
                meta_description TEXT NULL,
                is_published TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                show_in_header TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                show_in_footer TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                show_in_legal_information TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                menu_order INT(10) UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY slug (slug),
                KEY published_order (is_published, menu_order, title),
                KEY header_order (show_in_header, is_published, menu_order),
                KEY footer_order (show_in_footer, is_published, menu_order),
                KEY show_in_legal_information (show_in_legal_information, is_published, menu_order, title)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->ensureColumns();
        self::$schemaReady = true;
    }

    /**
     * Adds missing columns for clients that already have an older pages table.
     */
    protected function ensureColumns(): void
    {
        $columns = [
            'menu_title' => "ALTER TABLE {$this->table} ADD COLUMN menu_title VARCHAR(255) NULL AFTER title",
            'content' => "ALTER TABLE {$this->table} ADD COLUMN content MEDIUMTEXT NOT NULL AFTER slug",
            'meta_title' => "ALTER TABLE {$this->table} ADD COLUMN meta_title VARCHAR(255) NULL AFTER content",
            'meta_description' => "ALTER TABLE {$this->table} ADD COLUMN meta_description TEXT NULL AFTER meta_title",
            'is_published' => "ALTER TABLE {$this->table} ADD COLUMN is_published TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER meta_description",
            'show_in_header' => "ALTER TABLE {$this->table} ADD COLUMN show_in_header TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER is_published",
            'show_in_footer' => "ALTER TABLE {$this->table} ADD COLUMN show_in_footer TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER show_in_header",
            'show_in_legal_information' => "ALTER TABLE {$this->table} ADD COLUMN show_in_legal_information TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER show_in_footer",
            'menu_order' => "ALTER TABLE {$this->table} ADD COLUMN menu_order INT(10) UNSIGNED NOT NULL DEFAULT 0 AFTER show_in_legal_information",
            'created_at' => "ALTER TABLE {$this->table} ADD COLUMN created_at DATETIME NOT NULL AFTER menu_order",
            'updated_at' => "ALTER TABLE {$this->table} ADD COLUMN updated_at DATETIME NOT NULL AFTER created_at",
        ];

        foreach ($columns as $column => $sql) {
            $exists = (bool)db()->query("SHOW COLUMNS FROM {$this->table} LIKE ?", [$column])->getColumn();
            if (!$exists) {
                db()->query($sql);
            }
        }

        $legalIndexExists = (bool)db()->query(
            "SHOW INDEX FROM {$this->table} WHERE Key_name = 'show_in_legal_information'"
        )->getOne();
        if (!$legalIndexExists) {
            db()->query(
                "ALTER TABLE {$this->table}
                 ADD KEY show_in_legal_information (show_in_legal_information, is_published, menu_order, title)"
            );
        }
    }

    /**
     * Returns admin table rows with search, sorting and pagination.
     */
    public function getPaginated(array $options = []): array
    {
        $this->ensureSchema();

        $perPage = max(1, (int)($options['per_page'] ?? 20));
        $search = trim((string)($options['search'] ?? ''));
        $sort = (string)($options['sort'] ?? 'menu_order');
        $direction = strtolower((string)($options['direction'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
        $sortMap = [
            'id' => 'id',
            'title' => 'title',
            'menu_title' => 'menu_title',
            'slug' => 'slug',
            'status' => 'is_published',
            'show_in_header' => 'show_in_header',
            'show_in_footer' => 'show_in_footer',
            'show_in_legal_information' => 'show_in_legal_information',
            'menu_order' => 'menu_order',
            'updated_at' => 'updated_at',
        ];
        $orderBy = $sortMap[$sort] ?? 'menu_order';
        $params = [];
        $where = '';

        if ($search !== '') {
            $where = "WHERE title LIKE ? OR menu_title LIKE ? OR slug LIKE ? OR meta_title LIKE ? OR meta_description LIKE ?";
            $like = '%' . $search . '%';
            $params = [$like, $like, $like, $like, $like];
        }

        $total = (int)db()->query("SELECT COUNT(*) FROM {$this->table} {$where}", $params)->getColumn();
        $pagination = new Pagination($total, $perPage);
        $offset = $pagination->getOffset();

        $items = db()->query(
            "SELECT id, title, menu_title, slug, is_published, show_in_header, show_in_footer, show_in_legal_information, menu_order, created_at, updated_at
             FROM {$this->table}
             {$where}
             ORDER BY {$orderBy} {$direction}, id DESC
             LIMIT {$offset}, {$perPage}",
            $params
        )->get() ?: [];

        return [
            'items' => array_map(fn(array $page): array => $this->normalizePage($page), $items),
            'total' => $total,
            'pagination' => $pagination,
            'search' => $search,
            'sort' => $sort,
            'direction' => strtolower($direction),
            'per_page' => $perPage,
        ];
    }

    /**
     * Returns admin rows split by publication status, mirroring the posts table workflow.
     */
    public function getPagesByPublicationStatus(int $status, array $options = []): array
    {
        $this->ensureSchema();

        $perPage = max(1, (int)($options['per_page'] ?? 15));
        $pageParam = (string)($options['page_param'] ?? 'page');
        $search = trim((string)($options['search'] ?? ''));
        $sort = (string)($options['sort'] ?? 'menu_order');
        $direction = strtolower((string)($options['direction'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
        $sortMap = [
            'id' => 'id',
            'title' => 'title',
            'menu_title' => 'menu_title',
            'slug' => 'slug',
            'status' => 'is_published',
            'menu_order' => 'menu_order',
            'updated_at' => 'updated_at',
        ];
        $orderBy = $sortMap[$sort] ?? 'menu_order';
        $whereParts = ['is_published = ?'];
        $params = [(int)$status === 1 ? 1 : 0];

        if ($search !== '') {
            $whereParts[] = "(title LIKE ? OR menu_title LIKE ? OR slug LIKE ? OR meta_title LIKE ? OR meta_description LIKE ?)";
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }

        $where = 'WHERE ' . implode(' AND ', $whereParts);
        $total = (int)db()->query("SELECT COUNT(*) FROM {$this->table} {$where}", $params)->getColumn();
        $pagination = new Pagination($total, $perPage, PAGINATION_SETTINGS['midSize'], PAGINATION_SETTINGS['maxPages'], PAGINATION_SETTINGS['tpl'], $pageParam);
        $offset = $pagination->getOffset();

        $items = db()->query(
            "SELECT id, title, menu_title, slug, is_published, show_in_header, show_in_footer, show_in_legal_information, menu_order, created_at, updated_at
             FROM {$this->table}
             {$where}
             ORDER BY {$orderBy} {$direction}, id DESC
             LIMIT {$offset}, {$perPage}",
            $params
        )->get() ?: [];

        return [
            'items' => array_map(fn(array $page): array => $this->normalizePage($page), $items),
            'total' => $total,
            'pagination' => $pagination,
            'search' => $search,
            'sort' => $sort,
            'direction' => strtolower($direction),
            'per_page' => $perPage,
        ];
    }

    /**
     * Finds a page by ID for admin editing.
     */
    public function findById(int $id): array|false
    {
        $this->ensureSchema();

        $page = db()->query(
            "SELECT {$this->pageSelectColumns()}
             FROM {$this->table}
             WHERE id = ?
             LIMIT 1",
            [$id]
        )->getOne();

        return $page ? $this->normalizePage($page) : false;
    }

    /**
     * Finds a published page by URL slug.
     */
    public function findPublishedBySlug(string $slug): array|false
    {
        $this->ensureSchema();
        $slug = trim($slug, '/');
        $cacheKey = $this->publicCacheKey('published:' . md5($slug));
        $cached = cache()->get($cacheKey);
        if (is_array($cached)) {
            return $cached ?: false;
        }

        $page = db()->query(
            "SELECT {$this->pageSelectColumns()}
             FROM {$this->table}
             WHERE slug = ? AND is_published = 1
             LIMIT 1",
            [$slug]
        )->getOne();

        if (!$page) {
            cache()->set($cacheKey, [], 300);
            return false;
        }

        $item = $this->normalizePage($page);
        cache()->set($cacheKey, $item, 600);

        return $item;
    }

    /**
     * Finds a published page by ID for public rendering.
     */
    public function findPublishedById(int $id): array|false
    {
        if ($id <= 0) {
            return false;
        }

        $this->ensureSchema();
        $cacheKey = $this->publicCacheKey('published_id:' . $id);
        $cached = cache()->get($cacheKey);
        if (is_array($cached)) {
            return $cached ?: false;
        }

        $page = db()->query(
            "SELECT {$this->pageSelectColumns()}
             FROM {$this->table}
             WHERE id = ? AND is_published = 1
             LIMIT 1",
            [$id]
        )->getOne();

        if (!$page) {
            cache()->set($cacheKey, [], 300);
            return false;
        }

        $item = $this->normalizePage($page);
        cache()->set($cacheKey, $item, 600);

        return $item;
    }

    /**
     * Returns published pages for settings dropdowns.
     */
    public function getPublishedOptions(): array
    {
        $this->ensureSchema();

        $pages = db()->query(
            "SELECT id, title, menu_title, slug
             FROM {$this->table}
             WHERE is_published = 1
             ORDER BY title ASC, id ASC"
        )->get() ?: [];

        return array_map(static function (array $page): array {
            $title = trim((string)($page['title'] ?? ''));
            $menuTitle = trim((string)($page['menu_title'] ?? ''));

            return [
                'id' => (int)$page['id'],
                'title' => $title,
                'label' => $menuTitle !== '' ? $menuTitle : $title,
                'slug' => trim((string)($page['slug'] ?? '')),
            ];
        }, $pages);
    }

    public function searchPublished(string $query, int $limit = 50): array
    {
        $this->ensureSchema();
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $limit = max(1, min(100, $limit));
        $like = '%' . $query . '%';
        $pages = db()->query(
            "SELECT id, title, slug, content, meta_description
             FROM {$this->table}
             WHERE is_published = 1
               AND (title LIKE ? OR content LIKE ? OR meta_description LIKE ?)
             ORDER BY menu_order ASC, title ASC
             LIMIT {$limit}",
            [$like, $like, $like]
        )->get() ?: [];

        return array_map(static function (array $page): array {
            $excerpt = trim((string)($page['meta_description'] ?? ''));
            if ($excerpt === '') {
                $content = trim((string)($page['content'] ?? ''));
                if ($content !== '' && $content[0] === '{') {
                    $content = (new BlockRenderer())->renderPublicContent($content);
                }
                $excerpt = mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags($content))), 0, 180);
            }

            return [
                'title' => (string)$page['title'],
                'url' => base_href('/' . ltrim((string)$page['slug'], '/')),
                'excerpt' => $excerpt,
                'type' => 'page',
            ];
        }, $pages);
    }

    /**
     * Creates a page.
     */
    public function createPage(array $data): int
    {
        $this->ensureSchema();
        $now = date('Y-m-d H:i:s');

        db()->query(
            "INSERT INTO {$this->table}
             (title, menu_title, slug, content, meta_title, meta_description, is_published, show_in_header, show_in_footer, show_in_legal_information, menu_order, created_at, updated_at)
             VALUES (:title, :menu_title, :slug, :content, :meta_title, :meta_description, :is_published, :show_in_header, :show_in_footer, :show_in_legal_information, :menu_order, :created_at, :updated_at)",
            [
                'title' => trim((string)$data['title']),
                'menu_title' => trim((string)($data['menu_title'] ?? '')),
                'slug' => $this->makeSlug((string)($data['slug'] ?: $data['title'])),
                'content' => trim((string)($data['content'] ?? '')),
                'meta_title' => trim((string)($data['meta_title'] ?? '')),
                'meta_description' => trim((string)($data['meta_description'] ?? '')),
                'is_published' => !empty($data['is_published']) ? 1 : 0,
                'show_in_header' => !empty($data['show_in_header']) ? 1 : 0,
                'show_in_footer' => !empty($data['show_in_footer']) ? 1 : 0,
                'show_in_legal_information' => !empty($data['show_in_legal_information']) ? 1 : 0,
                'menu_order' => max(0, (int)($data['menu_order'] ?? 0)),
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        self::clearPublicCache();

        return (int)db()->getInsertId();
    }

    /**
     * Updates a page.
     */
    public function updatePage(int $id, array $data): void
    {
        $this->ensureSchema();

        db()->query(
            "UPDATE {$this->table}
             SET title = :title,
                 menu_title = :menu_title,
                 slug = :slug,
                 content = :content,
                 meta_title = :meta_title,
                 meta_description = :meta_description,
                 is_published = :is_published,
                 show_in_header = :show_in_header,
                 show_in_footer = :show_in_footer,
                 show_in_legal_information = :show_in_legal_information,
                 menu_order = :menu_order,
                 updated_at = :updated_at
             WHERE id = :id",
            [
                'id' => $id,
                'title' => trim((string)$data['title']),
                'menu_title' => trim((string)($data['menu_title'] ?? '')),
                'slug' => $this->makeSlug((string)($data['slug'] ?: $data['title'])),
                'content' => trim((string)($data['content'] ?? '')),
                'meta_title' => trim((string)($data['meta_title'] ?? '')),
                'meta_description' => trim((string)($data['meta_description'] ?? '')),
                'is_published' => !empty($data['is_published']) ? 1 : 0,
                'show_in_header' => !empty($data['show_in_header']) ? 1 : 0,
                'show_in_footer' => !empty($data['show_in_footer']) ? 1 : 0,
                'show_in_legal_information' => !empty($data['show_in_legal_information']) ? 1 : 0,
                'menu_order' => max(0, (int)($data['menu_order'] ?? 0)),
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        );
        self::clearPublicCache();
    }

    /**
     * Deletes a page.
     */
    public function deletePage(int $id): void
    {
        $this->ensureSchema();
        db()->query("DELETE FROM {$this->table} WHERE id = ?", [$id]);
        self::clearPublicCache();
    }

    /**
     * Toggles publication status and returns the new status.
     */
    public function togglePublished(int $id): ?int
    {
        $this->ensureSchema();

        $page = db()->query("SELECT id, is_published FROM {$this->table} WHERE id = ? LIMIT 1", [$id])->getOne();
        if (!$page) {
            return null;
        }

        $nextStatus = (int)$page['is_published'] === 1 ? 0 : 1;
        db()->query(
            "UPDATE {$this->table}
             SET is_published = ?, updated_at = ?
             WHERE id = ?",
            [$nextStatus, date('Y-m-d H:i:s'), $id]
        );
        self::clearPublicCache();

        return $nextStatus;
    }

    /**
     * Returns published pages for a named menu location.
     */
    public function getMenuPages(string $location): array
    {
        $this->ensureSchema();

        $locations = [
            'header' => 'show_in_header',
            'footer' => 'show_in_footer',
            'legal_information' => 'show_in_legal_information',
        ];
        $location = array_key_exists($location, $locations) ? $location : 'header';
        $languageCode = (string)(app()->get('lang')['code'] ?? DEFAULT_LOCALE);
        $cacheKey = $this->publicCacheKey('menu:' . $location . ':' . $languageCode);
        if (array_key_exists($cacheKey, self::$runtimeCache)) {
            return self::$runtimeCache[$cacheKey];
        }

        $cached = cache()->get($cacheKey);
        if (is_array($cached)) {
            return self::$runtimeCache[$cacheKey] = $cached;
        }

        $column = $locations[$location];
        $pages = db()->query(
            "SELECT id, title, menu_title, slug, menu_order
             FROM {$this->table}
             WHERE is_published = 1 AND {$column} = 1
             ORDER BY menu_order ASC, title ASC, id ASC"
        )->get() ?: [];

        $items = array_map(function (array $page): array {
            $title = trim((string)($page['title'] ?? ''));
            $menuTitle = trim((string)($page['menu_title'] ?? ''));
            $slug = trim((string)$page['slug'], '/');

            return [
                'id' => (int)$page['id'],
                'href' => base_href('/' . $slug),
                'label' => $this->localizedMenuLabel($slug, $menuTitle !== '' ? $menuTitle : $title),
                'menu_order' => (int)($page['menu_order'] ?? 0),
            ];
        }, $pages);
        if ($location === 'legal_information') {
            usort($items, static function (array $left, array $right): int {
                return [$left['menu_order'], mb_strtolower($left['label']), $left['id']]
                    <=> [$right['menu_order'], mb_strtolower($right['label']), $right['id']];
            });
        }
        cache()->set($cacheKey, $items, 600);

        return self::$runtimeCache[$cacheKey] = $items;
    }

    public function getLegalInformationMenu(): array
    {
        return $this->getMenuPages('legal_information');
    }

    /**
     * Checks exact slug uniqueness for validation.
     */
    public function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        $this->ensureSchema();
        $slug = $this->makeSlug($slug);

        if ($ignoreId !== null && $ignoreId > 0) {
            return (int)db()->query(
                "SELECT COUNT(*) FROM {$this->table} WHERE slug = ? AND id != ?",
                [$slug, $ignoreId]
            )->getColumn() > 0;
        }

        return (int)db()->query("SELECT COUNT(*) FROM {$this->table} WHERE slug = ?", [$slug])->getColumn() > 0;
    }

    /**
     * Prevents pages from shadowing existing first-level routes.
     */
    public function isReservedSlug(string $slug): bool
    {
        return in_array($this->makeSlug($slug), $this->reservedSlugs, true)
            || array_key_exists($this->makeSlug($slug), LANGS);
    }

    /**
     * Normalizes a page for templates.
     */
    protected function normalizePage(array $page): array
    {
        $page['title'] = trim((string)($page['title'] ?? 'Untitled'));
        $page['menu_title'] = trim((string)($page['menu_title'] ?? ''));
        $page['menu_label'] = $page['menu_title'] !== '' ? $page['menu_title'] : $page['title'];
        $page['slug'] = trim((string)($page['slug'] ?? ''));
        $page['content'] = trim((string)($page['content'] ?? ''));
        $page['meta_title'] = trim((string)($page['meta_title'] ?? ''));
        $page['meta_description'] = trim((string)($page['meta_description'] ?? ''));
        $page['seo_title'] = $page['meta_title'] !== '' ? $page['meta_title'] : $page['title'];
        $page['seo_description'] = $page['meta_description'];
        $page['seo_keywords'] = '';
        $page['url'] = base_href('/' . ltrim($page['slug'], '/'));
        $page['is_published'] = (int)($page['is_published'] ?? 0);
        $page['show_in_header'] = (int)($page['show_in_header'] ?? 0);
        $page['show_in_footer'] = (int)($page['show_in_footer'] ?? 0);
        $page['show_in_legal_information'] = (int)($page['show_in_legal_information'] ?? 0);
        $page['menu_order'] = max(0, (int)($page['menu_order'] ?? 0));
        $page['created_at'] = (string)($page['created_at'] ?? '');
        $page['updated_at'] = (string)($page['updated_at'] ?? '');

        if ($page['content'] !== '') {
            $page['content'] = (new BlockRenderer())->renderPublicContent($page['content']);
        }

        if ($page['content'] !== '' && $page['content'] === strip_tags($page['content'])) {
            $page['content'] = '<p>' . nl2br(htmlSC($page['content'])) . '</p>';
        }

        if ($page['meta_description'] === '') {
            $page['meta_description'] = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($page['content']))), 0, 160);
        }

        return $page;
    }

    /**
     * Returns the page fields used by public and admin page views.
     */
    protected function pageSelectColumns(): string
    {
        return 'id, title, menu_title, slug, content, meta_title, meta_description, is_published, show_in_header, show_in_footer, show_in_legal_information, menu_order, created_at, updated_at';
    }

    protected function localizedMenuLabel(string $slug, string $fallback): string
    {
        $translationKey = 'page_menu_' . str_replace('-', '_', $slug);
        $translated = return_translation($translationKey);

        return $translated !== $translationKey ? $translated : $fallback;
    }

    /**
     * Converts arbitrary text to a URL slug.
     */
    protected function makeSlug(string $value): string
    {
        return make_slug($value, 'page');
    }

}
