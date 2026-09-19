<?php

namespace Fireball\VpnManagerV2\Controllers\Admin;

use Fireball\VpnManagerV2\Exceptions\VpnManagerV2Exception;
use Fireball\VpnManagerV2\Repositories\SubscriptionRepository;
use Fireball\VpnManagerV2\Services\ConnectionEditingService;
use Fireball\VpnManagerV2\Services\VpnPlanSubscriptionReconciler;
use Fireball\VpnManagerV2\Services\VpnFlowResolver;
use Fireball\VpnManagerV2\Support\Permissions;
use Fireball\VpnManagerV2\Support\TrafficFormatter;

final class ConnectionController
{
    public function index(): string
    {
        Permissions::authorize(Permissions::VIEW);

        return plugin_view(\FireballPluginVpnManagerV2::SLUG, 'admin/connections', \FireballPluginVpnManagerV2::viewData('connections', [
            'title' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_connections_title'),
            'subtitle' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_connections_subtitle'),
            'connections' => (new SubscriptionRepository())->connections(),
        ]));
    }

    public function show(): string
    {
        Permissions::authorize(Permissions::VIEW);

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

    public function edit(): string
    {
        Permissions::authorize(Permissions::RECONCILE);

        $connection = (new SubscriptionRepository())->connection((int)get_route_param('id'));
        if (!$connection) {
            abort('', 404);
        }
        $allowedFlows = (new VpnFlowResolver())->allowedFlows($connection);

        return plugin_view(\FireballPluginVpnManagerV2::SLUG, 'admin/connection-edit', \FireballPluginVpnManagerV2::viewData('connections', [
            'title' => sprintf(\FireballPluginVpnManagerV2::t('vpn_manager_v2_connection_edit_title'), (int)$connection['id']),
            'subtitle' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_connection_edit_subtitle'),
            'connection' => $connection,
            'allowedFlows' => $allowedFlows,
            'trafficInput' => TrafficFormatter::inputParts(
                isset($connection['traffic_limit_bytes']) ? (int)$connection['traffic_limit_bytes'] : null
            ),
        ]));
    }

    public function update(): void
    {
        Permissions::authorize(Permissions::RECONCILE);

        $nodeId = (int)get_route_param('id');
        try {
            $result = (new ConnectionEditingService())->update($nodeId, request()->getData(), $this->adminId());
            session()->setFlash($result->changed ? 'success' : 'info', \FireballPluginVpnManagerV2::t(
                $result->changed ? 'vpn_manager_v2_flash_connection_updated' : 'vpn_manager_v2_flash_no_changes'
            ));
        } catch (VpnManagerV2Exception $exception) {
            session()->setFlash('error', $exception->getMessage());
            $this->redirect('/admin/plugins/vpn-manager-v2/connections/' . $nodeId . '/edit');
        } catch (\Throwable $exception) {
            error_log('VPN Manager V2 connection update failed: ' . get_class($exception));
            session()->setFlash('error', \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_sync_generic'));
            $this->redirect('/admin/plugins/vpn-manager-v2/connections/' . $nodeId . '/edit');
        }

        $this->redirect('/admin/plugins/vpn-manager-v2/connections/' . $nodeId);
    }

    public function sync(): void
    {
        Permissions::authorize(Permissions::RECONCILE);
        $nodeId = (int)get_route_param('id');
        $mode = strtolower(trim((string)request()->post('mode', request()->get('mode', ''))));
        try {
            $service = new ConnectionEditingService();
            $result = match ($mode) {
                'pull' => $service->receiveFromRemote($nodeId, $this->adminId()),
                'push' => $service->sendToRemote($nodeId, $this->adminId()),
                default => throw new \Fireball\VpnManagerV2\Exceptions\ValidationException(
                    \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_sync_mode')
                ),
            };
            session()->setFlash('success', \FireballPluginVpnManagerV2::t(
                $mode === 'pull' ? 'vpn_manager_v2_flash_sync_pulled' : 'vpn_manager_v2_flash_sync_pushed'
            ));
        } catch (VpnManagerV2Exception $exception) {
            session()->setFlash('error', $exception->getMessage());
        } catch (\Throwable $exception) {
            error_log('VPN Manager V2 connection sync failed: ' . get_class($exception));
            session()->setFlash('error', \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_sync_generic'));
        }

        $this->redirect('/admin/plugins/vpn-manager-v2/connections/' . $nodeId);
    }

    public function retry(): void
    {
        Permissions::authorize(Permissions::CREATE_CONNECTIONS);
        Permissions::authorize(Permissions::RECONCILE);
        $nodeId = (int)get_route_param('id');
        try {
            $result = (new VpnPlanSubscriptionReconciler())->retryFailedNode($nodeId);
            if ($result->flowError) {
                session()->setFlash('error', \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_client_flow_not_saved'));
            } elseif (!$result->successful()) {
                session()->setFlash('error', \FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_connection_retry_failed'));
            } else {
                session()->setFlash('success', \FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_create_missing_success'));
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

    private function adminId(): int
    {
        $user = get_user();

        return is_array($user) ? (int)($user['id'] ?? 0) : 0;
    }
}
