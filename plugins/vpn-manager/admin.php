<?php

use Fireball\VpnManager\Clients\ThreeXuiClient;
use Fireball\VpnManager\Jobs\VpnCheckExpirationsJob;
use Fireball\VpnManager\Jobs\VpnCheckTrafficLimitsJob;
use Fireball\VpnManager\Jobs\VpnSendExpirationNotificationsJob;
use Fireball\VpnManager\Jobs\VpnSendTrafficNotificationsJob;
use Fireball\VpnManager\Jobs\VpnSyncTrafficJob;
use Fireball\VpnManager\Services\ConnectionActionService;
use Fireball\VpnManager\Services\NotificationScheduler;
use Fireball\VpnManager\Services\SettingsService;
use Fireball\VpnManager\Services\SubscriptionAutomationService;
use Fireball\VpnManager\Services\SubscriptionProvisioningService;

/** @var \FBL\Router $router */

$vpnRedirect = static function (string $path = '/admin/plugins/vpn-manager'): void {
    response()->redirect(base_href($path));
};

$vpnRepo = static fn() => FireballPluginVpnManager::repository();

$router->get('/admin/plugins/vpn-manager/assets/(?P<file>[a-z0-9._-]+)', static function (): never {
    $file = (string)get_route_param('file');
    if (!in_array($file, ['vpn-manager.css', 'vpn-manager.js'], true)) {
        abort();
    }

    $path = __DIR__ . '/assets/' . $file;
    $real = realpath($path);
    $base = realpath(__DIR__ . '/assets');
    if ($real === false || $base === false || !str_starts_with($real, rtrim($base, '/') . '/')) {
        abort();
    }

    header('Content-Type: ' . (str_ends_with($file, '.css') ? 'text/css' : 'application/javascript') . '; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
    readfile($real);
    exit;
})->middleware(['auth', 'admin']);

$router->get('/admin/plugins/vpn-manager', static function () use ($vpnRepo): string {
    $repo = $vpnRepo();

    return plugin_view('vpn-manager', 'dashboard', FireballPluginVpnManager::viewData('dashboard', [
        'title' => FireballPluginVpnManager::t('vpn_manager_dashboard_title'),
        'subtitle' => FireballPluginVpnManager::t('vpn_manager_dashboard_subtitle'),
        'stats' => $repo->dashboardStats(),
        'subscriptions' => $repo->subscriptions(8),
        'events' => $repo->events(8),
    ]));
})->middleware(['auth', 'admin']);

$router->get('/admin/plugins/vpn-manager/servers', static function () use ($vpnRepo): string {
    return plugin_view('vpn-manager', 'servers', FireballPluginVpnManager::viewData('servers', [
        'title' => FireballPluginVpnManager::t('vpn_manager_servers_title'),
        'subtitle' => FireballPluginVpnManager::t('vpn_manager_servers_subtitle'),
        'servers' => $vpnRepo()->servers(),
    ]));
})->middleware(['auth', 'admin']);

$router->get('/admin/plugins/vpn-manager/servers/create', static function () use ($vpnRepo): string {
    return plugin_view('vpn-manager', 'server-form', FireballPluginVpnManager::viewData('servers', [
        'title' => FireballPluginVpnManager::t('vpn_manager_server_create_title'),
        'subtitle' => FireballPluginVpnManager::t('vpn_manager_server_form_subtitle'),
        'server' => null,
    ]));
})->middleware(['auth', 'admin']);

$router->post('/admin/plugins/vpn-manager/servers/create', static function () use ($vpnRedirect, $vpnRepo): void {
    try {
        $vpnRepo()->saveServer(request()->getData());
        session()->setFlash('success', FireballPluginVpnManager::t('vpn_manager_flash_server_saved'));
        $vpnRedirect('/admin/plugins/vpn-manager/servers');
    } catch (Throwable $exception) {
        log_error_details('VPN Manager server create failed', [], $exception);
        session()->setFlash('error', $exception->getMessage());
        $vpnRedirect('/admin/plugins/vpn-manager/servers/create');
    }
})->middleware(['auth', 'admin']);

$router->get('/admin/plugins/vpn-manager/servers/edit/(?P<id>\d+)/?', static function () use ($vpnRepo): string {
    $server = $vpnRepo()->server((int)get_route_param('id'));
    if (!$server) {
        abort();
    }

    return plugin_view('vpn-manager', 'server-form', FireballPluginVpnManager::viewData('servers', [
        'title' => FireballPluginVpnManager::t('vpn_manager_server_edit_title'),
        'subtitle' => FireballPluginVpnManager::t('vpn_manager_server_form_subtitle'),
        'server' => $server,
    ]));
})->middleware(['auth', 'admin']);

$router->post('/admin/plugins/vpn-manager/servers/edit/(?P<id>\d+)/?', static function () use ($vpnRedirect, $vpnRepo): void {
    $id = (int)get_route_param('id');
    try {
        $vpnRepo()->saveServer(request()->getData(), $id);
        session()->setFlash('success', FireballPluginVpnManager::t('vpn_manager_flash_server_saved'));
        $vpnRedirect('/admin/plugins/vpn-manager/servers');
    } catch (Throwable $exception) {
        log_error_details('VPN Manager server update failed', ['Server' => $id], $exception);
        session()->setFlash('error', $exception->getMessage());
        $vpnRedirect('/admin/plugins/vpn-manager/servers/edit/' . $id);
    }
})->middleware(['auth', 'admin']);

$router->post('/admin/plugins/vpn-manager/servers/toggle', static function () use ($vpnRedirect, $vpnRepo): void {
    try {
        $vpnRepo()->toggleServer((int)request()->post('id'));
        session()->setFlash('success', FireballPluginVpnManager::t('vpn_manager_flash_server_toggled'));
    } catch (Throwable $exception) {
        log_error_details('VPN Manager server toggle failed', ['Server' => request()->post('id')], $exception);
        session()->setFlash('error', $exception->getMessage());
    }

    $vpnRedirect('/admin/plugins/vpn-manager/servers');
})->middleware(['auth', 'admin']);

$router->post('/admin/plugins/vpn-manager/servers/delete', static function () use ($vpnRedirect, $vpnRepo): void {
    try {
        $vpnRepo()->deleteServer((int)request()->post('id'));
        session()->setFlash('success', FireballPluginVpnManager::t('vpn_manager_flash_server_deleted'));
    } catch (Throwable $exception) {
        log_error_details('VPN Manager server delete failed', ['Server' => request()->post('id')], $exception);
        session()->setFlash('error', $exception->getMessage());
    }

    $vpnRedirect('/admin/plugins/vpn-manager/servers');
})->middleware(['auth', 'admin']);

$router->post('/admin/plugins/vpn-manager/servers/test', static function () use ($vpnRedirect, $vpnRepo): void {
    $id = (int)request()->post('id');
    try {
        $repo = $vpnRepo();
        $server = $repo->server($id);
        if (!$server) {
            throw new RuntimeException(FireballPluginVpnManager::t('vpn_manager_error_server_not_found'));
        }

        $result = (new ThreeXuiClient($server))->testConnection();
        $repo->updateServerCheck($id, !empty($result['success']), (string)($result['message'] ?? ''));
        session()->setFlash(
            !empty($result['success']) ? 'success' : 'error',
            !empty($result['success'])
                ? FireballPluginVpnManager::t('vpn_manager_connection_established')
                : (string)($result['message'] ?? FireballPluginVpnManager::t('vpn_manager_connection_failed'))
        );
    } catch (Throwable $exception) {
        log_error_details('VPN Manager server test failed', ['Server' => $id], $exception);
        session()->setFlash('error', $exception->getMessage());
    }

    $vpnRedirect('/admin/plugins/vpn-manager/servers');
})->middleware(['auth', 'admin']);

$router->post('/admin/plugins/vpn-manager/servers/sync-inbounds', static function () use ($vpnRedirect, $vpnRepo): void {
    $id = (int)request()->post('id');
    try {
        $repo = $vpnRepo();
        $server = $repo->server($id);
        if (!$server) {
            throw new RuntimeException(FireballPluginVpnManager::t('vpn_manager_error_server_not_found'));
        }

        $response = (new ThreeXuiClient($server))->listInbounds();
        $items = $response['obj'] ?? $response['inbounds'] ?? $response;
        if (!is_array($items)) {
            $items = [];
        }
        $count = $repo->syncInboundsFromRemote($id, $items);
        session()->setFlash(
            $count > 0 ? 'success' : 'warning',
            $count > 0
                ? FireballPluginVpnManager::t('vpn_manager_flash_inbounds_synced')
                : FireballPluginVpnManager::t('vpn_manager_flash_inbounds_not_found')
        );
    } catch (Throwable $exception) {
        log_error_details('VPN Manager inbound sync failed', ['Server' => $id], $exception);
        $vpnRepo()->logEvent('inbounds.sync_failed', 'VPN inbounds synchronization failed.', ['error' => $exception->getMessage()], serverId: $id);
        session()->setFlash('error', FireballPluginVpnManager::t('vpn_manager_flash_inbounds_sync_failed'));
    }

    $vpnRedirect('/admin/plugins/vpn-manager/inbounds');
})->middleware(['auth', 'admin']);

$router->get('/admin/plugins/vpn-manager/inbounds/(?P<id>\d+)/?', static function () use ($vpnRepo): string {
    $inbound = $vpnRepo()->inbound((int)get_route_param('id'));
    if (!$inbound) {
        abort();
    }

    return plugin_view('vpn-manager', 'inbound-show', FireballPluginVpnManager::viewData('inbounds', [
        'title' => FireballPluginVpnManager::t('vpn_manager_inbound_json_title'),
        'subtitle' => FireballPluginVpnManager::t('vpn_manager_inbound_json_subtitle'),
        'inbound' => $inbound,
    ]));
})->middleware(['auth', 'admin']);

$router->get('/admin/plugins/vpn-manager/inbounds', static function () use ($vpnRepo): string {
    return plugin_view('vpn-manager', 'inbounds', FireballPluginVpnManager::viewData('inbounds', [
        'title' => FireballPluginVpnManager::t('vpn_manager_inbounds_title'),
        'subtitle' => FireballPluginVpnManager::t('vpn_manager_inbounds_subtitle'),
        'inbounds' => $vpnRepo()->inbounds(),
        'servers' => $vpnRepo()->servers(),
    ]));
})->middleware(['auth', 'admin']);

$router->post('/admin/plugins/vpn-manager/inbounds/toggle', static function () use ($vpnRedirect, $vpnRepo): void {
    try {
        $vpnRepo()->toggleInbound((int)request()->post('id'));
        session()->setFlash('success', FireballPluginVpnManager::t('vpn_manager_flash_inbound_toggled'));
    } catch (Throwable $exception) {
        log_error_details('VPN Manager inbound toggle failed', ['Inbound' => request()->post('id')], $exception);
        session()->setFlash('error', $exception->getMessage());
    }

    $vpnRedirect('/admin/plugins/vpn-manager/inbounds');
})->middleware(['auth', 'admin']);

$router->get('/admin/plugins/vpn-manager/plans', static function () use ($vpnRepo): string {
    return plugin_view('vpn-manager', 'plans', FireballPluginVpnManager::viewData('plans', [
        'title' => FireballPluginVpnManager::t('vpn_manager_plans_title'),
        'subtitle' => FireballPluginVpnManager::t('vpn_manager_plans_subtitle'),
        'plans' => $vpnRepo()->plans(),
    ]));
})->middleware(['auth', 'admin']);

$router->get('/admin/plugins/vpn-manager/plans/create', static function () use ($vpnRepo): string {
    return plugin_view('vpn-manager', 'plan-form', FireballPluginVpnManager::viewData('plans', [
        'title' => FireballPluginVpnManager::t('vpn_manager_plan_create_title'),
        'subtitle' => FireballPluginVpnManager::t('vpn_manager_plan_form_subtitle'),
        'plan' => null,
        'inbounds' => $vpnRepo()->activeInboundsWithServers(),
        'selectedInboundIds' => [],
    ]));
})->middleware(['auth', 'admin']);

$router->post('/admin/plugins/vpn-manager/plans/create', static function () use ($vpnRedirect, $vpnRepo): void {
    try {
        $vpnRepo()->savePlan(request()->getData());
        session()->setFlash('success', FireballPluginVpnManager::t('vpn_manager_flash_plan_saved'));
        $vpnRedirect('/admin/plugins/vpn-manager/plans');
    } catch (Throwable $exception) {
        log_error_details('VPN Manager plan create failed', [], $exception);
        session()->setFlash('error', $exception->getMessage());
        $vpnRedirect('/admin/plugins/vpn-manager/plans/create');
    }
})->middleware(['auth', 'admin']);

$router->get('/admin/plugins/vpn-manager/plans/edit/(?P<id>\d+)/?', static function () use ($vpnRepo): string {
    $id = (int)get_route_param('id');
    $plan = $vpnRepo()->plan($id);
    if (!$plan) {
        abort();
    }

    return plugin_view('vpn-manager', 'plan-form', FireballPluginVpnManager::viewData('plans', [
        'title' => FireballPluginVpnManager::t('vpn_manager_plan_edit_title'),
        'subtitle' => FireballPluginVpnManager::t('vpn_manager_plan_form_subtitle'),
        'plan' => $plan,
        'inbounds' => $vpnRepo()->activeInboundsWithServers(),
        'selectedInboundIds' => $vpnRepo()->planInboundIds($id),
    ]));
})->middleware(['auth', 'admin']);

$router->post('/admin/plugins/vpn-manager/plans/edit/(?P<id>\d+)/?', static function () use ($vpnRedirect, $vpnRepo): void {
    $id = (int)get_route_param('id');
    try {
        $vpnRepo()->savePlan(request()->getData(), $id);
        session()->setFlash('success', FireballPluginVpnManager::t('vpn_manager_flash_plan_saved'));
        $vpnRedirect('/admin/plugins/vpn-manager/plans');
    } catch (Throwable $exception) {
        log_error_details('VPN Manager plan update failed', ['Plan' => $id], $exception);
        session()->setFlash('error', $exception->getMessage());
        $vpnRedirect('/admin/plugins/vpn-manager/plans/edit/' . $id);
    }
})->middleware(['auth', 'admin']);

$router->post('/admin/plugins/vpn-manager/plans/toggle', static function () use ($vpnRedirect, $vpnRepo): void {
    try {
        $vpnRepo()->togglePlan((int)request()->post('id'));
        session()->setFlash('success', FireballPluginVpnManager::t('vpn_manager_flash_plan_toggled'));
    } catch (Throwable $exception) {
        log_error_details('VPN Manager plan toggle failed', ['Plan' => request()->post('id')], $exception);
        session()->setFlash('error', $exception->getMessage());
    }

    $vpnRedirect('/admin/plugins/vpn-manager/plans');
})->middleware(['auth', 'admin']);

$router->post('/admin/plugins/vpn-manager/plans/delete', static function () use ($vpnRedirect, $vpnRepo): void {
    try {
        $vpnRepo()->deletePlan((int)request()->post('id'));
        session()->setFlash('success', FireballPluginVpnManager::t('vpn_manager_flash_plan_deleted'));
    } catch (Throwable $exception) {
        log_error_details('VPN Manager plan delete failed', ['Plan' => request()->post('id')], $exception);
        session()->setFlash('error', $exception->getMessage());
    }

    $vpnRedirect('/admin/plugins/vpn-manager/plans');
})->middleware(['auth', 'admin']);

$router->get('/admin/plugins/vpn-manager/subscriptions', static function () use ($vpnRepo): string {
    return plugin_view('vpn-manager', 'subscriptions', FireballPluginVpnManager::viewData('subscriptions', [
        'title' => FireballPluginVpnManager::t('vpn_manager_subscriptions_title'),
        'subtitle' => FireballPluginVpnManager::t('vpn_manager_subscriptions_subtitle'),
        'subscriptions' => $vpnRepo()->subscriptions(),
    ]));
})->middleware(['auth', 'admin']);

$router->get('/admin/plugins/vpn-manager/subscriptions/create', static function () use ($vpnRepo): string {
    return plugin_view('vpn-manager', 'subscription-form', FireballPluginVpnManager::viewData('subscriptions', [
        'title' => FireballPluginVpnManager::t('vpn_manager_subscription_create_title'),
        'subtitle' => FireballPluginVpnManager::t('vpn_manager_subscription_form_subtitle'),
        'users' => $vpnRepo()->usersForSubscriptionForm(),
        'plans' => $vpnRepo()->activePlans(),
        'statuses' => $vpnRepo()->subscriptionStatuses(),
    ]));
})->middleware(['auth', 'admin']);

$router->post('/admin/plugins/vpn-manager/subscriptions/create', static function () use ($vpnRedirect, $vpnRepo): void {
    $subscriptionId = 0;
    try {
        $repo = $vpnRepo();
        $subscriptionId = $repo->createSubscription(request()->getData());
        $result = ['created' => 0, 'skipped' => 0, 'failed' => 0];
        if (!empty(request()->post('create_clients'))) {
            try {
                $result = (new SubscriptionProvisioningService($repo))->provision($subscriptionId);
            } catch (Throwable $exception) {
                $repo->updateSubscriptionProvisioningStatus($subscriptionId, 'provisioning_failed');
                $repo->logEvent('provisioning_failed', 'VPN subscription provisioning failed after local creation.', [
                    'subscription_id' => $subscriptionId,
                    'error_code' => get_class($exception),
                    'error_message' => $exception->getMessage(),
                ], subscriptionId: $subscriptionId);
                log_error_details('VPN Manager subscription provisioning failed after create', ['Subscription' => $subscriptionId], $exception);
                $result = ['created' => 0, 'skipped' => 0, 'failed' => 1];
            }
        }

        session()->setFlash(
            empty($result['failed']) ? 'success' : 'error',
            empty($result['failed'])
                ? FireballPluginVpnManager::t('vpn_manager_flash_subscription_created')
                : FireballPluginVpnManager::t('vpn_manager_flash_subscription_created_with_errors')
        );
        $vpnRedirect('/admin/plugins/vpn-manager/subscriptions/' . $subscriptionId);
    } catch (Throwable $exception) {
        log_error_details('VPN Manager subscription create failed', [], $exception);
        session()->setFlash('error', $exception->getMessage());
        $vpnRedirect($subscriptionId > 0 ? '/admin/plugins/vpn-manager/subscriptions/' . $subscriptionId : '/admin/plugins/vpn-manager/subscriptions/create');
    }
})->middleware(['auth', 'admin']);

$router->get('/admin/plugins/vpn-manager/subscriptions/extend/(?P<id>\d+)/?', static function () use ($vpnRepo): string {
    $id = (int)get_route_param('id');
    $subscription = $vpnRepo()->subscription($id);
    if (!$subscription) {
        abort();
    }

    return plugin_view('vpn-manager', 'subscription-extend', FireballPluginVpnManager::viewData('subscriptions', [
        'title' => FireballPluginVpnManager::t('vpn_manager_subscription_extend_title'),
        'subtitle' => FireballPluginVpnManager::t('vpn_manager_subscription_extend_subtitle'),
        'subscription' => $subscription,
    ]));
})->middleware(['auth', 'admin']);

$router->post('/admin/plugins/vpn-manager/subscriptions/extend', static function () use ($vpnRedirect, $vpnRepo): void {
    $id = (int)request()->post('id');
    try {
        $result = (new SubscriptionAutomationService($vpnRepo()))->extend(
            $id,
            (int)request()->post('days', 7),
            !empty(request()->post('reset_traffic')),
            !empty(request()->post('enable_clients'))
        );
        session()->setFlash(
            empty($result['failed']) ? 'success' : 'warning',
            empty($result['failed'])
                ? FireballPluginVpnManager::t('vpn_manager_flash_subscription_extended')
                : FireballPluginVpnManager::t('vpn_manager_flash_subscription_extended_with_errors')
        );
    } catch (Throwable $exception) {
        log_error_details('VPN Manager subscription extend failed', ['Subscription' => $id], $exception);
        session()->setFlash('error', $exception->getMessage());
    }

    $vpnRedirect('/admin/plugins/vpn-manager/subscriptions/' . $id);
})->middleware(['auth', 'admin']);

$router->get('/admin/plugins/vpn-manager/subscriptions/(?P<id>\d+)/?', static function () use ($vpnRepo): string {
    $id = (int)get_route_param('id');
    $subscription = $vpnRepo()->subscription($id);
    if (!$subscription) {
        abort();
    }

    return plugin_view('vpn-manager', 'subscription-show', FireballPluginVpnManager::viewData('subscriptions', [
        'title' => FireballPluginVpnManager::t('vpn_manager_subscription_card_title'),
        'subtitle' => FireballPluginVpnManager::t('vpn_manager_subscription_card_subtitle'),
        'subscription' => $subscription,
        'nodes' => $vpnRepo()->subscriptionNodes($id),
        'notifications' => $vpnRepo()->subscriptionNotifications($id),
        'diagnostics' => $vpnRepo()->subscriptionDiagnostics($id),
    ]));
})->middleware(['auth', 'admin']);

$router->post('/admin/plugins/vpn-manager/subscriptions/manual-reminder', static function () use ($vpnRedirect): void {
    $id = (int)request()->post('id');
    try {
        (new NotificationScheduler())->manualReminder($id);
        session()->setFlash('success', FireballPluginVpnManager::t('vpn_manager_flash_manual_reminder_sent'));
    } catch (Throwable $exception) {
        log_error_details('VPN Manager manual reminder failed', ['Subscription' => $id], $exception);
        session()->setFlash('error', $exception->getMessage());
    }

    $vpnRedirect('/admin/plugins/vpn-manager/subscriptions/' . $id);
})->middleware(['auth', 'admin']);

$router->post('/admin/plugins/vpn-manager/subscriptions/provision', static function () use ($vpnRedirect, $vpnRepo): void {
    $id = (int)request()->post('id');
    try {
        $result = (new SubscriptionProvisioningService($vpnRepo()))->provision($id);
        session()->setFlash(
            empty($result['failed']) ? 'success' : 'error',
            empty($result['failed'])
                ? FireballPluginVpnManager::t('vpn_manager_flash_provisioning_done')
                : FireballPluginVpnManager::t('vpn_manager_flash_provisioning_failed')
        );
    } catch (Throwable $exception) {
        log_error_details('VPN Manager subscription provisioning failed', ['Subscription' => $id], $exception);
        session()->setFlash('error', $exception->getMessage());
    }

    $vpnRedirect('/admin/plugins/vpn-manager/subscriptions/' . $id);
})->middleware(['auth', 'admin']);

$router->post('/admin/plugins/vpn-manager/subscriptions/status', static function () use ($vpnRedirect, $vpnRepo): void {
    $id = (int)request()->post('id');
    $status = (string)request()->post('status', 'active');
    try {
        $service = new SubscriptionAutomationService($vpnRepo());
        if ($status === 'active') {
            $result = $service->activate($id);
            $message = empty($result['failed']) ? FireballPluginVpnManager::t('vpn_manager_flash_subscription_enabled') : FireballPluginVpnManager::t('vpn_manager_flash_subscription_enabled_with_errors');
        } elseif ($status === 'suspended') {
            $result = $service->suspend($id);
            $message = empty($result['failed']) ? FireballPluginVpnManager::t('vpn_manager_flash_subscription_disabled') : FireballPluginVpnManager::t('vpn_manager_flash_subscription_disabled_with_errors');
        } else {
            $vpnRepo()->setSubscriptionStatus($id, $status);
            $result = ['failed' => 0];
            $message = FireballPluginVpnManager::t('vpn_manager_flash_subscription_status_saved');
        }

        session()->setFlash(empty($result['failed']) ? 'success' : 'warning', $message);
    } catch (Throwable $exception) {
        log_error_details('VPN Manager subscription status failed', ['Subscription' => $id, 'Status' => $status], $exception);
        session()->setFlash('error', $exception->getMessage());
    }

    $vpnRedirect('/admin/plugins/vpn-manager/subscriptions/' . $id);
})->middleware(['auth', 'admin']);

$router->post('/admin/plugins/vpn-manager/subscriptions/reset-traffic', static function () use ($vpnRedirect, $vpnRepo): void {
    $id = (int)request()->post('id');
    try {
        $result = (new SubscriptionAutomationService($vpnRepo()))->resetTraffic($id);
        session()->setFlash(
            empty($result['failed']) ? 'success' : 'warning',
            empty($result['failed'])
                ? FireballPluginVpnManager::t('vpn_manager_flash_subscription_traffic_reset')
                : FireballPluginVpnManager::t('vpn_manager_flash_subscription_traffic_reset_with_errors')
        );
    } catch (Throwable $exception) {
        log_error_details('VPN Manager subscription traffic reset failed', ['Subscription' => $id], $exception);
        session()->setFlash('error', $exception->getMessage());
    }

    $vpnRedirect('/admin/plugins/vpn-manager/subscriptions/' . $id);
})->middleware(['auth', 'admin']);

$router->get('/admin/plugins/vpn-manager/connections', static function () use ($vpnRepo): string {
    return plugin_view('vpn-manager', 'connections', FireballPluginVpnManager::viewData('connections', [
        'title' => FireballPluginVpnManager::t('vpn_manager_connections_title'),
        'subtitle' => FireballPluginVpnManager::t('vpn_manager_connections_subtitle'),
        'connections' => $vpnRepo()->connections(),
    ]));
})->middleware(['auth', 'admin']);

$router->post('/admin/plugins/vpn-manager/connections/sync', static function () use ($vpnRedirect, $vpnRepo): void {
    $id = (int)request()->post('id');
    try {
        (new ConnectionActionService($vpnRepo()))->sync($id);
        session()->setFlash('success', FireballPluginVpnManager::t('vpn_manager_flash_connection_synced'));
    } catch (Throwable $exception) {
        log_error_details('VPN Manager connection sync failed', ['Node' => $id], $exception);
        session()->setFlash('error', $exception->getMessage());
    }

    $vpnRedirect('/admin/plugins/vpn-manager/connections');
})->middleware(['auth', 'admin']);

$router->post('/admin/plugins/vpn-manager/connections/status', static function () use ($vpnRedirect, $vpnRepo): void {
    $id = (int)request()->post('id');
    $status = (string)request()->post('status', 'active');
    try {
        $service = new ConnectionActionService($vpnRepo());
        if ($status === 'active') {
            $service->enable($id);
        } elseif ($status === 'disabled') {
            $service->disable($id);
        } else {
            throw new RuntimeException(FireballPluginVpnManager::t('vpn_manager_error_invalid_status'));
        }
        session()->setFlash('success', FireballPluginVpnManager::t('vpn_manager_flash_connection_status_saved'));
    } catch (Throwable $exception) {
        log_error_details('VPN Manager connection status failed', ['Node' => $id, 'Status' => $status], $exception);
        session()->setFlash('error', $exception->getMessage());
    }

    $vpnRedirect('/admin/plugins/vpn-manager/connections');
})->middleware(['auth', 'admin']);

$router->post('/admin/plugins/vpn-manager/connections/reset-traffic', static function () use ($vpnRedirect, $vpnRepo): void {
    $id = (int)request()->post('id');
    try {
        (new ConnectionActionService($vpnRepo()))->resetTraffic($id);
        session()->setFlash('success', FireballPluginVpnManager::t('vpn_manager_flash_connection_traffic_reset'));
    } catch (Throwable $exception) {
        log_error_details('VPN Manager connection traffic reset failed', ['Node' => $id], $exception);
        session()->setFlash('error', $exception->getMessage());
    }

    $vpnRedirect('/admin/plugins/vpn-manager/connections');
})->middleware(['auth', 'admin']);

$router->post('/admin/plugins/vpn-manager/connections/delete', static function () use ($vpnRedirect, $vpnRepo): void {
    $id = (int)request()->post('id');
    try {
        (new ConnectionActionService($vpnRepo()))->delete($id);
        session()->setFlash('success', FireballPluginVpnManager::t('vpn_manager_flash_connection_deleted'));
    } catch (Throwable $exception) {
        log_error_details('VPN Manager connection delete failed', ['Node' => $id], $exception);
        session()->setFlash('error', $exception->getMessage());
    }

    $vpnRedirect('/admin/plugins/vpn-manager/connections');
})->middleware(['auth', 'admin']);

$router->get('/admin/plugins/vpn-manager/users', static function () use ($vpnRepo): string {
    return plugin_view('vpn-manager', 'users', FireballPluginVpnManager::viewData('users', [
        'title' => FireballPluginVpnManager::t('vpn_manager_users_title'),
        'subtitle' => FireballPluginVpnManager::t('vpn_manager_users_subtitle'),
        'users' => $vpnRepo()->userSummaries(),
    ]));
})->middleware(['auth', 'admin']);

$router->get('/admin/plugins/vpn-manager/statistics', static function () use ($vpnRepo): string {
    return plugin_view('vpn-manager', 'statistics', FireballPluginVpnManager::viewData('statistics', [
        'title' => FireballPluginVpnManager::t('vpn_manager_statistics_title'),
        'subtitle' => FireballPluginVpnManager::t('vpn_manager_statistics_subtitle'),
        'stats' => $vpnRepo()->dashboardStats(),
    ]));
})->middleware(['auth', 'admin']);

$router->get('/admin/plugins/vpn-manager/instructions', static function () use ($vpnRepo): string {
    return plugin_view('vpn-manager', 'instructions', FireballPluginVpnManager::viewData('instructions', [
        'title' => FireballPluginVpnManager::t('vpn_manager_instructions_title'),
        'subtitle' => FireballPluginVpnManager::t('vpn_manager_instructions_subtitle'),
        'instructions' => $vpnRepo()->instructions(),
    ]));
})->middleware(['auth', 'admin']);

$router->get('/admin/plugins/vpn-manager/logs', static function () use ($vpnRepo): string {
    return plugin_view('vpn-manager', 'logs', FireballPluginVpnManager::viewData('logs', [
        'title' => FireballPluginVpnManager::t('vpn_manager_logs_title'),
        'subtitle' => FireballPluginVpnManager::t('vpn_manager_logs_subtitle'),
        'events' => $vpnRepo()->events(),
    ]));
})->middleware(['auth', 'admin']);

$router->get('/admin/plugins/vpn-manager/settings', static function (): string {
    return plugin_view('vpn-manager', 'settings', FireballPluginVpnManager::viewData('settings', [
        'title' => FireballPluginVpnManager::t('vpn_manager_settings_title'),
        'subtitle' => FireballPluginVpnManager::t('vpn_manager_settings_subtitle'),
    ]));
})->middleware(['auth', 'admin']);

$router->post('/admin/plugins/vpn-manager/settings', static function () use ($vpnRedirect, $vpnRepo): void {
    try {
        SettingsService::save(request()->getData());
        $vpnRepo()->logEvent('settings.updated', 'VPN Manager settings updated.');
        session()->setFlash('success', FireballPluginVpnManager::t('vpn_manager_flash_settings_saved'));
    } catch (Throwable $exception) {
        log_error_details('VPN Manager settings save failed', [], $exception);
        session()->setFlash('error', $exception->getMessage());
    }

    $vpnRedirect('/admin/plugins/vpn-manager/settings');
})->middleware(['auth', 'admin']);

$router->post('/admin/plugins/vpn-manager/jobs/check-expirations', static function () use ($vpnRedirect): void {
    try {
        $result = (new VpnCheckExpirationsJob())->handle();
        session()->setFlash('success', sprintf(FireballPluginVpnManager::t('vpn_manager_flash_expiration_check_done'), (int)($result['expired'] ?? 0), (int)($result['disabled'] ?? 0)));
    } catch (Throwable $exception) {
        log_error_details('VPN Manager expiration queue failed', [], $exception);
        session()->setFlash('error', $exception->getMessage());
    }

    $vpnRedirect('/admin/plugins/vpn-manager/subscriptions');
})->middleware(['auth', 'admin']);

$router->post('/admin/plugins/vpn-manager/jobs/sync-traffic', static function () use ($vpnRedirect): void {
    try {
        $result = (new VpnSyncTrafficJob())->handle();
        session()->setFlash(
            empty($result['failed']) ? 'success' : 'warning',
            empty($result['failed'])
                ? sprintf(FireballPluginVpnManager::t('vpn_manager_flash_traffic_sync_done'), (int)($result['synced'] ?? 0))
                : FireballPluginVpnManager::t('vpn_manager_flash_traffic_sync_failed')
        );
    } catch (Throwable $exception) {
        log_error_details('VPN Manager traffic sync failed', [], $exception);
        session()->setFlash('error', FireballPluginVpnManager::t('vpn_manager_flash_traffic_sync_failed'));
    }

    $vpnRedirect('/admin/plugins/vpn-manager/connections');
})->middleware(['auth', 'admin']);

$router->post('/admin/plugins/vpn-manager/jobs/check-traffic-limits', static function () use ($vpnRedirect): void {
    try {
        $result = (new VpnCheckTrafficLimitsJob())->handle();
        session()->setFlash('success', sprintf(FireballPluginVpnManager::t('vpn_manager_flash_traffic_limits_done'), (int)($result['exceeded'] ?? 0), (int)($result['disabled'] ?? 0)));
    } catch (Throwable $exception) {
        log_error_details('VPN Manager traffic limits failed', [], $exception);
        session()->setFlash('error', $exception->getMessage());
    }

    $vpnRedirect('/admin/plugins/vpn-manager/subscriptions');
})->middleware(['auth', 'admin']);

$router->post('/admin/plugins/vpn-manager/jobs/send-expiration-notifications', static function () use ($vpnRedirect): void {
    try {
        $result = (new VpnSendExpirationNotificationsJob())->handle();
        session()->setFlash('success', sprintf(FireballPluginVpnManager::t('vpn_manager_flash_notifications_sent'), (int)($result['sent'] ?? 0), (int)($result['failed'] ?? 0)));
    } catch (Throwable $exception) {
        log_error_details('VPN Manager expiration notifications failed', [], $exception);
        session()->setFlash('error', $exception->getMessage());
    }

    $vpnRedirect('/admin/plugins/vpn-manager/subscriptions');
})->middleware(['auth', 'admin']);

$router->post('/admin/plugins/vpn-manager/jobs/send-traffic-notifications', static function () use ($vpnRedirect): void {
    try {
        $result = (new VpnSendTrafficNotificationsJob())->handle();
        session()->setFlash('success', sprintf(FireballPluginVpnManager::t('vpn_manager_flash_notifications_sent'), (int)($result['sent'] ?? 0), (int)($result['failed'] ?? 0)));
    } catch (Throwable $exception) {
        log_error_details('VPN Manager traffic notifications failed', [], $exception);
        session()->setFlash('error', $exception->getMessage());
    }

    $vpnRedirect('/admin/plugins/vpn-manager/subscriptions');
})->middleware(['auth', 'admin']);
