<?php

namespace Fireball\VpnManagerV2\Jobs;

use Fireball\VpnManagerV2\Services\RetryFailedOperationsService;
use Fireball\VpnManagerV2\Services\RemoteOperationProcessor;

final class VpnV2RetryFailedOperationsJob
{
    public function handle(): array
    {
        return [
            'legacy' => (new RetryFailedOperationsService())->retry(),
            'queue' => (new RemoteOperationProcessor())->processNext(['delete_client', 'reset_traffic']),
        ];
    }
}
