<?php

namespace Fireball\VpnManagerV2\Controllers\Admin;

use Fireball\VpnManagerV2\Services\SubscriptionPresenceService;
use Fireball\VpnManagerV2\Support\Permissions;

final class OnlineController
{
    public function index(): string
    {
        Permissions::authorize(Permissions::VIEW);
        return plugin_view(\FireballPluginVpnManagerV2::SLUG, 'admin/online',
            \FireballPluginVpnManagerV2::viewData('online', [
                'title' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_tab_online'),
                'subtitle' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_online_subtitle'),
            ]));
    }

    public function data(): never
    {
        Permissions::authorize(Permissions::VIEW);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, no-store');
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        try {
            $snapshot = (new SubscriptionPresenceService())->snapshot();
            $data = ['html' => plugin_view(\FireballPluginVpnManagerV2::SLUG, 'admin/partials/online-results',
                ['snapshot' => $snapshot], false)];
        } catch (\Throwable $exception) {
            error_log('VPN online list failed: ' . $exception::class);
            http_response_code(503);
            $data = ['error' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_online_failed')];
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        exit;
    }
}
