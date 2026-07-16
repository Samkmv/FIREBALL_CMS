<?php

namespace App\Controllers;

use App\Search\SearchConfig;
use App\Search\SearchEngine;
use FBL\Pagination;
use FBL\Theme;

/**
 * Выполняет единый поиск по публичному содержимому сайта и отдаёт подсказки.
 */
class SearchController extends BaseController
{
    protected SearchEngine $search;
    protected SearchConfig $config;

    public function __construct()
    {
        parent::__construct();

        $this->config = new SearchConfig();
        $this->search = new SearchEngine(
            search_registry(),
            search_indexer(),
            $this->config
        );
    }

    /**
     * Показывает общую ленту результатов с сортировкой по релевантности.
     */
    public function index()
    {
        $query = $this->query();
        $perPage = max(1, (int)PAGINATION_SETTINGS['perPage']);
        $page = max(1, (int)request()->get('page', 1));
        $searchResult = $query !== ''
            ? $this->search->search($query, $perPage, ($page - 1) * $perPage)
            : $this->emptyResult($query);
        $items = array_map(fn(array $item): array => $this->decorateItem($item), $searchResult['items']);
        $total = (int)$searchResult['total'];
        $pagination = $total > $perPage ? new Pagination($total, $perPage) : null;

        // The generic result variables are the public search contract. The typed
        // collections keep older custom themes working while they migrate.
        $products = array_values(array_filter($items, static fn(array $item): bool => $item['type'] === 'product'));
        $posts = array_values(array_filter($items, static fn(array $item): bool => $item['type'] === 'post'));
        $counts = (array)($searchResult['counts'] ?? []);

        return Theme::render('search', [
            'title' => return_translation('search_index_title'),
            'query' => $query,
            'results' => $items,
            'total' => $total,
            'pagination' => $pagination,
            'normalized_query' => (string)$searchResult['normalized_query'],
            'search_tokens' => (array)$searchResult['tokens'],
            'search_query' => $query,
            'total_results' => $total,
            'products' => $products,
            'products_total' => (int)($counts['product'] ?? 0),
            'products_pagination' => null,
            'posts' => $posts,
            'posts_total' => (int)($counts['post'] ?? 0),
            'posts_pagination' => null,
            'seo_robots' => 'noindex,follow',
        ]);
    }

    /**
     * Возвращает релевантные подсказки из всех зарегистрированных источников.
     */
    public function suggest(): void
    {
        $query = $this->query();
        if (mb_strlen(trim(preg_replace('/[^\p{L}\p{N}]+/u', '', $query) ?? ''), 'UTF-8') < $this->config->minimumQueryLength()) {
            response()->json([
                'items' => [],
                'empty_text' => return_translation('search_suggest_empty'),
            ]);
        }

        $results = $this->search->search($query, 12);
        $items = array_map(function (array $item): array {
            $item = $this->decorateItem($item);

            return [
                'type' => $item['type'],
                'type_label' => $item['type_label'],
                'title' => $item['title'],
                'meta' => $this->suggestionMeta($item),
                'url' => $item['url'],
            ];
        }, $results['items']);

        response()->json([
            'items' => $items,
            'empty_text' => return_translation('search_suggest_empty'),
        ]);
    }

    private function query(): string
    {
        $query = trim((string)request()->get('q', ''));
        $query = preg_replace('/\s+/u', ' ', $query) ?? '';

        return mb_substr($query, 0, $this->config->maximumQueryLength(), 'UTF-8');
    }

    private function decorateItem(array $item): array
    {
        $metadata = (array)($item['metadata'] ?? []);
        $item['type_label'] = trim((string)($metadata['type_label'] ?? '')) ?: $this->typeLabel((string)$item['type']);

        return $item;
    }

    private function typeLabel(string $type): string
    {
        $key = match ($type) {
            'product' => 'search_suggest_product',
            'post' => 'search_suggest_post',
            'page' => 'search_suggest_page',
            'support' => 'search_suggest_support',
            'faq' => 'search_suggest_faq',
            default => '',
        };

        if ($key !== '') {
            return return_translation($key);
        }

        $label = trim(str_replace(['-', '_', '.'], ' ', $type));

        return $label !== '' ? mb_convert_case($label, MB_CASE_TITLE, 'UTF-8') : return_translation('search_suggest_result');
    }

    private function suggestionMeta(array $item): string
    {
        $metadata = (array)($item['metadata'] ?? []);
        if ($item['type'] === 'product' && (int)($metadata['price'] ?? 0) > 0) {
            return '$' . (int)$metadata['price'];
        }

        return trim((string)($item['subtitle'] ?? '')) ?: trim((string)($item['module'] ?? ''));
    }

    private function emptyResult(string $query): array
    {
        return [
            'query' => $query,
            'normalized_query' => '',
            'tokens' => [],
            'items' => [],
            'counts' => [],
            'total' => 0,
        ];
    }
}
