<?php

use Fireball\VpnManagerV2\Controllers\Admin\OverviewController;
use Fireball\VpnManagerV2\Controllers\Admin\InboundController;
use Fireball\VpnManagerV2\Controllers\Admin\PlanController;
use Fireball\VpnManagerV2\Controllers\Admin\ServerController;
use Fireball\VpnManagerV2\Controllers\Admin\SubscriptionController;
use Fireball\VpnManagerV2\Controllers\Admin\ConnectionController;
use Fireball\VpnManagerV2\Controllers\Admin\SettingsController;
use Fireball\VpnManagerV2\Controllers\Admin\SyncController;

/** @var \FBL\Router $router */

$router->get('/admin/plugins/vpn-manager-v2', static function (): string {
    return (new OverviewController())->index();
})->middleware(['auth', 'admin']);

$router->get('/admin/plugins/vpn-manager-v2/servers', [ServerController::class, 'index'])
    ->middleware(['auth', 'admin']);
$router->get('/admin/plugins/vpn-manager-v2/servers/create', [ServerController::class, 'create'])
    ->middleware(['auth', 'admin']);
$router->post('/admin/plugins/vpn-manager-v2/servers/create', [ServerController::class, 'store'])
    ->middleware(['auth', 'admin']);
$router->get('/admin/plugins/vpn-manager-v2/servers/edit/(?P<id>\d+)/?', [ServerController::class, 'edit'])
    ->middleware(['auth', 'admin']);
$router->post('/admin/plugins/vpn-manager-v2/servers/edit/(?P<id>\d+)/?', [ServerController::class, 'update'])
    ->middleware(['auth', 'admin']);
$router->post('/admin/plugins/vpn-manager-v2/servers/test', [ServerController::class, 'test'])
    ->middleware(['auth', 'admin']);
$router->post('/admin/plugins/vpn-manager-v2/servers/toggle', [ServerController::class, 'toggle'])
    ->middleware(['auth', 'admin']);

$router->get('/admin/plugins/vpn-manager-v2/inbounds', [InboundController::class, 'index'])
    ->middleware(['auth', 'admin']);
$router->post('/admin/plugins/vpn-manager-v2/inbounds/sync', [InboundController::class, 'sync'])
    ->middleware(['auth', 'admin']);

$router->get('/admin/plugins/vpn-manager-v2/plans', [PlanController::class, 'index'])
    ->middleware(['auth', 'admin']);
$router->get('/admin/plugins/vpn-manager-v2/plans/create', [PlanController::class, 'create'])
    ->middleware(['auth', 'admin']);
$router->post('/admin/plugins/vpn-manager-v2/plans/create', [PlanController::class, 'store'])
    ->middleware(['auth', 'admin']);
$router->get('/admin/plugins/vpn-manager-v2/plans/edit/(?P<id>\d+)/?', [PlanController::class, 'edit'])
    ->middleware(['auth', 'admin']);
$router->post('/admin/plugins/vpn-manager-v2/plans/edit/(?P<id>\d+)/?', [PlanController::class, 'update'])
    ->middleware(['auth', 'admin']);
$router->post('/admin/plugins/vpn-manager-v2/plans/toggle', [PlanController::class, 'toggle'])
    ->middleware(['auth', 'admin']);
$router->post('/admin/plugins/vpn-manager-v2/plans/(?P<id>\d+)/preview/?', [PlanController::class, 'preview'])
    ->middleware(['auth', 'admin']);
$router->post('/admin/plugins/vpn-manager-v2/plans/(?P<id>\d+)/reconcile/?', [PlanController::class, 'reconcile'])
    ->middleware(['auth', 'admin']);
$router->post('/admin/plugins/vpn-manager-v2/plans/(?P<id>\d+)/remove-obsolete/?', [PlanController::class, 'removeObsolete'])
    ->middleware(['auth', 'admin']);

$router->get('/admin/plugins/vpn-manager-v2/subscriptions', [SubscriptionController::class, 'index'])
    ->middleware(['auth', 'admin']);
$router->get('/admin/plugins/vpn-manager-v2/subscriptions/create', [SubscriptionController::class, 'create'])
    ->middleware(['auth', 'admin']);
$router->post('/admin/plugins/vpn-manager-v2/subscriptions/create', [SubscriptionController::class, 'store'])
    ->middleware(['auth', 'admin']);
$router->get('/admin/plugins/vpn-manager-v2/subscriptions/edit/(?P<id>\d+)/?', [SubscriptionController::class, 'edit'])
    ->middleware(['auth', 'admin']);
$router->post('/admin/plugins/vpn-manager-v2/subscriptions/edit/(?P<id>\d+)/?', [SubscriptionController::class, 'update'])
    ->middleware(['auth', 'admin']);
$router->post('/admin/plugins/vpn-manager-v2/subscriptions/(?P<id>\d+)/suspend/?', [SubscriptionController::class, 'suspend'])
    ->middleware(['auth', 'admin']);
$router->post('/admin/plugins/vpn-manager-v2/subscriptions/(?P<id>\d+)/delete/?', [SubscriptionController::class, 'delete'])
    ->middleware(['auth', 'admin']);
$router->post('/admin/plugins/vpn-manager-v2/subscriptions/(?P<id>\d+)/create-missing/?', [SubscriptionController::class, 'createMissing'])
    ->middleware(['auth', 'admin']);
$router->post('/admin/plugins/vpn-manager-v2/subscriptions/(?P<id>\d+)/connections/order/?', [SubscriptionController::class, 'updateConnectionOrder'])
    ->middleware(['auth', 'admin']);
$router->get('/admin/plugins/vpn-manager-v2/subscriptions/(?P<id>\d+)/?', [SubscriptionController::class, 'show'])
    ->middleware(['auth', 'admin']);

$router->get('/admin/plugins/vpn-manager-v2/connections', [ConnectionController::class, 'index'])
    ->middleware(['auth', 'admin']);
$router->get('/admin/plugins/vpn-manager-v2/connections/(?P<id>\d+)/?', [ConnectionController::class, 'show'])
    ->middleware(['auth', 'admin']);
$router->get('/admin/plugins/vpn-manager-v2/connections/(?P<id>\d+)/edit/?', [ConnectionController::class, 'edit'])
    ->middleware(['auth', 'admin']);
$router->post('/admin/plugins/vpn-manager-v2/connections/(?P<id>\d+)/edit/?', [ConnectionController::class, 'update'])
    ->middleware(['auth', 'admin']);
$router->post('/admin/plugins/vpn-manager-v2/connections/(?P<id>\d+)/sync/?', [ConnectionController::class, 'sync'])
    ->middleware(['auth', 'admin']);
$router->post('/admin/plugins/vpn-manager-v2/connections/(?P<id>\d+)/retry/?', [ConnectionController::class, 'retry'])
    ->middleware(['auth', 'admin']);

$router->get('/admin/plugins/vpn-manager-v2/operations', [SyncController::class, 'operations'])
    ->middleware(['auth', 'admin']);
$router->get('/admin/plugins/vpn-manager-v2/conflicts', [SyncController::class, 'conflicts'])
    ->middleware(['auth', 'admin']);
$router->post('/admin/plugins/vpn-manager-v2/conflicts/link', [SyncController::class, 'linkRemoteClient'])
    ->middleware(['auth', 'admin']);
$router->get('/admin/plugins/vpn-manager-v2/sync-logs', [SyncController::class, 'logs'])
    ->middleware(['auth', 'admin']);
$router->post('/admin/plugins/vpn-manager-v2/sync/server/(?P<id>\d+)/?', [SyncController::class, 'server'])
    ->middleware(['auth', 'admin']);
$router->post('/admin/plugins/vpn-manager-v2/sync/subscription/(?P<id>\d+)/?', [SyncController::class, 'subscription'])
    ->middleware(['auth', 'admin']);
$router->post('/admin/plugins/vpn-manager-v2/sync/connection/(?P<id>\d+)/?', [SyncController::class, 'connection'])
    ->middleware(['auth', 'admin']);
$router->post('/admin/plugins/vpn-manager-v2/sync/connection/(?P<id>\d+)/reset-traffic/?', [SyncController::class, 'resetTraffic'])
    ->middleware(['auth', 'admin']);
$router->post('/admin/plugins/vpn-manager-v2/sync/full/?', [SyncController::class, 'full'])
    ->middleware(['auth', 'admin']);
$router->post('/admin/plugins/vpn-manager-v2/operations/retry/?', [SyncController::class, 'retry'])
    ->middleware(['auth', 'admin']);
$router->post('/admin/plugins/vpn-manager-v2/operations/(?P<operation>[a-fA-F0-9-]{36})/cancel/?', [SyncController::class, 'cancel'])
    ->middleware(['auth', 'admin']);
$router->get('/admin/plugins/vpn-manager-v2/operations/(?P<operation>[a-fA-F0-9-]{36})/?', [SyncController::class, 'progress'])
    ->middleware(['auth', 'admin']);

$router->get('/admin/plugins/vpn-manager-v2/settings', [SettingsController::class, 'index'])
    ->middleware(['auth', 'admin']);
$router->post('/admin/plugins/vpn-manager-v2/settings', [SettingsController::class, 'update'])
    ->middleware(['auth', 'admin']);
