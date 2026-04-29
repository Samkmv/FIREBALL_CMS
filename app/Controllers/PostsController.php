<?php

namespace App\Controllers;

use App\Models\Post;

/**
 * Обслуживает публичный раздел блога: список записей и страницу отдельного поста.
 */
class PostsController extends BaseController
{

    protected Post $posts;

    /**
     * Инициализирует модель постов для работы с блогом.
     */
    public function __construct()
    {
        parent::__construct();
        $this->posts = new Post();
    }

    /**
     * Показывает список постов с фильтрацией по категории и SEO-метаданными.
     */
    public function index()
    {
        $currentCategory = request()->get('category');
        $postsData = $this->posts->getPaginatedPosts($currentCategory);
        $sidebarData = $this->posts->getSidebarData();
        $currentCategoryMeta = $postsData['current_category_meta'] ?? null;
        $currentCategoryLabel = $postsData['current_category_label'] ?? null;
        $seoTitle = trim((string)($currentCategoryMeta['seo_title'] ?? ''));
        $seoDescription = trim((string)($currentCategoryMeta['seo_description'] ?? ''));
        $seoKeywords = trim((string)($currentCategoryMeta['seo_keywords'] ?? ''));
        $seoImage = trim((string)($currentCategoryMeta['seo_image'] ?? ''));
        $canonicalUrl = !empty($postsData['current_category'])
            ? base_href('/posts') . '?category=' . rawurlencode((string)$postsData['current_category'])
            : base_href('/posts');

        return view('posts/index', [
            'title' => return_translation('posts_index_title'),
            'posts' => $postsData['posts'],
            'total_posts' => $postsData['total'],
            'pagination' => $postsData['pagination'],
            'current_category' => $postsData['current_category'],
            'current_category_label' => $currentCategoryLabel,
            'categories' => $sidebarData['categories'],
            'trending_posts' => $sidebarData['trending_posts'],
            'seo_title' => $seoTitle !== '' ? $seoTitle : ($currentCategoryLabel ?: return_translation('posts_index_title')),
            'seo_description' => $seoDescription,
            'seo_keywords' => $seoKeywords,
            'seo_image' => $seoImage,
            'seo_canonical' => $canonicalUrl,
        ]);
    }

    /**
     * Показывает страницу конкретного опубликованного поста по его slug.
     */
    public function show()
    {
        $slug = get_route_param('slug');
        $post = $this->posts->findPublishedBySlug($slug);

        if (!$post) {
            abort();
        }

        $this->posts->incrementViews((int)$post['id']);
        $post['views_count'] = (int)$post['views_count'] + 1;

        $sidebarData = $this->posts->getSidebarData($slug);
        $popularPosts = $this->posts->getPopularPosts(6, $slug);

        return view('posts/show', [
            'title' => $post['title'],
            'post' => $post,
            'categories' => $sidebarData['categories'],
            'trending_posts' => $sidebarData['trending_posts'],
            'popular_posts' => $popularPosts,
            'seo_title' => $post['seo_title'] !== '' ? $post['seo_title'] : $post['title'],
            'seo_description' => $post['seo_description'],
            'seo_keywords' => $post['seo_keywords'],
            'seo_image' => $post['seo_image'],
            'seo_canonical' => base_href('/posts/' . $post['slug']),
        ]);
    }

}
