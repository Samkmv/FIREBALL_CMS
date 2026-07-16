<?php

namespace Fireball\VpnManagerV2\Jobs;

use Fireball\VpnManagerV2\Services\SubscriptionAutomationService;

final class VpnV2CheckTrafficLimitsJob
{
    public function handle(): array
    {
        return (new SubscriptionAutomationService())->checkTrafficLimits();
    }
}
