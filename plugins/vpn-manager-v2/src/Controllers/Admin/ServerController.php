<?php

namespace Fireball\VpnManagerV2\Controllers\Admin;

use Fireball\VpnManagerV2\Exceptions\VpnManagerV2Exception;
use Fireball\VpnManagerV2\Repositories\ServerRepository;
use Fireball\VpnManagerV2\Services\ServerConnectionService;
use Fireball\VpnManagerV2\Services\ServerManagerService;
use Fireball\VpnManagerV2\Support\Permissions;

final class ServerController
{
    public function index(): string
    {
        Permissions::authorize(Permissions::VIEW);

        return plugin_view(\FireballPluginVpnManagerV2::SLUG, 'admin/servers', \FireballPluginVpnManagerV2::viewData('servers', [
            'title' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_servers_title'),
            'subtitle' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_servers_subtitle'),
            'servers' => (new ServerRepository())->all(),
        ]));
    }

    public function create(): string
    {
        Permissions::authorize(Permissions::MANAGE_SERVERS);

        return $this->form(null);
    }

    public function store(): void
    {
        Permissions::authorize(Permissions::MANAGE_SERVERS);

        try {
            (new ServerManagerService())->create(request()->getData());
            session()->setFlash('success', \FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_server_created'));
            $this->redirect('/admin/plugins/vpn-manager-v2/servers');
        } catch (VpnManagerV2Exception $exception) {
            session()->setFlash('error', $exception->getMessage());
            $this->redirect('/admin/plugins/vpn-manager-v2/servers/create');
        } catch (\Throwable $exception) {
            log_error_details('VPN Manager V2 server create failed', [], $exception);
            session()->setFlash('error', \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_save_generic'));
            $this->redirect('/admin/plugins/vpn-manager-v2/servers/create');
        }
    }

    public function edit(): string
    {
        Permissions::authorize(Permissions::MANAGE_SERVERS);

        $server = (new ServerRepository())->find((int)get_route_param('id'));
        if (!$server) {
            abort('', 404);
        }

        return $this->form($server);
    }

    public function update(): void
    {
        Permissions::authorize(Permissions::MANAGE_SERVERS);

        $id = (int)get_route_param('id');
        try {
            (new ServerManagerService())->update($id, request()->getData());
            session()->setFlash('success', \FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_server_updated'));
            $this->redirect('/admin/plugins/vpn-manager-v2/servers');
        } catch (VpnManagerV2Exception $exception) {
            session()->setFlash('error', $exception->getMessage());
            $this->redirect('/admin/plugins/vpn-manager-v2/servers/edit/' . $id);
        } catch (\Throwable $exception) {
            log_error_details('VPN Manager V2 server update failed', ['Server' => $id], $exception);
            session()->setFlash('error', \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_save_generic'));
            $this->redirect('/admin/plugins/vpn-manager-v2/servers/edit/' . $id);
        }
    }

    public function test(): void
    {
        Permissions::authorize(Permissions::MANAGE_SERVERS);

        $id = (int)request()->post('id');
        $result = (new ServerConnectionService())->test($id);
        session()->setFlash($result->success ? 'success' : 'error', $result->message);
        $this->redirect('/admin/plugins/vpn-manager-v2/servers');
    }

    public function toggle(): void
    {
        Permissions::authorize(Permissions::MANAGE_SERVERS);

        $id = (int)request()->post('id');
        try {
            $enabled = (new ServerManagerService())->toggle($id);
            session()->setFlash(
                'success',
                \FireballPluginVpnManagerV2::t($enabled ? 'vpn_manager_v2_flash_server_enabled' : 'vpn_manager_v2_flash_server_disabled')
            );
        } catch (VpnManagerV2Exception $exception) {
            session()->setFlash('error', $exception->getMessage());
        } catch (\Throwable $exception) {
            log_error_details('VPN Manager V2 server toggle failed', ['Server' => $id], $exception);
            session()->setFlash('error', \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_toggle_generic'));
        }

        $this->redirect('/admin/plugins/vpn-manager-v2/servers');
    }

    private function form(?array $server): string
    {
        $editing = $server !== null;

        return plugin_view(\FireballPluginVpnManagerV2::SLUG, 'admin/server-form', \FireballPluginVpnManagerV2::viewData('servers', [
            'title' => \FireballPluginVpnManagerV2::t($editing ? 'vpn_manager_v2_server_edit_title' : 'vpn_manager_v2_server_create_title'),
            'subtitle' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_server_form_subtitle'),
            'server' => $server,
        ]));
    }

    private function redirect(string $path): never
    {
        response()->redirect(base_href($path));
    }
}
