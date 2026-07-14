<?php

namespace Fireball\VpnManagerV2\Controllers\Admin;

use Fireball\VpnManagerV2\Services\MigrationStatusService;

final class OverviewController
{
    public function index(): string
    {
        $migrationStatus = (new MigrationStatusService())->status();

        return plugin_view(\FireballPluginVpnManagerV2::SLUG, 'admin/overview', \FireballPluginVpnManagerV2::viewData('overview', [
            'title' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_title'),
            'subtitle' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_subtitle'),
            'migrationStatus' => $migrationStatus->toArray(),
            'permissions' => \FireballPluginVpnManagerV2::permissions(),
        ]));
    }
}
