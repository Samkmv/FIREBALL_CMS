<?php

namespace Fireball\VpnManager\Jobs;

use Fireball\VpnManager\Services\NotificationScheduler;

final class VpnSendExpirationNotificationsJob
{
    public function handle(): array
    {
        return (new NotificationScheduler())->sendPending();
    }
}
