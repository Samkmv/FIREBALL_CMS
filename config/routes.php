<?php

/** @var Application $app */

use FBL\Application;
use App\Controllers\AuthController;
use App\Controllers\HomeController;
use App\Controllers\PagesController;
use App\Controllers\PostsController;
use App\Controllers\SearchController;
use App\Controllers\CartController;
use App\Controllers\ChatController;
use App\Controllers\InstallController;
use App\Controllers\StreamController;
use App\Controllers\AdminController;
use App\Controllers\AdminMaintenanceController;
use App\Controllers\AdminPostController;
use App\Controllers\AdminPagesController;
use App\Controllers\AnalyticsController;
use App\Controllers\NotificationController;
use App\Controllers\FileManagerController;
use App\Controllers\ThemeEditorController;
use App\Controllers\PluginController;
use App\Controllers\PwaController;
use App\Controllers\Api\V1\MenuController;
use App\Modules\BlockEditor\BlockEditorController;

/* Documentation:
 * withoutCSRFToken() - Исключает проверку на CSRF если метода нет то по умолчанию true
 * */

const MIDDLEWARE = [
    'auth' => \FBL\Middleware\Auth::class,
    'guest' => \FBL\Middleware\Guest::class,
    'admin' => \FBL\Middleware\Admin::class,
    'creator' => \FBL\Middleware\Creator::class,
];

$app->router->get('/product/(?P<slug>[a-z0-9-]+)/?', function () {
    return 'Product ' . get_route_param('slug');
});

// Installer ---------- //
$app->router->get('/install', [InstallController::class, 'index']);
$app->router->post('/install', [InstallController::class, 'submit']);

// Site pages ---------- //
$app->router->get('/api/v1/menu/(?P<type>[a-z_]+)', [MenuController::class, 'index']);
$app->router->post('/api/analytics/track', [AnalyticsController::class, 'track'])->withoutCSRFToken();
$app->router->post('/api/streams/wake', [StreamController::class, 'wake'])->withoutCSRFToken();
$app->router->get('/manifest.webmanifest', [PwaController::class, 'manifest']);
$app->router->get('/service-worker.js', [PwaController::class, 'serviceWorker']);
$app->router->get('/sw.js', [PwaController::class, 'serviceWorker']);
$app->router->get('/offline', [PwaController::class, 'offline']);
$app->router->get('/api/pwa/support', [PwaController::class, 'support']);
$app->router->get('/api/pwa/status', [PwaController::class, 'pushStatus']);
$app->router->post('/api/pwa/subscriptions', [PwaController::class, 'subscribe']);
$app->router->put('/api/pwa/subscriptions', [PwaController::class, 'updateSubscription']);
$app->router->delete('/api/pwa/subscriptions', [PwaController::class, 'unsubscribe']);
$app->router->post('/api/pwa/subscriptions/delete', [PwaController::class, 'unsubscribe']);
$app->router->post('/api/pwa/badge/clear', [PwaController::class, 'clearBadge']);
$app->router->get('/login', [AuthController::class, 'login'])->middleware(['guest']);
$app->router->post('/login', [AuthController::class, 'login'])->middleware(['guest']);
$app->router->get('/two-factor-challenge', [AuthController::class, 'twoFactorChallenge'])->middleware(['guest']);
$app->router->post('/two-factor-challenge', [AuthController::class, 'twoFactorChallenge'])->middleware(['guest']);
$app->router->get('/two-factor-recovery', [AuthController::class, 'twoFactorRecovery'])->middleware(['guest']);
$app->router->post('/two-factor-recovery', [AuthController::class, 'twoFactorRecovery'])->middleware(['guest']);
$app->router->get('/two-factor-recovery/reset', [AuthController::class, 'resetTwoFactorRecovery'])->middleware(['guest']);
$app->router->post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware(['guest']);
$app->router->get('/reset-password', [AuthController::class, 'resetPassword'])->middleware(['guest']);
$app->router->post('/reset-password', [AuthController::class, 'resetPassword'])->middleware(['guest']);
$app->router->get('/register', [AuthController::class, 'register'])->middleware(['guest']);
$app->router->post('/register', [AuthController::class, 'register'])->middleware(['guest']);
$app->router->get('/profile', [AuthController::class, 'profile'])->middleware(['auth']);
$app->router->post('/profile', [AuthController::class, 'profile'])->middleware(['auth']);
$app->router->get('/chat', [ChatController::class, 'index'])->middleware(['auth']);
$app->router->get('/chat/messages', [ChatController::class, 'messages'])->middleware(['auth']);
$app->router->get('/chat/unread-count', [ChatController::class, 'unreadCount'])->middleware(['auth']);
$app->router->post('/chat/send', [ChatController::class, 'send'])->middleware(['auth']);
$app->router->post('/chat/messages/delete', [ChatController::class, 'deleteMessages'])->middleware(['auth']);
$app->router->post('/chat/conversation/clear', [ChatController::class, 'clearConversation'])->middleware(['auth']);
$app->router->get('/chat/conversation/audit', [ChatController::class, 'audit'])->middleware(['auth']);
$app->router->get('/notifications/feed', [NotificationController::class, 'feed'])->middleware(['auth']);
$app->router->post('/notifications/read', [NotificationController::class, 'markRead'])->middleware(['auth']);
$app->router->post('/logout', [AuthController::class, 'logout'])->middleware(['auth']);
$app->router->get('/search/suggest', [SearchController::class, 'suggest']);
$app->router->get('/search', [SearchController::class, 'index']);
$app->router->get('/support', [HomeController::class, 'support']);
$app->router->post('/support', [HomeController::class, 'support']);
$app->router->get('/support/articles', [HomeController::class, 'support']);
$app->router->post('/support/articles/feedback', [HomeController::class, 'supportArticleFeedback']);
$app->router->get('/support/articles/(?P<slug>[a-z0-9-]+)/?', [HomeController::class, 'supportArticle']);
$app->router->get('/contacts', [HomeController::class, 'contacts']);
$app->router->post('/contacts', [HomeController::class, 'contacts']);
$app->router->get('/category/(?P<slug>[a-z0-9-]+)/?', [PostsController::class, 'category']);
$app->router->get('/archive', [PostsController::class, 'archive']);
$app->router->get('/posts/(?P<slug>[a-z0-9-]+)/?', [PostsController::class, 'show']);
$app->router->get('/posts', [PostsController::class, 'index']);

// Admin pages ---------- //
$app->router->get('/admin', [AdminController::class, 'dashboard'])->middleware(['auth', 'admin']);
$app->router->get('/admin/analytics', [AnalyticsController::class, 'index'])->middleware(['auth', 'admin']);
$app->router->get('/admin/analytics/data', [AnalyticsController::class, 'dashboardData'])->middleware(['auth', 'admin']);
$app->router->post('/admin/analytics/refresh', [AnalyticsController::class, 'refresh'])->middleware(['auth', 'admin']);
$app->router->post('/admin/analytics/reset', [AnalyticsController::class, 'reset'])->middleware(['auth', 'admin']);
$app->router->get('/admin/contact-requests', [AdminController::class, 'contactRequests'])->middleware(['auth', 'admin']);
$app->router->post('/admin/contact-requests/delete', [AdminController::class, 'contactRequestDelete'])->middleware(['auth', 'admin']);
$app->router->get('/admin/support', [AdminController::class, 'contactRequests'])->middleware(['auth', 'admin']);
$app->router->get('/admin/support/requests', [AdminController::class, 'contactRequests'])->middleware(['auth', 'admin']);
$app->router->post('/admin/support/requests/status', [AdminController::class, 'contactRequestStatus'])->middleware(['auth', 'admin']);
$app->router->post('/admin/support/requests/bulk', [AdminController::class, 'contactRequestBulk'])->middleware(['auth', 'admin']);
$app->router->post('/admin/support/requests/delete', [AdminController::class, 'contactRequestDelete'])->middleware(['auth', 'admin']);
$app->router->get('/admin/support/faq', [AdminController::class, 'supportFaq'])->middleware(['auth', 'admin']);
$app->router->get('/admin/support/faq/create', [AdminController::class, 'supportFaqForm'])->middleware(['auth', 'admin']);
$app->router->post('/admin/support/faq/create', [AdminController::class, 'supportFaqForm'])->middleware(['auth', 'admin']);
$app->router->get('/admin/support/faq/edit/(?P<id>\d+)/?', [AdminController::class, 'supportFaqForm'])->middleware(['auth', 'admin']);
$app->router->post('/admin/support/faq/edit/(?P<id>\d+)/?', [AdminController::class, 'supportFaqForm'])->middleware(['auth', 'admin']);
$app->router->post('/admin/support/faq/delete', [AdminController::class, 'supportFaqDelete'])->middleware(['auth', 'admin']);
$app->router->get('/admin/support/faq/categories', [AdminController::class, 'supportFaqCategories'])->middleware(['auth', 'admin']);
$app->router->get('/admin/support/faq/categories/create', [AdminController::class, 'supportFaqCategoryForm'])->middleware(['auth', 'admin']);
$app->router->post('/admin/support/faq/categories/create', [AdminController::class, 'supportFaqCategoryForm'])->middleware(['auth', 'admin']);
$app->router->get('/admin/support/faq/categories/edit/(?P<id>\d+)/?', [AdminController::class, 'supportFaqCategoryForm'])->middleware(['auth', 'admin']);
$app->router->post('/admin/support/faq/categories/edit/(?P<id>\d+)/?', [AdminController::class, 'supportFaqCategoryForm'])->middleware(['auth', 'admin']);
$app->router->post('/admin/support/faq/categories/delete', [AdminController::class, 'supportFaqCategoryDelete'])->middleware(['auth', 'admin']);
$app->router->get('/admin/support/knowledge-base', [AdminController::class, 'supportKnowledgeBase'])->middleware(['auth', 'admin']);
$app->router->get('/admin/support/knowledge-base/create', [AdminController::class, 'supportKbArticleForm'])->middleware(['auth', 'admin']);
$app->router->post('/admin/support/knowledge-base/create', [AdminController::class, 'supportKbArticleForm'])->middleware(['auth', 'admin']);
$app->router->get('/admin/support/knowledge-base/edit/(?P<id>\d+)/?', [AdminController::class, 'supportKbArticleForm'])->middleware(['auth', 'admin']);
$app->router->post('/admin/support/knowledge-base/edit/(?P<id>\d+)/?', [AdminController::class, 'supportKbArticleForm'])->middleware(['auth', 'admin']);
$app->router->post('/admin/support/knowledge-base/delete', [AdminController::class, 'supportKbArticleDelete'])->middleware(['auth', 'admin']);
$app->router->get('/admin/support/knowledge-base/categories', [AdminController::class, 'supportKbCategories'])->middleware(['auth', 'admin']);
$app->router->get('/admin/support/knowledge-base/categories/create', [AdminController::class, 'supportKbCategoryForm'])->middleware(['auth', 'admin']);
$app->router->post('/admin/support/knowledge-base/categories/create', [AdminController::class, 'supportKbCategoryForm'])->middleware(['auth', 'admin']);
$app->router->get('/admin/support/knowledge-base/categories/edit/(?P<id>\d+)/?', [AdminController::class, 'supportKbCategoryForm'])->middleware(['auth', 'admin']);
$app->router->post('/admin/support/knowledge-base/categories/edit/(?P<id>\d+)/?', [AdminController::class, 'supportKbCategoryForm'])->middleware(['auth', 'admin']);
$app->router->post('/admin/support/knowledge-base/categories/delete', [AdminController::class, 'supportKbCategoryDelete'])->middleware(['auth', 'admin']);
$app->router->get('/admin/support/subjects', [AdminController::class, 'contactSubjects'])->middleware(['auth', 'admin']);
$app->router->get('/admin/support/subjects/create', [AdminController::class, 'contactSubjectForm'])->middleware(['auth', 'admin']);
$app->router->post('/admin/support/subjects/create', [AdminController::class, 'contactSubjectForm'])->middleware(['auth', 'admin']);
$app->router->get('/admin/support/subjects/edit/(?P<id>\d+)/?', [AdminController::class, 'contactSubjectForm'])->middleware(['auth', 'admin']);
$app->router->post('/admin/support/subjects/edit/(?P<id>\d+)/?', [AdminController::class, 'contactSubjectForm'])->middleware(['auth', 'admin']);
$app->router->post('/admin/support/subjects/toggle', [AdminController::class, 'contactSubjectToggle'])->middleware(['auth', 'admin']);
$app->router->post('/admin/support/subjects/delete', [AdminController::class, 'contactSubjectDelete'])->middleware(['auth', 'admin']);
$app->router->get('/admin/support/settings', [AdminController::class, 'supportSettings'])->middleware(['auth', 'admin']);
$app->router->post('/admin/support/settings', [AdminController::class, 'supportSettings'])->middleware(['auth', 'admin']);
$app->router->get('/admin/posts', [AdminPostController::class, 'posts'])->middleware(['auth', 'admin']);
$app->router->get('/admin/posts/create', [AdminPostController::class, 'postForm'])->middleware(['auth', 'admin']);
$app->router->post('/admin/posts/create', [AdminPostController::class, 'postForm'])->middleware(['auth', 'admin']);
$app->router->post('/admin/posts/autosave', [AdminPostController::class, 'postAutosave'])->middleware(['auth', 'admin']);
$app->router->get('/admin/posts/edit/(?P<id>\d+)/?', [AdminPostController::class, 'postForm'])->middleware(['auth', 'admin']);
$app->router->post('/admin/posts/edit/(?P<id>\d+)/?', [AdminPostController::class, 'postForm'])->middleware(['auth', 'admin']);
$app->router->get('/admin/posts/preview/(?P<id>\d+)/?', [AdminPostController::class, 'postPreview'])->middleware(['auth', 'admin']);
$app->router->post('/admin/posts/toggle-published', [AdminPostController::class, 'postTogglePublished'])->middleware(['auth', 'admin']);
$app->router->post('/admin/posts/delete', [AdminPostController::class, 'postDelete'])->middleware(['auth', 'admin']);
$app->router->get('/admin/pages', [AdminPagesController::class, 'index'])->middleware(['auth', 'admin']);
$app->router->get('/admin/pages/create', [AdminPagesController::class, 'form'])->middleware(['auth', 'admin']);
$app->router->post('/admin/pages/create', [AdminPagesController::class, 'form'])->middleware(['auth', 'admin']);
$app->router->post('/admin/pages/autosave', [AdminPagesController::class, 'autosave'])->middleware(['auth', 'admin']);
$app->router->get('/admin/pages/edit/(?P<id>\d+)/?', [AdminPagesController::class, 'form'])->middleware(['auth', 'admin']);
$app->router->post('/admin/pages/edit/(?P<id>\d+)/?', [AdminPagesController::class, 'form'])->middleware(['auth', 'admin']);
$app->router->get('/admin/pages/preview/(?P<id>\d+)/?', [AdminPagesController::class, 'preview'])->middleware(['auth', 'admin']);
$app->router->post('/admin/pages/toggle-published', [AdminPagesController::class, 'togglePublished'])->middleware(['auth', 'admin']);
$app->router->post('/admin/pages/delete', [AdminPagesController::class, 'delete'])->middleware(['auth', 'admin']);
$app->router->post('/admin/block-editor/add', [BlockEditorController::class, 'add'])->middleware(['auth', 'admin']);
$app->router->post('/admin/block-editor/update', [BlockEditorController::class, 'update'])->middleware(['auth', 'admin']);
$app->router->post('/admin/block-editor/delete', [BlockEditorController::class, 'delete'])->middleware(['auth', 'admin']);
$app->router->post('/admin/block-editor/reorder', [BlockEditorController::class, 'reorder'])->middleware(['auth', 'admin']);
$app->router->post('/admin/block-editor/preview', [BlockEditorController::class, 'preview'])->middleware(['auth', 'admin']);
$app->router->post('/admin/block-editor/order-modal', [BlockEditorController::class, 'orderModal'])->middleware(['auth', 'admin']);
$app->router->get('/admin/categories', [AdminController::class, 'categories'])->middleware(['auth', 'admin']);
$app->router->get('/admin/categories/create', [AdminController::class, 'categoryForm'])->middleware(['auth', 'admin']);
$app->router->post('/admin/categories/create', [AdminController::class, 'categoryForm'])->middleware(['auth', 'admin']);
$app->router->get('/admin/categories/edit/(?P<id>\d+)/?', [AdminController::class, 'categoryForm'])->middleware(['auth', 'admin']);
$app->router->post('/admin/categories/edit/(?P<id>\d+)/?', [AdminController::class, 'categoryForm'])->middleware(['auth', 'admin']);
$app->router->post('/admin/categories/delete', [AdminController::class, 'categoryDelete'])->middleware(['auth', 'admin']);
$app->router->get('/admin/users', [AdminController::class, 'users'])->middleware(['auth', 'admin']);
$app->router->get('/admin/users/create', [AdminController::class, 'userForm'])->middleware(['auth', 'admin', 'creator']);
$app->router->post('/admin/users/create', [AdminController::class, 'userForm'])->middleware(['auth', 'admin', 'creator']);
$app->router->get('/admin/users/edit/(?P<id>\d+)/?', [AdminController::class, 'userForm'])->middleware(['auth', 'admin', 'creator']);
$app->router->post('/admin/users/edit/(?P<id>\d+)/?', [AdminController::class, 'userForm'])->middleware(['auth', 'admin', 'creator']);
$app->router->post('/admin/users/reset-2fa', [AdminController::class, 'resetUserTwoFactor'])->middleware(['auth', 'admin']);
$app->router->post('/admin/users/delete', [AdminController::class, 'userDelete'])->middleware(['auth', 'admin', 'creator']);
$app->router->get('/admin/roles', [AdminController::class, 'roles'])->middleware(['auth', 'admin']);
$app->router->get('/admin/roles/create', [AdminController::class, 'roleForm'])->middleware(['auth', 'admin', 'creator']);
$app->router->post('/admin/roles/create', [AdminController::class, 'roleForm'])->middleware(['auth', 'admin', 'creator']);
$app->router->get('/admin/roles/edit/(?P<id>\d+)/?', [AdminController::class, 'roleForm'])->middleware(['auth', 'admin', 'creator']);
$app->router->post('/admin/roles/edit/(?P<id>\d+)/?', [AdminController::class, 'roleForm'])->middleware(['auth', 'admin', 'creator']);
$app->router->post('/admin/roles/delete', [AdminController::class, 'roleDelete'])->middleware(['auth', 'admin', 'creator']);
$app->router->get('/admin/settings', [AdminController::class, 'settings'])->middleware(['auth', 'admin']);
$app->router->post('/admin/settings', [AdminController::class, 'settings'])->middleware(['auth', 'admin']);
$app->router->get('/admin/settings/pwa', [AdminController::class, 'pwaSettings'])->middleware(['auth', 'admin']);
$app->router->post('/admin/settings/pwa', [AdminController::class, 'pwaSettings'])->middleware(['auth', 'admin']);
$app->router->post('/admin/settings/pwa/vapid', [AdminController::class, 'generatePwaVapid'])->middleware(['auth', 'admin']);
$app->router->post('/admin/settings/pwa/test-push', [AdminController::class, 'testPwaPush'])->middleware(['auth', 'admin']);
$app->router->get('/admin/settings/contact-subjects', [AdminController::class, 'contactSubjects'])->middleware(['auth', 'admin']);
$app->router->get('/admin/settings/contact-subjects/create', [AdminController::class, 'contactSubjectForm'])->middleware(['auth', 'admin']);
$app->router->post('/admin/settings/contact-subjects/create', [AdminController::class, 'contactSubjectForm'])->middleware(['auth', 'admin']);
$app->router->get('/admin/settings/contact-subjects/edit/(?P<id>\d+)/?', [AdminController::class, 'contactSubjectForm'])->middleware(['auth', 'admin']);
$app->router->post('/admin/settings/contact-subjects/edit/(?P<id>\d+)/?', [AdminController::class, 'contactSubjectForm'])->middleware(['auth', 'admin']);
$app->router->post('/admin/settings/contact-subjects/toggle', [AdminController::class, 'contactSubjectToggle'])->middleware(['auth', 'admin']);
$app->router->post('/admin/settings/contact-subjects/delete', [AdminController::class, 'contactSubjectDelete'])->middleware(['auth', 'admin']);
$app->router->get('/admin/settings/privacy', [AdminController::class, 'privacySettings'])->middleware(['auth', 'admin']);
$app->router->post('/admin/settings/privacy', [AdminController::class, 'privacySettings'])->middleware(['auth', 'admin']);
$app->router->get('/admin/settings/mail', [AdminController::class, 'mailSettings'])->middleware(['auth', 'admin']);
$app->router->post('/admin/settings/mail', [AdminController::class, 'mailSettings'])->middleware(['auth', 'admin']);
$app->router->get('/admin/settings/mail/logs', [AdminController::class, 'mailLogs'])->middleware(['auth', 'admin']);
$app->router->get('/admin/security/logs', [AdminController::class, 'securityLogs'])->middleware(['auth', 'admin']);
$app->router->post('/admin/security/logs/clear', [AdminController::class, 'clearSecurityLogs'])->middleware(['auth', 'admin', 'creator']);
$app->router->get('/admin/system/database-maintenance', [AdminMaintenanceController::class, 'index'])->middleware(['auth', 'admin']);
$app->router->post('/admin/system/database-maintenance/run', [AdminMaintenanceController::class, 'run'])->middleware(['auth', 'admin']);
$app->router->post('/admin/system/database-maintenance/logs/clear', [AdminMaintenanceController::class, 'clearLogs'])->middleware(['auth', 'admin']);
$app->router->get('/admin/themes', [AdminController::class, 'themes'])->middleware(['auth', 'admin']);
$app->router->get('/admin/themes/create', [AdminController::class, 'themeCreate'])->middleware(['auth', 'admin', 'creator']);
$app->router->post('/admin/themes/create', [AdminController::class, 'themeCreate'])->middleware(['auth', 'admin', 'creator']);
$app->router->get('/admin/themes/import', [AdminController::class, 'themeImport'])->middleware(['auth', 'admin', 'creator']);
$app->router->post('/admin/themes/import', [AdminController::class, 'themeImport'])->middleware(['auth', 'admin', 'creator']);
$app->router->post('/admin/themes/activate', [AdminController::class, 'activateTheme'])->middleware(['auth', 'admin', 'creator']);
$app->router->get('/admin/themes/preview/(?P<slug>[a-z0-9_-]+)/?', [AdminController::class, 'themePreview'])->middleware(['auth', 'admin']);
$app->router->get('/admin/themes/export/(?P<slug>[a-z0-9_-]+)/?', [AdminController::class, 'themeExport'])->middleware(['auth', 'admin']);
$app->router->get('/admin/themes/edit/(?P<slug>[a-z0-9_-]+)/?', [AdminController::class, 'themeEdit'])->middleware(['auth', 'admin', 'creator']);
$app->router->post('/admin/themes/edit/(?P<slug>[a-z0-9_-]+)/?', [AdminController::class, 'themeEdit'])->middleware(['auth', 'admin', 'creator']);
$app->router->get('/admin/themes/files/(?P<slug>[a-z0-9_-]+)/?', [ThemeEditorController::class, 'index'])->middleware(['auth', 'admin', 'creator']);
$app->router->get('/admin/theme-editor/(?P<slug>[a-z0-9_-]+)/?', [ThemeEditorController::class, 'index'])->middleware(['auth', 'admin', 'creator']);
$app->router->post('/admin/theme-editor/save', [ThemeEditorController::class, 'save'])->middleware(['auth', 'admin', 'creator']);
$app->router->post('/admin/theme-editor/create-file', [ThemeEditorController::class, 'createFile'])->middleware(['auth', 'admin', 'creator']);
$app->router->post('/admin/theme-editor/create-directory', [ThemeEditorController::class, 'createDirectory'])->middleware(['auth', 'admin', 'creator']);
$app->router->post('/admin/theme-editor/rename', [ThemeEditorController::class, 'rename'])->middleware(['auth', 'admin', 'creator']);
$app->router->post('/admin/theme-editor/delete', [ThemeEditorController::class, 'delete'])->middleware(['auth', 'admin', 'creator']);
$app->router->post('/admin/theme-editor/replace-image', [ThemeEditorController::class, 'replaceImage'])->middleware(['auth', 'admin', 'creator']);
$app->router->post('/admin/theme-editor/restore', [ThemeEditorController::class, 'restore'])->middleware(['auth', 'admin', 'creator']);
$app->router->post('/admin/theme-editor/copy', [ThemeEditorController::class, 'copyTheme'])->middleware(['auth', 'admin', 'creator']);
$app->router->post('/admin/themes/delete', [AdminController::class, 'themeDelete'])->middleware(['auth', 'admin', 'creator']);
$app->router->get('/admin/plugins', [PluginController::class, 'index'])->middleware(['auth', 'admin']);
$app->router->post('/admin/plugins/install', [PluginController::class, 'install'])->middleware(['auth', 'admin']);
$app->router->post('/admin/plugins/activate', [PluginController::class, 'activate'])->middleware(['auth', 'admin']);
$app->router->post('/admin/plugins/deactivate', [PluginController::class, 'deactivate'])->middleware(['auth', 'admin']);
$app->router->post('/admin/plugins/check-update', [PluginController::class, 'checkUpdate'])->middleware(['auth', 'admin']);
$app->router->post('/admin/plugins/check-updates', [PluginController::class, 'checkAllUpdates'])->middleware(['auth', 'admin']);
$app->router->post('/admin/plugins/update', [PluginController::class, 'update'])->middleware(['auth', 'admin']);
$app->router->get('/admin/updates', [AdminController::class, 'updates'])->middleware(['auth', 'admin', 'creator']);
$app->router->post('/admin/updates', [AdminController::class, 'updates'])->middleware(['auth', 'admin', 'creator']);
$app->router->post('/admin/settings/update-center/check', [AdminController::class, 'checkForUpdates'])->middleware(['auth', 'admin', 'creator']);
$app->router->post('/admin/settings/update-center/update', [AdminController::class, 'runUpdate'])->middleware(['auth', 'admin', 'creator']);
$app->router->post('/admin/settings/update-center/rollback', [AdminController::class, 'rollbackUpdate'])->middleware(['auth', 'admin', 'creator']);
$app->router->get('/admin/files', [FileManagerController::class, 'index'])->middleware(['auth', 'admin']);
$app->router->post('/admin/files/upload', [FileManagerController::class, 'upload'])->middleware(['auth', 'admin']);
$app->router->post('/admin/files/folder/create', [FileManagerController::class, 'createDirectory'])->middleware(['auth', 'admin']);
$app->router->post('/admin/files/rename', [FileManagerController::class, 'rename'])->middleware(['auth', 'admin']);
$app->router->post('/admin/files/action', [FileManagerController::class, 'bulkAction'])->middleware(['auth', 'admin']);
$app->router->post('/admin/files/delete', [FileManagerController::class, 'delete'])->middleware(['auth', 'admin']);
$app->router->post('/admin/files/folder/delete', [FileManagerController::class, 'deleteDirectory'])->middleware(['auth', 'admin']);
$app->router->get('/admin/docs', [AdminController::class, 'docs'])->middleware(['auth', 'admin']);
$app->router->get('/admin/docs/themes', [AdminController::class, 'themeDocs'])->middleware(['auth', 'admin']);
$app->router->get('/admin/docs/themes/(?P<article>[a-z0-9_-]+)/?', [AdminController::class, 'themeDocs'])->middleware(['auth', 'admin']);
$app->router->get('/admin/docs/plugins', [AdminController::class, 'pluginDocs'])->middleware(['auth', 'admin']);
$app->router->get('/admin/docs/plugins/(?P<article>[a-z0-9_-]+)/?', [AdminController::class, 'pluginDocs'])->middleware(['auth', 'admin']);
$app->router->get('/admin/docs/pwa', [AdminController::class, 'pwaDocs'])->middleware(['auth', 'admin']);
$app->router->get('/admin/docs/pwa/(?P<article>[a-z0-9_-]+)/?', [AdminController::class, 'pwaDocs'])->middleware(['auth', 'admin']);

// Store pages ---------- //
$app->router->post('/add-to-cart', [CartController::class, 'addToCart']);
$app->router->post('/remove-from-cart', [CartController::class, 'removeFromCart']);

// Active plugin routes are registered before the home and generic CMS page routes.
if ($app->isInstalled()) {
    plugin_manager()->bootActivePlugins($app->router);
}

// Home must be checked before the generic page slug so localized roots like /en/ do not become slug "en".
$app->router->get('/', [HomeController::class, 'index']);

// CMS pages ---------- //
$app->router->get('/(?P<slug>[a-z0-9-]+)/?', [PagesController::class, 'show']);
