<?php

namespace App\Search\Providers;

use App\Search\SearchDocument;
use App\Search\SearchText;

final class PostSearchProvider extends DatabaseSearchProvider
{
    public function getDocuments(): iterable
    {
        if (!$this->tableExists('posts')) {
            return [];
        }

        $rows = db()->query(
            'SELECT p.id, p.title, p.slug, p.category, p.excerpt, p.content, p.image,
                    p.seo_title, p.seo_description, p.seo_keywords, p.priority,
                    p.published_at, p.is_published,
                    COALESCE(c.name_ru, c.name, p.category) AS category_label
             FROM posts p
             LEFT JOIN post_categories c ON c.id = p.category_id
             ORDER BY p.id ASC'
        )->get() ?: [];

        foreach ($rows as $row) {
            yield $this->map($row);
        }
    }

    public function getDocument(int|string $entityId): ?SearchDocument
    {
        if (!$this->tableExists('posts')) {
            return null;
        }
        $row = db()->query(
            'SELECT p.id, p.title, p.slug, p.category, p.excerpt, p.content, p.image,
                    p.seo_title, p.seo_description, p.seo_keywords, p.priority,
                    p.published_at, p.is_published,
                    COALESCE(c.name_ru, c.name, p.category) AS category_label
             FROM posts p
             LEFT JOIN post_categories c ON c.id = p.category_id
             WHERE p.id = ? LIMIT 1',
            [$entityId]
        )->getOne();

        return $row ? $this->map($row) : null;
    }

    private function map(array $row): SearchDocument
    {
        $category = trim((string)($row['category_label'] ?? $row['category'] ?? ''));
        $keywords = preg_split('/[,;\n]+/u', (string)($row['seo_keywords'] ?? '')) ?: [];
        $keywords[] = $category;

        return new SearchDocument(
            type: 'post',
            entityId: (int)$row['id'],
            title: (string)$row['title'],
            subtitle: $category,
            content: implode(' ', array_filter([
                (string)($row['excerpt'] ?? ''),
                (string)($row['seo_description'] ?? ''),
                SearchText::plainText((string)($row['content'] ?? '')),
            ])),
            keywords: array_values(array_filter(array_map('trim', $keywords))),
            url: base_href('/posts/' . ltrim((string)$row['slug'], '/')),
            module: 'posts',
            icon: 'newspaper',
            priority: (int)($row['priority'] ?? 0),
            status: (int)$row['is_published'] === 1 ? 'published' : 'draft',
            publishedAt: (string)($row['published_at'] ?? '') ?: null,
            metadata: [
                'slug' => (string)$row['slug'],
                'category' => $category,
                'category_label' => $category,
                'excerpt' => (string)($row['excerpt'] ?? ''),
                'image' => (string)($row['image'] ?? ''),
                'published_at' => (string)($row['published_at'] ?? ''),
            ],
        );
    }
}
