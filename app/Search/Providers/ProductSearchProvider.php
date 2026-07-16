<?php

namespace App\Search\Providers;

use App\Search\SearchDocument;
use App\Search\SearchText;

final class ProductSearchProvider extends DatabaseSearchProvider
{
    public function getDocuments(): iterable
    {
        if (!$this->tableExists('products')) {
            return [];
        }

        $rows = db()->query(
            'SELECT id, title, slug, price, old_price, excerpt, content, image, in_stock, is_sale
             FROM products ORDER BY id ASC'
        )->get() ?: [];

        foreach ($rows as $row) {
            yield $this->map($row);
        }
    }

    public function getDocument(int|string $entityId): ?SearchDocument
    {
        if (!$this->tableExists('products')) {
            return null;
        }
        $row = db()->query(
            'SELECT id, title, slug, price, old_price, excerpt, content, image, in_stock, is_sale
             FROM products WHERE id = ? LIMIT 1',
            [$entityId]
        )->getOne();

        return $row ? $this->map($row) : null;
    }

    private function map(array $row): SearchDocument
    {
        $price = (int)($row['price'] ?? 0);

        return new SearchDocument(
            type: 'product',
            entityId: (int)$row['id'],
            title: (string)$row['title'],
            subtitle: $price > 0 ? (string)$price : '',
            content: implode(' ', array_filter([
                (string)($row['excerpt'] ?? ''),
                SearchText::plainText((string)($row['content'] ?? '')),
            ])),
            url: base_href('/product/' . ltrim((string)$row['slug'], '/')),
            module: 'commerce',
            icon: 'bag',
            status: (int)($row['in_stock'] ?? 1) === 1 ? 'published' : 'disabled',
            metadata: [
                'slug' => (string)$row['slug'],
                'price' => $price,
                'old_price' => (int)($row['old_price'] ?? 0),
                'excerpt' => (string)($row['excerpt'] ?? ''),
                'image' => (string)($row['image'] ?? ''),
                'in_stock' => (int)($row['in_stock'] ?? 0),
                'is_sale' => (int)($row['is_sale'] ?? 0),
            ],
        );
    }
}
