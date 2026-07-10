<?php

namespace Fireball\VpnManager\Jobs;

use Fireball\VpnManager\Services\NotificationScheduler;

final class VpnSendTrafficNotificationsJob
{
    public function handle(): array
    {
        $scheduler = new NotificationScheduler();
        $queued = $scheduler->queueTrafficNotifications();
        $sent = $scheduler->sendPending(['traffic_80', 'traffic_100']);

        return array_merge(['queued' => (int)($queued['created'] ?? 0)], $sent);
    }
}
