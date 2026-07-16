<?php

namespace Fireball\VpnManagerV2\Jobs;

use Fireball\VpnManagerV2\Services\TrafficSyncService;

final class VpnV2SyncTrafficJob
{
    public function handle(): array
    {
        return (new TrafficSyncService())->syncActiveNodes();
    }
}
