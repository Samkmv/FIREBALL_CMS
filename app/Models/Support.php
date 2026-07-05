<?php

namespace App\Models;

use App\Modules\BlockEditor\BlockRenderer;
use FBL\Pagination;

/**
 * Stores support module content: FAQ, knowledge base categories and articles.
 */
class Support
{

    protected string $faqCategoriesTable = 'support_faq_categories';
    protected string $faqTable = 'support_faq';
    protected string $kbCategoriesTable = 'support_kb_categories';
    protected string $kbArticlesTable = 'support_kb_articles';
    protected string $kbArticleStatsTable = 'support_kb_article_stats';
    protected string $kbArticleVotesTable = 'support_kb_article_votes';
    protected static bool $schemaReady = false;

    public function ensureTableExists(): void
    {
        if (self::$schemaReady) {
            return;
        }

        db()->query(
            "CREATE TABLE IF NOT EXISTS {$this->faqCategoriesTable} (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(190) NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY sort_order (sort_order, id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        db()->query(
            "CREATE TABLE IF NOT EXISTS {$this->faqTable} (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                question VARCHAR(255) NOT NULL,
                answer TEXT NOT NULL,
                category_id INT(10) UNSIGNED NULL,
                sort_order INT NOT NULL DEFAULT 0,
                is_published TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY category_id (category_id),
                KEY published_sort (is_published, sort_order, id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        db()->query(
            "CREATE TABLE IF NOT EXISTS {$this->kbCategoriesTable} (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(190) NOT NULL,
                slug VARCHAR(190) NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY slug (slug),
                KEY sort_order (sort_order, id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        db()->query(
            "CREATE TABLE IF NOT EXISTS {$this->kbArticlesTable} (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(190) NOT NULL,
                excerpt TEXT NULL,
                content MEDIUMTEXT NOT NULL,
                category_id INT(10) UNSIGNED NULL,
                is_published TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY slug (slug),
                KEY category_id (category_id),
                KEY published_created (is_published, created_at, id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        db()->query(
            "CREATE TABLE IF NOT EXISTS {$this->kbArticleStatsTable} (
                article_id INT(10) UNSIGNED NOT NULL,
                views_count INT(10) UNSIGNED NOT NULL DEFAULT 0,
                helpful_count INT(10) UNSIGNED NOT NULL DEFAULT 0,
                not_helpful_count INT(10) UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (article_id),
                KEY updated_at (updated_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        db()->query(
            "CREATE TABLE IF NOT EXISTS {$this->kbArticleVotesTable} (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                article_id INT(10) UNSIGNED NOT NULL,
                visitor_key CHAR(64) NOT NULL,
                vote ENUM('helpful', 'not_helpful') NOT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY article_visitor (article_id, visitor_key),
                KEY article_id (article_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        self::$schemaReady = true;
    }

    public function getFaqCategories(): array
    {
        $this->ensureTableExists();

        return db()->query(
            "SELECT id, name, sort_order, created_at, updated_at
             FROM {$this->faqCategoriesTable}
             ORDER BY sort_order ASC, name ASC, id ASC"
        )->get() ?: [];
    }

    public function getKbCategories(): array
    {
        $this->ensureTableExists();

        return db()->query(
            "SELECT id, name, slug, sort_order, created_at, updated_at
             FROM {$this->kbCategoriesTable}
             ORDER BY sort_order ASC, name ASC, id ASC"
        )->get() ?: [];
    }

    public function getStats(): array
    {
        $this->ensureTableExists();

        return [
            'support_faq' => (int)db()->query("SELECT COUNT(*) FROM {$this->faqTable}")->getColumn(),
            'support_kb_articles' => (int)db()->query("SELECT COUNT(*) FROM {$this->kbArticlesTable}")->getColumn(),
            'support_kb_categories' => (int)db()->query("SELECT COUNT(*) FROM {$this->kbCategoriesTable}")->getColumn(),
        ];
    }

    public function getPublishedFaq(int $limit = 10, string $search = ''): array
    {
        $this->ensureTableExists();
        $limit = max(1, min(50, $limit));
        $search = trim($search);
        $where = 'WHERE f.is_published = 1';
        $params = [];

        if ($search !== '') {
            $where .= ' AND (f.question LIKE ? OR f.answer LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }

        return db()->query(
            "SELECT f.*, c.name AS category_name
             FROM {$this->faqTable} f
             LEFT JOIN {$this->faqCategoriesTable} c ON c.id = f.category_id
             {$where}
             ORDER BY f.sort_order ASC, f.id ASC
             LIMIT {$limit}",
            $params
        )->get() ?: [];
    }

    public function getPublishedKbArticles(int $limit = 30, string $search = '', int $categoryId = 0): array
    {
        $this->ensureTableExists();
        $limit = max(1, min(100, $limit));
        $search = trim($search);
        $where = ['a.is_published = 1'];
        $params = [];

        if ($search !== '') {
            $where[] = '(a.title LIKE ? OR a.slug LIKE ? OR a.excerpt LIKE ? OR a.content LIKE ?)';
            $searchLike = '%' . $search . '%';
            array_push($params, $searchLike, $searchLike, $searchLike, $searchLike);
        }

        if ($categoryId > 0) {
            $where[] = 'a.category_id = ?';
            $params[] = $categoryId;
        }

        return db()->query(
            "SELECT a.*, c.name AS category_name, c.slug AS category_slug,
                    COALESCE(s.views_count, 0) AS views_count,
                    COALESCE(s.helpful_count, 0) AS helpful_count,
                    COALESCE(s.not_helpful_count, 0) AS not_helpful_count,
                    CASE
                        WHEN (COALESCE(s.helpful_count, 0) + COALESCE(s.not_helpful_count, 0)) > 0
                        THEN ROUND((COALESCE(s.helpful_count, 0) / (COALESCE(s.helpful_count, 0) + COALESCE(s.not_helpful_count, 0))) * 100)
                        ELSE NULL
                    END AS helpful_percent
             FROM {$this->kbArticlesTable} a
             LEFT JOIN {$this->kbCategoriesTable} c ON c.id = a.category_id
             LEFT JOIN {$this->kbArticleStatsTable} s ON s.article_id = a.id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY c.sort_order ASC, c.name ASC, a.created_at DESC, a.id DESC
             LIMIT {$limit}",
            $params
        )->get() ?: [];
    }

    public function getPublishedKbCategoriesWithArticles(int $limitPerCategory = 5, string $search = ''): array
    {
        $this->ensureTableExists();
        $limitPerCategory = max(1, min(20, $limitPerCategory));
        $categories = $this->getKbCategories();
        $result = [];

        foreach ($categories as $category) {
            $articles = $this->getPublishedKbArticles($limitPerCategory, $search, (int)$category['id']);
            if (!$articles) {
                continue;
            }

            $category['articles'] = $articles;
            $result[] = $category;
        }

        $uncategorized = array_values(array_filter(
            $this->getPublishedKbArticles(100, $search),
            static fn(array $article): bool => empty($article['category_id'])
        ));

        if ($uncategorized) {
            $result[] = [
                'id' => 0,
                'name' => return_translation('support_uncategorized'),
                'slug' => 'uncategorized',
                'sort_order' => 999999,
                'articles' => array_slice($uncategorized, 0, $limitPerCategory),
            ];
        }

        return $result;
    }

    public function findPublishedKbArticleBySlug(string $slug): ?array
    {
        $this->ensureTableExists();
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        $row = db()->query(
            "SELECT a.*, c.name AS category_name, c.slug AS category_slug,
                    COALESCE(s.views_count, 0) AS views_count,
                    COALESCE(s.helpful_count, 0) AS helpful_count,
                    COALESCE(s.not_helpful_count, 0) AS not_helpful_count,
                    CASE
                        WHEN (COALESCE(s.helpful_count, 0) + COALESCE(s.not_helpful_count, 0)) > 0
                        THEN ROUND((COALESCE(s.helpful_count, 0) / (COALESCE(s.helpful_count, 0) + COALESCE(s.not_helpful_count, 0))) * 100)
                        ELSE NULL
                    END AS helpful_percent
             FROM {$this->kbArticlesTable} a
             LEFT JOIN {$this->kbCategoriesTable} c ON c.id = a.category_id
             LEFT JOIN {$this->kbArticleStatsTable} s ON s.article_id = a.id
             WHERE a.slug = ? AND a.is_published = 1
             LIMIT 1",
            [$slug]
        )->getOne();

        return is_array($row) ? $this->prepareKbArticleForPublic($row) : null;
    }

    public function findPublishedKbArticleById(int $id): ?array
    {
        $this->ensureTableExists();
        if ($id <= 0) {
            return null;
        }

        $row = db()->query(
            "SELECT a.*, c.name AS category_name, c.slug AS category_slug
             FROM {$this->kbArticlesTable} a
             LEFT JOIN {$this->kbCategoriesTable} c ON c.id = a.category_id
             WHERE a.id = ? AND a.is_published = 1
             LIMIT 1",
            [$id]
        )->getOne();

        return is_array($row) ? $this->prepareKbArticleForPublic($row) : null;
    }

    protected function prepareKbArticleForPublic(array $article): array
    {
        $content = trim((string)($article['content'] ?? ''));

        if ($content !== '') {
            $article['content'] = (new BlockRenderer())->renderPublicContent($content);
        }

        if ($content !== '' && $article['content'] !== '' && $article['content'] === strip_tags((string)$article['content'])) {
            $article['content'] = '<p>' . nl2br(htmlSC((string)$article['content'])) . '</p>';
        }

        return $article;
    }

    public function recordKbArticleView(int $articleId): void
    {
        $this->ensureTableExists();
        if ($articleId <= 0) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        db()->query(
            "INSERT INTO {$this->kbArticleStatsTable}
             (article_id, views_count, helpful_count, not_helpful_count, created_at, updated_at)
             VALUES (?, 1, 0, 0, ?, ?)
             ON DUPLICATE KEY UPDATE views_count = views_count + 1, updated_at = VALUES(updated_at)",
            [$articleId, $now, $now]
        );
    }

    public function voteKbArticle(int $articleId, string $visitorKey, bool $helpful): array
    {
        $this->ensureTableExists();
        $visitorKey = strtolower(trim($visitorKey));
        if ($articleId <= 0 || !preg_match('/^[a-f0-9]{64}$/', $visitorKey)) {
            return [
                'recorded' => false,
                'already_voted' => false,
                'stats' => $this->getKbArticleStats($articleId),
            ];
        }

        $now = date('Y-m-d H:i:s');
        $vote = $helpful ? 'helpful' : 'not_helpful';

        db()->query(
            "INSERT IGNORE INTO {$this->kbArticleVotesTable}
             (article_id, visitor_key, vote, created_at)
             VALUES (?, ?, ?, ?)",
            [$articleId, $visitorKey, $vote, $now]
        );

        $recorded = db()->rowCount() > 0;
        if ($recorded) {
            $this->ensureKbArticleStats($articleId);
            $column = $helpful ? 'helpful_count' : 'not_helpful_count';
            db()->query(
                "UPDATE {$this->kbArticleStatsTable}
                 SET {$column} = {$column} + 1, updated_at = ?
                 WHERE article_id = ?
                 LIMIT 1",
                [$now, $articleId]
            );
        }

        return [
            'recorded' => $recorded,
            'already_voted' => !$recorded,
            'stats' => $this->getKbArticleStats($articleId),
        ];
    }

    public function getKbArticleStats(int $articleId): array
    {
        $this->ensureTableExists();
        if ($articleId <= 0) {
            return [
                'views_count' => 0,
                'helpful_count' => 0,
                'not_helpful_count' => 0,
                'helpful_percent' => null,
            ];
        }

        $row = db()->query(
            "SELECT views_count, helpful_count, not_helpful_count
             FROM {$this->kbArticleStatsTable}
             WHERE article_id = ?
             LIMIT 1",
            [$articleId]
        )->getOne();

        $views = (int)($row['views_count'] ?? 0);
        $helpful = (int)($row['helpful_count'] ?? 0);
        $notHelpful = (int)($row['not_helpful_count'] ?? 0);
        $totalVotes = $helpful + $notHelpful;

        return [
            'views_count' => $views,
            'helpful_count' => $helpful,
            'not_helpful_count' => $notHelpful,
            'helpful_percent' => $totalVotes > 0 ? (int)round(($helpful / $totalVotes) * 100) : null,
        ];
    }

    protected function ensureKbArticleStats(int $articleId): void
    {
        if ($articleId <= 0) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        db()->query(
            "INSERT IGNORE INTO {$this->kbArticleStatsTable}
             (article_id, views_count, helpful_count, not_helpful_count, created_at, updated_at)
             VALUES (?, 0, 0, 0, ?, ?)",
            [$articleId, $now, $now]
        );
    }

    public function getRelatedPublishedKbArticles(array $article, int $limit = 8): array
    {
        $this->ensureTableExists();
        $limit = max(1, min(30, $limit));
        $articleId = (int)($article['id'] ?? 0);
        $categoryId = (int)($article['category_id'] ?? 0);

        $where = ['a.is_published = 1'];
        $params = [];

        if ($categoryId > 0) {
            $where[] = 'a.category_id = ?';
            $params[] = $categoryId;
        }

        $rows = db()->query(
            "SELECT a.*, c.name AS category_name, c.slug AS category_slug
             FROM {$this->kbArticlesTable} a
             LEFT JOIN {$this->kbCategoriesTable} c ON c.id = a.category_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT {$limit}",
            $params
        )->get() ?: [];

        if ($articleId > 0 && !array_filter($rows, static fn(array $row): bool => (int)$row['id'] === $articleId)) {
            array_unshift($rows, $article);
        }

        return array_slice($rows, 0, $limit);
    }

    public function getPaginatedFaq(array $options = []): array
    {
        $this->ensureTableExists();

        $perPage = max(1, (int)($options['per_page'] ?? 20));
        $search = trim((string)($options['search'] ?? ''));
        $categoryId = max(0, (int)($options['category_id'] ?? 0));
        $published = (string)($options['published'] ?? '');
        $sort = (string)($options['sort'] ?? 'sort_order');
        $direction = strtolower((string)($options['direction'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
        $sortMap = [
            'id' => 'f.id',
            'question' => 'f.question',
            'category' => 'category_name',
            'sort_order' => 'f.sort_order',
            'is_published' => 'f.is_published',
            'created_at' => 'f.created_at',
        ];
        $orderBy = $sortMap[$sort] ?? 'f.sort_order';

        [$where, $params] = $this->buildContentFilters($search, $categoryId, $published, ['f.question', 'f.answer']);
        $from = "FROM {$this->faqTable} f LEFT JOIN {$this->faqCategoriesTable} c ON c.id = f.category_id";

        $total = (int)db()->query("SELECT COUNT(*) {$from} {$where}", $params)->getColumn();
        $pagination = new Pagination($total, $perPage);
        $offset = $pagination->getOffset();

        $items = db()->query(
            "SELECT f.*, c.name AS category_name
             {$from}
             {$where}
             ORDER BY {$orderBy} {$direction}, f.id DESC
             LIMIT {$offset}, {$perPage}",
            $params
        )->get() ?: [];

        return $this->paginatedResult($items, $total, $pagination, $search, $sort, strtolower($direction), [
            'category_id' => $categoryId,
            'published' => $published,
        ]);
    }

    public function findFaq(int $id): ?array
    {
        return $this->findRow($this->faqTable, $id);
    }

    public function saveFaq(int $id, array $data): int
    {
        $this->ensureTableExists();
        $now = date('Y-m-d H:i:s');
        $payload = [
            'question' => trim((string)($data['question'] ?? '')),
            'answer' => trim((string)($data['answer'] ?? '')),
            'category_id' => max(0, (int)($data['category_id'] ?? 0)) ?: null,
            'sort_order' => (int)($data['sort_order'] ?? 0),
            'is_published' => !empty($data['is_published']) ? 1 : 0,
        ];

        if ($id > 0) {
            db()->query(
                "UPDATE {$this->faqTable}
                 SET question = ?, answer = ?, category_id = ?, sort_order = ?, is_published = ?, updated_at = ?
                 WHERE id = ?
                 LIMIT 1",
                [$payload['question'], $payload['answer'], $payload['category_id'], $payload['sort_order'], $payload['is_published'], $now, $id]
            );
            return $id;
        }

        db()->query(
            "INSERT INTO {$this->faqTable}
             (question, answer, category_id, sort_order, is_published, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$payload['question'], $payload['answer'], $payload['category_id'], $payload['sort_order'], $payload['is_published'], $now, $now]
        );

        return (int)db()->getInsertId();
    }

    public function deleteFaq(int $id): bool
    {
        return $this->deleteRow($this->faqTable, $id);
    }

    public function getPaginatedKbArticles(array $options = []): array
    {
        $this->ensureTableExists();

        $perPage = max(1, (int)($options['per_page'] ?? 20));
        $search = trim((string)($options['search'] ?? ''));
        $categoryId = max(0, (int)($options['category_id'] ?? 0));
        $published = (string)($options['published'] ?? '');
        $sort = (string)($options['sort'] ?? 'created_at');
        $direction = strtolower((string)($options['direction'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $sortMap = [
            'id' => 'a.id',
            'title' => 'a.title',
            'slug' => 'a.slug',
            'category' => 'category_name',
            'is_published' => 'a.is_published',
            'views' => 'views_count',
            'helpful' => 'helpful_count',
            'not_helpful' => 'not_helpful_count',
            'helpful_percent' => 'helpful_percent',
            'created_at' => 'a.created_at',
            'updated_at' => 'a.updated_at',
        ];
        $orderBy = $sortMap[$sort] ?? 'a.created_at';

        [$where, $params] = $this->buildContentFilters($search, $categoryId, $published, ['a.title', 'a.slug', 'a.excerpt', 'a.content']);
        $from = "FROM {$this->kbArticlesTable} a
                 LEFT JOIN {$this->kbCategoriesTable} c ON c.id = a.category_id
                 LEFT JOIN {$this->kbArticleStatsTable} s ON s.article_id = a.id";

        $total = (int)db()->query("SELECT COUNT(*) {$from} {$where}", $params)->getColumn();
        $pagination = new Pagination($total, $perPage);
        $offset = $pagination->getOffset();

        $items = db()->query(
            "SELECT a.*, c.name AS category_name,
                    COALESCE(s.views_count, 0) AS views_count,
                    COALESCE(s.helpful_count, 0) AS helpful_count,
                    COALESCE(s.not_helpful_count, 0) AS not_helpful_count,
                    CASE
                        WHEN (COALESCE(s.helpful_count, 0) + COALESCE(s.not_helpful_count, 0)) > 0
                        THEN ROUND((COALESCE(s.helpful_count, 0) / (COALESCE(s.helpful_count, 0) + COALESCE(s.not_helpful_count, 0))) * 100)
                        ELSE NULL
                    END AS helpful_percent
             {$from}
             {$where}
             ORDER BY {$orderBy} {$direction}, a.id DESC
             LIMIT {$offset}, {$perPage}",
            $params
        )->get() ?: [];

        return $this->paginatedResult($items, $total, $pagination, $search, $sort, strtolower($direction), [
            'category_id' => $categoryId,
            'published' => $published,
        ]);
    }

    public function findKbArticle(int $id): ?array
    {
        return $this->findRow($this->kbArticlesTable, $id);
    }

    public function saveKbArticle(int $id, array $data): int
    {
        $this->ensureTableExists();
        $now = date('Y-m-d H:i:s');
        $title = trim((string)($data['title'] ?? ''));
        $slug = $this->uniqueSlug($this->kbArticlesTable, $this->normalizeSlug((string)($data['slug'] ?? ''), $title), $id);

        if ($id > 0) {
            db()->query(
                "UPDATE {$this->kbArticlesTable}
                 SET title = ?, slug = ?, excerpt = ?, content = ?, category_id = ?, is_published = ?, updated_at = ?
                 WHERE id = ?
                 LIMIT 1",
                [
                    $title,
                    $slug,
                    trim((string)($data['excerpt'] ?? '')),
                    trim((string)($data['content'] ?? '')),
                    max(0, (int)($data['category_id'] ?? 0)) ?: null,
                    !empty($data['is_published']) ? 1 : 0,
                    $now,
                    $id,
                ]
            );
            return $id;
        }

        db()->query(
            "INSERT INTO {$this->kbArticlesTable}
             (title, slug, excerpt, content, category_id, is_published, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $title,
                $slug,
                trim((string)($data['excerpt'] ?? '')),
                trim((string)($data['content'] ?? '')),
                max(0, (int)($data['category_id'] ?? 0)) ?: null,
                !empty($data['is_published']) ? 1 : 0,
                $now,
                $now,
            ]
        );

        return (int)db()->getInsertId();
    }

    public function deleteKbArticle(int $id): bool
    {
        $deleted = $this->deleteRow($this->kbArticlesTable, $id);
        if ($deleted) {
            db()->query("DELETE FROM {$this->kbArticleStatsTable} WHERE article_id = ?", [$id]);
            db()->query("DELETE FROM {$this->kbArticleVotesTable} WHERE article_id = ?", [$id]);
        }

        return $deleted;
    }

    public function findKbCategory(int $id): ?array
    {
        return $this->findRow($this->kbCategoriesTable, $id);
    }

    public function saveKbCategory(int $id, array $data): int
    {
        $this->ensureTableExists();
        $now = date('Y-m-d H:i:s');
        $name = trim((string)($data['name'] ?? ''));
        $slug = $this->uniqueSlug($this->kbCategoriesTable, $this->normalizeSlug((string)($data['slug'] ?? ''), $name), $id);
        $sortOrder = (int)($data['sort_order'] ?? 0);

        if ($id > 0) {
            db()->query(
                "UPDATE {$this->kbCategoriesTable}
                 SET name = ?, slug = ?, sort_order = ?, updated_at = ?
                 WHERE id = ?
                 LIMIT 1",
                [$name, $slug, $sortOrder, $now, $id]
            );
            return $id;
        }

        db()->query(
            "INSERT INTO {$this->kbCategoriesTable}
             (name, slug, sort_order, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?)",
            [$name, $slug, $sortOrder, $now, $now]
        );

        return (int)db()->getInsertId();
    }

    public function deleteKbCategory(int $id): bool
    {
        $this->ensureTableExists();
        if ($id <= 0) {
            return false;
        }

        db()->query("UPDATE {$this->kbArticlesTable} SET category_id = NULL WHERE category_id = ?", [$id]);
        return $this->deleteRow($this->kbCategoriesTable, $id);
    }

    public function findFaqCategory(int $id): ?array
    {
        return $this->findRow($this->faqCategoriesTable, $id);
    }

    public function saveFaqCategory(int $id, array $data): int
    {
        $this->ensureTableExists();
        $now = date('Y-m-d H:i:s');
        $name = trim((string)($data['name'] ?? ''));
        $sortOrder = (int)($data['sort_order'] ?? 0);

        if ($id > 0) {
            db()->query(
                "UPDATE {$this->faqCategoriesTable}
                 SET name = ?, sort_order = ?, updated_at = ?
                 WHERE id = ?
                 LIMIT 1",
                [$name, $sortOrder, $now, $id]
            );
            return $id;
        }

        db()->query(
            "INSERT INTO {$this->faqCategoriesTable}
             (name, sort_order, created_at, updated_at)
             VALUES (?, ?, ?, ?)",
            [$name, $sortOrder, $now, $now]
        );

        return (int)db()->getInsertId();
    }

    public function deleteFaqCategory(int $id): bool
    {
        $this->ensureTableExists();
        if ($id <= 0) {
            return false;
        }

        db()->query("UPDATE {$this->faqTable} SET category_id = NULL WHERE category_id = ?", [$id]);
        return $this->deleteRow($this->faqCategoriesTable, $id);
    }

    protected function buildContentFilters(string $search, int $categoryId, string $published, array $searchColumns): array
    {
        $where = [];
        $params = [];

        if ($search !== '') {
            $where[] = '(' . implode(' OR ', array_map(static fn(string $column): string => "{$column} LIKE ?", $searchColumns)) . ')';
            foreach ($searchColumns as $_) {
                $params[] = '%' . $search . '%';
            }
        }

        if ($categoryId > 0) {
            $where[] = 'category_id = ?';
            $params[] = $categoryId;
        }

        if ($published === '0' || $published === '1') {
            $where[] = 'is_published = ?';
            $params[] = (int)$published;
        }

        return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
    }

    protected function paginatedResult(array $items, int $total, Pagination $pagination, string $search, string $sort, string $direction, array $extra = []): array
    {
        return array_merge([
            'items' => $items,
            'total' => $total,
            'pagination' => $pagination,
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
        ], $extra);
    }

    protected function findRow(string $table, int $id): ?array
    {
        $this->ensureTableExists();
        if ($id <= 0) {
            return null;
        }

        $row = db()->query("SELECT * FROM {$table} WHERE id = ? LIMIT 1", [$id])->getOne();
        return is_array($row) ? $row : null;
    }

    protected function deleteRow(string $table, int $id): bool
    {
        $this->ensureTableExists();
        if ($id <= 0) {
            return false;
        }

        db()->query("DELETE FROM {$table} WHERE id = ? LIMIT 1", [$id]);
        return db()->rowCount() > 0;
    }

    protected function normalizeSlug(string $slug, string $fallback): string
    {
        $slug = trim($slug) !== '' ? $slug : $fallback;
        $slug = make_slug($slug);

        return $slug !== '' ? $slug : 'support-' . date('YmdHis');
    }

    protected function uniqueSlug(string $table, string $slug, int $excludeId = 0): string
    {
        $base = mb_substr($slug, 0, 170);
        $candidate = $base;
        $counter = 2;

        while ($this->slugExists($table, $candidate, $excludeId)) {
            $suffix = '-' . $counter;
            $candidate = mb_substr($base, 0, 190 - mb_strlen($suffix)) . $suffix;
            $counter++;
        }

        return $candidate;
    }

    protected function slugExists(string $table, string $slug, int $excludeId = 0): bool
    {
        $sql = "SELECT COUNT(*) FROM {$table} WHERE slug = ?";
        $params = [$slug];
        if ($excludeId > 0) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }

        return (int)db()->query($sql, $params)->getColumn() > 0;
    }
}
