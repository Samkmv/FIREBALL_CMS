<?php

namespace App\Search\Providers;

use App\Search\SearchDocument;
use App\Search\SearchText;

final class PageSearchProvider extends DatabaseSearchProvider
{
    public function getDocuments(): iterable
    {
        if (!$this->tableExists('pages')) {
            return [];
        }

        $rows = db()->query(
            'SELECT id, title, menu_title, slug, content, meta_title, meta_description,
                    is_published, menu_order, created_at, updated_at
             FROM pages ORDER BY id ASC'
        )->get() ?: [];

        foreach ($rows as $row) {
            yield $this->map($row);
        }
    }

    public function getDocument(int|string $entityId): ?SearchDocument
    {
        if (!$this->tableExists('pages')) {
            return null;
        }
        $row = db()->query(
            'SELECT id, title, menu_title, slug, content, meta_title, meta_description,
                    is_published, menu_order, created_at, updated_at
             FROM pages WHERE id = ? LIMIT 1',
            [$entityId]
        )->getOne();

        return $row ? $this->map($row) : null;
    }

    private function map(array $row): SearchDocument
    {
        $description = trim((string)($row['meta_description'] ?? ''));

        return new SearchDocument(
            type: 'page',
            entityId: (int)$row['id'],
            title: (string)$row['title'],
            subtitle: trim((string)($row['menu_title'] ?? '')),
            content: implode(' ', array_filter([
                $description,
                SearchText::plainText((string)($row['content'] ?? '')),
            ])),
            keywords: array_values(array_filter([(string)($row['meta_title'] ?? '')])),
            url: base_href('/' . ltrim((string)$row['slug'], '/')),
            module: 'pages',
            icon: 'file-text',
            priority: max(0, 100 - (int)($row['menu_order'] ?? 0)),
            status: (int)$row['is_published'] === 1 ? 'published' : 'draft',
            metadata: [
                'slug' => (string)$row['slug'],
                'updated_at' => (string)($row['updated_at'] ?? ''),
            ],
        );
    }
}
