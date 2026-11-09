<?php

namespace Fireball\VpnManagerV2\Jobs;

use Fireball\VpnManagerV2\Services\ExternalVpnSourceService;

final class VpnV2SyncExternalSourcesJob
{
    public function handle(): array
    {
        return (new ExternalVpnSourceService())->syncAll(5);
    }
}
