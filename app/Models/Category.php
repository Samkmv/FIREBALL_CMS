<?php

namespace App\Models;

/**
 * Работает с категориями блога из таблицы post_categories.
 */
class Category
{

    protected string $table = 'post_categories';
    protected static bool $schemaReady = false;

    public function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }

        db()->query(
            "CREATE TABLE IF NOT EXISTS {$this->table} (
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

        $this->ensureTranslationColumns();
        $this->ensureSeoColumns();
        self::$schemaReady = true;
    }

    public function nameSql(string $alias = ''): string
    {
        $prefix = $alias !== '' ? "{$alias}." : '';
        $column = match (app()->get('lang')['code'] ?? 'ru') {
            'en', 'de', 'zh-cn' => 'name_en',
            default => 'name_ru',
        };

        return "COALESCE(NULLIF({$prefix}{$column}, ''), {$prefix}name)";
    }

    public function getNavigationCategories(string $postsTable): array
    {
        $categoryNameSql = $this->nameSql('c');
        $categories = db()->query(
            "SELECT c.id, {$categoryNameSql} AS name, c.name_ru, c.name_en, c.slug, COUNT(p.id) AS total
             FROM {$this->table} c
             LEFT JOIN {$postsTable} p ON p.category_id = c.id AND p.is_published = 1
             GROUP BY c.id, c.name, c.name_ru, c.name_en, c.slug
             ORDER BY c.name ASC"
        )->get() ?: [];

        return array_map(fn(array $category): array => $this->normalizeListItem($category), $categories);
    }

    public function getSidebarCategories(string $postsTable): array
    {
        $categoryNameSql = $this->nameSql('c');
        $categories = db()->query(
            "SELECT c.id, {$categoryNameSql} AS name, c.name_ru, c.name_en, c.slug, COUNT(p.id) AS total
             FROM {$this->table} c
             LEFT JOIN {$postsTable} p ON p.category_id = c.id AND p.is_published = 1
             GROUP BY c.id, c.name, c.name_ru, c.name_en, c.slug
             HAVING total > 0
             ORDER BY total DESC, c.name ASC"
        )->get() ?: [];

        if (!empty($categories)) {
            return array_map(fn(array $category): array => $this->normalizeListItem($category), $categories);
        }

        return $this->getLegacySidebarCategories($postsTable);
    }

    public function getNameBySlug(string $slug, string $postsTable): string
    {
        $categoryNameSql = $this->nameSql();
        $name = db()->query(
            "SELECT {$categoryNameSql} FROM {$this->table} WHERE slug = ? LIMIT 1",
            [$slug]
        )->getColumn();

        if ($name) {
            return (string)$name;
        }

        $legacyCategories = db()->query(
            "SELECT DISTINCT category
             FROM {$postsTable}
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

    public function getMetaBySlug(string $slug): ?array
    {
        $categoryNameSql = $this->nameSql();
        $category = db()->query(
            "SELECT {$categoryNameSql} AS name, slug, seo_title, seo_description, seo_keywords, seo_image
             FROM {$this->table}
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

    public function syncLegacyPostCategories(string $postsTable): void
    {
        $oldCategoryExists = (bool)db()->query("SHOW COLUMNS FROM {$postsTable} LIKE 'category'")->getColumn();
        if ($oldCategoryExists) {
            $categories = db()->query(
                "SELECT DISTINCT category FROM {$postsTable}
                 WHERE category IS NOT NULL
                   AND category != ''
                   AND (category_id IS NULL OR category_id = 0)"
            )->get() ?: [];

            foreach ($categories as $category) {
                $name = trim((string)$category['category']);
                if ($name === '') {
                    continue;
                }

                db()->query(
                    "INSERT IGNORE INTO {$this->table} (name, name_ru, name_en, slug, created_at)
                     VALUES (?, ?, ?, ?, ?)",
                    [$name, $name, $name, $this->makeSlug($name), date('Y-m-d H:i:s')]
                );
                db()->query(
                    "UPDATE {$postsTable} p
                     INNER JOIN {$this->table} c ON c.name = p.category
                     SET p.category_id = c.id
                     WHERE (p.category_id IS NULL OR p.category_id = 0)
                       AND p.category = ?",
                    [$name]
                );
            }
        }

        db()->query(
            "UPDATE {$postsTable} p
             INNER JOIN (
                SELECT id FROM {$this->table} ORDER BY id ASC LIMIT 1
             ) c ON 1 = 1
             SET p.category_id = c.id
             WHERE p.category_id IS NULL OR p.category_id = 0"
        );
    }

    protected function getLegacySidebarCategories(string $postsTable): array
    {
        $legacyCategories = db()->query(
            "SELECT p.category AS name, COUNT(*) AS total
             FROM {$postsTable} p
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

    protected function normalizeListItem(array $category): array
    {
        return [
            'id' => (int)$category['id'],
            'name' => (string)$category['name'],
            'slug' => (string)$category['slug'],
            'label' => (string)$category['name'],
            'total' => (int)$category['total'],
        ];
    }

    protected function ensureTranslationColumns(): void
    {
        $nameRuExists = (bool)db()->query("SHOW COLUMNS FROM {$this->table} LIKE 'name_ru'")->getColumn();
        if (!$nameRuExists) {
            db()->query("ALTER TABLE {$this->table} ADD COLUMN name_ru VARCHAR(150) NULL AFTER name");
        }

        $nameEnExists = (bool)db()->query("SHOW COLUMNS FROM {$this->table} LIKE 'name_en'")->getColumn();
        if (!$nameEnExists) {
            db()->query("ALTER TABLE {$this->table} ADD COLUMN name_en VARCHAR(150) NULL AFTER name_ru");
        }

        db()->query(
            "UPDATE {$this->table}
             SET name_ru = COALESCE(NULLIF(name_ru, ''), name),
                 name_en = COALESCE(NULLIF(name_en, ''), name)
             WHERE name_ru IS NULL OR name_ru = '' OR name_en IS NULL OR name_en = ''"
        );
    }

    protected function ensureSeoColumns(): void
    {
        $columns = [
            'seo_title' => "ALTER TABLE {$this->table} ADD COLUMN seo_title VARCHAR(255) NULL AFTER slug",
            'seo_description' => "ALTER TABLE {$this->table} ADD COLUMN seo_description TEXT NULL AFTER seo_title",
            'seo_keywords' => "ALTER TABLE {$this->table} ADD COLUMN seo_keywords TEXT NULL AFTER seo_description",
            'seo_image' => "ALTER TABLE {$this->table} ADD COLUMN seo_image VARCHAR(255) NULL AFTER seo_keywords",
        ];

        foreach ($columns as $column => $sql) {
            $exists = (bool)db()->query("SHOW COLUMNS FROM {$this->table} LIKE ?", [$column])->getColumn();
            if (!$exists) {
                db()->query($sql);
            }
        }
    }

    protected function makeSlug(string $value): string
    {
        return make_slug($value, 'general');
    }

}
