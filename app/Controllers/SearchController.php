<?php

namespace App\Controllers;

use App\Models\Search;
use App\Models\Page;
use App\Models\Post;
use FBL\Pagination;
use FBL\Theme;

/**
 * Выполняет поиск по сайту и отдаёт поисковые подсказки.
 */
class SearchController extends BaseController
{

    protected Search $search;
    protected Page $pages;
    protected Post $posts;

    /**
     * Инициализирует поисковую модель.
     */
    public function __construct()
    {
        parent::__construct();
        $this->search = new Search();
        $this->pages = new Page();
        $this->posts = new Post();
    }

    /**
     * Показывает страницу результатов поиска по товарам и постам.
     */
    public function index()
    {
        $query = trim((string)request()->get('q', ''));
        $results = $query !== '' ? $this->search->find($query) : [
            'products' => [],
            'products_total' => 0,
            'products_pagination' => null,
            'posts' => [],
            'posts_total' => 0,
            'posts_pagination' => null,
            'total' => 0,
        ];

        $allStandardResults = $this->pages->searchPublished($query, 100);
        foreach ($this->posts->searchPublished($query, 100) as $post) {
            $allStandardResults[] = [
                'title' => (string)$post['title'],
                'url' => (string)$post['url'],
                'excerpt' => (string)($post['excerpt'] ?? ''),
                'type' => 'post',
            ];
        }
        $standardTotal = count($allStandardResults);
        $perPage = PAGINATION_SETTINGS['perPage'];
        $standardPagination = $standardTotal > $perPage
            ? new Pagination($standardTotal, $perPage)
            : null;
        $standardOffset = $standardPagination ? $standardPagination->getOffset() : 0;
        $standardResults = array_slice($allStandardResults, $standardOffset, $perPage);

        return Theme::render('search', [
            'title' => return_translation('search_index_title'),
            'query' => $query,
            'results' => $standardResults,
            'total' => $standardTotal,
            'pagination' => $standardPagination,
            'search_query' => $query,
            'products' => $results['products'],
            'products_total' => $results['products_total'],
            'products_pagination' => $results['products_pagination'],
            'posts' => $results['posts'],
            'posts_total' => $results['posts_total'],
            'posts_pagination' => $results['posts_pagination'],
            'total_results' => $results['total'],
            'seo_robots' => 'noindex,follow',
        ]);
    }

    /**
     * Возвращает короткие поисковые подсказки для строки поиска.
     */
    public function suggest()
    {
        $query = trim((string)request()->get('q', ''));

        if (mb_strlen($query) < 2) {
            response()->json([
                'items' => [],
            ]);
        }

        $maxItems = 24;
        $results = $this->search->suggest($query, 12);
        $items = [];

        foreach ($results['products'] as $product) {
            $items[] = [
                'type' => 'product',
                'type_label' => return_translation('search_suggest_product'),
                'title' => $product['title'],
                'meta' => '$' . (int)$product['price'],
                'url' => base_href('/product/' . $product['slug']),
            ];
        }

        foreach ($results['posts'] as $post) {
            $items[] = [
                'type' => 'post',
                'type_label' => return_translation('search_suggest_post'),
                'title' => $post['title'],
                'meta' => $post['category_label'] ?? $post['category'],
                'url' => base_href('/posts/' . $post['slug']),
            ];
        }

        response()->json([
            'items' => array_slice($items, 0, $maxItems),
            'empty_text' => return_translation('search_suggest_empty'),
        ]);
    }

}
