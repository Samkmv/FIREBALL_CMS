<?php

namespace App\Controllers;

use App\Models\Admin;
use App\Models\Analytics;
use App\Models\ContactRequest;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Services\UpdateCenter;
use FBL\Auth;
use FBL\File;

/**
 * Управляет административной частью: контентом, пользователями, ролями, заявками и настройками сайта.
 */
class AdminController extends BaseController
{

    protected Admin $blog;
    protected Analytics $analytics;
    protected ContactRequest $contactRequests;
    protected User $users;
    protected SiteSetting $siteSettings;
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
        $this->users = new User();
        $this->siteSettings = new SiteSetting();
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
        $stats['contact_requests'] = $this->contactRequests->countAll();

        return view('admin/dashboard', [
            'title' => return_translation('admin_dashboard_title'),
            'stats' => $stats,
            'analytics_dashboard' => $this->analyticsService->dashboardData(),
            'engine_release' => require CONFIG . '/version.php',
            'update_center' => $this->updateCenter->getDashboardData(),
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
        $requests = $this->contactRequests->getPaginated($this->getTableParams('created_at', 'desc'));
        $this->contactRequests->markAllViewed();

        return view('admin/contact_requests', [
            'title' => return_translation('admin_contacts_title'),
            'requests' => $requests['items'],
            'pagination' => $requests['pagination'],
            'total' => $requests['total'],
            'search' => $requests['search'],
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

        response()->redirect(base_href('/admin/contact-requests'));
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
            $data = $this->normalizeSettingsData(request()->getData());
            $errors = $this->validateSettingsData($data);

            if (!empty($errors)) {
                session()->set('form_data', $data);
                session()->set('form_errors', $errors);
                response()->redirect(base_href('/admin/settings'));
            }

            $this->siteSettings->setMany($data);
            session()->remove('form_data');
            session()->remove('form_errors');
            session()->setFlash('success', return_translation('admin_settings_saved'));
            response()->redirect(base_href('/admin/settings'));
        }

        return view('admin/settings', [
            'title' => return_translation('admin_settings_title'),
            'settings' => $this->siteSettings->all(),
            'engine_release' => require CONFIG . '/version.php',
            'footer_scripts' => [
                base_url('/assets/default/js/admin-file-manager.js?v=' . filemtime(WWW . '/assets/default/js/admin-file-manager.js')),
            ],
        ]);
    }

    /**
     * Показывает отдельную страницу центра обновлений и сохраняет его настройки.
     */
    public function updates()
    {
        if (request()->isPost()) {
            if (!Auth::hasRole('creator')) {
                session()->setFlash('error', return_translation('admin_updates_creator_only'));
                response()->redirect(base_href('/admin/updates'));
            }

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
        try {
            $result = $this->updateCenter->checkForUpdates();
            session()->setFlash('success', (string)($result['message'] ?? return_translation('admin_update_check_available')));
        } catch (\Throwable $exception) {
            session()->setFlash('error', $exception->getMessage());
        }

        response()->redirect(base_href('/admin/updates#update-center'));
    }

    /**
     * Запускает обновление CMS из GitHub-репозитория.
     */
    public function runUpdate()
    {
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

        response()->redirect(base_href('/admin/updates#update-center'));
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
        ];
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

        return $errors;
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

}
