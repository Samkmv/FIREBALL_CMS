<?php

use Fireball\Subscriptions\Controllers\AdminController as SubscriptionsAdminController;
use Fireball\Subscriptions\Controllers\PublicController as SubscriptionsPublicController;

/** @var \FBL\Router $router */

$router->get('/plugins/subscriptions/assets/(?P<file>[a-z0-9._-]+)', static function (): never {
    if ((string)get_route_param('file') !== 'subscriptions.css') {
        abort();
    }
    $path = __DIR__ . '/assets/subscriptions.css';
    if (!is_file($path)) {
        abort();
    }
    header('Content-Type: text/css; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
    readfile($path);
    exit;
});

$router->get('/subscriptions', [SubscriptionsPublicController::class, 'plans']);
$router->get('/subscriptions/plans', [SubscriptionsPublicController::class, 'plans']);
$router->get('/subscriptions/checkout/(?P<id>\d+)/?', [SubscriptionsPublicController::class, 'checkout'])->middleware(['auth']);
$router->post('/subscriptions/payment/create', [SubscriptionsPublicController::class, 'createPayment'])->middleware(['auth']);
$router->add('/subscriptions/robokassa/result', [SubscriptionsPublicController::class, 'result'], ['GET', 'POST'])->withoutCSRFToken();
$router->add('/subscriptions/robokassa/success', [SubscriptionsPublicController::class, 'success'], ['GET', 'POST'])->withoutCSRFToken();
$router->add('/subscriptions/robokassa/fail', [SubscriptionsPublicController::class, 'fail'], ['GET', 'POST'])->withoutCSRFToken();
$router->get('/account/subscription', [SubscriptionsPublicController::class, 'account'])->middleware(['auth']);
$router->post('/account/subscription/auto-renew', [SubscriptionsPublicController::class, 'autoRenew'])->middleware(['auth']);
$router->get('/profile/subscription-details', [SubscriptionsPublicController::class, 'profile'])->middleware(['auth']);
$router->post('/profile/subscription-details', [SubscriptionsPublicController::class, 'profile'])->middleware(['auth']);
$router->post('/subscription-media/token/(?P<type>video|camera_archive)/(?P<id>[a-zA-Z0-9._:-]+)', [SubscriptionsPublicController::class, 'mediaToken'])->middleware(['auth']);
$router->get('/subscription-media/validate/(?P<type>video|camera_archive)/(?P<id>[a-zA-Z0-9._:-]+)', [SubscriptionsPublicController::class, 'validateMediaToken']);

$router->get('/admin/subscriptions', [SubscriptionsAdminController::class, 'dashboard'])->middleware(['auth', 'admin']);
$router->get('/admin/subscriptions/plans', [SubscriptionsAdminController::class, 'plans'])->middleware(['auth', 'admin']);
$router->get('/admin/subscriptions/plans/create', [SubscriptionsAdminController::class, 'planForm'])->middleware(['auth', 'admin']);
$router->post('/admin/subscriptions/plans/create', [SubscriptionsAdminController::class, 'planForm'])->middleware(['auth', 'admin']);
$router->get('/admin/subscriptions/plans/edit/(?P<id>\d+)/?', [SubscriptionsAdminController::class, 'planForm'])->middleware(['auth', 'admin']);
$router->post('/admin/subscriptions/plans/edit/(?P<id>\d+)/?', [SubscriptionsAdminController::class, 'planForm'])->middleware(['auth', 'admin']);
$router->post('/admin/subscriptions/plans/action', [SubscriptionsAdminController::class, 'planAction'])->middleware(['auth', 'admin']);
$router->get('/admin/subscriptions/subscribers', [SubscriptionsAdminController::class, 'subscribers'])->middleware(['auth', 'admin']);
$router->post('/admin/subscriptions/subscribers/grant', [SubscriptionsAdminController::class, 'grant'])->middleware(['auth', 'admin']);
$router->post('/admin/subscriptions/subscribers/update', [SubscriptionsAdminController::class, 'updateSubscriber'])->middleware(['auth', 'admin']);
$router->get('/admin/subscriptions/payments', [SubscriptionsAdminController::class, 'payments'])->middleware(['auth', 'admin']);
$router->post('/admin/subscriptions/payments/clear', [SubscriptionsAdminController::class, 'clearPayments'])->middleware(['auth', 'admin']);
$router->get('/admin/subscriptions/content', [SubscriptionsAdminController::class, 'contentAccess'])->middleware(['auth', 'admin']);
$router->post('/admin/subscriptions/content', [SubscriptionsAdminController::class, 'saveContentAccess'])->middleware(['auth', 'admin']);
$router->get('/admin/subscriptions/profile-fields', [SubscriptionsAdminController::class, 'fields'])->middleware(['auth', 'admin']);
$router->get('/admin/subscriptions/profile-fields/create', [SubscriptionsAdminController::class, 'fieldForm'])->middleware(['auth', 'admin']);
$router->post('/admin/subscriptions/profile-fields/create', [SubscriptionsAdminController::class, 'fieldForm'])->middleware(['auth', 'admin']);
$router->get('/admin/subscriptions/profile-fields/edit/(?P<id>\d+)/?', [SubscriptionsAdminController::class, 'fieldForm'])->middleware(['auth', 'admin']);
$router->post('/admin/subscriptions/profile-fields/edit/(?P<id>\d+)/?', [SubscriptionsAdminController::class, 'fieldForm'])->middleware(['auth', 'admin']);
$router->post('/admin/subscriptions/profile-fields/delete', [SubscriptionsAdminController::class, 'deleteField'])->middleware(['auth', 'admin']);
$router->get('/admin/subscriptions/settings', [SubscriptionsAdminController::class, 'settings'])->middleware(['auth', 'admin']);
$router->post('/admin/subscriptions/settings', [SubscriptionsAdminController::class, 'settings'])->middleware(['auth', 'admin']);
