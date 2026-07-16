<?php

use Fireball\VpnManagerV2\Controllers\Public\SubscriptionController;
use Fireball\VpnManagerV2\Controllers\Public\ProfileVpnController;

/** @var \FBL\Router $router */

$router->get(
    '/vpn-v2/subscription/(?P<token>[a-fA-F0-9]{64})/?',
    [SubscriptionController::class, 'show']
)->withoutCSRFToken();

$router->get('/profile/vpn-v2', [ProfileVpnController::class, 'index'])
    ->middleware(['auth']);
$router->get(
    '/profile/vpn-v2/instructions/(?P<platform>ios|android|windows|macos)/?',
    [ProfileVpnController::class, 'instructions']
)->middleware(['auth']);
$router->get('/profile/vpn-v2/(?P<id>\d+)/?', [ProfileVpnController::class, 'show'])
    ->middleware(['auth']);
