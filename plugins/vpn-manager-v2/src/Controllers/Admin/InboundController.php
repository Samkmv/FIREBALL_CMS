<?php

namespace Fireball\VpnManagerV2\Controllers\Admin;

use Fireball\VpnManagerV2\Exceptions\VpnManagerV2Exception;
use Fireball\VpnManagerV2\Repositories\InboundRepository;
use Fireball\VpnManagerV2\Repositories\ServerRepository;
use Fireball\VpnManagerV2\Services\InboundSyncService;
use Fireball\VpnManagerV2\Support\Permissions;

final class InboundController
{
    public function index(): string
    {
        Permissions::authorize(Permissions::VIEW);

        return plugin_view(\FireballPluginVpnManagerV2::SLUG, 'admin/inbounds', \FireballPluginVpnManagerV2::viewData('inbounds', [
            'title' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_inbounds_title'),
            'subtitle' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_inbounds_subtitle'),
            'inbounds' => (new InboundRepository())->all(),
            'servers' => (new ServerRepository())->all(),
        ]));
    }

    public function sync(): void
    {
        Permissions::authorize(Permissions::MANAGE_INBOUNDS);

        $serverId = (int)request()->post('server_id');
        try {
            $result = (new InboundSyncService())->sync($serverId);
            session()->setFlash('success', sprintf(
                \FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_inbounds_synced'),
                $result->received,
                $result->created,
                $result->updated,
                $result->missing,
            ));
        } catch (VpnManagerV2Exception $exception) {
            session()->setFlash('error', $exception->getMessage());
        } catch (\Throwable $exception) {
            log_error_details('VPN Manager V2 inbound sync failed', ['Server' => $serverId], $exception);
            session()->setFlash('error', \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_inbound_sync_generic'));
        }

        response()->redirect(base_href('/admin/plugins/vpn-manager-v2/inbounds'));
    }
}
