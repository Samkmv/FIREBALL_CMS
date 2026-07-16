<?php

namespace App\Search\Providers;

use App\Search\SearchDocument;
use App\Search\SearchText;

/**
 * Indexes public CMS pages together with the built-in support knowledge base.
 */
final class PageSearchProvider extends DatabaseSearchProvider
{
    public function getDocuments(): iterable
    {
        if ($this->tableExists('pages')) {
            $rows = db()->query(
                'SELECT id, title, menu_title, slug, content, meta_title, meta_description,
                        is_published, menu_order, created_at, updated_at
                 FROM pages ORDER BY id ASC'
            )->get() ?: [];

            foreach ($rows as $row) {
                yield $this->mapPage($row);
            }
        }

        yield from $this->supportDocuments();
    }

    public function getDocument(int|string $entityId): ?SearchDocument
    {
        $entityId = (string)$entityId;
        if (preg_match('/^support-article:(\d+)$/', $entityId, $match)) {
            return $this->getSupportArticle((int)$match[1]);
        }
        if (preg_match('/^support-faq:(\d+)$/', $entityId, $match)) {
            return $this->getSupportFaq((int)$match[1]);
        }
        if (!ctype_digit($entityId) || !$this->tableExists('pages')) {
            return null;
        }

        $row = db()->query(
            'SELECT id, title, menu_title, slug, content, meta_title, meta_description,
                    is_published, menu_order, created_at, updated_at
             FROM pages WHERE id = ? LIMIT 1',
            [(int)$entityId]
        )->getOne();

        return $row ? $this->mapPage($row) : null;
    }

    private function supportDocuments(): iterable
    {
        if ($this->tableExists('support_kb_articles') && $this->tableExists('support_kb_categories')) {
            $articles = db()->query(
                'SELECT a.id, a.title, a.slug, a.excerpt, a.content, a.is_published,
                        a.created_at, a.updated_at, c.name AS category_name
                 FROM support_kb_articles a
                 LEFT JOIN support_kb_categories c ON c.id = a.category_id
                 ORDER BY a.id ASC'
            )->get() ?: [];

            foreach ($articles as $article) {
                yield $this->mapSupportArticle($article);
            }
        }

        if ($this->tableExists('support_faq') && $this->tableExists('support_faq_categories')) {
            $faqItems = db()->query(
                'SELECT f.id, f.question, f.answer, f.sort_order, f.is_published,
                        f.created_at, f.updated_at, c.name AS category_name
                 FROM support_faq f
                 LEFT JOIN support_faq_categories c ON c.id = f.category_id
                 ORDER BY f.id ASC'
            )->get() ?: [];

            foreach ($faqItems as $faq) {
                yield $this->mapSupportFaq($faq);
            }
        }
    }

    private function getSupportArticle(int $id): ?SearchDocument
    {
        if (!$this->tableExists('support_kb_articles') || !$this->tableExists('support_kb_categories')) {
            return null;
        }

        $row = db()->query(
            'SELECT a.id, a.title, a.slug, a.excerpt, a.content, a.is_published,
                    a.created_at, a.updated_at, c.name AS category_name
             FROM support_kb_articles a
             LEFT JOIN support_kb_categories c ON c.id = a.category_id
             WHERE a.id = ? LIMIT 1',
            [$id]
        )->getOne();

        return $row ? $this->mapSupportArticle($row) : null;
    }

    private function getSupportFaq(int $id): ?SearchDocument
    {
        if (!$this->tableExists('support_faq') || !$this->tableExists('support_faq_categories')) {
            return null;
        }

        $row = db()->query(
            'SELECT f.id, f.question, f.answer, f.sort_order, f.is_published,
                    f.created_at, f.updated_at, c.name AS category_name
             FROM support_faq f
             LEFT JOIN support_faq_categories c ON c.id = f.category_id
             WHERE f.id = ? LIMIT 1',
            [$id]
        )->getOne();

        return $row ? $this->mapSupportFaq($row) : null;
    }

    private function mapPage(array $row): SearchDocument
    {
        $description = trim((string)($row['meta_description'] ?? ''));
        $slug = trim((string)($row['slug'] ?? ''));

        return new SearchDocument(
            type: 'page',
            entityId: (int)$row['id'],
            title: (string)$row['title'],
            subtitle: trim((string)($row['menu_title'] ?? '')),
            content: implode(' ', array_filter([
                $description,
                SearchText::plainText((string)($row['content'] ?? '')),
            ])),
            keywords: array_values(array_filter([
                (string)($row['meta_title'] ?? ''),
                str_replace('-', ' ', $slug),
            ])),
            url: base_href('/' . ltrim($slug, '/')),
            module: 'pages',
            icon: 'file-text',
            priority: max(0, 100 - (int)($row['menu_order'] ?? 0)),
            status: (int)$row['is_published'] === 1 ? 'published' : 'draft',
            metadata: [
                'slug' => $slug,
                'updated_at' => (string)($row['updated_at'] ?? ''),
            ],
        );
    }

    private function mapSupportArticle(array $row): SearchDocument
    {
        $category = trim((string)($row['category_name'] ?? ''));
        $slug = trim((string)($row['slug'] ?? ''));

        return new SearchDocument(
            type: 'support',
            entityId: 'support-article:' . (int)$row['id'],
            title: (string)$row['title'],
            subtitle: $category,
            content: implode(' ', array_filter([
                (string)($row['excerpt'] ?? ''),
                SearchText::plainText((string)($row['content'] ?? '')),
            ])),
            keywords: array_values(array_filter([$category, str_replace('-', ' ', $slug)])),
            url: base_href('/support/articles/' . ltrim($slug, '/')),
            module: 'support',
            icon: 'help-circle',
            priority: 60,
            status: (int)$row['is_published'] === 1 ? 'published' : 'draft',
            publishedAt: (string)($row['created_at'] ?? '') ?: null,
            metadata: [
                'slug' => $slug,
                'category' => $category,
                'updated_at' => (string)($row['updated_at'] ?? ''),
            ],
        );
    }

    private function mapSupportFaq(array $row): SearchDocument
    {
        $category = trim((string)($row['category_name'] ?? ''));

        return new SearchDocument(
            type: 'faq',
            entityId: 'support-faq:' . (int)$row['id'],
            title: (string)$row['question'],
            subtitle: $category,
            content: SearchText::plainText((string)($row['answer'] ?? '')),
            keywords: array_values(array_filter([$category])),
            url: base_href('/support#support-faq'),
            module: 'support',
            icon: 'help-circle',
            priority: max(0, 40 - (int)($row['sort_order'] ?? 0)),
            status: (int)$row['is_published'] === 1 ? 'published' : 'draft',
            publishedAt: (string)($row['created_at'] ?? '') ?: null,
            metadata: [
                'category' => $category,
                'updated_at' => (string)($row['updated_at'] ?? ''),
            ],
        );
    }
}
