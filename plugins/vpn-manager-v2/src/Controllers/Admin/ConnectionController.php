<?php

namespace Fireball\VpnManagerV2\Controllers\Admin;

use Fireball\VpnManagerV2\Exceptions\VpnManagerV2Exception;
use Fireball\VpnManagerV2\Repositories\SubscriptionRepository;
use Fireball\VpnManagerV2\Services\SubscriptionProvisioningService;

final class ConnectionController
{
    public function index(): string
    {
        return plugin_view(\FireballPluginVpnManagerV2::SLUG, 'admin/connections', \FireballPluginVpnManagerV2::viewData('connections', [
            'title' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_connections_title'),
            'subtitle' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_connections_subtitle'),
            'connections' => (new SubscriptionRepository())->connections(),
        ]));
    }

    public function show(): string
    {
        $connection = (new SubscriptionRepository())->connection((int)get_route_param('id'));
        if (!$connection) {
            abort('', 404);
        }

        return plugin_view(\FireballPluginVpnManagerV2::SLUG, 'admin/connection-show', \FireballPluginVpnManagerV2::viewData('connections', [
            'title' => sprintf(\FireballPluginVpnManagerV2::t('vpn_manager_v2_connection_show_title'), (int)$connection['id']),
            'subtitle' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_connection_show_subtitle'),
            'connection' => $connection,
        ]));
    }

    public function retry(): void
    {
        $nodeId = (int)get_route_param('id');
        try {
            $result = (new SubscriptionProvisioningService())->retryNode($nodeId);
            if ($result->flowError) {
                session()->setFlash('error', \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_client_flow_not_saved'));
            } elseif ($result->failed > 0 || $result->syncErrors > 0) {
                session()->setFlash('error', \FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_connection_retry_failed'));
            } else {
                session()->setFlash('success', sprintf(
                    \FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_connection_retry_success'),
                    $result->created,
                    $result->reused
                ));
            }
        } catch (VpnManagerV2Exception $exception) {
            session()->setFlash('error', $exception->getMessage());
        } catch (\Throwable $exception) {
            error_log('VPN Manager V2 connection retry failed: ' . get_class($exception));
            session()->setFlash('error', \FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_connection_retry_failed'));
        }

        $this->redirect('/admin/plugins/vpn-manager-v2/connections/' . $nodeId);
    }

    private function redirect(string $path): never
    {
        response()->redirect(base_href($path));
    }
}
