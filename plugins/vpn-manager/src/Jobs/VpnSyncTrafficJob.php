<?php

namespace Fireball\VpnManager\Jobs;

use Fireball\VpnManager\Services\TrafficSyncService;

final class VpnSyncTrafficJob
{
    public function handle(): array
    {
        return (new TrafficSyncService())->syncActiveNodes();
    }
}
