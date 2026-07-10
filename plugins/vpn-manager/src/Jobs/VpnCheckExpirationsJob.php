<?php

namespace Fireball\VpnManager\Jobs;

use Fireball\VpnManager\Services\NotificationScheduler;

final class VpnCheckExpirationsJob
{
    public function handle(): array
    {
        return (new NotificationScheduler())->queueExpirationNotifications();
    }
}
