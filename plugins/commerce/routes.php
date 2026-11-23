<?php

use Fireball\Commerce\Controllers\AdminController;
use Fireball\Commerce\Controllers\ShopController;

/** @var \FBL\Router $router */
$router->get('/shop', [ShopController::class, 'catalog']);
$router->get('/shop/home', [ShopController::class, 'home']);
$router->get('/shop/search/suggest', [ShopController::class, 'suggest']);
$router->get('/shop/favorites', [ShopController::class, 'favorites']);
$router->post('/shop/favorites/toggle', [ShopController::class, 'toggleFavorite']);
$router->get('/shop/product/(?P<slug>[a-z0-9-]+)', [ShopController::class, 'product']);
$router->get('/shop/product/(?P<slug>[a-z0-9-]+)/(?P<section>details|reviews)', [ShopController::class, 'product']);
$router->post('/shop/product/(?P<slug>[a-z0-9-]+)/reviews', [ShopController::class, 'submitReview'])->middleware(['auth']);
$router->get('/shop/cart', [ShopController::class, 'cart']);
$router->post('/shop/cart/add', [ShopController::class, 'add']);
$router->post('/shop/cart/line', [ShopController::class, 'updateLine']);
$router->post('/shop/cart/clear', [ShopController::class, 'clearCart']);
$router->post('/shop/cart/update', [ShopController::class, 'update']);
$router->get('/shop/checkout', [ShopController::class, 'checkout']);
$router->post('/shop/checkout', [ShopController::class, 'place']);
$router->get('/shop/order/(?P<id>\d+)', [ShopController::class, 'order']);
$router->get('/profile/shop', [ShopController::class, 'history'])->middleware(['auth']);
foreach (['css' => 'text/css', 'js' => 'application/javascript'] as $extension => $contentType) {
    $router->get('/plugins/commerce/storefront\.' . $extension, static function () use ($extension, $contentType): never {
        header('Content-Type: ' . $contentType . '; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: public, max-age=3600');
        readfile(__DIR__ . '/assets/storefront.' . $extension);
        exit;
    });
}
$router->get('/plugins/commerce/mini-cart\.js', static function (): never {
    header('Content-Type: application/javascript; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: public, max-age=3600');
    readfile(__DIR__ . '/assets/mini-cart.js');
    exit;
});

$prefix = '/admin/commerce';
$router->get($prefix, [AdminController::class, 'overview'])->middleware(['auth', 'admin']);
$router->get($prefix . '/products', [AdminController::class, 'products'])->middleware(['auth', 'admin']);
$router->get($prefix . '/products/edit/(?P<id>\d+)', [AdminController::class, 'productForm'])->middleware(['auth', 'admin']);
$router->post($prefix . '/products/edit/(?P<id>\d+)', [AdminController::class, 'saveProduct'])->middleware(['auth', 'admin']);
$router->get($prefix . '/categories', [AdminController::class, 'categories'])->middleware(['auth', 'admin']);
$router->post($prefix . '/categories/(?P<id>\d+)', [AdminController::class, 'saveCategory'])->middleware(['auth', 'admin']);
$router->get($prefix . '/slides', [AdminController::class, 'slides'])->middleware(['auth', 'admin']);
$router->post($prefix . '/slides/(?P<id>\d+)', [AdminController::class, 'saveSlide'])->middleware(['auth', 'admin']);
$router->post($prefix . '/slides/(?P<id>\d+)/delete', [AdminController::class, 'deleteSlide'])->middleware(['auth', 'admin']);
$router->get($prefix . '/reviews', [AdminController::class, 'reviews'])->middleware(['auth', 'admin']);
$router->post($prefix . '/reviews/(?P<id>\d+)', [AdminController::class, 'moderateReview'])->middleware(['auth', 'admin']);
$router->get($prefix . '/orders', [AdminController::class, 'orders'])->middleware(['auth', 'admin']);
$router->get($prefix . '/orders/(?P<id>\d+)', [AdminController::class, 'order'])->middleware(['auth', 'admin']);
$router->post($prefix . '/orders/(?P<id>\d+)', [AdminController::class, 'updateOrder'])->middleware(['auth', 'admin']);
$router->get($prefix . '/settings', [AdminController::class, 'settings'])->middleware(['auth', 'admin']);
$router->post($prefix . '/settings', [AdminController::class, 'saveSettings'])->middleware(['auth', 'admin']);
