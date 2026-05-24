<?php

namespace App\Models;

use FBL\Pagination;

/**
 * Выполняет поиск по товарам и постам, а также формирует поисковые подсказки.
 */
class Search
{

    protected Post $posts;

    /**
     * Инициализирует модель постов для совместного поиска по блогу.
     */
    public function __construct()
    {
        $this->posts = new Post();
    }

    /**
     * Выполняет полный поиск по сайту с пагинацией по товарам и постам.
     */
    public function find(string $query, int $perPage = PAGINATION_SETTINGS['perPage']): array
    {
        $query = trim($query);

        if ($query === '') {
            return [
                'products' => [],
                'products_total' => 0,
                'products_pagination' => null,
                'posts' => [],
                'posts_total' => 0,
                'posts_pagination' => null,
                'total' => 0,
            ];
        }

        $products = $this->searchProductsPaginated($query, $perPage, 'product_page');
        $posts = $this->posts->searchPublishedPaginated($query, $perPage, 'post_page');

        return [
            'products' => $products['items'],
            'products_total' => $products['total'],
            'products_pagination' => $products['pagination'],
            'posts' => $posts['items'],
            'posts_total' => $posts['total'],
            'posts_pagination' => $posts['pagination'],
            'total' => $products['total'] + $posts['total'],
        ];
    }

    /**
     * Возвращает короткие поисковые подсказки по товарам и постам.
     */
    public function suggest(string $query, int $limitPerSection = 4): array
    {
        $query = trim($query);

        if ($query === '') {
            return [
                'products' => [],
                'posts' => [],
            ];
        }

        $products = $this->searchProducts($query, $limitPerSection);
        $posts = $this->posts->searchPublished($query, $limitPerSection);

        return [
            'products' => $products,
            'posts' => $posts,
        ];
    }

    /**
     * Ищет товары без пагинации для блока подсказок.
     */
    protected function searchProducts(string $query, int $limit = 8): array
    {
        $like = '%' . $query . '%';
        $limit = (int)$limit;

        return db()->query(
            "SELECT id, title, slug, price, old_price, excerpt, image, in_stock, is_sale
             FROM products
             WHERE title LIKE ?
                OR excerpt LIKE ?
                OR content LIKE ?
             ORDER BY id DESC
             LIMIT {$limit}",
            [$like, $like, $like]
        )->get() ?: [];
    }

    /**
     * Ищет товары с пагинацией для страницы результатов поиска.
     */
    protected function searchProductsPaginated(string $query, int $perPage = PAGINATION_SETTINGS['perPage'], string $pageParam = 'page'): array
    {
        $query = trim($query);
        if ($query === '') {
            return [
                'items' => [],
                'total' => 0,
                'pagination' => null,
            ];
        }

        $total = $this->countSearchProducts($query);
        $pagination = new Pagination($total, $perPage, PAGINATION_SETTINGS['midSize'], PAGINATION_SETTINGS['maxPages'], PAGINATION_SETTINGS['tpl'], $pageParam);
        $offset = $pagination->getOffset();
        $limit = (int)$perPage;
        $like = '%' . $query . '%';

        $items = db()->query(
            "SELECT id, title, slug, price, old_price, excerpt, image, in_stock, is_sale
             FROM products
             WHERE title LIKE ?
                OR excerpt LIKE ?
                OR content LIKE ?
             ORDER BY id DESC
             LIMIT {$limit} OFFSET {$offset}",
            [$like, $like, $like]
        )->get() ?: [];

        return [
            'items' => $items,
            'total' => $total,
            'pagination' => $total > $perPage ? $pagination : null,
        ];
    }

    /**
     * Возвращает количество товаров, подходящих под поисковый запрос.
     */
    protected function countSearchProducts(string $query): int
    {
        $like = '%' . trim($query) . '%';

        return (int)db()->query(
            "SELECT COUNT(*)
             FROM products
             WHERE title LIKE ?
                OR excerpt LIKE ?
                OR content LIKE ?",
            [$like, $like, $like]
        )->getColumn();
    }

}
