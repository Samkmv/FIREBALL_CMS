<?php

use Fireball\VpnManager\Clients\ThreeXuiClient;
use Fireball\VpnManager\Services\NotificationScheduler;
use Fireball\VpnManager\Services\SettingsService;

/** @var \FBL\Router $router */

$vpnRedirect = static function (string $path = '/admin/plugins/vpn-manager'): void {
    response()->redirect(base_href($path));
};

$vpnRepo = static fn() => FireballPluginVpnManager::repository();

$router->get('/admin/plugins/vpn-manager', static function () use ($vpnRepo): string {
    $repo = $vpnRepo();

    return plugin_view('vpn-manager', 'dashboard', FireballPluginVpnManager::viewData('dashboard', [
        'title' => FireballPluginVpnManager::t('vpn_manager_dashboard_title'),
        'subtitle' => FireballPluginVpnManager::t('vpn_manager_dashboard_subtitle'),
        'stats' => $repo->dashboardStats(),
        'subscriptions' => $repo->subscriptions(8),
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
        session()->setFlash(!empty($result['success']) ? 'success' : 'error', (string)($result['message'] ?? ''));
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
        session()->setFlash('success', sprintf(FireballPluginVpnManager::t('vpn_manager_flash_inbounds_synced'), $count));
    } catch (Throwable $exception) {
        log_error_details('VPN Manager inbound sync failed', ['Server' => $id], $exception);
        session()->setFlash('error', $exception->getMessage());
    }

    $vpnRedirect('/admin/plugins/vpn-manager/inbounds');
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
        'servers' => $vpnRepo()->servers(),
        'selectedServers' => [],
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
        'servers' => $vpnRepo()->servers(),
        'selectedServers' => $vpnRepo()->planServerIds($id),
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

$router->get('/admin/plugins/vpn-manager/connections', static function () use ($vpnRepo): string {
    return plugin_view('vpn-manager', 'connections', FireballPluginVpnManager::viewData('connections', [
        'title' => FireballPluginVpnManager::t('vpn_manager_connections_title'),
        'subtitle' => FireballPluginVpnManager::t('vpn_manager_connections_subtitle'),
        'connections' => $vpnRepo()->connections(),
    ]));
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
        $result = (new NotificationScheduler())->queueExpirationNotifications();
        session()->setFlash('success', sprintf(FireballPluginVpnManager::t('vpn_manager_flash_expiration_queue_done'), (int)($result['created'] ?? 0)));
    } catch (Throwable $exception) {
        log_error_details('VPN Manager expiration queue failed', [], $exception);
        session()->setFlash('error', $exception->getMessage());
    }

    $vpnRedirect('/admin/plugins/vpn-manager/subscriptions');
})->middleware(['auth', 'admin']);
