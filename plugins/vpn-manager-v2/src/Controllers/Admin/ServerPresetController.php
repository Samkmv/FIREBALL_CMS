<?php

namespace Fireball\VpnManagerV2\Controllers\Admin;

use Fireball\VpnManagerV2\Exceptions\VpnManagerV2Exception;
use Fireball\VpnManagerV2\Repositories\PlanRepository;
use Fireball\VpnManagerV2\Repositories\ServerPresetRepository;
use Fireball\VpnManagerV2\Services\ServerPresetService;
use Fireball\VpnManagerV2\Support\Permissions;

final class ServerPresetController
{
    private const PATH = '/admin/plugins/vpn-manager-v2/servers/presets';

    public function index(): string
    {
        Permissions::authorize(Permissions::VIEW);
        return plugin_view(\FireballPluginVpnManagerV2::SLUG, 'admin/server-presets', \FireballPluginVpnManagerV2::viewData('servers', [
            'title' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_presets_title'),
            'presets' => (new ServerPresetRepository())->all(),
        ]));
    }

    public function form(): string
    {
        $this->authorizeChanges();
        $id = (int)get_route_param('id');
        $presets = new ServerPresetRepository();
        $preset = $id > 0 ? $presets->find($id) : null;
        if ($id > 0 && !$preset) {
            abort('', 404);
        }
        $plans = new PlanRepository();
        return plugin_view(\FireballPluginVpnManagerV2::SLUG, 'admin/server-preset-form', \FireballPluginVpnManagerV2::viewData('servers', [
            'title' => \FireballPluginVpnManagerV2::t($preset ? 'vpn_manager_v2_preset_edit' : 'vpn_manager_v2_preset_add'),
            'preset' => $preset,
            'selectedNodes' => $preset ? $presets->nodes($id) : [],
            'linkedPlans' => $preset ? $presets->plans($id) : [],
            'servers' => $plans->serversForForm(),
            'inbounds' => $plans->inboundsForForm(),
        ]));
    }

    public function save(): never
    {
        $this->authorizeChanges();
        $id = (int)get_route_param('id');
        $destination = self::PATH;
        try {
            $result = (new ServerPresetService())->save($id, request()->getData(), $this->adminId());
            $message = \FireballPluginVpnManagerV2::t('vpn_manager_v2_preset_saved');
            if ($result['plans'] > 0) {
                $message .= ' ' . sprintf(\FireballPluginVpnManagerV2::t('vpn_manager_v2_preset_applied'), $result['plans'], $result['queued']);
            }
            session()->setFlash('success', $message);
        } catch (VpnManagerV2Exception $exception) {
            session()->setFlash('error', $exception->getMessage());
            $destination .= $id > 0 ? '/' . $id : '/create';
        } catch (\Throwable $exception) {
            log_error_details('VPN server preset save failed', ['Preset' => $id], $exception);
            session()->setFlash('error', \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_save_generic'));
            $destination .= $id > 0 ? '/' . $id : '/create';
        }
        response()->redirect(base_href($destination));
    }

    public function delete(): never
    {
        $this->authorizeChanges();
        $id = (int)get_route_param('id');
        try {
            (new ServerPresetService())->delete($id, $this->adminId());
            session()->setFlash('success', \FireballPluginVpnManagerV2::t('vpn_manager_v2_preset_deleted'));
        } catch (VpnManagerV2Exception $exception) {
            session()->setFlash('error', $exception->getMessage());
        } catch (\Throwable $exception) {
            log_error_details('VPN server preset removal failed', ['Preset' => $id], $exception);
            session()->setFlash('error', \FireballPluginVpnManagerV2::t('vpn_manager_v2_preset_delete_error'));
        }
        response()->redirect(base_href(self::PATH));
    }

    private function authorizeChanges(): void
    {
        Permissions::authorize(Permissions::MANAGE_SERVERS);
        Permissions::authorize(Permissions::MANAGE_PLANS);
    }

    private function adminId(): int
    {
        return (int)(get_user()['id'] ?? 0);
    }
}
