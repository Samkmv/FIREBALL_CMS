<?php
namespace Fireball\Subscriptions\Controllers;

use Fireball\Subscriptions\Repositories\BusinessRepository;
use Fireball\Subscriptions\Services\BusinessImageService;
use FBL\Pagination;

final class BusinessController
{
    public function manage(): string
    {
        $userId = (int)(get_user()['id'] ?? 0);
        $repo = new BusinessRepository();
        $allowed = $repo->canManage($userId);
        if (request()->isPost()) {
            if (!$allowed) { abort('', 403); }
            $data = request()->getData();
            $uploads = [];
            $images = new BusinessImageService();
            try {
                $action = (string)($data['action'] ?? 'page');
                if ($action === 'page') {
                    foreach (['avatar','cover'] as $field) {
                        $path = $images->upload($field, $userId);
                        if ($path !== null) { $uploads[$field] = $path; }
                    }
                    $repo->save($userId, $data, $uploads);
                } elseif ($action === 'camera') {
                    $repo->saveCameraSettings($userId, $data);
                } elseif ($action === 'post') {
                    $path = $images->upload('image', $userId);
                    if ($path !== null) { $uploads['image'] = $path; }
                    $repo->savePost($userId, $data, $path);
                } elseif ($action === 'delete-post') {
                    $repo->deletePost($userId, (int)($data['post_id'] ?? 0));
                } elseif ($action === 'reply') {
                    $repo->reply($userId, (int)($data['review_id'] ?? 0), $data);
                } else { abort('', 400); }
                session()->setFlash('success', \FireballPluginSubscriptions::t('business_saved'));
            } catch (\DomainException $exception) {
                foreach ($uploads as $path) { $images->remove($path, $userId); }
                abort('', 403);
            } catch (\InvalidArgumentException $exception) {
                foreach ($uploads as $path) { $images->remove($path, $userId); }
                session()->setFlash('error', $exception->getMessage());
                session()->set('subscriptions.business_form', $data);
            } catch (\Throwable $exception) {
                foreach ($uploads as $path) { $images->remove($path, $userId); }
                log_error_details('Business page save failed', [], $exception);
                session()->setFlash('error', \FireballPluginSubscriptions::t('business_save_error'));
                session()->set('subscriptions.business_form', $data);
            }
            $returnSection = match ((string)($data['action'] ?? 'page')) {
                'camera' => 'camera', 'reply' => 'reviews',
                'post', 'delete-post' => match ((string)($data['kind'] ?? '')) { 'promotion'=>'promotions', 'photo'=>'gallery', default=>'posts' },
                default=>'settings',
            };
            response()->redirect(base_href('/account/business?section=' . $returnSection));
        }
        $page = $repo->forUser($userId);
        $section = (string)request()->get('section', 'overview');
        if ($section === 'company') { $section = 'settings'; }
        if (!in_array($section, ['overview','posts','promotions','camera','gallery','statistics','settings','reviews'], true)) { $section = 'overview'; }
        $kind = match ($section) { 'promotions'=>'promotion', 'gallery'=>'photo', default=>null };
        $postsPagination = new Pagination($page ? $repo->countPosts((int)$page['id'], $kind) : 0, 20);
        $reviewsPagination = new Pagination($page ? $repo->countReviews((int)$page['id']) : 0, 20, pageParam: 'reviews_page');
        $form = (array)session()->get('subscriptions.business_form', []);
        session()->remove('subscriptions.business_form');
        return $this->view('public/business-manage', [
            'use_profile_styles'=>true, 'title'=>\FireballPluginSubscriptions::t('business_manage'), 'page'=>$page ?? [], 'allowed'=>$allowed,
            'section'=>$section, 'owner'=>get_user(), 'business_subscription'=>$repo->businessSubscription($userId),
            'business_camera'=>$this->camera($page ?? [], true),
            'business_stats'=>['posts'=>$page ? $repo->countPosts((int)$page['id']) : 0,
                'photos'=>$page ? $repo->countPosts((int)$page['id'], 'photo') : 0,
                'promotions'=>$page ? $repo->countPosts((int)$page['id'], 'promotion') : 0,
                'reviews'=>$page ? $repo->countReviews((int)$page['id']) : 0,
                'rating'=>$page ? $repo->rating((int)$page['id'])['average'] : null],
            'latest_posts'=>$page ? array_slice($repo->posts((int)$page['id']), 0, 3) : [],
            'latest_promotions'=>$page ? array_slice($repo->posts((int)$page['id'], 0, 'promotion'), 0, 1) : [],
            'footer_scripts'=>[base_href('/plugins/subscriptions/assets/business-dashboard.js?v=' . (is_file(__DIR__ . '/../../assets/business-dashboard.js') ? filemtime(__DIR__ . '/../../assets/business-dashboard.js') : time()))],
            'form_data'=>$form, 'posts'=>$page ? $repo->posts((int)$page['id'], $postsPagination->getOffset(), $kind) : [],
            'posts_pagination'=>$postsPagination,
            'reviews'=>$page ? $repo->reviews((int)$page['id'], false, $reviewsPagination->getOffset()) : [],
            'reviews_pagination'=>$reviewsPagination,
        ]);
    }

    public function directory(): string
    {
        if (!\FireballPluginSubscriptions::businessPublicEnabled()) { abort(); }
        $repo = new BusinessRepository();
        $rawSearch = request()->get('q', '');
        $search = is_scalar($rawSearch) ? mb_substr(trim((string)$rawSearch), 0, 190) : '';
        $sort = request()->get('sort', 'newest');
        if (!in_array($sort, ['newest','rating','name'], true)) { $sort = 'newest'; }
        $total = $repo->publicCount($search);
        $pagination = new Pagination($total);
        return $this->view('public/business-directory', [
            'title'=>\FireballPluginSubscriptions::t('business_directory'), 'search'=>$search, 'sort'=>$sort,
            'businesses'=>$repo->publicPages($search, $sort, PAGINATION_SETTINGS['perPage'], $pagination->getOffset()),
            'total_businesses'=>$total, 'pagination'=>$pagination->getHtml(),
            'top_businesses'=>$repo->publicPages('', 'rating', 5),
            'seo_canonical'=>base_href('/business'),
            'seo_robots'=>$search !== '' ? 'noindex,follow' : 'index,follow',
        ]);
    }

    public function show(): string
    {
        if (!\FireballPluginSubscriptions::businessPublicEnabled()) { abort(); }
        $repo = new BusinessRepository();
        $slug = (string)get_route_param('slug');
        $page = ctype_digit($slug) ? $repo->find((int)$slug, true) : $repo->findBySlug($slug, true);
        if (!$page) { abort(); }
        if ($slug !== $page['slug']) { response()->redirect(base_href(BusinessRepository::publicPath($page))); }
        $id = (int)$page['id'];
        $userId = (int)(get_user()['id'] ?? 0);
        $rating = $repo->rating($id);
        $publicSection = (string)request()->get('section', 'home');
        $kind = match ($publicSection) { 'promotions'=>'promotion', 'news'=>'news', 'gallery'=>'photo', default=>null };
        if ($kind === null) { $publicSection = 'home'; }
        $pagination = new Pagination($repo->countPosts($id, $kind), 20);
        $reviewPagination = new Pagination((int)$rating['total'], 20, pageParam: 'reviews_page');
        $camera = $this->camera($page);
        return $this->view('public/business', ['use_profile_styles'=>true, 'title'=>$page['name'], 'page'=>$page, 'camera'=>$camera,
            'public_section'=>$publicSection,
            'featured_promotion'=>$repo->posts($id, 0, 'promotion')[0] ?? null,
            'latest_news'=>array_slice($repo->posts($id, 0, 'news'), 0, 3),
            'photos'=>array_slice($repo->posts($id, 0, 'photo'), 0, 6), 'photo_total'=>$repo->countPosts($id, 'photo'),
            'posts'=>$repo->posts($id, $pagination->getOffset(), $kind), 'posts_pagination'=>$pagination,
            'reviews'=>$repo->reviews($id, false, $reviewPagination->getOffset()), 'reviews_pagination'=>$reviewPagination,
            'rating'=>$rating, 'user_id'=>$userId, 'own_review'=>$repo->reviewForUser($id, $userId),
            'can_manage'=>(int)$page['user_id'] === $userId && $repo->canManage($userId)]);
    }

    public function review(): never
    {
        if (!\FireballPluginSubscriptions::businessPublicEnabled()) { abort(); }
        $repo = new BusinessRepository();
        $slug = (string)get_route_param('slug');
        $page = ctype_digit($slug) ? $repo->find((int)$slug, true) : $repo->findBySlug($slug, true);
        if (!$page) { abort(); }
        $id = (int)$page['id'];
        $userId = (int)(get_user()['id'] ?? 0);
        try {
            if (request()->post('action') === 'delete') { $repo->deleteReview($id, $userId); }
            else { $repo->saveReview($id, $userId, request()->getData()); }
            session()->setFlash('success', \FireballPluginSubscriptions::t('business_saved'));
        } catch (\DomainException $exception) { abort('', 403);
        } catch (\InvalidArgumentException $exception) {
            session()->setFlash('error', $exception->getMessage());
        } catch (\Throwable $exception) {
            log_error_details('Business review save failed', [], $exception);
            session()->setFlash('error', \FireballPluginSubscriptions::t('business_save_error'));
        }
        response()->redirect(base_href(BusinessRepository::publicPath($page) . '#reviews'));
    }

    public function admin(): string
    {
        $repo = new BusinessRepository();
        if (request()->isPost()) {
            $id = (int)request()->post('business_id', 0);
            try {
                if (!$repo->find($id)) { throw new \InvalidArgumentException(); }
                if (request()->post('action') === 'moderate') {
                    db()->query('UPDATE subscription_business_reviews SET is_hidden=?, updated_at=? WHERE id=? AND business_id=?', [!empty(request()->post('is_hidden')) ? 1 : 0, date('Y-m-d H:i:s'), (int)request()->post('review_id', 0), $id]);
                } elseif (request()->post('action') === 'unpublish') {
                    db()->query('UPDATE subscription_business_pages SET is_published=0, updated_at=? WHERE id=?', [date('Y-m-d H:i:s'), $id]);
                    search_remove_entity(\Fireball\Subscriptions\Search\BusinessSearchProvider::NAME, $id);
                } elseif (request()->post('action') === 'camera-links') {
                    $repo->saveCameraLinks($id, request()->getData());
                } else {
                    $repo->assignCamera($id, (int)request()->post('camera_id', 0) ?: null);
                }
                session()->setFlash('success', \FireballPluginSubscriptions::t('business_saved'));
            } catch (\InvalidArgumentException $exception) {
                session()->setFlash('error', $exception->getMessage() ?: \FireballPluginSubscriptions::t('business_camera_error'));
                if (request()->post('action') === 'camera-links') {
                    session()->set('subscriptions.business_camera_form', ['business_id'=>$id,
                        'camera_url'=>(string)request()->post('camera_url', ''), 'camera_poster'=>(string)request()->post('camera_poster', '')]);
                }
            } catch (\Throwable $exception) {
                log_error_details('Business administration failed', [], $exception);
                session()->setFlash('error', \FireballPluginSubscriptions::t('business_camera_error'));
                if (request()->post('action') === 'camera-links') {
                    session()->set('subscriptions.business_camera_form', ['business_id'=>$id,
                        'camera_url'=>(string)request()->post('camera_url', ''), 'camera_poster'=>(string)request()->post('camera_poster', '')]);
                }
            }
            response()->redirect(base_href('/admin/subscriptions/business' . ($id ? '?business=' . $id : '')));
        }
        $pagination = new Pagination((int)db()->query('SELECT COUNT(*) FROM subscription_business_pages')->getColumn(), 20);
        $offset = $pagination->getOffset();
        $selected = $repo->find((int)request()->get('business', 0));
        $reviewsPagination = new Pagination($selected ? $repo->countReviews((int)$selected['id'], true) : 0, 20, pageParam: 'reviews_page');
        $pages = db()->query("SELECT b.*, u.name AS owner FROM subscription_business_pages b INNER JOIN users u ON u.id=b.user_id ORDER BY b.id DESC LIMIT 20 OFFSET {$offset}")->get() ?: [];
        $cameraForm = (array)session()->get('subscriptions.business_camera_form', []);
        session()->remove('subscriptions.business_camera_form');
        if ($cameraForm && $selected && !in_array((int)$selected['id'], array_map('intval', array_column($pages, 'id')), true)) {
            $selected['owner'] = db()->query('SELECT name FROM users WHERE id=?', [$selected['user_id']])->getColumn();
            array_unshift($pages, $selected);
        }
        foreach ($pages as &$business) {
            if (empty($business['camera_url']) && !empty($business['camera_id'])) {
                $legacy = $this->camera($business, true);
                if ($legacy) { $business['camera_url'] = $legacy['url']; $business['camera_poster'] = $legacy['poster']; }
            }
            if ((int)($cameraForm['business_id'] ?? 0) === (int)$business['id']) {
                $business['camera_url'] = $cameraForm['camera_url'];
                $business['camera_poster'] = $cameraForm['camera_poster'];
            }
        }
        unset($business);
        return $this->view('admin/business', ['title'=>\FireballPluginSubscriptions::t('business_manage'),
            'tabs'=>\FireballPluginSubscriptions::tabs('business'),
            'footer_scripts'=>[base_href('/plugins/subscriptions/assets/business-dashboard.js?v=' . filemtime(__DIR__ . '/../../assets/business-dashboard.js'))],
            'pages'=>$pages,
            'pagination'=>$pagination, 'selected'=>$selected,
            'reviews'=>$selected ? $repo->reviews((int)$selected['id'], true, $reviewsPagination->getOffset()) : [],
            'reviews_pagination'=>$reviewsPagination]);
    }

    private function camera(array $page, bool $owner = false): ?array
    {
        if (!$owner && empty($page['show_camera'])) { return null; }
        if (!empty($page['camera_url'])) {
            return ['title'=>$page['camera_title'] ?: \FireballPluginSubscriptions::t('business_camera'),
                'stream_key'=>'', 'url'=>$page['camera_url'], 'poster'=>$page['camera_poster'] ?? ''];
        }
        if (empty($page['camera_id']) || !class_exists('FireballPluginCameraManager')) { return null; }
        $assigned = \FireballPluginCameraManager::camera((int)$page['camera_id']);
        if (!$assigned || empty($assigned['enabled'])) { return null; }
        $site = \FireballPluginCameraManager::site((int)$assigned['site_id']);
        if (!$site || empty($site['enabled'])) { return null; }
        return ['title'=>$page['camera_title'] ?: $assigned['name'], 'stream_key'=>$assigned['stream_key'],
            'url'=>\FireballPluginCameraManager::hlsUrl($assigned['stream_key']),
            'poster'=>\FireballPluginCameraManager::posterUrl($assigned['stream_key'])];
    }

    private function view(string $view, array $data): string
    {
        return plugin_view('subscriptions', $view, \FireballPluginSubscriptions::viewData($data));
    }
}
