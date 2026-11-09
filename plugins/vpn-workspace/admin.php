<?php

use Fireball\VpnManagerV2\Repositories\PlanRepository;
use Fireball\VpnManagerV2\Repositories\ServerRepository;
use Fireball\VpnManagerV2\Repositories\SubscriptionRepository;
use Fireball\VpnManagerV2\Support\Permissions;

/** @var \FBL\Router $router */
$router->get('/admin/plugins/vpn-workspace', static function (): string {
    $sections = ['servers', 'plans', 'subscriptions', 'errors'];
    $section = request()->get('section', 'servers');
    if (!in_array($section, $sections, true)) {
        $section = 'servers';
    }
    $ready = class_exists(Permissions::class);
    $rows = [];
    $loadError = false;
    $search = request()->get('search', '');
    $search = is_string($search) ? mb_substr(trim($search), 0, 120) : '';
    $page = max(1, min(100000, (int)request()->get('page', 1)));

    if ($ready) {
        Permissions::authorize($section === 'errors' ? Permissions::VIEW_EVENTS : Permissions::VIEW);
        try {
            $rows = match ($section) {
                'servers' => (new ServerRepository())->all(),
                'plans' => (new PlanRepository())->all(),
                'subscriptions' => (new SubscriptionRepository())->adminPage($search, 31, ($page - 1) * 30),
                'errors' => db()->query(
                    "SELECT l.created_at, l.operation_type, l.subscription_id, l.server_id,
                            l.status, l.error_code, l.safe_error, s.name AS server_name
                     FROM vpn_v2_sync_logs l
                     LEFT JOIN vpn_v2_servers s ON s.id = l.server_id
                     WHERE (l.error_code IS NOT NULL AND l.error_code <> '')
                        OR (l.safe_error IS NOT NULL AND l.safe_error <> '')
                        OR l.status IN ('failed', 'retry', 'sync_conflict', 'remote_unavailable')
                     ORDER BY l.id DESC LIMIT 50"
                )->get() ?: [],
            };
        } catch (\Throwable $exception) {
            $loadError = true;
            log_error_details('VPN Workspace read failed', ['Section' => $section], $exception);
        }
    }

    $title = return_translation('vpn_workspace_title');

    return plugin_view('vpn-workspace', 'workspace', compact(
        'title', 'section', 'sections', 'ready', 'rows', 'loadError', 'search', 'page'
    ));
})->middleware(['auth', 'admin']);
