<?php

namespace App\Controllers;

use App\Models\Admin;
use App\Models\Post;
use App\Modules\BlockEditor\BlockEditor;
use App\Modules\BlockEditor\BlockEditorService;
use FBL\File;
use FBL\Language;

/**
 * Управляет административными действиями с записями блога.
 */
class AdminPostController extends BaseController
{

    protected Admin $blog;

    public function __construct()
    {
        $this->blog = new Admin();
    }

    /**
     * Показывает таблицу постов в административной панели.
     */
    public function posts()
    {
        $params = $this->getTableParams('published_at', 'desc');
        $activeStatus = $this->normalizeTableStatus((string)request()->get(
            'status',
            request()->get('draft_page') !== null ? 'drafts' : 'published'
        ));

        if (request()->isAjax()) {
            $statusCode = $activeStatus === 'drafts' ? 0 : 1;
            $posts = $this->blog->getPostsByPublicationStatus($statusCode, array_merge($params, [
                'page_param' => 'page',
            ]));
            $publishedCount = $activeStatus === 'published'
                ? $posts['total']
                : $this->blog->getPostsByPublicationStatus(1, array_merge($params, ['per_page' => 1, 'page_param' => 'published_count_page']))['total'];
            $draftCount = $activeStatus === 'drafts'
                ? $posts['total']
                : $this->blog->getPostsByPublicationStatus(0, array_merge($params, ['per_page' => 1, 'page_param' => 'draft_count_page']))['total'];

            response()->json([
                'status' => $activeStatus,
                'search' => $params['search'],
                'sort' => $posts['sort'],
                'direction' => $posts['direction'],
                'visible' => count($posts['items']),
                'total' => $posts['total'],
                'counts' => [
                    'published' => $publishedCount,
                    'drafts' => $draftCount,
                ],
                'html' => view()->renderPartial('admin/partials/posts_table_pane', [
                    'items' => $posts['items'],
                    'table_key' => $activeStatus,
                    'empty_text' => $params['search'] !== '' ? return_translation('admin_table_empty_search') : return_translation('admin_posts_empty'),
                    'pagination' => $posts['pagination'],
                    'total' => $posts['total'],
                    'sort' => $posts['sort'],
                    'direction' => $posts['direction'],
                ]),
            ]);
        }

        $publishedPosts = $this->blog->getPostsByPublicationStatus(1, array_merge($params, [
            'page_param' => $activeStatus === 'published' ? 'page' : 'published_page',
        ]));
        $draftPosts = $this->blog->getPostsByPublicationStatus(0, array_merge($params, [
            'page_param' => $activeStatus === 'drafts' ? 'page' : 'draft_page',
        ]));
        $posts = array_merge($publishedPosts['items'], $draftPosts['items']);

        return view('admin/posts', [
            'title' => return_translation('admin_posts_title'),
            'posts' => $posts,
            'published_posts' => $publishedPosts['items'],
            'draft_posts' => $draftPosts['items'],
            'published_pagination' => $publishedPosts['pagination'],
            'draft_pagination' => $draftPosts['pagination'],
            'total' => $publishedPosts['total'] + $draftPosts['total'],
            'published_total' => $publishedPosts['total'],
            'draft_total' => $draftPosts['total'],
            'search' => $params['search'],
            'active_status' => $activeStatus,
            'sort' => $publishedPosts['sort'],
            'direction' => $publishedPosts['direction'],
        ]);
    }

    /**
     * Показывает предпросмотр опубликованной записи или черновика из админки.
     */
    public function postPreview()
    {
        $postId = (int)get_route_param('id', 0);
        $posts = new Post();
        $post = $posts->findByIdForPreview($postId);

        if (!$post) {
            abort();
        }

        $sidebarData = $posts->getSidebarData($post['slug']);
        $popularPosts = $posts->getPopularPosts(5, $post['slug']);

        Language::load([PostsController::class, 'show']);

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
            'seo_robots' => 'noindex,nofollow',
            'seo_canonical' => base_href('/admin/posts/preview/' . $postId),
        ]);
    }

    /**
     * Показывает форму создания или редактирования поста и обрабатывает её отправку.
     */
    public function postForm()
    {
        $postId = (int)get_route_param('id', 0);
        $isEdit = $postId > 0;

        if (request()->isPost()) {
            $data = $this->normalizePostData(request()->getData());
            $imageFile = new File('image_file');
            $hasUploadedImage = $imageFile->isFile && $imageFile->getError() !== UPLOAD_ERR_NO_FILE;
            $errors = $this->validatePostData($data, $hasUploadedImage);
            $errors = array_merge_recursive($errors, $this->validatePostImageFile($imageFile));

            if (!empty($errors)) {
                session()->set('form_data', $data);
                session()->set('form_errors', $errors);
                response()->redirect($isEdit ? base_href("/admin/posts/edit/{$postId}") : base_href('/admin/posts/create'));
            }

            $this->storePostImageFile($imageFile, $data, $errors);
            if (!empty($errors)) {
                session()->set('form_data', $data);
                session()->set('form_errors', $errors);
                response()->redirect($isEdit ? base_href("/admin/posts/edit/{$postId}") : base_href('/admin/posts/create'));
            }

            if ($isEdit) {
                $this->blog->updatePost($postId, $data);
                session()->setFlash('success', return_translation('admin_post_updated'));
            } else {
                $this->blog->createPost($data);
                session()->setFlash('success', return_translation('admin_post_created'));
            }

            session()->remove('form_data');
            session()->remove('form_errors');
            response()->redirect(base_href('/admin/posts'));
        }

        $post = $isEdit ? $this->blog->findPostById($postId) : false;
        if ($isEdit && !$post) {
            abort();
        }

        return view('admin/post_form', [
            'title' => $isEdit ? return_translation('admin_post_edit_title') : return_translation('admin_post_create_title'),
            'post' => $post ?: [],
            'categories' => $this->blog->getCategories(),
            'is_edit' => $isEdit,
            'styles' => array_merge(BlockEditor::styles(), [
                base_url('/assets/default/vendor/flatpickr/flatpickr.min.css?v=' . filemtime(WWW . '/assets/default/vendor/flatpickr/flatpickr.min.css')),
            ]),
            'footer_scripts' => array_merge([
                base_url('/assets/default/vendor/flatpickr/flatpickr.min.js?v=' . filemtime(WWW . '/assets/default/vendor/flatpickr/flatpickr.min.js')),
            ], BlockEditor::scripts()),
        ]);
    }

    /**
     * Автоматически сохраняет форму записи как черновик.
     */
    public function postAutosave()
    {
        $postId = (int)(request()->post('autosave_post_id') ?: request()->post('id'));
        $post = $postId > 0 ? $this->blog->findPostById($postId) : false;

        if ($postId > 0 && !$post) {
            response()->json([
                'status' => 'error',
                'message' => return_translation('admin_post_autosave_not_found'),
            ], 404);
        }

        $data = $this->normalizePostData(request()->getData());
        $data = $this->prepareAutosaveDraftData($data, $post ?: []);

        if ($postId > 0) {
            $this->blog->updatePost($postId, $data);
        } else {
            $postId = $this->blog->createPost($data);
        }

        response()->json([
            'status' => 'success',
            'id' => $postId,
            'edit_url' => base_href('/admin/posts/edit/' . $postId),
            'preview_url' => base_href('/admin/posts/preview/' . $postId),
            'saved_at' => date('H:i'),
            'message' => return_translation('admin_post_autosave_saved'),
        ]);
    }

    /**
     * Удаляет пост по идентификатору и возвращает в список постов.
     */
    public function postDelete()
    {
        $postId = (int)request()->post('id');
        if ($postId > 0) {
            $this->blog->deletePost($postId);
            session()->setFlash('success', return_translation('admin_post_deleted'));
        }

        response()->redirect(base_href('/admin/posts'));
    }

    /**
     * Переключает публикацию записи из таблицы админки.
     */
    public function postTogglePublished()
    {
        $postId = (int)request()->post('id');
        $nextStatus = $postId > 0 ? $this->blog->togglePostPublished($postId) : null;

        if ($nextStatus === null) {
            session()->setFlash('error', return_translation('admin_post_not_found'));
            response()->redirect(base_href('/admin/posts'));
        }

        session()->setFlash(
            'success',
            return_translation($nextStatus === 1 ? 'admin_post_published' : 'admin_post_unpublished')
        );
        response()->redirect(base_href('/admin/posts'));
    }

    /**
     * Возвращает параметры таблицы для поиска, сортировки и пагинации.
     */
    protected function getTableParams(string $defaultSort, string $defaultDirection = 'desc'): array
    {
        return [
            'per_page' => 20,
            'search' => request()->get('search', request()->get('q', '')),
            'sort' => request()->get('sort', $defaultSort),
            'direction' => request()->get('direction', $defaultDirection),
        ];
    }

    protected function normalizeTableStatus(string $status): string
    {
        return in_array($status, ['drafts', 'draft'], true) ? 'drafts' : 'published';
    }

    /**
     * Нормализует данные поста из формы перед валидацией и сохранением.
     */
    protected function normalizePostData(array $data): array
    {
        $categoryId = (int)($data['category_id'] ?? 0);
        $category = $this->blog->findCategoryById($categoryId);
        $title = trim((string)($data['title'] ?? ''));
        $slug = trim((string)($data['slug'] ?? ''));
        $imageUrl = trim((string)($data['image_url'] ?? ''));
        $user = get_user() ?: [];

        return [
            'title' => $title,
            'slug' => $slug !== '' ? $slug : $title,
            'category_id' => $categoryId,
            'category_name' => $category['name'] ?? '',
            'priority' => max(0, (int)($data['priority'] ?? 0)),
            'excerpt' => trim((string)($data['excerpt'] ?? '')),
            'content' => sanitize_content_html((string)($data['content'] ?? '')),
            'image' => $imageUrl !== '' ? $imageUrl : trim((string)($data['image'] ?? '')),
            'image_url' => $imageUrl,
            'seo_title' => trim((string)($data['seo_title'] ?? '')),
            'seo_description' => trim((string)($data['seo_description'] ?? '')),
            'seo_keywords' => trim((string)($data['seo_keywords'] ?? '')),
            'seo_image' => trim((string)($data['seo_image'] ?? '')),
            'hide_placeholder_image' => (int)($data['hide_placeholder_image'] ?? 0),
            'show_on_home' => (int)($data['show_on_home'] ?? 0),
            'published_at' => trim((string)($data['published_at'] ?? '')),
            'is_published' => (int)($data['is_published'] ?? 0),
            'author_id' => isset($user['id']) ? (int)$user['id'] : null,
            'author_name' => trim((string)($user['name'] ?? 'Fireball')),
            'author_role' => trim((string)($user['role'] ?? 'user')),
        ];
    }

    /**
     * Заполняет обязательные поля для автосохранения, чтобы запись могла остаться черновиком.
     */
    protected function prepareAutosaveDraftData(array $data, array $post = []): array
    {
        if ($data['title'] === '') {
            $data['title'] = trim((string)($post['title'] ?? ''));
        }

        if ($data['title'] === '') {
            $data['title'] = return_translation('admin_posts_status_draft') . ' ' . date('d.m.Y H:i');
        }

        if (trim((string)$data['slug']) === '' && !empty($post['slug'])) {
            $data['slug'] = (string)$post['slug'];
        }

        if ((int)$data['category_id'] <= 0 || trim((string)$data['category_name']) === '') {
            $category = $this->resolveAutosaveCategory($post);
            $data['category_id'] = (int)$category['id'];
            $data['category_name'] = (string)$category['name'];
        }

        if ($data['published_at'] === '' && !empty($post['published_at'])) {
            $data['published_at'] = (string)$post['published_at'];
        }

        $data['is_published'] = 0;

        return $data;
    }

    /**
     * Возвращает категорию для черновика, если пользователь ещё не выбрал её в форме.
     */
    protected function resolveAutosaveCategory(array $post = []): array
    {
        $postCategoryId = (int)($post['category_id'] ?? 0);
        if ($postCategoryId > 0) {
            $category = $this->blog->findCategoryById($postCategoryId);
            if ($category) {
                return [
                    'id' => (int)$category['id'],
                    'name' => localized_value($category, 'name', 'name'),
                ];
            }
        }

        $categories = $this->blog->getCategories();
        if (!empty($categories[0])) {
            return [
                'id' => (int)$categories[0]['id'],
                'name' => localized_value($categories[0], 'name', 'name'),
            ];
        }

        $categoryId = $this->blog->createCategory('Без категории', 'Uncategorized', 'uncategorized');

        return [
            'id' => $categoryId,
            'name' => 'Без категории',
        ];
    }

    /**
     * Проверяет обязательные поля поста и корректность SEO-изображения.
     * Содержимое и основное изображение могут отсутствовать.
     */
    protected function validatePostData(array $data, bool $hasUploadedImage = false): array
    {
        $errors = [];

        if ($data['title'] === '') {
            $errors['title'][] = return_translation('admin_validation_title_required');
        }
        if ($data['slug'] === '') {
            $errors['slug'][] = return_translation('admin_validation_slug_required');
        } elseif (!$this->isValidSlug($data['slug'])) {
            $errors['slug'][] = return_translation('admin_validation_slug_format');
        }
        if ((int)$data['category_id'] <= 0 || $data['category_name'] === '') {
            $errors['category_id'][] = return_translation('admin_validation_category_required');
        }
        if (!$hasUploadedImage && $data['image'] !== '' && !$this->isValidSeoImage($data['image'])) {
            $errors['image_url'][] = return_translation('admin_validation_image_url_invalid');
        }
        if ($data['seo_image'] !== '' && !$this->isValidSeoImage($data['seo_image'])) {
            $errors['seo_image'][] = return_translation('admin_validation_seo_image_invalid');
        }
        if (!(new BlockEditorService())->validateContentJson((string)($data['content'] ?? ''))) {
            $errors['content'][] = return_translation('admin_validation_content_invalid');
        }

        return $errors;
    }

    /**
     * Проверяет файл изображения поста по базовым ограничениям.
     */
    protected function validatePostImageFile(File $file): array
    {
        if (!$file->isFile && $file->getError() === UPLOAD_ERR_NO_FILE) {
            return [];
        }

        if (!$file->isFile || $file->getError() !== UPLOAD_ERR_OK) {
            return ['image_file' => [return_translation('admin_validation_image_upload')]];
        }

        if ($file->getSize() > 25 * 1024 * 1024) {
            return ['image_file' => [return_translation('admin_validation_image_size')]];
        }

        $extension = strtolower($file->getExt());
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            return ['image_file' => [return_translation('admin_validation_image_type')]];
        }

        if (!@getimagesize($file->getTmpName())) {
            return ['image_file' => [return_translation('admin_validation_image_type')]];
        }

        return [];
    }

    /**
     * Сохраняет изображение поста и записывает путь в массив данных формы.
     */
    protected function storePostImageFile(File $file, array &$data, array &$errors): void
    {
        if (!$file->isFile) {
            return;
        }

        $savedPath = $file->save('posts');
        if (!$savedPath) {
            $errors['image_file'][] = return_translation('admin_validation_image_upload');
            return;
        }

        $data['image'] = ltrim((string)$savedPath, '/');
    }

    /**
     * Проверяет slug для URL: только нижний латинский регистр, цифры и дефисы.
     */
    protected function isValidSlug(string $value): bool
    {
        return preg_match('/^[a-z0-9-]+$/', $value) === 1;
    }

    /**
     * Проверяет, что значение SEO-изображения является URL или локальным путём.
     */
    protected function isValidSeoImage(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return true;
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return true;
        }

        if (str_contains($value, "\0") || preg_match('~(^|/)\.\.(/|$)~', $value)) {
            return false;
        }

        return preg_match('~^/?[A-Za-z0-9/_\-.%]+$~', $value) === 1;
    }

}
