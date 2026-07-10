<?php

namespace Fireball\VpnManager\Jobs;

final class VpnRetryFailedOperationsJob
{
    public function handle(): array
    {
        return ['retried' => 0, 'message' => 'Retry queue is prepared for the next integration stage.'];
    }
}
