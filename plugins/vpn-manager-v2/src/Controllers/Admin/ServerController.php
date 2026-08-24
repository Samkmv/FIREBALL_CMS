<?php

namespace Fireball\VpnManagerV2\Controllers\Admin;

use Fireball\VpnManagerV2\Exceptions\VpnManagerV2Exception;
use Fireball\VpnManagerV2\Repositories\ServerRepository;
use Fireball\VpnManagerV2\Services\ServerConnectionService;
use Fireball\VpnManagerV2\Services\ServerManagerService;
use Fireball\VpnManagerV2\Services\ServerMetricsService;
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

    public function metrics(): never
    {
        Permissions::authorize(Permissions::VIEW);
        $id = (int)get_route_param('id');
        try {
            $this->json((new ServerMetricsService())->fetch($id));
        } catch (VpnManagerV2Exception $exception) {
            $this->json(['error' => $exception->getMessage()], 502);
        } catch (\Throwable $exception) {
            log_error_details('VPN Manager V2 server metrics failed', ['Server' => $id], $exception);
            $this->json(['error' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_server_metrics_generic')], 502);
        }
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

    public function delete(): void
    {
        Permissions::authorize(Permissions::MANAGE_SERVERS);

        $id = (int)request()->post('id');
        $expected = 'delete_vpn_server_' . $id;
        if ($id <= 0 || !hash_equals($expected, trim((string)request()->post('confirmation', '')))) {
            session()->setFlash('error', \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_server_delete_confirmation'));
            $this->redirect('/admin/plugins/vpn-manager-v2/servers');
        }

        try {
            $name = (new ServerManagerService())->delete($id);
            session()->setFlash('success', sprintf(
                \FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_server_deleted'),
                $name
            ));
        } catch (VpnManagerV2Exception $exception) {
            session()->setFlash('error', $exception->getMessage());
        } catch (\Throwable $exception) {
            log_error_details('VPN Manager V2 server delete failed', ['Server' => $id], $exception);
            session()->setFlash('error', \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_server_delete_generic'));
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

    private function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        exit;
    }
}
