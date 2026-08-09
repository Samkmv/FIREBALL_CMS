<?php

namespace Fireball\Subscriptions\Jobs;

use Fireball\Subscriptions\Services\MaintenanceService;

final class SubscriptionsMaintenanceJob
{
    public function handle(): array
    {
        return (new MaintenanceService())->run();
    }

    public function __invoke(): array
    {
        return $this->handle();
    }
}
