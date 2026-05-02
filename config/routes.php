<?php

/** @var Application $app */

use FBL\Application;
use App\Controllers\AuthController;
use App\Controllers\HomeController;
use App\Controllers\PostsController;
use App\Controllers\SearchController;
use App\Controllers\CartController;
use App\Controllers\ChatController;
use App\Controllers\AdminController;
use App\Controllers\NotificationController;
use App\Controllers\FileManagerController;

/* Documentation:
 * withoutCSRFToken() - Исключает проверку на CSRF если метода нет то по умолчанию true
 * */

const MIDDLEWARE = [
    'auth' => \FBL\Middleware\Auth::class,
    'guest' => \FBL\Middleware\Guest::class,
    'admin' => \FBL\Middleware\Admin::class,
];

$app->router->get('/product/(?P<slug>[a-z0-9-]+)/?', function () {
    return 'Product ' . get_route_param('slug');
});

// Site pages ---------- //
$app->router->get('/login', [AuthController::class, 'login'])->middleware(['guest']);
$app->router->post('/login', [AuthController::class, 'login'])->middleware(['guest']);
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
$app->router->get('/notifications/feed', [NotificationController::class, 'feed'])->middleware(['auth']);
$app->router->get('/logout', [AuthController::class, 'logout'])->middleware(['auth']);
$app->router->get('/search/suggest', [SearchController::class, 'suggest']);
$app->router->get('/search', [SearchController::class, 'index']);
$app->router->get('/contacts', [HomeController::class, 'contacts']);
$app->router->post('/contacts', [HomeController::class, 'contacts']);
$app->router->get('/posts/(?P<slug>[a-z0-9-]+)/?', [PostsController::class, 'show']);
$app->router->get('/posts', [PostsController::class, 'index']);

// Admin pages ---------- //
$app->router->get('/admin', [AdminController::class, 'dashboard'])->middleware(['auth', 'admin']);
$app->router->get('/admin/contact-requests', [AdminController::class, 'contactRequests'])->middleware(['auth', 'admin']);
$app->router->post('/admin/contact-requests/delete', [AdminController::class, 'contactRequestDelete'])->middleware(['auth', 'admin']);
$app->router->get('/admin/posts', [AdminController::class, 'posts'])->middleware(['auth', 'admin']);
$app->router->get('/admin/posts/create', [AdminController::class, 'postForm'])->middleware(['auth', 'admin']);
$app->router->post('/admin/posts/create', [AdminController::class, 'postForm'])->middleware(['auth', 'admin']);
$app->router->get('/admin/posts/edit/(?P<id>\d+)/?', [AdminController::class, 'postForm'])->middleware(['auth', 'admin']);
$app->router->post('/admin/posts/edit/(?P<id>\d+)/?', [AdminController::class, 'postForm'])->middleware(['auth', 'admin']);
$app->router->post('/admin/posts/delete', [AdminController::class, 'postDelete'])->middleware(['auth', 'admin']);
$app->router->get('/admin/categories', [AdminController::class, 'categories'])->middleware(['auth', 'admin']);
$app->router->get('/admin/categories/create', [AdminController::class, 'categoryForm'])->middleware(['auth', 'admin']);
$app->router->post('/admin/categories/create', [AdminController::class, 'categoryForm'])->middleware(['auth', 'admin']);
$app->router->get('/admin/categories/edit/(?P<id>\d+)/?', [AdminController::class, 'categoryForm'])->middleware(['auth', 'admin']);
$app->router->post('/admin/categories/edit/(?P<id>\d+)/?', [AdminController::class, 'categoryForm'])->middleware(['auth', 'admin']);
$app->router->post('/admin/categories/delete', [AdminController::class, 'categoryDelete'])->middleware(['auth', 'admin']);
$app->router->get('/admin/users', [AdminController::class, 'users'])->middleware(['auth', 'admin']);
$app->router->get('/admin/users/edit/(?P<id>\d+)/?', [AdminController::class, 'userForm'])->middleware(['auth', 'admin']);
$app->router->post('/admin/users/edit/(?P<id>\d+)/?', [AdminController::class, 'userForm'])->middleware(['auth', 'admin']);
$app->router->post('/admin/users/delete', [AdminController::class, 'userDelete'])->middleware(['auth', 'admin']);
$app->router->get('/admin/roles', [AdminController::class, 'roles'])->middleware(['auth', 'admin']);
$app->router->get('/admin/roles/create', [AdminController::class, 'roleForm'])->middleware(['auth', 'admin']);
$app->router->post('/admin/roles/create', [AdminController::class, 'roleForm'])->middleware(['auth', 'admin']);
$app->router->get('/admin/roles/edit/(?P<id>\d+)/?', [AdminController::class, 'roleForm'])->middleware(['auth', 'admin']);
$app->router->post('/admin/roles/edit/(?P<id>\d+)/?', [AdminController::class, 'roleForm'])->middleware(['auth', 'admin']);
$app->router->post('/admin/roles/delete', [AdminController::class, 'roleDelete'])->middleware(['auth', 'admin']);
$app->router->get('/admin/settings', [AdminController::class, 'settings'])->middleware(['auth', 'admin']);
$app->router->post('/admin/settings', [AdminController::class, 'settings'])->middleware(['auth', 'admin']);
$app->router->get('/admin/updates', [AdminController::class, 'updates'])->middleware(['auth', 'admin']);
$app->router->post('/admin/updates', [AdminController::class, 'updates'])->middleware(['auth', 'admin']);
$app->router->post('/admin/settings/update-center/check', [AdminController::class, 'checkForUpdates'])->middleware(['auth', 'admin']);
$app->router->post('/admin/settings/update-center/update', [AdminController::class, 'runUpdate'])->middleware(['auth', 'admin']);
$app->router->get('/admin/files', [FileManagerController::class, 'index'])->middleware(['auth', 'admin']);
$app->router->post('/admin/files/upload', [FileManagerController::class, 'upload'])->middleware(['auth', 'admin']);
$app->router->post('/admin/files/folder/create', [FileManagerController::class, 'createDirectory'])->middleware(['auth', 'admin']);
$app->router->post('/admin/files/rename', [FileManagerController::class, 'rename'])->middleware(['auth', 'admin']);
$app->router->post('/admin/files/action', [FileManagerController::class, 'bulkAction'])->middleware(['auth', 'admin']);
$app->router->post('/admin/files/delete', [FileManagerController::class, 'delete'])->middleware(['auth', 'admin']);
$app->router->post('/admin/files/folder/delete', [FileManagerController::class, 'deleteDirectory'])->middleware(['auth', 'admin']);

// Store pages ---------- //
$app->router->get('/add-to-cart', [CartController::class, 'addToCart']);
$app->router->get('/remove-from-cart', [CartController::class, 'removeFromCart']);

// Home (keep last among dynamic routes to avoid locale false-positive)
$app->router->get('/', [HomeController::class, 'index']);

// Seed
//$app->router->get('/seed-full', function () {
//    $result = require __DIR__ . '/seeders/full_database.php';
//    return json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
//});
//
//$app->router->get('/seed-reset-creator', function () {
//    $result = require __DIR__ . '/seeders/reset_creator.php';
//    return json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
//});
//
//$app->router->get('/seed-demo', function () {
//    $result = require __DIR__ . '/seeders/reset_demo.php';
//    return json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
//}
//);
