<?php

namespace Fireball\VpnManagerV2\Jobs;

use Fireball\VpnManagerV2\Services\ServerHealthMonitorService;
use Fireball\VpnManagerV2\Services\SettingsService;

final class VpnV2ServerHealthJob
{
    public function handle(): array
    {
        return (new ServerHealthMonitorService())->runDue((new SettingsService())->current());
    }
}
