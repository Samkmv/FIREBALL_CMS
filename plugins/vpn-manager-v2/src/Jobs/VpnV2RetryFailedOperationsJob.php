<?php

namespace Fireball\VpnManagerV2\Jobs;

use Fireball\VpnManagerV2\Services\RetryFailedOperationsService;

final class VpnV2RetryFailedOperationsJob
{
    public function handle(): array
    {
        return (new RetryFailedOperationsService())->retry();
    }
}
