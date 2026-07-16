<?php

namespace Fireball\VpnManagerV2\Controllers\Admin;

use Fireball\VpnManagerV2\Repositories\OverviewRepository;
use Fireball\VpnManagerV2\Services\MigrationStatusService;

final class OverviewController
{
    public function index(): string
    {
        $migrationStatus = (new MigrationStatusService())->status();
        try {
            $overview = (new OverviewRepository())->diagnostics();
        } catch (\Throwable) {
            $overview = OverviewRepository::unavailable();
        }

        return plugin_view(\FireballPluginVpnManagerV2::SLUG, 'admin/overview', \FireballPluginVpnManagerV2::viewData('overview', [
            'title' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_title'),
            'subtitle' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_subtitle'),
            'migrationStatus' => $migrationStatus->toArray(),
            'overview' => $overview,
            'permissions' => \FireballPluginVpnManagerV2::permissions(),
        ]));
    }
}
