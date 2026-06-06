<?php

namespace App\Controllers;

use App\Models\Page;
use App\Modules\BlockEditor\BlockEditor;
use App\Modules\BlockEditor\BlockEditorService;

/**
 * Handles admin CRUD for CMS pages while reusing the post block editor.
 */
class AdminPagesController extends BaseController
{

    protected Page $pages;

    public function __construct()
    {
        $this->pages = new Page();
    }

    /**
     * Lists pages in the admin panel.
     */
    public function index()
    {
        $params = $this->getTableParams('menu_order', 'asc');
        $activeStatus = $this->normalizeTableStatus((string)request()->get(
            'status',
            request()->get('draft_page') !== null ? 'drafts' : 'published'
        ));

        if (request()->isAjax()) {
            $statusCode = $activeStatus === 'drafts' ? 0 : 1;
            $pages = $this->pages->getPagesByPublicationStatus($statusCode, array_merge($params, [
                'page_param' => 'page',
            ]));
            $publishedCount = $activeStatus === 'published'
                ? $pages['total']
                : $this->pages->getPagesByPublicationStatus(1, array_merge($params, ['per_page' => 1, 'page_param' => 'published_count_page']))['total'];
            $draftCount = $activeStatus === 'drafts'
                ? $pages['total']
                : $this->pages->getPagesByPublicationStatus(0, array_merge($params, ['per_page' => 1, 'page_param' => 'draft_count_page']))['total'];

            response()->json([
                'status' => $activeStatus,
                'search' => $params['search'],
                'sort' => $pages['sort'],
                'direction' => $pages['direction'],
                'visible' => count($pages['items']),
                'total' => $pages['total'],
                'counts' => [
                    'published' => $publishedCount,
                    'drafts' => $draftCount,
                ],
                'html' => view()->renderPartial('admin/partials/pages_table_pane', [
                    'items' => $pages['items'],
                    'table_key' => $activeStatus,
                    'empty_text' => $params['search'] !== '' ? return_translation('admin_table_empty_search') : return_translation('admin_pages_empty'),
                    'pagination' => $pages['pagination'],
                    'total' => $pages['total'],
                    'sort' => $pages['sort'],
                    'direction' => $pages['direction'],
                ]),
            ]);
        }

        $publishedPages = $this->pages->getPagesByPublicationStatus(1, array_merge($params, [
            'page_param' => $activeStatus === 'published' ? 'page' : 'published_page',
        ]));
        $draftPages = $this->pages->getPagesByPublicationStatus(0, array_merge($params, [
            'page_param' => $activeStatus === 'drafts' ? 'page' : 'draft_page',
        ]));

        return view('admin/pages', [
            'title' => return_translation('admin_pages_title'),
            'pages' => array_merge($publishedPages['items'], $draftPages['items']),
            'published_pages' => $publishedPages['items'],
            'draft_pages' => $draftPages['items'],
            'published_pagination' => $publishedPages['pagination'],
            'draft_pagination' => $draftPages['pagination'],
            'total' => $publishedPages['total'] + $draftPages['total'],
            'published_total' => $publishedPages['total'],
            'draft_total' => $draftPages['total'],
            'search' => $params['search'],
            'active_status' => $activeStatus,
            'sort' => $publishedPages['sort'],
            'direction' => $publishedPages['direction'],
        ]);
    }

    /**
     * Shows a preview for both published pages and drafts from the admin panel.
     */
    public function preview()
    {
        $pageId = (int)get_route_param('id', 0);
        $page = $this->pages->findById($pageId);

        if (!$page) {
            abort();
        }

        return view('pages/show', [
            'title' => $page['title'],
            'page' => $page,
            'seo_title' => $page['meta_title'] !== '' ? $page['meta_title'] : $page['title'],
            'seo_description' => $page['meta_description'],
            'seo_robots' => 'noindex,nofollow',
            'seo_canonical' => base_href('/admin/pages/preview/' . $pageId),
        ]);
    }

    /**
     * Creates or edits a CMS page.
     */
    public function form()
    {
        $pageId = (int)get_route_param('id', 0);
        $isEdit = $pageId > 0;

        if (request()->isPost()) {
            $data = $this->normalizePageData(request()->getData());
            $errors = $this->validatePageData($data, $isEdit ? $pageId : null);

            if (!empty($errors)) {
                session()->set('form_data', $data);
                session()->set('form_errors', $errors);
                response()->redirect($isEdit ? base_href("/admin/pages/edit/{$pageId}") : base_href('/admin/pages/create'));
            }

            if ($isEdit) {
                $this->pages->updatePage($pageId, $data);
                session()->setFlash('success', return_translation('admin_page_updated'));
            } else {
                $this->pages->createPage($data);
                session()->setFlash('success', return_translation('admin_page_created'));
            }

            session()->remove('form_data');
            session()->remove('form_errors');
            response()->redirect(base_href('/admin/pages'));
        }

        $page = $isEdit ? $this->pages->findById($pageId) : false;
        if ($isEdit && !$page) {
            abort();
        }

        return view('admin/post_form', [
            'title' => $isEdit ? return_translation('admin_page_edit_title') : return_translation('admin_page_create_title'),
            'editor_mode' => 'page',
            'page' => $page ?: [],
            'is_edit' => $isEdit,
            'styles' => BlockEditor::styles(),
            'footer_scripts' => BlockEditor::scripts(),
        ]);
    }

    /**
     * Saves the page form as a draft through the shared editor autosave flow.
     */
    public function autosave()
    {
        $pageId = (int)(request()->post('autosave_post_id') ?: request()->post('id'));
        $page = $pageId > 0 ? $this->pages->findById($pageId) : false;

        if ($pageId > 0 && !$page) {
            response()->json([
                'status' => 'error',
                'message' => return_translation('admin_page_not_found'),
            ], 404);
        }

        $data = $this->prepareAutosaveDraftData($this->normalizePageData(request()->getData()), $page ?: []);

        if ($pageId > 0) {
            $errors = $this->validatePageData($data, $pageId, true);
            if (!empty($errors)) {
                response()->json([
                    'status' => 'error',
                    'message' => reset($errors)[0] ?? return_translation('admin_post_autosave_error'),
                ], 422);
            }
            $this->pages->updatePage($pageId, $data);
        } else {
            $data['slug'] = $this->makeUniqueDraftSlug($data['slug']);
            $pageId = $this->pages->createPage($data);
        }

        response()->json([
            'status' => 'success',
            'id' => $pageId,
            'edit_url' => base_href('/admin/pages/edit/' . $pageId),
            'preview_url' => base_href('/admin/pages/preview/' . $pageId),
            'saved_at' => date('H:i'),
            'message' => return_translation('admin_post_autosave_saved'),
        ]);
    }

    /**
     * Toggles publication state.
     */
    public function togglePublished()
    {
        $pageId = (int)request()->post('id');
        if ($pageId <= 0) {
            session()->setFlash('error', return_translation('admin_page_not_found'));
            response()->redirect(base_href('/admin/pages'));
        }

        $status = $this->pages->togglePublished($pageId);
        if ($status === null) {
            session()->setFlash('error', return_translation('admin_page_not_found'));
        } else {
            session()->setFlash('success', $status === 1 ? return_translation('admin_page_published') : return_translation('admin_page_unpublished'));
        }

        response()->redirect(base_href('/admin/pages'));
    }

    /**
     * Deletes a page.
     */
    public function delete()
    {
        $pageId = (int)request()->post('id');
        if ($pageId > 0) {
            $this->pages->deletePage($pageId);
            session()->setFlash('success', return_translation('admin_page_deleted'));
        }

        response()->redirect(base_href('/admin/pages'));
    }

    protected function getTableParams(string $defaultSort, string $defaultDirection = 'desc'): array
    {
        return [
            'per_page' => 15,
            'search' => request()->get('search', request()->get('q', '')),
            'sort' => request()->get('sort', $defaultSort),
            'direction' => request()->get('direction', $defaultDirection),
        ];
    }

    protected function normalizeTableStatus(string $status): string
    {
        return in_array($status, ['drafts', 'draft'], true) ? 'drafts' : 'published';
    }

    protected function normalizePageData(array $data): array
    {
        $title = trim((string)($data['title'] ?? ''));
        $slug = trim((string)($data['slug'] ?? ''));
        $visibility = trim((string)($data['menu_visibility'] ?? ''));

        if ($visibility === '') {
            $showInHeader = !empty($data['show_in_header']) ? 1 : 0;
            $showInFooter = !empty($data['show_in_footer']) ? 1 : 0;
            $visibility = match (true) {
                $showInHeader === 1 && $showInFooter === 1 => 'both',
                $showInHeader === 1 => 'header',
                $showInFooter === 1 => 'footer',
                default => 'none',
            };
        }

        $visibilityMap = [
            'none' => [0, 0],
            'header' => [1, 0],
            'footer' => [0, 1],
            'both' => [1, 1],
        ];
        [$showInHeader, $showInFooter] = $visibilityMap[$visibility] ?? $visibilityMap['none'];

        return [
            'title' => $title,
            'menu_title' => trim((string)($data['menu_title'] ?? '')),
            'slug' => $slug !== '' ? $slug : $title,
            'content' => sanitize_content_html((string)($data['content'] ?? '')),
            'meta_title' => trim((string)($data['meta_title'] ?? '')),
            'meta_description' => trim((string)($data['meta_description'] ?? '')),
            'is_published' => (int)($data['is_published'] ?? 0),
            'show_in_header' => $showInHeader,
            'show_in_footer' => $showInFooter,
            'show_in_legal_information' => !empty($data['show_in_legal_information']) ? 1 : 0,
            'menu_visibility' => $visibility,
            'menu_order' => max(0, (int)($data['menu_order'] ?? 0)),
        ];
    }

    protected function prepareAutosaveDraftData(array $data, array $page = []): array
    {
        if ($data['title'] === '') {
            $data['title'] = trim((string)($page['title'] ?? ''));
        }

        if ($data['title'] === '') {
            $data['title'] = return_translation('admin_posts_status_draft') . ' ' . date('d.m.Y H:i');
        }

        if (trim((string)$data['slug']) === '' && !empty($page['slug'])) {
            $data['slug'] = (string)$page['slug'];
        }

        $data['is_published'] = 0;

        return $data;
    }

    protected function validatePageData(array $data, ?int $ignoreId = null, bool $autosave = false): array
    {
        $errors = [];
        $slug = make_slug((string)($data['slug'] ?: $data['title']), 'page');

        if ($data['title'] === '') {
            $errors['title'][] = return_translation('admin_validation_page_title_required');
        }
        if (!$autosave && trim((string)($data['content'] ?? '')) === '') {
            $errors['content'][] = return_translation('admin_validation_page_content_required');
        }
        if (!in_array((string)($data['menu_visibility'] ?? 'none'), ['none', 'header', 'footer', 'both'], true)) {
            $errors['menu_visibility'][] = return_translation('admin_validation_page_menu_visibility_invalid');
        }
        if (!$autosave && $slug === '') {
            $errors['slug'][] = return_translation('admin_validation_slug_required');
        } elseif (!$this->isValidSlug($slug)) {
            $errors['slug'][] = return_translation('admin_validation_slug_format');
        } elseif ($this->pages->isReservedSlug($slug)) {
            $errors['slug'][] = return_translation('admin_validation_page_slug_reserved');
        } elseif ($this->pages->slugExists($slug, $ignoreId)) {
            $errors['slug'][] = return_translation('admin_validation_page_slug_unique');
        }
        if (!ctype_digit((string)$data['menu_order']) && (int)$data['menu_order'] < 0) {
            $errors['menu_order'][] = return_translation('admin_validation_menu_order_invalid');
        }
        if (!(new BlockEditorService())->validateContentJson((string)($data['content'] ?? ''))) {
            $errors['content'][] = return_translation('admin_validation_content_invalid');
        }

        return $errors;
    }

    protected function makeUniqueDraftSlug(string $slug): string
    {
        $base = make_slug($slug, 'page');
        $candidate = $base;
        $counter = 1;

        while ($this->pages->isReservedSlug($candidate) || $this->pages->slugExists($candidate)) {
            $candidate = $base . '-' . $counter;
            $counter++;
        }

        return $candidate;
    }

    protected function isValidSlug(string $value): bool
    {
        return preg_match('/^[a-z0-9-]+$/', $value) === 1;
    }

}
