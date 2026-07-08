<?php

namespace App\Controllers;

use App\Models\Admin;
use App\Models\Analytics;
use App\Models\ContactRequest;
use App\Models\ContactSubject;
use App\Models\MailLog;
use App\Models\Page;
use App\Models\Post;
use App\Models\SecurityLog;
use App\Models\SiteSetting;
use App\Models\Support;
use App\Models\User;
use App\Modules\BlockEditor\BlockEditor;
use App\Modules\BlockEditor\BlockEditorService;
use App\Services\AnalyticsService;
use App\Services\MailService;
use App\Services\NotificationService;
use App\Services\PwaService;
use App\Services\UpdateCenter;
use App\Widgets\Menu\Menu;
use FBL\Auth;
use FBL\File;
use FBL\Markdown;
use FBL\Theme;

/**
 * Управляет административной частью: контентом, пользователями, ролями, заявками и настройками сайта.
 */
class AdminController extends BaseController
{

    protected Admin $blog;
    protected Analytics $analytics;
    protected ContactRequest $contactRequests;
    protected ContactSubject $contactSubjects;
    protected MailLog $mailLogs;
    protected Page $pages;
    protected SecurityLog $securityLogs;
    protected User $users;
    protected SiteSetting $siteSettings;
    protected Support $support;
    protected UpdateCenter $updateCenter;
    protected AnalyticsService $analyticsService;

    /**
     * Инициализирует модели, используемые в административной панели.
     */
    public function __construct()
    {
        $this->blog = new Admin();
        $this->analytics = new Analytics();
        $this->contactRequests = new ContactRequest();
        $this->contactSubjects = new ContactSubject();
        $this->mailLogs = new MailLog();
        $this->pages = new Page();
        $this->securityLogs = new SecurityLog();
        $this->users = new User();
        $this->siteSettings = new SiteSetting();
        $this->support = new Support();
        $this->updateCenter = new UpdateCenter($this->siteSettings);
        $this->analyticsService = new AnalyticsService();
    }

    /**
     * Показывает главную страницу админки со сводной статистикой.
     */
    public function dashboard()
    {
        $stats = $this->blog->getStats();
        $stats = array_merge($stats, $this->analytics->getStats());
        $stats = array_merge($stats, $this->support->getStats());
        $stats['pages'] = (int)($this->pages->getPaginated(['per_page' => 1])['total'] ?? 0);
        $stats['contact_requests'] = $this->contactRequests->countAll();
        $stats['contact_requests_new'] = $this->contactRequests->countNew();

        return view('admin/dashboard', [
            'title' => return_translation('admin_dashboard_title'),
            'stats' => $stats,
            'analytics_dashboard' => $this->analyticsService->dashboardData(),
            'engine_release' => require CONFIG . '/version.php',
            'update_center' => check_creator() ? $this->updateCenter->getDashboardData() : [],
            'footer_scripts' => [
                base_url('/assets/default/vendor/chart.js/chart.umd.js'),
                base_url('/assets/default/vendor/apexcharts/apexcharts.min.js?v=' . filemtime(WWW . '/assets/default/vendor/apexcharts/apexcharts.min.js')),
                base_url('/assets/default/js/admin-analytics.js?v=' . filemtime(WWW . '/assets/default/js/admin-analytics.js')),
            ],
        ]);
    }

    /**
     * Показывает список заявок из формы контактов и помечает их просмотренными.
     */
    public function contactRequests()
    {
        $params = $this->getTableParams('created_at', 'desc');
        $params['status'] = request()->get('status', '');
        $requests = $this->contactRequests->getPaginated($params);
        $this->contactRequests->markAllViewed();

        return view('admin/contact_requests', [
            'title' => return_translation('admin_support_requests_title'),
            'requests' => $requests['items'],
            'pagination' => $requests['pagination'],
            'total' => $requests['total'],
            'search' => $requests['search'],
            'status' => $requests['status'],
            'statuses' => $this->contactRequests->statuses(),
            'new_count' => $this->contactRequests->countNew(),
            'sort' => $requests['sort'],
            'direction' => $requests['direction'],
        ]);
    }

    /**
     * Удаляет заявку по идентификатору и возвращает в список заявок.
     */
    public function contactRequestDelete()
    {
        $requestId = (int)request()->post('id');
        if ($requestId > 0) {
            $this->contactRequests->deleteById($requestId);
            session()->setFlash('success', return_translation('admin_contact_deleted'));
        }

        response()->redirect(base_href('/admin/support/requests'));
    }

    public function contactRequestStatus()
    {
        $this->contactRequests->updateStatus((int)request()->post('id'), (string)request()->post('status'));
        session()->setFlash('success', return_translation('admin_support_request_status_updated'));

        response()->redirect(base_href('/admin/support/requests'));
    }

    public function contactRequestBulk()
    {
        $count = $this->contactRequests->bulkAction(
            (array)request()->post('ids', []),
            (string)request()->post('action', ''),
            (string)request()->post('status', '')
        );

        if ($count > 0) {
            session()->setFlash('success', return_translation('admin_support_bulk_done'));
        }

        response()->redirect(base_href('/admin/support/requests'));
    }

    public function supportFaq()
    {
        $params = $this->getTableParams('sort_order', 'asc');
        $params['category_id'] = request()->get('category_id', 0);
        $params['published'] = request()->get('published', '');
        $faq = $this->support->getPaginatedFaq($params);

        return view('admin/support_faq', [
            'title' => return_translation('admin_support_faq_title'),
            'items' => $faq['items'],
            'categories' => $this->support->getFaqCategories(),
            'pagination' => $faq['pagination'],
            'total' => $faq['total'],
            'search' => $faq['search'],
            'category_id' => $faq['category_id'],
            'published' => $faq['published'],
            'sort' => $faq['sort'],
            'direction' => $faq['direction'],
        ]);
    }

    public function supportFaqForm()
    {
        $id = (int)get_route_param('id', 0);
        $isEdit = $id > 0;
        $item = $isEdit ? $this->support->findFaq($id) : [];
        if ($isEdit && !$item) {
            abort();
        }

        if (request()->isPost()) {
            $data = $this->normalizeSupportFaqData(request()->getData());
            $errors = $this->validateSupportRequired($data, ['question', 'answer']);

            if ($errors) {
                $this->redirectSupportForm($data, $errors, $isEdit ? "/admin/support/faq/edit/{$id}" : '/admin/support/faq/create');
            }

            $this->support->saveFaq($id, $data);
            session()->remove('form_data');
            session()->remove('form_errors');
            session()->setFlash('success', return_translation($isEdit ? 'admin_support_faq_updated' : 'admin_support_faq_created'));
            response()->redirect(base_href('/admin/support/faq'));
        }

        return view('admin/support_faq_form', [
            'title' => return_translation($isEdit ? 'admin_support_faq_edit_title' : 'admin_support_faq_create_title'),
            'item' => $item ?: [],
            'categories' => $this->support->getFaqCategories(),
            'is_edit' => $isEdit,
        ]);
    }

    public function supportFaqDelete()
    {
        if ($this->support->deleteFaq((int)request()->post('id'))) {
            session()->setFlash('success', return_translation('admin_support_faq_deleted'));
        }

        response()->redirect(base_href('/admin/support/faq'));
    }

    public function supportFaqCategories()
    {
        return view('admin/support_faq_categories', [
            'title' => return_translation('admin_support_faq_categories_title'),
            'categories' => $this->support->getFaqCategories(),
        ]);
    }

    public function supportFaqCategoryForm()
    {
        $id = (int)get_route_param('id', 0);
        $isEdit = $id > 0;
        $category = $isEdit ? $this->support->findFaqCategory($id) : [];
        if ($isEdit && !$category) {
            abort();
        }

        if (request()->isPost()) {
            $data = $this->normalizeSupportCategoryData(request()->getData(), false);
            $errors = $this->validateSupportRequired($data, ['name']);
            if ($errors) {
                $this->redirectSupportForm($data, $errors, $isEdit ? "/admin/support/faq/categories/edit/{$id}" : '/admin/support/faq/categories/create');
            }

            $this->support->saveFaqCategory($id, $data);
            session()->remove('form_data');
            session()->remove('form_errors');
            session()->setFlash('success', return_translation($isEdit ? 'admin_support_category_updated' : 'admin_support_category_created'));
            response()->redirect(base_href('/admin/support/faq/categories'));
        }

        return view('admin/support_category_form', [
            'title' => return_translation($isEdit ? 'admin_support_category_edit_title' : 'admin_support_category_create_title'),
            'category' => $category ?: [],
            'is_edit' => $isEdit,
            'kind' => 'faq',
        ]);
    }

    public function supportFaqCategoryDelete()
    {
        if ($this->support->deleteFaqCategory((int)request()->post('id'))) {
            session()->setFlash('success', return_translation('admin_support_category_deleted'));
        }

        response()->redirect(base_href('/admin/support/faq/categories'));
    }

    public function supportKnowledgeBase()
    {
        $params = $this->getTableParams('created_at', 'desc');
        $params['category_id'] = request()->get('category_id', 0);
        $params['published'] = request()->get('published', '');
        $articles = $this->support->getPaginatedKbArticles($params);

        return view('admin/support_kb', [
            'title' => return_translation('admin_support_kb_title'),
            'articles' => $articles['items'],
            'categories' => $this->support->getKbCategories(),
            'pagination' => $articles['pagination'],
            'total' => $articles['total'],
            'search' => $articles['search'],
            'category_id' => $articles['category_id'],
            'published' => $articles['published'],
            'sort' => $articles['sort'],
            'direction' => $articles['direction'],
        ]);
    }

    public function supportKbArticleForm()
    {
        $id = (int)get_route_param('id', 0);
        $isEdit = $id > 0;
        $article = $isEdit ? $this->support->findKbArticle($id) : [];
        if ($isEdit && !$article) {
            abort();
        }

        if (request()->isPost()) {
            $data = $this->normalizeSupportKbArticleData(request()->getData());
            $errors = $this->validateSupportRequired($data, ['title']);
            if (!$this->hasSupportKbArticleContent((string)($data['content'] ?? ''))) {
                $errors['content'][] = return_translation('admin_support_validation_required');
            }
            if (!(new BlockEditorService())->validateContentJson((string)($data['content'] ?? ''))) {
                $errors['content'][] = return_translation('admin_validation_content_invalid');
            }
            if ($errors) {
                $this->redirectSupportForm($data, $errors, $isEdit ? "/admin/support/knowledge-base/edit/{$id}" : '/admin/support/knowledge-base/create');
            }

            $this->support->saveKbArticle($id, $data);
            session()->remove('form_data');
            session()->remove('form_errors');
            session()->setFlash('success', return_translation($isEdit ? 'admin_support_kb_updated' : 'admin_support_kb_created'));
            response()->redirect(base_href('/admin/support/knowledge-base'));
        }

        return view('admin/support_kb_form', [
            'title' => return_translation($isEdit ? 'admin_support_kb_edit_title' : 'admin_support_kb_create_title'),
            'article' => $article ?: [],
            'categories' => $this->support->getKbCategories(),
            'is_edit' => $isEdit,
            'styles' => BlockEditor::styles(),
            'footer_scripts' => BlockEditor::scripts(),
        ]);
    }

    public function supportKbArticleDelete()
    {
        if ($this->support->deleteKbArticle((int)request()->post('id'))) {
            session()->setFlash('success', return_translation('admin_support_kb_deleted'));
        }

        response()->redirect(base_href('/admin/support/knowledge-base'));
    }

    public function supportKbCategories()
    {
        return view('admin/support_kb_categories', [
            'title' => return_translation('admin_support_kb_categories_title'),
            'categories' => $this->support->getKbCategories(),
        ]);
    }

    public function supportKbCategoryForm()
    {
        $id = (int)get_route_param('id', 0);
        $isEdit = $id > 0;
        $category = $isEdit ? $this->support->findKbCategory($id) : [];
        if ($isEdit && !$category) {
            abort();
        }

        if (request()->isPost()) {
            $data = $this->normalizeSupportCategoryData(request()->getData(), true);
            $errors = $this->validateSupportRequired($data, ['name']);
            if ($errors) {
                $this->redirectSupportForm($data, $errors, $isEdit ? "/admin/support/knowledge-base/categories/edit/{$id}" : '/admin/support/knowledge-base/categories/create');
            }

            $this->support->saveKbCategory($id, $data);
            session()->remove('form_data');
            session()->remove('form_errors');
            session()->setFlash('success', return_translation($isEdit ? 'admin_support_category_updated' : 'admin_support_category_created'));
            response()->redirect(base_href('/admin/support/knowledge-base/categories'));
        }

        return view('admin/support_category_form', [
            'title' => return_translation($isEdit ? 'admin_support_category_edit_title' : 'admin_support_category_create_title'),
            'category' => $category ?: [],
            'is_edit' => $isEdit,
            'kind' => 'kb',
        ]);
    }

    public function supportKbCategoryDelete()
    {
        if ($this->support->deleteKbCategory((int)request()->post('id'))) {
            session()->setFlash('success', return_translation('admin_support_category_deleted'));
        }

        response()->redirect(base_href('/admin/support/knowledge-base/categories'));
    }

    public function supportSettings()
    {
        $settings = $this->siteSettings->all();

        if (request()->isPost()) {
            $data = $this->normalizeSupportSettingsData(request()->getData());
            $errors = [];
            if ($data['support_notification_email'] !== '' && !filter_var($data['support_notification_email'], FILTER_VALIDATE_EMAIL)) {
                $errors['support_notification_email'][] = return_translation('admin_support_validation_email');
            }

            if ($errors) {
                $this->redirectSupportForm($data, $errors, '/admin/support/settings');
            }

            $this->siteSettings->setMany($data);
            session()->remove('form_data');
            session()->remove('form_errors');
            session()->setFlash('success', return_translation('admin_support_settings_saved'));
            response()->redirect(base_href('/admin/support/settings'));
        }

        return view('admin/support_settings', [
            'title' => return_translation('admin_support_settings_title'),
            'settings' => $settings,
        ]);
    }

    public function mailSettings()
    {
        $settings = $this->siteSettings->all();
        $mailService = new MailService($this->siteSettings, $this->mailLogs);

        if (request()->isPost()) {
            $action = trim((string)request()->post('mail_action', 'save'));

            if ($action === 'test') {
                $email = mb_strtolower(trim((string)request()->post('test_email', '')));
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    session()->setFlash('error', return_translation('admin_mail_test_email_invalid'));
                    response()->redirect(base_href('/admin/settings/mail'));
                }

                if (!$mailService->isEnabled()) {
                    session()->setFlash('error', return_translation('admin_mail_not_configured'));
                    response()->redirect(base_href('/admin/settings/mail'));
                }

                $sent = $mailService->sendTest($email);
                session()->setFlash(
                    $sent ? 'success' : 'error',
                    return_translation($sent ? 'admin_mail_test_sent' : 'admin_mail_test_failed')
                );
                response()->redirect(base_href('/admin/settings/mail'));
            }

            $data = $this->normalizeMailSettingsData(request()->getData(), $settings);
            $errors = $this->validateMailSettingsData($data);
            if ($errors) {
                session()->set('form_data', array_diff_key($data, ['mail_password' => true]));
                session()->set('form_errors', $errors);
                response()->redirect(base_href('/admin/settings/mail'));
            }

            $this->siteSettings->setMany($data);
            session()->remove('form_data');
            session()->remove('form_errors');
            session()->setFlash('success', return_translation('admin_mail_settings_saved'));
            response()->redirect(base_href('/admin/settings/mail'));
        }

        return view('admin/mail_settings', [
            'title' => return_translation('admin_mail_settings_title'),
            'settings' => $settings,
            'mail_configured' => $mailService->isEnabled(),
        ]);
    }

    public function mailLogs()
    {
        $logs = $this->mailLogs->getPaginated($this->getTableParams('created_at', 'desc'));

        return view('admin/mail_logs', [
            'title' => return_translation('admin_mail_logs_title'),
            'logs' => $logs['items'],
            'pagination' => $logs['pagination'],
            'total' => $logs['total'],
            'search' => $logs['search'],
            'sort' => $logs['sort'],
            'direction' => $logs['direction'],
        ]);
    }

    public function securityLogs()
    {
        $logs = $this->securityLogs->getPaginated($this->getTableParams('created_at', 'desc'));

        return view('admin/security_logs', [
            'title' => return_translation('admin_security_logs_title'),
            'logs' => $logs['items'],
            'pagination' => $logs['pagination'],
            'total' => $logs['total'],
            'search' => $logs['search'],
            'sort' => $logs['sort'],
            'direction' => $logs['direction'],
        ]);
    }

    /**
     * Показывает список категорий блога в административной панели.
     */
    public function categories()
    {
        $categories = $this->blog->getPaginatedCategories($this->getTableParams('id', 'desc'));

        return view('admin/categories', [
            'title' => return_translation('admin_categories_title'),
            'categories' => $categories['items'],
            'pagination' => $categories['pagination'],
            'total' => $categories['total'],
            'search' => $categories['search'],
            'sort' => $categories['sort'],
            'direction' => $categories['direction'],
        ]);
    }

    /**
     * Показывает форму категории и обрабатывает создание или обновление категории.
     */
    public function categoryForm()
    {
        $categoryId = (int)get_route_param('id', 0);
        $isEdit = $categoryId > 0;

        if (request()->isPost()) {
            $data = $this->normalizeCategoryData(request()->getData());
            $errors = $this->validateCategoryData($data);

            if (!empty($errors)) {
                session()->set('form_data', $data);
                session()->set('form_errors', $errors);
                response()->redirect($isEdit ? base_href("/admin/categories/edit/{$categoryId}") : base_href('/admin/categories/create'));
            }

            if ($isEdit) {
                $this->blog->updateCategory($categoryId, $data['name_ru'], $data['name_en'], $data['slug'], $this->extractSeoData($data));
                session()->setFlash('success', return_translation('admin_category_updated'));
            } else {
                $this->blog->createCategory($data['name_ru'], $data['name_en'], $data['slug'], $this->extractSeoData($data));
                session()->setFlash('success', return_translation('admin_category_created'));
            }

            session()->remove('form_data');
            session()->remove('form_errors');
            response()->redirect(base_href('/admin/categories'));
        }

        $category = $isEdit ? $this->blog->findCategoryById($categoryId) : false;
        if ($isEdit && !$category) {
            abort();
        }

        return view('admin/category_form', [
            'title' => $isEdit ? return_translation('admin_category_edit_title') : return_translation('admin_category_create_title'),
            'category' => $category ?: [],
            'is_edit' => $isEdit,
            'footer_scripts' => [
                base_url('/assets/default/js/admin-file-manager.js?v=' . filemtime(WWW . '/assets/default/js/admin-file-manager.js')),
            ],
        ]);
    }

    /**
     * Удаляет категорию, если это разрешено бизнес-логикой.
     */
    public function categoryDelete()
    {
        $categoryId = (int)request()->post('id');
        if ($categoryId <= 0) {
            response()->redirect(base_href('/admin/categories'));
        }

        if (!$this->blog->deleteCategory($categoryId)) {
            session()->setFlash('error', return_translation('admin_category_delete_blocked'));
            response()->redirect(base_href('/admin/categories'));
        }

        session()->setFlash('success', return_translation('admin_category_deleted'));
        response()->redirect(base_href('/admin/categories'));
    }

    /**
     * Показывает список пользователей административной панели.
     */
    public function users()
    {
        Auth::touchPresence(1);
        $users = $this->users->getPaginatedUsers($this->getTableParams('created_at', 'desc'));

        return view('admin/users', [
            'title' => return_translation('admin_users_title'),
            'users' => $users['items'],
            'pagination' => $users['pagination'],
            'total' => $users['total'],
            'search' => $users['search'],
            'sort' => $users['sort'],
            'direction' => $users['direction'],
            'allow_admin_reset_user_2fa' => $this->siteSettingEnabled('allow_admin_reset_user_2fa'),
        ]);
    }

    /**
     * Показывает форму редактирования пользователя и обрабатывает её отправку.
     */
    public function userForm()
    {
        $userId = (int)get_route_param('id', 0);
        $isEdit = $userId > 0;

        $user = $isEdit ? $this->users->findEditableUserById($userId) : [];
        if ($isEdit && !$user) {
            abort();
        }

        if ($isEdit && request()->isPost() && $this->users->isProtectedUser($user)) {
            session()->setFlash('error', return_translation('admin_users_creator_protected'));
            response()->redirect(base_href('/admin/users'));
        }

        if (request()->isPost()) {
            $data = $this->normalizeUserData(request()->getData());
            $errors = $isEdit
                ? $this->users->validateAdminUpdate($data, $userId)
                : $this->users->validateAdminCreate($data);
            $avatarFile = new File('avatar_file');
            $errors = array_merge_recursive($errors, $this->users->validateAvatarFile($avatarFile));

            if (!empty($errors)) {
                session()->set('form_data', $data);
                session()->set('form_errors', $errors);
                response()->redirect($isEdit ? base_href("/admin/users/edit/{$userId}") : base_href('/admin/users/create'));
            }

            $avatar = $this->users->storeAvatar($avatarFile, $isEdit ? ($user['avatar'] ?? null) : null);
            if ($avatar === false) {
                session()->set('form_data', $data);
                session()->set('form_errors', [
                    'avatar_file' => [return_translation('auth_profile_avatar_upload_error')],
                ]);
                response()->redirect($isEdit ? base_href("/admin/users/edit/{$userId}") : base_href('/admin/users/create'));
            }

            $data['avatar'] = $avatar;

            if ($isEdit) {
                $this->users->updateFromAdmin($userId, $data);
            } else {
                $userId = $this->users->createFromAdmin($data);
            }

            if ($isEdit && (int)(get_user()['id'] ?? 0) === $userId) {
                \FBL\Auth::setUser();
            }

            session()->remove('form_data');
            session()->remove('form_errors');
            session()->setFlash('success', return_translation($isEdit ? 'admin_user_updated' : 'admin_user_created'));
            response()->redirect(base_href('/admin/users'));
        }

        return view('admin/user_form', [
            'title' => return_translation($isEdit ? 'admin_user_edit_title' : 'admin_user_create_title'),
            'user_item' => $user,
            'roles' => $this->users->getRoles(),
            'is_edit' => $isEdit,
        ]);
    }

    /**
     * Удаляет пользователя с учётом ограничений на самоудаление и последнего администратора.
     */
    public function userDelete()
    {
        $userId = (int)request()->post('id');
        if ($userId <= 0) {
            response()->redirect(base_href('/admin/users'));
        }

        $error = $this->users->deleteUser($userId, (int)(get_user()['id'] ?? 0));
        if ($error !== null) {
            $message = match ($error) {
                'protected' => return_translation('admin_users_creator_protected'),
                'self' => return_translation('admin_users_delete_self_blocked'),
                'last_admin' => return_translation('admin_users_delete_last_admin_blocked'),
                default => return_translation('admin_users_not_found'),
            };

            session()->setFlash('error', $message);
            response()->redirect(base_href('/admin/users'));
        }

        session()->setFlash('success', return_translation('admin_user_deleted'));
        response()->redirect(base_href('/admin/users'));
    }

    public function resetUserTwoFactor()
    {
        $targetUserId = (int)request()->post('id');
        $reason = trim((string)request()->post('reason', ''));
        $adminPassword = (string)request()->post('admin_password', '');
        $adminCode = trim((string)request()->post('admin_2fa_code', ''));
        $redirect = base_href('/admin/users');

        $targetUser = $this->users->findById($targetUserId);
        $actorUser = $this->users->findById((int)(get_user()['id'] ?? 0));
        if (!$targetUser || !$actorUser) {
            session()->setFlash('error', return_translation('admin_users_not_found'));
            response()->redirect($redirect);
        }

        if (!$this->siteSettingEnabled('allow_admin_reset_user_2fa') || !$this->canResetUserTwoFactor($actorUser, $targetUser)) {
            $this->securityLogs->record('admin_reset_2fa', 'denied', (int)$actorUser['id'], (int)$targetUser['id'], 'permission_denied');
            session()->setFlash('error', return_translation('admin_2fa_reset_denied'));
            response()->redirect($redirect);
        }

        if (!$this->users->hasTwoFactorEnabled($targetUser)) {
            session()->setFlash('error', return_translation('admin_2fa_reset_not_enabled'));
            response()->redirect($redirect);
        }

        if ($reason === '') {
            session()->setFlash('error', return_translation('admin_2fa_reset_reason_required'));
            response()->redirect($redirect);
        }

        if ($this->siteSettingEnabled('require_admin_password_for_2fa_reset') && !password_verify($adminPassword, (string)$actorUser['password'])) {
            $this->securityLogs->record('admin_reset_2fa', 'failed', (int)$actorUser['id'], (int)$targetUser['id'], 'password_failed');
            session()->setFlash('error', return_translation('admin_2fa_reset_password_invalid'));
            response()->redirect($redirect);
        }

        if ($this->siteSettingEnabled('require_admin_2fa_for_2fa_reset') && $this->users->hasTwoFactorEnabled($actorUser)) {
            if (!$this->users->verifyTwoFactorCode($actorUser, $adminCode)) {
                $this->securityLogs->record('admin_reset_2fa', 'failed', (int)$actorUser['id'], (int)$targetUser['id'], '2fa_failed');
                session()->setFlash('error', return_translation('admin_2fa_reset_code_invalid'));
                response()->redirect($redirect);
            }
        }

        $this->users->disableTwoFactor((int)$targetUser['id']);
        $this->users->invalidateSessions((int)$targetUser['id']);
        $this->users->markTwoFactorResetNotice((int)$targetUser['id']);
        $this->securityLogs->record('admin_reset_2fa', 'success', (int)$actorUser['id'], (int)$targetUser['id'], $reason);

        if ($this->siteSettingEnabled('notify_user_after_admin_2fa_reset')) {
            $mailService = new MailService();
            if ($mailService->isEnabled()) {
                $mailService->sendTemplate(
                    [(string)$targetUser['email']],
                    return_translation('auth_two_factor_admin_reset_notice_email_subject'),
                    'auth/admin_two_factor_reset_notice_email',
                    []
                );
            }
        }

        session()->setFlash('success', return_translation('admin_2fa_reset_success'));
        response()->redirect($redirect);
    }

    /**
     * Показывает список ролей пользователей.
     */
    public function roles()
    {
        $roles = $this->users->getPaginatedRoles($this->getTableParams('id', 'asc'));

        return view('admin/roles', [
            'title' => return_translation('admin_roles_title'),
            'roles' => $roles['items'],
            'pagination' => $roles['pagination'],
            'total' => $roles['total'],
            'search' => $roles['search'],
            'sort' => $roles['sort'],
            'direction' => $roles['direction'],
        ]);
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

    protected function siteSettingEnabled(string $key): bool
    {
        return $this->siteSettings->get($key, '1') === '1';
    }

    protected function canResetUserTwoFactor(array $actorUser, array $targetUser): bool
    {
        $actorId = (int)($actorUser['id'] ?? 0);
        $targetId = (int)($targetUser['id'] ?? 0);
        if ($actorId <= 0 || $targetId <= 0 || $actorId === $targetId) {
            return false;
        }

        $actorRole = (string)($actorUser['role'] ?? User::ROLE_USER);
        $targetRole = (string)($targetUser['role'] ?? User::ROLE_USER);
        if ($targetRole === User::ROLE_CREATOR) {
            return false;
        }

        if ($actorRole === User::ROLE_CREATOR) {
            return in_array($targetRole, [User::ROLE_ADMIN, User::ROLE_MODERATOR, User::ROLE_USER], true);
        }

        if ($actorRole === User::ROLE_ADMIN) {
            return in_array($targetRole, [User::ROLE_MODERATOR, User::ROLE_USER], true);
        }

        return false;
    }

    protected function normalizeSupportFaqData(array $data): array
    {
        return [
            'question' => trim((string)($data['question'] ?? '')),
            'answer' => trim((string)($data['answer'] ?? '')),
            'category_id' => max(0, (int)($data['category_id'] ?? 0)),
            'sort_order' => (int)($data['sort_order'] ?? 0),
            'is_published' => !empty($data['is_published']) ? 1 : 0,
        ];
    }

    protected function normalizeSupportKbArticleData(array $data): array
    {
        return [
            'title' => trim((string)($data['title'] ?? '')),
            'slug' => trim((string)($data['slug'] ?? '')),
            'excerpt' => trim((string)($data['excerpt'] ?? '')),
            'content' => trim((string)($data['content'] ?? '')),
            'category_id' => max(0, (int)($data['category_id'] ?? 0)),
            'is_published' => !empty($data['is_published']) ? 1 : 0,
        ];
    }

    protected function hasSupportKbArticleContent(string $content): bool
    {
        $content = trim($content);
        if ($content === '') {
            return false;
        }

        if ($content[0] !== '{') {
            return $this->supportMarkupHasContent($content);
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded) || !isset($decoded['blocks']) || !is_array($decoded['blocks'])) {
            return true;
        }

        foreach ($decoded['blocks'] as $block) {
            if (!is_array($block) || !empty($block['hidden'])) {
                continue;
            }

            $data = is_array($block['data'] ?? null) ? $block['data'] : [];
            $type = (string)($block['type'] ?? '');

            if (in_array($type, ['text', 'heading', 'html'], true)) {
                if ($this->supportMarkupHasContent((string)($data['html'] ?? ''))) {
                    return true;
                }

                continue;
            }

            if ($type === 'code') {
                if (trim((string)($data['code'] ?? '')) !== '') {
                    return true;
                }

                continue;
            }

            if (in_array($type, ['image', 'video', 'audio'], true)) {
                if (trim((string)($data['src'] ?? '')) !== '') {
                    return true;
                }

                continue;
            }

            if ($type === 'slider') {
                foreach ((array)($data['items'] ?? []) as $item) {
                    if (!is_array($item)) {
                        continue;
                    }

                    if (
                        trim((string)($item['image'] ?? '')) !== ''
                        || trim((string)($item['title'] ?? '')) !== ''
                        || trim((string)($item['text'] ?? '')) !== ''
                    ) {
                        return true;
                    }
                }
                continue;
            }

            if ($type === 'social') {
                foreach ((array)($data['items'] ?? []) as $item) {
                    if (!is_array($item)) {
                        continue;
                    }

                    if (trim((string)($item['url'] ?? '')) !== '' || trim((string)($item['label'] ?? '')) !== '') {
                        return true;
                    }
                }
                continue;
            }

            if ($this->supportBlockValueHasContent($data)) {
                return true;
            }
        }

        return false;
    }

    protected function supportMarkupHasContent(string $html): bool
    {
        $html = trim($html);

        return trim(strip_tags($html)) !== ''
            || preg_match('/<(img|video|audio|iframe|table|ul|ol|pre|blockquote|figure)\b/i', $html) === 1;
    }

    protected function supportBlockValueHasContent(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if ($this->supportBlockValueHasContent($item)) {
                    return true;
                }
            }

            return false;
        }

        return trim(strip_tags((string)$value)) !== '';
    }

    protected function normalizeSupportCategoryData(array $data, bool $withSlug): array
    {
        $normalized = [
            'name' => trim((string)($data['name'] ?? '')),
            'sort_order' => (int)($data['sort_order'] ?? 0),
        ];

        if ($withSlug) {
            $normalized['slug'] = trim((string)($data['slug'] ?? ''));
        }

        return $normalized;
    }

    protected function normalizeSupportSettingsData(array $data): array
    {
        return [
            'support_public_enabled' => !empty($data['support_public_enabled']) ? '1' : '0',
            'support_notification_email' => trim((string)($data['support_notification_email'] ?? '')),
            'support_autoreply_enabled' => !empty($data['support_autoreply_enabled']) ? '1' : '0',
            'support_autoreply_subject' => trim((string)($data['support_autoreply_subject'] ?? '')),
            'support_autoreply_message' => trim((string)($data['support_autoreply_message'] ?? '')),
            'support_spam_protection' => !empty($data['support_spam_protection']) ? '1' : '0',
            'support_notify_new_requests' => !empty($data['support_notify_new_requests']) ? '1' : '0',
            'support_notify_status_changes' => !empty($data['support_notify_status_changes']) ? '1' : '0',
        ];
    }

    protected function validateSupportRequired(array $data, array $fields): array
    {
        $errors = [];
        foreach ($fields as $field) {
            if (trim((string)($data[$field] ?? '')) === '') {
                $errors[$field][] = return_translation('admin_support_validation_required');
            }
        }

        return $errors;
    }

    protected function redirectSupportForm(array $data, array $errors, string $path): void
    {
        session()->set('form_data', $data);
        session()->set('form_errors', $errors);
        response()->redirect(base_href($path));
    }

    /**
     * Показывает форму роли и обрабатывает создание или редактирование роли.
     */
    public function roleForm()
    {
        $roleId = (int)get_route_param('id', 0);
        $isEdit = $roleId > 0;
        $role = $isEdit ? $this->users->findRoleById($roleId) : false;

        if ($isEdit && !$role) {
            abort();
        }

        if ($isEdit && request()->isPost() && $this->users->isProtectedRole($role)) {
            session()->setFlash('error', return_translation('admin_roles_creator_protected'));
            response()->redirect(base_href('/admin/roles'));
        }

        if (request()->isPost()) {
            $data = $this->normalizeRoleData(request()->getData(), $role ?: []);
            $errors = $this->users->validateRoleData($data, $isEdit ? $roleId : null);

            if (!empty($errors)) {
                session()->set('form_data', $data);
                session()->set('form_errors', $errors);
                response()->redirect($isEdit ? base_href("/admin/roles/edit/{$roleId}") : base_href('/admin/roles/create'));
            }

            if ($isEdit) {
                $this->users->updateRole($roleId, $data);
                session()->setFlash('success', return_translation('admin_role_updated'));
            } else {
                $this->users->createRole($data);
                session()->setFlash('success', return_translation('admin_role_created'));
            }

            session()->remove('form_data');
            session()->remove('form_errors');
            response()->redirect(base_href('/admin/roles'));
        }

        return view('admin/role_form', [
            'title' => $isEdit ? return_translation('admin_role_edit_title') : return_translation('admin_role_create_title'),
            'role' => $role ?: [],
            'is_edit' => $isEdit,
        ]);
    }

    /**
     * Удаляет роль, если она не системная и не привязана к пользователям.
     */
    public function roleDelete()
    {
        $roleId = (int)request()->post('id');
        if ($roleId <= 0) {
            response()->redirect(base_href('/admin/roles'));
        }

        $error = $this->users->deleteRole($roleId);
        if ($error !== null) {
            $message = match ($error) {
                'protected' => return_translation('admin_roles_creator_protected'),
                'system' => return_translation('admin_roles_delete_system_blocked'),
                'assigned' => return_translation('admin_roles_delete_assigned_blocked'),
                default => return_translation('admin_roles_not_found'),
            };

            session()->setFlash('error', $message);
            response()->redirect(base_href('/admin/roles'));
        }

        session()->setFlash('success', return_translation('admin_role_deleted'));
        response()->redirect(base_href('/admin/roles'));
    }

    /**
     * Показывает страницу настроек сайта и сохраняет их изменения.
     */
    public function settings()
    {
        if (request()->isPost()) {
            $previousDefaultLocale = \FBL\Localization::siteLocale();
            $data = $this->normalizeSettingsData(request()->getData());
            $errors = $this->validateSettingsData($data);

            if (!empty($errors)) {
                session()->set('form_data', $data);
                session()->set('form_errors', $errors);
                response()->redirect(base_href('/admin/settings'));
            }

            $this->siteSettings->setMany($data);
            if ($previousDefaultLocale !== $data['default_locale']) {
                Page::clearPublicCache();
                Post::clearPublicCache();
                Menu::clearCache();
            }
            try {
                (new PwaService($this->siteSettings))->syncIcons();
            } catch (\Throwable $exception) {
                log_error_details('PWA icon sync failed', ['site_favicon' => $data['site_favicon']], $exception);
            }
            session()->remove('form_data');
            session()->remove('form_errors');
            session()->setFlash('success', return_translation('admin_settings_saved'));
            response()->redirect(base_href('/admin/settings'));
        }

        return view('admin/settings', [
            'title' => return_translation('admin_settings_title'),
            'settings' => $this->siteSettings->all(),
            'published_pages' => $this->pages->getPublishedOptions(),
            'engine_release' => require CONFIG . '/version.php',
            'footer_scripts' => [
                base_url('/assets/default/js/admin-file-manager.js?v=' . filemtime(WWW . '/assets/default/js/admin-file-manager.js')),
            ],
        ]);
    }

    public function pwaSettings()
    {
        $pwa = new PwaService($this->siteSettings);

        if (request()->isPost()) {
            $data = $this->normalizePwaSettingsData(request()->getData());
            $errors = $this->validatePwaSettingsData($data);

            if (!empty($errors)) {
                session()->set('form_data', $data);
                session()->set('form_errors', $errors);
                response()->redirect(base_href('/admin/settings/pwa'));
            }

            $this->siteSettings->setMany($data);
            try {
                $pwa->syncIcons();
            } catch (\Throwable $exception) {
                log_error_details('PWA icon sync failed', $data, $exception);
            }
            session()->remove('form_data');
            session()->remove('form_errors');
            session()->setFlash('success', return_translation('admin_pwa_saved'));
            response()->redirect(base_href('/admin/settings/pwa'));
        }

        return view('admin/pwa_settings', [
            'title' => return_translation('admin_pwa_title'),
            'settings' => $this->siteSettings->all(),
            'status' => $pwa->status(),
            'devices' => $pwa->devices(),
            'notifications' => $pwa->recentNotifications(),
            'footer_scripts' => [
                base_url('/assets/default/js/admin-file-manager.js?v=' . filemtime(WWW . '/assets/default/js/admin-file-manager.js')),
            ],
        ]);
    }

    public function generatePwaVapid()
    {
        try {
            (new PwaService($this->siteSettings))->generateVapidKeys();
            session()->setFlash('success', return_translation('admin_pwa_vapid_generated'));
        } catch (\Throwable $exception) {
            log_error_details('PWA VAPID generation failed', [], $exception);
            session()->setFlash('error', return_translation('admin_pwa_vapid_error'));
        }

        response()->redirect(base_href('/admin/settings/pwa'));
    }

    public function testPwaPush()
    {
        try {
            $result = NotificationService::broadcast([
                'title' => return_translation('pwa_test_push_title'),
                'body' => return_translation('pwa_test_push_body'),
                'url' => base_href('/'),
                'tag' => 'fireball-test-push',
            ]);
            session()->setFlash(
                $result['sent'] > 0 ? 'success' : 'warning',
                str_replace(
                    [':sent', ':failed', ':total'],
                    [(string)$result['sent'], (string)$result['failed'], (string)$result['total']],
                    return_translation('admin_pwa_test_push_result')
                )
            );
        } catch (\Throwable $exception) {
            log_error_details('PWA test push failed', [], $exception);
            session()->setFlash('error', return_translation('admin_pwa_test_push_error'));
        }

        response()->redirect(base_href('/admin/settings/pwa'));
    }

    /**
     * Shows and saves cookie consent and privacy settings.
     */
    public function privacySettings()
    {
        if (request()->isPost()) {
            $data = $this->normalizePrivacySettingsData(request()->getData());
            $errors = $this->validatePrivacySettingsData($data);

            if (!empty($errors)) {
                session()->set('form_data', $data);
                session()->set('form_errors', $errors);
                response()->redirect(base_href('/admin/settings/privacy'));
            }

            $this->siteSettings->setMany($data);
            session()->remove('form_data');
            session()->remove('form_errors');
            session()->setFlash('success', return_translation('admin_privacy_saved'));
            response()->redirect(base_href('/admin/settings/privacy'));
        }

        return view('admin/privacy_settings', [
            'title' => return_translation('admin_privacy_title'),
            'settings' => $this->siteSettings->all(),
            'published_pages' => $this->pages->getPublishedOptions(),
            'footer_scripts' => [
                base_url('/assets/default/js/admin-cookie-consent.js?v=' . filemtime(WWW . '/assets/default/js/admin-cookie-consent.js')),
            ],
        ]);
    }

    /**
     * Shows contact subjects managed by the site administrator.
     */
    public function contactSubjects()
    {
        return view('admin/contact_subjects', [
            'title' => return_translation('admin_contact_subjects_title'),
            'subjects' => $this->contactSubjects->getAll(),
        ]);
    }

    /**
     * Creates or updates a contact subject.
     */
    public function contactSubjectForm()
    {
        $id = (int)(get_route_param('id') ?? 0);
        $subject = $id > 0 ? $this->contactSubjects->find($id) : null;
        if ($id > 0 && $subject === null) {
            session()->setFlash('error', return_translation('admin_contact_subject_not_found'));
            response()->redirect(base_href('/admin/support/subjects'));
        }

        if (request()->isPost()) {
            $data = [
                'name' => trim((string)request()->post('name', '')),
                'is_active' => request()->post('is_active', '0') === '1' ? 1 : 0,
                'sort_order' => trim((string)request()->post('sort_order', '0')),
            ];
            $errors = [];

            if ($data['name'] === '') {
                $errors['name'][] = return_translation('admin_validation_contact_subject_name_required');
            } elseif (mb_strlen($data['name']) > 190) {
                $errors['name'][] = return_translation('admin_validation_contact_subject_name_length');
            } elseif ($this->contactSubjects->existsByName($data['name'], $id)) {
                $errors['name'][] = return_translation('admin_validation_contact_subject_duplicate');
            }

            if (
                filter_var($data['sort_order'], FILTER_VALIDATE_INT) === false
                || (int)$data['sort_order'] < -999999
                || (int)$data['sort_order'] > 999999
            ) {
                $errors['sort_order'][] = return_translation('admin_validation_contact_subject_sort_order');
            }

            if (!empty($errors)) {
                session()->set('form_data', $data);
                session()->set('form_errors', $errors);
                response()->redirect($id > 0
                    ? base_href('/admin/support/subjects/edit/' . $id)
                    : base_href('/admin/support/subjects/create'));
            }

            $data['sort_order'] = (int)$data['sort_order'];
            if ($id > 0) {
                $this->contactSubjects->update($id, $data);
                $message = return_translation('admin_contact_subject_updated');
            } else {
                $this->contactSubjects->create($data);
                $message = return_translation('admin_contact_subject_created');
            }

            session()->remove('form_data');
            session()->remove('form_errors');
            session()->setFlash('success', $message);
            response()->redirect(base_href('/admin/support/subjects'));
        }

        return view('admin/contact_subject_form', [
            'title' => return_translation($id > 0
                ? 'admin_contact_subject_edit_title'
                : 'admin_contact_subject_create_title'),
            'subject' => $subject,
            'is_edit' => $id > 0,
        ]);
    }

    public function contactSubjectToggle()
    {
        $id = (int)request()->post('id');
        $isActive = request()->post('is_active', '0') === '1';

        if (!$this->contactSubjects->setActive($id, $isActive)) {
            session()->setFlash('error', return_translation('admin_contact_subject_not_found'));
        } else {
            session()->setFlash(
                'success',
                return_translation($isActive
                    ? 'admin_contact_subject_enabled'
                    : 'admin_contact_subject_disabled')
            );
        }

        response()->redirect(base_href('/admin/support/subjects'));
    }

    public function contactSubjectDelete()
    {
        $id = (int)request()->post('id');

        if (!$this->contactSubjects->delete($id)) {
            session()->setFlash('error', return_translation('admin_contact_subject_not_found'));
        } else {
            session()->setFlash('success', return_translation('admin_contact_subject_deleted'));
        }

        response()->redirect(base_href('/admin/support/subjects'));
    }

    /**
     * Shows installed CMS themes and the active theme state.
     */
    public function themes()
    {
        return view('admin/themes', [
            'title' => return_translation('admin_themes_title'),
            'themes' => Theme::getThemes(),
            'active_theme' => Theme::getActiveTheme(),
        ]);
    }

    /**
     * Creates a new theme from the admin panel.
     */
    public function themeCreate()
    {
        if (request()->isPost()) {
            $data = $this->normalizeThemeData(request()->getData(), true);
            $data['preview_upload'] = request()->files['preview_upload'] ?? null;
            $errors = $this->validateThemeData($data, true);

            if (!empty($errors)) {
                session()->set('form_data', $data);
                session()->set('form_errors', $errors);
                response()->redirect(base_href('/admin/themes/create'));
            }

            try {
                $theme = Theme::createTheme($data);
            } catch (\Throwable $exception) {
                session()->set('form_data', $data);
                session()->set('form_errors', [
                    'slug' => [return_translation('admin_themes_create_error')],
                ]);
                response()->redirect(base_href('/admin/themes/create'));
            }

            session()->remove('form_data');
            session()->remove('form_errors');
            session()->setFlash('success', return_translation('admin_themes_created'));
            response()->redirect(base_href('/admin/themes/edit/' . $theme['slug'] . '?created=1'));
        }

        return view('admin/theme_form', [
            'title' => return_translation('admin_themes_create_title'),
            'theme_item' => [],
            'is_edit' => false,
            'created' => false,
            'footer_scripts' => [
                base_url('/assets/default/js/admin-file-manager.js?v=' . filemtime(WWW . '/assets/default/js/admin-file-manager.js')),
            ],
        ]);
    }

    /**
     * Imports a theme ZIP package after validating it.
     */
    public function themeImport()
    {
        if (request()->isPost()) {
            try {
                $theme = Theme::import(request()->files['theme_zip'] ?? null);
            } catch (\Throwable $exception) {
                session()->setFlash('error', return_translation('admin_themes_import_error') . ' ' . $exception->getMessage());
                response()->redirect(base_href('/admin/themes/import'));
            }

            session()->setFlash('success', return_translation('admin_themes_imported'));
            response()->redirect(base_href('/admin/themes/import?imported=' . rawurlencode((string)$theme['slug'])));
        }

        $importedSlug = trim((string)request()->get('imported', ''));
        $importedTheme = $importedSlug !== '' ? Theme::getTheme($importedSlug) : null;

        return view('admin/theme_import', [
            'title' => return_translation('admin_themes_import_title'),
            'imported_theme' => $importedTheme,
        ]);
    }

    /**
     * Edits theme metadata stored in theme.json. Slug is immutable.
     */
    public function themeEdit()
    {
        $slug = trim((string)get_route_param('slug'));
        $theme = Theme::getTheme($slug);
        if (!$theme) {
            abort();
        }

        if (request()->isPost()) {
            $data = $this->normalizeThemeData(request()->getData(), false);
            $data['preview_upload'] = request()->files['preview_upload'] ?? null;
            $errors = $this->validateThemeData($data, false);

            if (!empty($errors)) {
                session()->set('form_data', $data);
                session()->set('form_errors', $errors);
                response()->redirect(base_href('/admin/themes/edit/' . $slug));
            }

            if (!Theme::updateTheme($slug, $data)) {
                session()->setFlash('error', return_translation('admin_themes_update_error'));
                response()->redirect(base_href('/admin/themes/edit/' . $slug));
            }

            session()->remove('form_data');
            session()->remove('form_errors');
            session()->setFlash('success', return_translation('admin_themes_updated'));
            response()->redirect(base_href('/admin/themes/edit/' . $slug));
        }

        return view('admin/theme_form', [
            'title' => return_translation('admin_themes_edit_title'),
            'theme_item' => $theme,
            'is_edit' => true,
            'created' => request()->get('created') === '1',
            'footer_scripts' => [
                base_url('/assets/default/js/admin-file-manager.js?v=' . filemtime(WWW . '/assets/default/js/admin-file-manager.js')),
            ],
        ]);
    }

    /**
     * Deletes a theme unless it is default or currently active.
     */
    public function themeDelete()
    {
        $slug = trim((string)request()->post('slug'));

        if (!Theme::deleteTheme($slug)) {
            session()->setFlash('error', return_translation('admin_themes_delete_error'));
            response()->redirect(base_href('/admin/themes'));
        }

        session()->setFlash('success', return_translation('admin_themes_deleted'));
        response()->redirect(base_href('/admin/themes'));
    }

    /**
     * Shows a read-only list of files generated for a theme.
     */
    public function themeFiles()
    {
        $slug = trim((string)get_route_param('slug'));
        $theme = Theme::getTheme($slug);
        if (!$theme) {
            abort();
        }

        return view('admin/theme_files', [
            'title' => return_translation('admin_themes_files_title'),
            'theme_item' => $theme,
            'files' => Theme::listThemeFiles($slug),
        ]);
    }

    /**
     * Redirects an admin to frontend preview mode without activating the theme.
     */
    public function themePreview()
    {
        $slug = trim((string)get_route_param('slug'));
        $previewUrl = Theme::preview($slug);
        if ($previewUrl === null) {
            session()->setFlash('error', return_translation('admin_themes_preview_error'));
            response()->redirect(base_href('/admin/themes'));
        }

        response()->redirect($previewUrl);
    }

    /**
     * Exports a theme as a ZIP package.
     */
    public function themeExport()
    {
        $slug = trim((string)get_route_param('slug'));

        try {
            $zipPath = Theme::export($slug);
        } catch (\Throwable $exception) {
            session()->setFlash('error', return_translation('admin_themes_export_error') . ' ' . $exception->getMessage());
            response()->redirect(base_href('/admin/themes'));
        }

        $downloadName = $slug . '.zip';
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Content-Length: ' . filesize($zipPath));
        readfile($zipPath);
        @unlink($zipPath);
        exit;
    }

    public function docs()
    {
        return view('admin/docs_index', [
            'title' => 'Документация',
        ]);
    }

    /**
     * Shows developer documentation for FIREBALL CMS themes.
     */
    public function themeDocs()
    {
        $articles = $this->themeDocsArticles();
        $article = trim((string)get_route_param('article', 'introduction'));
        if (!isset($articles[$article])) {
            $article = 'introduction';
        }

        $query = trim((string)request()->get('q', ''));
        $language = current_locale();
        $resolved = $this->resolveThemeDocsArticle($language, $article);
        $content = is_file($resolved['path']) ? (string)file_get_contents($resolved['path']) : '';
        $articleKeys = array_keys($articles);
        $currentIndex = array_search($article, $articleKeys, true);
        $previousArticle = $currentIndex > 0 ? $articleKeys[$currentIndex - 1] : null;
        $nextArticle = $currentIndex < count($articleKeys) - 1 ? $articleKeys[$currentIndex + 1] : null;

        return view('admin/docs_themes', [
            'title' => return_translation('admin_docs_themes_title'),
            'articles' => $articles,
            'article' => $article,
            'article_title' => $articles[$article],
            'content_html' => Markdown::render($content),
            'query' => $query,
            'search_results' => $this->searchThemeDocs($language, $query, $articles),
            'previous_article' => $previousArticle,
            'next_article' => $nextArticle,
            'resolved_language' => $resolved['language'],
            'route_base' => '/admin/docs/themes',
            'docs_path_label' => 'themes',
            'shell_title' => return_translation('admin_docs_themes_heading'),
            'shell_subtitle' => return_translation('admin_docs_themes_subtitle'),
            'back_url' => base_href('/admin/docs'),
            'back_label' => 'Все разделы',
            'nav_label' => 'Theme documentation',
        ]);
    }

    public function pluginDocs()
    {
        $articles = $this->pluginDocsArticles();
        $article = trim((string)get_route_param('article', 'introduction'));
        if (!isset($articles[$article])) {
            $article = 'introduction';
        }

        $query = trim((string)request()->get('q', ''));
        $language = current_locale();
        $resolved = $this->resolveDocsArticle($language, 'plugins', $article);
        $content = is_file($resolved['path']) ? (string)file_get_contents($resolved['path']) : '';
        $articleKeys = array_keys($articles);
        $currentIndex = array_search($article, $articleKeys, true);
        $previousArticle = $currentIndex > 0 ? $articleKeys[$currentIndex - 1] : null;
        $nextArticle = $currentIndex < count($articleKeys) - 1 ? $articleKeys[$currentIndex + 1] : null;

        return view('admin/docs_themes', [
            'title' => 'Документация плагинов',
            'articles' => $articles,
            'article' => $article,
            'article_title' => $articles[$article],
            'content_html' => Markdown::render($content),
            'query' => $query,
            'search_results' => $this->searchDocs($language, 'plugins', $query, $articles),
            'previous_article' => $previousArticle,
            'next_article' => $nextArticle,
            'resolved_language' => $resolved['language'],
            'route_base' => '/admin/docs/plugins',
            'docs_path_label' => 'plugins',
            'shell_title' => 'Документация плагинов',
            'shell_subtitle' => 'Разработка, подключение и жизненный цикл плагинов FIREBALL CMS.',
            'back_url' => base_href('/admin/docs'),
            'back_label' => 'Все разделы',
            'nav_label' => 'Plugin documentation',
        ]);
    }

    public function pwaDocs()
    {
        $articles = $this->pwaDocsArticles();
        $article = trim((string)get_route_param('article', 'introduction'));
        if (!isset($articles[$article])) {
            $article = 'introduction';
        }

        $query = trim((string)request()->get('q', ''));
        $language = current_locale();
        $resolved = $this->resolveDocsArticle($language, 'pwa', $article);
        $content = is_file($resolved['path']) ? (string)file_get_contents($resolved['path']) : '';
        $articleKeys = array_keys($articles);
        $currentIndex = array_search($article, $articleKeys, true);
        $previousArticle = $currentIndex > 0 ? $articleKeys[$currentIndex - 1] : null;
        $nextArticle = $currentIndex < count($articleKeys) - 1 ? $articleKeys[$currentIndex + 1] : null;

        return view('admin/docs_themes', [
            'title' => 'Документация PWA',
            'articles' => $articles,
            'article' => $article,
            'article_title' => $articles[$article],
            'content_html' => Markdown::render($content),
            'query' => $query,
            'search_results' => $this->searchDocs($language, 'pwa', $query, $articles),
            'previous_article' => $previousArticle,
            'next_article' => $nextArticle,
            'resolved_language' => $resolved['language'],
            'route_base' => '/admin/docs/pwa',
            'docs_path_label' => 'pwa',
            'shell_title' => 'Документация PWA',
            'shell_subtitle' => 'Установка приложения, manifest, Service Worker, Push и NotificationService.',
            'back_url' => base_href('/admin/docs'),
            'back_label' => 'Все разделы',
            'nav_label' => 'PWA documentation',
        ]);
    }

    /**
     * Activates a valid theme by slug.
     */
    public function activateTheme()
    {
        $slug = trim((string)request()->post('slug'));

        if (!Theme::activate($slug)) {
            session()->setFlash('error', return_translation('admin_themes_activate_error'));
            response()->redirect(base_href('/admin/themes'));
        }

        session()->setFlash('success', return_translation('admin_themes_activated'));
        response()->redirect(base_href('/admin/themes'));
    }

    /**
     * Показывает отдельную страницу центра обновлений и сохраняет его настройки.
     */
    public function updates()
    {
        $this->requireCreatorForUpdates();

        if (request()->isPost()) {
            $data = $this->normalizeUpdateSettingsData(request()->getData());
            $errors = $this->validateUpdateSettingsData($data);

            if (!empty($errors)) {
                session()->set('form_data', $data);
                session()->set('form_errors', $errors);
                response()->redirect(base_href('/admin/updates'));
            }

            $this->siteSettings->setMany($data);
            session()->remove('form_data');
            session()->remove('form_errors');
            session()->setFlash('success', return_translation('admin_updates_saved'));
            response()->redirect(base_href('/admin/updates'));
        }

        return view('admin/updates', [
            'title' => return_translation('admin_updates_title'),
            'settings' => $this->siteSettings->all(),
            'engine_release' => require CONFIG . '/version.php',
            'update_center' => $this->updateCenter->getDashboardData(),
        ]);
    }

    /**
     * Проверяет наличие обновлений CMS на GitHub.
     */
    public function checkForUpdates()
    {
        $this->requireCreatorForUpdates();

        try {
            $result = $this->updateCenter->checkForUpdates();
            session()->setFlash('success', (string)($result['message'] ?? return_translation('admin_update_check_available')));
        } catch (\Throwable $exception) {
            session()->setFlash('error', $exception->getMessage());
        }

        response()->redirect(base_href('/admin/updates?scroll=update-center'));
    }

    /**
     * Запускает обновление CMS из GitHub-репозитория.
     */
    public function runUpdate()
    {
        $this->requireCreatorForUpdates();

        try {
            $result = $this->updateCenter->runUpdate();
            $status = (string)($result['status'] ?? 'success');
            $message = (string)($result['message'] ?? return_translation('admin_update_success'));

            if ($status === 'warning') {
                session()->setFlash('info', $message);
            } else {
                session()->setFlash('success', $message);
            }
        } catch (\Throwable $exception) {
            session()->setFlash('error', $exception->getMessage());
        }

        response()->redirect(base_href('/admin/updates'));
    }

    /**
     * Откатывает git-установку к состоянию перед последним обновлением.
     */
    public function rollbackUpdate()
    {
        $this->requireCreatorForUpdates();

        try {
            $result = $this->updateCenter->runRollback();
            session()->setFlash('info', (string)($result['message'] ?? return_translation('admin_update_rollback_success')));
        } catch (\Throwable $exception) {
            session()->setFlash('error', $exception->getMessage());
        }

        response()->redirect(base_href('/admin/updates'));
    }

    private function requireCreatorForUpdates(): void
    {
        if (!Auth::hasRole('creator')) {
            abort(return_translation('error_403_message'), 403);
        }
    }

    /**
     * Нормализует данные категории перед сохранением.
     */
    protected function normalizeCategoryData(array $data): array
    {
        $nameRu = trim((string)($data['name_ru'] ?? $data['name'] ?? ''));
        $nameEn = trim((string)($data['name_en'] ?? ''));
        $slug = trim((string)($data['slug'] ?? ''));

        if ($slug === '') {
            $slug = $nameEn !== '' ? $nameEn : $nameRu;
        }

        return [
            'name_ru' => $nameRu,
            'name_en' => $nameEn,
            'slug' => $slug,
            'seo_title' => trim((string)($data['seo_title'] ?? '')),
            'seo_description' => trim((string)($data['seo_description'] ?? '')),
            'seo_keywords' => trim((string)($data['seo_keywords'] ?? '')),
            'seo_image' => trim((string)($data['seo_image'] ?? '')),
        ];
    }

    /**
     * Проверяет обязательные поля категории и валидность SEO-изображения.
     */
    protected function validateCategoryData(array $data): array
    {
        $errors = [];

        if ($data['name_ru'] === '') {
            $errors['name_ru'][] = return_translation('admin_validation_name_ru_required');
        }
        if ($data['name_en'] === '') {
            $errors['name_en'][] = return_translation('admin_validation_name_en_required');
        } elseif (!$this->isValidEnglishLabel($data['name_en'])) {
            $errors['name_en'][] = return_translation('admin_validation_name_en_latin');
        }
        if ($data['slug'] === '') {
            $errors['slug'][] = return_translation('admin_validation_slug_required');
        } elseif (!$this->isValidSlug($data['slug'])) {
            $errors['slug'][] = return_translation('admin_validation_slug_format');
        }
        if ($data['seo_image'] !== '' && !$this->isValidSeoImage($data['seo_image'])) {
            $errors['seo_image'][] = return_translation('admin_validation_seo_image_invalid');
        }

        return $errors;
    }

    /**
     * Нормализует данные пользователя из административной формы.
     */
    protected function normalizeUserData(array $data): array
    {
        return [
            'name' => trim((string)($data['name'] ?? '')),
            'login' => trim((string)($data['login'] ?? '')),
            'email' => mb_strtolower(trim((string)($data['email'] ?? ''))),
            'role' => trim((string)($data['role'] ?? '')),
            'password' => (string)($data['password'] ?? ''),
            'password_confirmation' => (string)($data['password_confirmation'] ?? ''),
        ];
    }

    /**
     * Нормализует данные роли и генерирует slug при необходимости.
     */
    protected function normalizeRoleData(array $data, array $role = []): array
    {
        $name = trim((string)($data['name'] ?? ''));
        $slug = trim((string)($data['slug'] ?? ''));

        if ($slug === '') {
            $slug = make_slug($name, 'role');
        }

        if ((int)($role['is_system'] ?? 0) === 1) {
            $slug = (string)($role['slug'] ?? $slug);
        }

        return [
            'name' => $name,
            'slug' => $slug,
        ];
    }

    /**
     * Нормализует значения формы настроек сайта.
     */
    protected function normalizeSettingsData(array $data): array
    {
        return [
            'site_title' => trim((string)($data['site_title'] ?? '')),
            'site_description' => trim((string)($data['site_description'] ?? '')),
            'site_favicon' => trim((string)($data['site_favicon'] ?? '')),
            'default_locale' => \FBL\Localization::normalizeLocale((string)($data['default_locale'] ?? '')) ?: \FBL\Localization::siteLocale(),
            'admin_session_lifetime_hours' => trim((string)($data['admin_session_lifetime_hours'] ?? '12')),
            'social_links' => $this->normalizeSocialLinksSetting($data),
            'social_telegram' => trim((string)($data['social_telegram'] ?? '')),
            'social_instagram' => trim((string)($data['social_instagram'] ?? '')),
            'social_facebook' => trim((string)($data['social_facebook'] ?? '')),
            'social_youtube' => trim((string)($data['social_youtube'] ?? '')),
            'contacts_page_heading' => trim((string)($data['contacts_page_heading'] ?? '')),
            'contacts_page_subheading' => trim((string)($data['contacts_page_subheading'] ?? '')),
            'contacts_page_image' => trim((string)($data['contacts_page_image'] ?? '')),
            'contacts_phone_customers' => trim((string)($data['contacts_phone_customers'] ?? '')),
            'contacts_phone_support' => trim((string)($data['contacts_phone_support'] ?? '')),
            'contacts_email_customers' => mb_strtolower(trim((string)($data['contacts_email_customers'] ?? ''))),
            'contacts_email_support' => mb_strtolower(trim((string)($data['contacts_email_support'] ?? ''))),
            'contacts_location_city' => trim((string)($data['contacts_location_city'] ?? '')),
            'contacts_location_address' => trim((string)($data['contacts_location_address'] ?? '')),
            'contacts_hours_weekdays' => trim((string)($data['contacts_hours_weekdays'] ?? '')),
            'contacts_hours_weekends' => trim((string)($data['contacts_hours_weekends'] ?? '')),
            'contacts_support_title' => trim((string)($data['contacts_support_title'] ?? '')),
            'contacts_support_text' => trim((string)($data['contacts_support_text'] ?? '')),
            'seo_home_title' => trim((string)($data['seo_home_title'] ?? '')),
            'seo_default_title_suffix' => trim((string)($data['seo_default_title_suffix'] ?? '')),
            'seo_meta_description' => trim((string)($data['seo_meta_description'] ?? '')),
            'seo_meta_keywords' => trim((string)($data['seo_meta_keywords'] ?? '')),
            'seo_meta_author' => trim((string)($data['seo_meta_author'] ?? '')),
            'seo_robots' => trim((string)($data['seo_robots'] ?? 'index,follow')),
            'seo_og_image' => trim((string)($data['seo_og_image'] ?? '')),
            'seo_twitter_card' => trim((string)($data['seo_twitter_card'] ?? 'summary_large_image')),
            'homepage_type' => $this->normalizeHomepageType((string)($data['homepage_type'] ?? 'default')),
            'homepage_page_id' => (string)max(0, (int)($data['homepage_page_id'] ?? 0)),
            'posts_per_page' => (string)max(1, min(100, (int)($data['posts_per_page'] ?? 10))),
        ];
    }

    protected function normalizeHomepageType(string $type): string
    {
        return in_array($type, ['default', 'page', 'posts'], true) ? $type : 'default';
    }

    protected function normalizePrivacySettingsData(array $data): array
    {
        $style = (string)($data['cookie_style'] ?? 'card');
        $position = (string)($data['cookie_position'] ?? 'bottom_right');

        return [
            'cookie_enabled' => !empty($data['cookie_enabled']) ? '1' : '0',
            'cookie_message' => trim((string)($data['cookie_message'] ?? '')),
            'cookie_button_text' => trim((string)($data['cookie_button_text'] ?? '')),
            'cookie_policy_page_id' => (string)max(0, (int)($data['cookie_policy_page_id'] ?? 0)),
            'cookie_policy_use_on_registration' => !empty($data['cookie_policy_use_on_registration']) ? '1' : '0',
            'cookie_position' => in_array($position, ['bottom_right', 'bottom_left', 'bottom_center', 'top'], true)
                ? $position
                : 'bottom_right',
            'cookie_style' => in_array($style, ['card', 'bar'], true) ? $style : 'card',
            'cookie_expiration_days' => (string)(int)($data['cookie_expiration_days'] ?? 365),
            'cookie_consent_categories' => '["necessary"]',
        ];
    }

    protected function normalizePwaSettingsData(array $data): array
    {
        $orientation = (string)($data['pwa_orientation'] ?? 'any');

        return [
            'pwa_enabled' => !empty($data['pwa_enabled']) ? '1' : '0',
            'pwa_push_enabled' => !empty($data['pwa_push_enabled']) ? '1' : '0',
            'pwa_app_name' => mb_substr(trim((string)($data['pwa_app_name'] ?? '')), 0, 80),
            'pwa_short_name' => mb_substr(trim((string)($data['pwa_short_name'] ?? '')), 0, 32),
            'pwa_description' => mb_substr(trim((string)($data['pwa_description'] ?? '')), 0, 240),
            'pwa_theme_color' => trim((string)($data['pwa_theme_color'] ?? '#181d25')),
            'pwa_background_color' => trim((string)($data['pwa_background_color'] ?? '#ffffff')),
            'pwa_orientation' => in_array($orientation, ['any', 'portrait', 'portrait-primary', 'landscape', 'landscape-primary'], true) ? $orientation : 'any',
            'pwa_logo' => trim((string)($data['pwa_logo'] ?? '')),
            'pwa_startup_image' => trim((string)($data['pwa_startup_image'] ?? '')),
            'pwa_cache_version' => (string)time(),
        ];
    }

    protected function validatePwaSettingsData(array $data): array
    {
        $errors = [];

        foreach (['pwa_theme_color', 'pwa_background_color'] as $field) {
            if (!preg_match('/^#[0-9a-f]{6}$/i', (string)($data[$field] ?? ''))) {
                $errors[$field][] = return_translation('admin_pwa_color_error');
            }
        }

        foreach (['pwa_logo', 'pwa_startup_image'] as $field) {
            if (($data[$field] ?? '') !== '' && !$this->isValidSeoImage((string)$data[$field])) {
                $errors[$field][] = return_translation('admin_validation_seo_image_invalid');
            }
        }

        return $errors;
    }

    protected function validatePrivacySettingsData(array $data): array
    {
        $errors = [];

        if ($data['cookie_message'] === '') {
            $errors['cookie_message'][] = return_translation('admin_validation_cookie_message_required');
        } elseif (mb_strlen($data['cookie_message']) > 2000) {
            $errors['cookie_message'][] = return_translation('admin_validation_cookie_message_length');
        }

        if ($data['cookie_button_text'] === '') {
            $errors['cookie_button_text'][] = return_translation('admin_validation_cookie_button_required');
        } elseif (mb_strlen($data['cookie_button_text']) > 100) {
            $errors['cookie_button_text'][] = return_translation('admin_validation_cookie_button_length');
        }

        $expirationDays = (int)$data['cookie_expiration_days'];
        if ($expirationDays < 1 || $expirationDays > 3650) {
            $errors['cookie_expiration_days'][] = return_translation('admin_validation_cookie_expiration');
        }

        $policyPageId = (int)$data['cookie_policy_page_id'];
        if ($policyPageId > 0 && !$this->pages->findPublishedById($policyPageId)) {
            $errors['cookie_policy_page_id'][] = return_translation('admin_validation_cookie_policy_page');
        }
        if ($data['cookie_policy_use_on_registration'] === '1' && $policyPageId <= 0) {
            $errors['cookie_policy_use_on_registration'][] = return_translation('admin_validation_cookie_registration_page');
        }

        return $errors;
    }

    /**
     * Проверяет корректность обязательных SEO-настроек и перечислимых значений.
     */
    protected function validateSettingsData(array $data): array
    {
        $errors = [];

        if ($data['site_title'] === '') {
            $errors['site_title'][] = return_translation('admin_validation_site_title_required');
        }
        if ($data['site_favicon'] !== '' && !$this->isValidSeoImage($data['site_favicon'])) {
            $errors['site_favicon'][] = return_translation('admin_validation_seo_image_invalid');
        }
        if (!array_key_exists((string)($data['default_locale'] ?? ''), LANGS)) {
            $errors['default_locale'][] = return_translation('admin_validation_default_locale_invalid');
        }
        if (
            !ctype_digit((string)$data['admin_session_lifetime_hours'])
            || (int)$data['admin_session_lifetime_hours'] < 1
            || (int)$data['admin_session_lifetime_hours'] > 720
        ) {
            $errors['admin_session_lifetime_hours'][] = return_translation('admin_validation_admin_session_lifetime_hours_invalid');
        }
        foreach (['social_telegram', 'social_instagram', 'social_facebook', 'social_youtube'] as $field) {
            if (($data[$field] ?? '') !== '' && !$this->isValidExternalUrl((string)$data[$field])) {
                $errors[$field][] = return_translation('admin_validation_social_url_invalid');
            }
        }
        $socialLinks = json_decode((string)($data['social_links'] ?? '[]'), true);
        if (is_array($socialLinks)) {
            foreach ($socialLinks as $index => $item) {
                $network = (string)($item['network'] ?? '');
                $url = (string)($item['url'] ?? '');
                if ($network === 'phone') {
                    if (!preg_match('/^tel:[\d+\-().\s]+$/', $url)) {
                        $errors['social_links'][] = return_translation('admin_validation_social_url_invalid');
                    }
                    continue;
                }

                if (!$this->isValidExternalUrl($url)) {
                    $errors['social_links'][] = return_translation('admin_validation_social_url_invalid');
                    break;
                }
            }
        }
        if ($data['contacts_page_image'] !== '' && !$this->isValidSeoImage($data['contacts_page_image'])) {
            $errors['contacts_page_image'][] = return_translation('admin_validation_seo_image_invalid');
        }
        foreach (['contacts_email_customers', 'contacts_email_support'] as $field) {
            if (($data[$field] ?? '') !== '' && filter_var((string)$data[$field], FILTER_VALIDATE_EMAIL) === false) {
                $errors[$field][] = return_translation('contacts_validation_email_invalid');
            }
        }
        if (!in_array($data['seo_robots'], ['index,follow', 'index,nofollow', 'noindex,follow', 'noindex,nofollow'], true)) {
            $errors['seo_robots'][] = return_translation('admin_validation_seo_robots_invalid');
        }
        if (!in_array($data['seo_twitter_card'], ['summary', 'summary_large_image'], true)) {
            $errors['seo_twitter_card'][] = return_translation('admin_validation_seo_twitter_card_invalid');
        }
        if ($data['seo_og_image'] !== '' && !$this->isValidSeoImage($data['seo_og_image'])) {
            $errors['seo_og_image'][] = return_translation('admin_validation_seo_image_invalid');
        }

        return $errors;
    }

    /**
     * Собирает выбранные в настройках соцсети в JSON-структуру.
     */
    protected function normalizeSocialLinksSetting(array $data): string
    {
        $networks = $data['social_networks'] ?? [];
        $urls = $data['social_urls'] ?? [];
        $allowed = array_keys(site_social_network_options());
        $links = [];

        if (!is_array($networks) || !is_array($urls)) {
            return '[]';
        }

        foreach ($networks as $index => $network) {
            $network = trim((string)$network);
            $url = trim((string)($urls[$index] ?? ''));

            if ($network === '' || $url === '' || !in_array($network, $allowed, true)) {
                continue;
            }

            $links[] = [
                'network' => $network,
                'url' => $network === 'phone' ? normalize_phone_href($url) : $url,
            ];
        }

        return json_encode($links, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
    }

    /**
     * Нормализует настройки центра обновлений.
     */
    protected function normalizeUpdateSettingsData(array $data): array
    {
        $currentToken = $this->siteSettings->get('updater_github_token', '');
        $submittedToken = trim((string)($data['updater_github_token'] ?? ''));

        return [
            'updater_github_repository' => trim((string)($data['updater_github_repository'] ?? '')),
            'updater_github_branch' => trim((string)($data['updater_github_branch'] ?? 'main')),
            'updater_github_token' => $submittedToken !== '' ? $submittedToken : $currentToken,
            'update_channel' => trim(mb_strtolower((string)($data['update_channel'] ?? 'stable'))),
        ];
    }

    /**
     * Проверяет корректность настроек центра обновлений.
     */
    protected function validateUpdateSettingsData(array $data): array
    {
        $errors = [];

        if ($data['updater_github_repository'] !== '' && !$this->isValidGithubRepository($data['updater_github_repository'])) {
            $errors['updater_github_repository'][] = return_translation('admin_validation_updater_repository_invalid');
        }
        if ($data['updater_github_branch'] !== '' && !$this->isValidGithubBranch($data['updater_github_branch'])) {
            $errors['updater_github_branch'][] = return_translation('admin_validation_updater_branch_invalid');
        }
        if (!in_array($data['update_channel'], ['stable', 'dev'], true)) {
            $errors['update_channel'][] = return_translation('admin_validation_update_channel_invalid');
        }

        return $errors;
    }

    /**
     * Normalizes theme form fields before validation and storage.
     */
    protected function normalizeThemeData(array $data, bool $includeSlug): array
    {
        $normalized = [
            'name' => trim(strip_tags((string)($data['name'] ?? ''))),
            'author' => trim(strip_tags((string)($data['author'] ?? ''))),
            'description' => trim(strip_tags((string)($data['description'] ?? ''))),
            'version' => trim(strip_tags((string)($data['version'] ?? '1.0.0'))),
            'preview' => trim(strip_tags((string)($data['preview'] ?? 'preview.png'))),
            'preview_source' => trim(strip_tags((string)($data['preview_source'] ?? ''))),
        ];

        if ($normalized['version'] === '') {
            $normalized['version'] = '1.0.0';
        }

        if ($normalized['preview'] === '') {
            $normalized['preview'] = 'preview.png';
        }

        if ($includeSlug) {
            $normalized['slug'] = trim((string)($data['slug'] ?? ''));
        }

        return $normalized;
    }

    /**
     * Validates theme metadata and creation rules.
     */
    protected function validateThemeData(array $data, bool $includeSlug): array
    {
        $errors = [];

        if ($data['name'] === '') {
            $errors['name'][] = return_translation('admin_themes_validation_name_required');
        }

        if ($data['version'] === '' || !$this->isValidThemeVersion($data['version'])) {
            $errors['version'][] = return_translation('admin_themes_validation_version_invalid');
        }

        if ($data['preview'] !== '' && !$this->isValidThemePreview($data['preview'])) {
            $errors['preview'][] = return_translation('admin_themes_validation_preview_invalid');
        }
        if (($data['preview_source'] ?? '') !== '' && !$this->isValidThemePreviewSource((string)$data['preview_source'])) {
            $errors['preview_source'][] = return_translation('admin_themes_validation_preview_source_invalid');
        }
        if (!empty($data['preview_upload']['size']) && !$this->isValidThemePreviewUpload($data['preview_upload'])) {
            $errors['preview_upload'][] = return_translation('admin_themes_validation_preview_upload_invalid');
        }

        if ($includeSlug) {
            $slug = (string)($data['slug'] ?? '');

            if ($slug === '') {
                $errors['slug'][] = return_translation('admin_themes_validation_slug_required');
            } elseif (!$this->isValidThemeSlug($slug)) {
                $errors['slug'][] = return_translation('admin_themes_validation_slug_invalid');
            } elseif ($this->isReservedThemeSlug($slug)) {
                $errors['slug'][] = return_translation('admin_themes_validation_slug_reserved');
            } elseif (file_exists(ROOT . '/themes/' . $slug)) {
                $errors['slug'][] = return_translation('admin_themes_validation_slug_exists');
            }
        }

        return $errors;
    }

    protected function isValidThemeSlug(string $slug): bool
    {
        return preg_match('/^[a-z0-9][a-z0-9_-]*$/', $slug) === 1
            && !str_contains($slug, '..')
            && !str_contains($slug, '/')
            && !str_contains($slug, '\\');
    }

    protected function isReservedThemeSlug(string $slug): bool
    {
        return in_array($slug, [
            'default', 'admin', 'system', 'core', 'app', 'config', 'public', 'vendor',
            'tmp', 'cache', 'uploads', 'assets', 'themes', 'files', 'api', 'static',
        ], true);
    }

    protected function isValidThemeVersion(string $version): bool
    {
        return preg_match('/^[0-9]+(?:\.[0-9]+){0,3}(?:[-+][A-Za-z0-9._-]+)?$/', $version) === 1;
    }

    protected function isValidThemePreview(string $preview): bool
    {
        return preg_match('/^[A-Za-z0-9._-]+\.(png|jpg|jpeg|webp|gif|svg)$/i', $preview) === 1
            && !str_contains($preview, '..')
            && !str_contains($preview, '/')
            && !str_contains($preview, '\\');
    }

    protected function isValidThemePreviewSource(string $previewSource): bool
    {
        return preg_match('~^/uploads/(?!.*(?:^|/)\.\.(?:/|$))[A-Za-z0-9/_.,%+\-=]+\.(png|jpe?g|webp|gif|svg)$~i', $previewSource) === 1;
    }

    protected function isValidThemePreviewUpload(array $file): bool
    {
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            return false;
        }

        $size = (int)($file['size'] ?? 0);
        $name = (string)($file['name'] ?? '');

        return $size > 0
            && $size <= 5 * 1024 * 1024
            && preg_match('/\.(png|jpe?g|webp|gif)$/i', $name) === 1;
    }

    protected function themeDocsArticles(): array
    {
        return [
            'introduction' => 'Введение',
            'structure' => 'Структура темы',
            'theme-json' => 'theme.json',
            'templates' => 'Шаблоны',
            'partials' => 'Partials',
            'menus' => 'Меню страниц',
            'theme-api' => 'Theme API',
            'categories' => 'Категории',
            'search' => 'Поиск',
            'archives' => 'Архивы',
            'seo' => 'SEO',
            'pagination' => 'Пагинация',
            'multilanguage' => 'Мультиязычность',
            'dynamic-content' => 'Динамический контент',
            'assets' => 'Assets',
            'helpers' => 'Helper-функции',
            'create-theme' => 'Создание темы',
            'theme-editor' => 'Редактор тем',
            'import-export' => 'Импорт и экспорт',
        ];
    }

    protected function pluginDocsArticles(): array
    {
        return [
            'introduction' => 'Плагины',
            'languages' => 'Языковые файлы плагинов',
            'toy-car-rental' => 'Toy Car Rental',
        ];
    }

    protected function pwaDocsArticles(): array
    {
        return [
            'introduction' => 'PWA',
        ];
    }

    protected function resolveThemeDocsArticle(string $language, string $article): array
    {
        return $this->resolveDocsArticle($language, 'themes', $article);
    }

    protected function resolveDocsArticle(string $language, string $section, string $article): array
    {
        $article = preg_replace('/[^a-z0-9_-]/', '', $article) ?: 'introduction';
        $section = preg_replace('/[^a-z0-9_-]/', '', $section) ?: 'themes';
        $candidates = array_values(array_unique([
            strtolower($language),
            'en',
            'ru',
        ]));

        foreach ($candidates as $candidate) {
            $path = ROOT . '/docs/' . $candidate . '/' . $section . '/' . $article . '.md';
            $real = realpath($path);
            $base = realpath(ROOT . '/docs/' . $candidate . '/' . $section);
            if ($real !== false && $base !== false && str_starts_with($real, rtrim($base, '/') . '/') && is_file($real)) {
                return ['path' => $real, 'language' => $candidate];
            }
        }

        return [
            'path' => ROOT . '/docs/ru/' . $section . '/' . $article . '.md',
            'language' => 'ru',
        ];
    }

    protected function searchThemeDocs(string $language, string $query, array $articles): array
    {
        return $this->searchDocs($language, 'themes', $query, $articles);
    }

    protected function searchDocs(string $language, string $section, string $query, array $articles): array
    {
        if (mb_strlen($query) < 2) {
            return [];
        }

        $results = [];
        $needle = mb_strtolower($query);
        foreach ($articles as $slug => $title) {
            $resolved = $this->resolveDocsArticle($language, $section, $slug);
            if (!is_file($resolved['path'])) {
                continue;
            }

            $markdown = (string)file_get_contents($resolved['path']);
            $haystack = mb_strtolower(strip_tags($markdown . ' ' . $title));
            if (!str_contains($haystack, $needle)) {
                continue;
            }

            $plain = trim(preg_replace('/\s+/u', ' ', strip_tags($markdown)) ?? '');
            $position = mb_stripos($plain, $query);
            $excerpt = $position === false
                ? mb_substr($plain, 0, 180)
                : mb_substr($plain, max(0, $position - 70), 180);

            $results[] = [
                'slug' => $slug,
                'title' => $title,
                'excerpt' => $excerpt,
            ];
        }

        return $results;
    }

    /**
     * Извлекает SEO-поля из общего массива данных формы.
     */
    protected function extractSeoData(array $data): array
    {
        return [
            'seo_title' => trim((string)($data['seo_title'] ?? '')),
            'seo_description' => trim((string)($data['seo_description'] ?? '')),
            'seo_keywords' => trim((string)($data['seo_keywords'] ?? '')),
            'seo_image' => trim((string)($data['seo_image'] ?? '')),
        ];
    }

    /**
     * Проверяет slug для URL: только нижний латинский регистр, цифры и дефисы.
     */
    protected function isValidSlug(string $value): bool
    {
        return preg_match('/^[a-z0-9-]+$/', $value) === 1;
    }

    /**
     * Проверяет английское название без кириллицы и других нелатинских букв.
     */
    protected function isValidEnglishLabel(string $value): bool
    {
        return preg_match('~^[A-Za-z0-9][A-Za-z0-9\s&\'.,()_/-]*$~u', $value) === 1;
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

        if (str_contains($value, "\0") || preg_match('~[<>"\']~', $value)) {
            return false;
        }

        if (str_starts_with($value, '//')) {
            return true;
        }

        return preg_match('~^/?(?!.*(?:^|/)\.\.(?:/|$))[A-Za-z0-9][A-Za-z0-9/_.,%+\-=]*\.(?:jpe?g|png|webp|gif|svg)(?:\?[A-Za-z0-9._\~:/?#[\]@!$&()*+,;=%-]*)?$~i', $value) === 1;
    }

    /**
     * Проверяет, что ссылка на внешнюю соцсеть указана как абсолютный URL.
     */
    protected function isValidExternalUrl(string $value): bool
    {
        if ($value === '') {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Проверяет формат GitHub-репозитория: owner/repo или github URL.
     */
    protected function isValidGithubRepository(string $value): bool
    {
        return preg_match('~^(?:https?://github\.com/|git@github\.com:)?[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+(?:\.git)?/?$~', $value) === 1;
    }

    /**
     * Проверяет, что имя ветки безопасно для передачи git.
     */
    protected function isValidGithubBranch(string $value): bool
    {
        return preg_match('~^(?!-)[A-Za-z0-9._/-]+$~', $value) === 1;
    }

    protected function normalizeMailSettingsData(array $data, array $currentSettings): array
    {
        $password = trim((string)($data['mail_password'] ?? ''));
        $settings = [
            'mail_enabled' => !empty($data['mail_enabled']) ? '1' : '0',
            'mail_host' => trim((string)($data['mail_host'] ?? '')),
            'mail_port' => (string)max(0, (int)($data['mail_port'] ?? 0)),
            'mail_encryption' => in_array((string)($data['mail_encryption'] ?? 'none'), ['none', 'ssl', 'tls'], true)
                ? (string)$data['mail_encryption']
                : 'none',
            'mail_username' => trim((string)($data['mail_username'] ?? '')),
            'mail_from_email' => mb_strtolower(trim((string)($data['mail_from_email'] ?? ''))),
            'mail_from_name' => trim((string)($data['mail_from_name'] ?? '')),
            'mail_reply_to_email' => mb_strtolower(trim((string)($data['mail_reply_to_email'] ?? ''))),
            'allow_email_password_reset' => !empty($data['allow_email_password_reset']) ? '1' : '0',
            'allow_2fa_email_recovery' => !empty($data['allow_2fa_email_recovery']) ? '1' : '0',
            'allow_admin_reset_user_2fa' => !empty($data['allow_admin_reset_user_2fa']) ? '1' : '0',
            'require_admin_password_for_2fa_reset' => !empty($data['require_admin_password_for_2fa_reset']) ? '1' : '0',
            'require_admin_2fa_for_2fa_reset' => !empty($data['require_admin_2fa_for_2fa_reset']) ? '1' : '0',
            'notify_user_after_admin_2fa_reset' => !empty($data['notify_user_after_admin_2fa_reset']) ? '1' : '0',
        ];

        $settings['mail_password'] = $password !== ''
            ? $password
            : (string)($currentSettings['mail_password'] ?? '');

        return $settings;
    }

    protected function validateMailSettingsData(array $data): array
    {
        $errors = [];
        if (($data['mail_enabled'] ?? '0') !== '1') {
            return $errors;
        }

        if (trim((string)($data['mail_host'] ?? '')) === '') {
            $errors['mail_host'][] = return_translation('admin_mail_validation_host');
        }

        $port = (int)($data['mail_port'] ?? 0);
        if ($port < 1 || $port > 65535) {
            $errors['mail_port'][] = return_translation('admin_mail_validation_port');
        }

        if (!filter_var((string)($data['mail_from_email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
            $errors['mail_from_email'][] = return_translation('admin_mail_validation_from_email');
        }

        $replyTo = trim((string)($data['mail_reply_to_email'] ?? ''));
        if ($replyTo !== '' && !filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $errors['mail_reply_to_email'][] = return_translation('admin_mail_validation_reply_to');
        }

        return $errors;
    }

}
