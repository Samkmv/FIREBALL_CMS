<?php

namespace Fireball\VpnManager\Jobs;

final class VpnSyncTrafficJob
{
    public function handle(): array
    {
        return ['synced' => 0, 'message' => 'Traffic sync is prepared for the next integration stage.'];
    }
}
