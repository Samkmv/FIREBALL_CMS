<?php

namespace Fireball\VpnManagerV2\Controllers\Admin;

use Fireball\VpnManagerV2\Services\SubscriptionDeviceService;
use Fireball\VpnManagerV2\Support\Permissions;

final class SubscriptionDeviceController
{
    public function index(): string
    {
        Permissions::authorize(Permissions::VIEW);
        $data = (new SubscriptionDeviceService())->devices((int)get_route_param('id'));
        return plugin_view(\FireballPluginVpnManagerV2::SLUG, 'admin/subscription-devices',
            \FireballPluginVpnManagerV2::viewData('subscriptions', $data + [
                'title' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_devices_title'),
                'subtitle' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_devices_help'),
            ]));
    }

    public function delete(): void { $this->remove((int)get_route_param('device')); }
    public function clear(): void { $this->remove(null); }

    private function remove(?int $deviceId): void
    {
        Permissions::authorize(Permissions::MANAGE_SUBSCRIPTIONS);
        $id = (int)get_route_param('id');
        try {
            (new SubscriptionDeviceService())->remove($id, (int)get_route_param('node'), $deviceId);
            session()->setFlash('success', \FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_devices_removed'));
        } catch (\Throwable $exception) {
            error_log('VPN Manager V2 devices: ' . get_class($exception));
            session()->setFlash('error', \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_devices_generic'));
        }
        response()->redirect(base_href('/admin/plugins/vpn-manager-v2/subscriptions/' . $id . '/devices'));
    }
}
