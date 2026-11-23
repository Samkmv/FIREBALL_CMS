<?php

namespace Fireball\VpnManagerV2\Jobs;

use Fireball\VpnManagerV2\Services\VpnNotificationService;

final class VpnV2SendExpirationNotificationsJob
{
    public function handle(): array
    {
        $service = new VpnNotificationService();
        $queued = $service->queueExpirationNotifications();
        $sent = $service->dispatch([
            VpnNotificationService::EXPIRES_3_DAYS,
            VpnNotificationService::ADMIN_EXPIRES_3_DAYS,
            VpnNotificationService::ADMIN_EXPIRES_1_DAY,
            VpnNotificationService::EXPIRES_TODAY,
        ]);

        return array_merge($queued, $sent);
    }
}
