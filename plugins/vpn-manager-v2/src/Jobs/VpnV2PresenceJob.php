<?php

namespace Fireball\VpnManagerV2\Jobs;

use Fireball\VpnManagerV2\Services\SubscriptionPresenceService;

final class VpnV2PresenceJob
{
    public function handle(): array
    {
        return (new SubscriptionPresenceService())->snapshot()['summary'];
    }
}
