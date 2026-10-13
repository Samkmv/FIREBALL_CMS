<?php
use Fireball\ReelPlayer\Controllers\DiscoveryController;

/** @var \FBL\Router $router */
$router->get('/music/api/genres', [DiscoveryController::class, 'genres']);
$router->get('/music/api/tags', [DiscoveryController::class, 'tags']);
$router->get('/music/api/recommendations', [DiscoveryController::class, 'recommendations']);
$router->get('/admin/reel-player/api/discovery/dictionary', [DiscoveryController::class, 'dictionary'])->middleware(['auth','admin']);
$router->get('/admin/reel-player/api/discovery/entity', [DiscoveryController::class, 'entity'])->middleware(['auth','admin']);
$router->post('/admin/reel-player/api/discovery/action', [DiscoveryController::class, 'action'])->middleware(['auth','admin']);
