<?php

namespace Fireball\VpnManager\Jobs;

use Fireball\VpnManager\Services\SubscriptionAutomationService;

final class VpnCheckTrafficLimitsJob
{
    public function handle(): array
    {
        return (new SubscriptionAutomationService())->checkTrafficLimits();
    }
}
