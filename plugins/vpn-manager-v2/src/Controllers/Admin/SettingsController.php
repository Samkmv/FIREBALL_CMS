<?php

namespace Fireball\VpnManagerV2\Controllers\Admin;

use Fireball\VpnManagerV2\Exceptions\ValidationException;
use Fireball\VpnManagerV2\Services\SettingsService;
use Fireball\VpnManagerV2\Support\Permissions;
use Fireball\VpnManagerV2\Validators\SettingsValidator;

final class SettingsController
{
    public function index(): string
    {
        Permissions::authorize(Permissions::MANAGE_SETTINGS);
        $settings = (new SettingsService())->current();

        return plugin_view(
            \FireballPluginVpnManagerV2::SLUG,
            'admin/settings',
            \FireballPluginVpnManagerV2::viewData('settings', [
                'title' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_settings_title'),
                'subtitle' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_settings_subtitle'),
                'settings' => $settings,
                'templateVariables' => SettingsValidator::TEMPLATE_VARIABLES,
            ])
        );
    }

    public function update(): void
    {
        Permissions::authorize(Permissions::MANAGE_SETTINGS);
        $data = request()->getData();
        $fields = SettingsService::safeFieldNames($data);
        try {
            (new SettingsService())->save($data);
            session()->setFlash('success', \FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_settings_saved'));
        } catch (ValidationException $exception) {
            session()->setFlash('error', $exception->getMessage());
        } catch (\Throwable $exception) {
            log_error_details('VPN Manager V2 settings save failed', [
                'User ID' => (int)(get_user()['id'] ?? 0),
                'Plugin Slug' => \FireballPluginVpnManagerV2::SLUG,
                'Fields' => $fields,
                'Error Class' => get_class($exception),
            ]);
            session()->setFlash('error', \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_settings_save'));
        }

        response()->redirect(base_href('/admin/plugins/vpn-manager-v2/settings'));
    }
}
