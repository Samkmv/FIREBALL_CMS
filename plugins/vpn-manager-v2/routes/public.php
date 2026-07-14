<?php

use Fireball\VpnManagerV2\Controllers\Public\SubscriptionController;

/** @var \FBL\Router $router */

$router->get(
    '/vpn-v2/subscription/(?P<token>[a-fA-F0-9]{64})/?',
    [SubscriptionController::class, 'show']
)->withoutCSRFToken();
