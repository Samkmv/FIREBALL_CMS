<?php

/** @var Application $app */

use FBL\Application;
use App\Controllers\HomeController;
use App\Controllers\CartController;

/* Documentation:
 * withoutCSRFToken() - Исключает проверку на CSRF если метода нет то по умолчанию true
 * */

const MIDDLEWARE = [
    'auth' => \FBL\Middleware\Auth::class,
    'guest' => \FBL\Middleware\Guest::class,
];

$app->router->get('/product/(?P<slug>[a-z0-9-]+)/?', function () {
    return 'Product ' . get_route_param('slug');
});

// Site pages ---------- //
$app->router->get('/', [HomeController::class, 'index']);

// Store pages ---------- //
$app->router->get('/add-to-cart', [CartController::class, 'addToCart']);
$app->router->get('/remove-from-cart', [CartController::class, 'removeFromCart']);

// Blog pages ---------- //

// Seed
$app->router->get('/seed', function () {
    require_once __DIR__ . '/seeders/categories.php';
    require_once __DIR__ . '/seeders/products.php';
    return 'OK';
});