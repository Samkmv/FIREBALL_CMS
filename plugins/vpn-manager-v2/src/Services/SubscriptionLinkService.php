<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\Repositories\SubscriptionRepository;

final class SubscriptionLinkService
{
    public function rotate(int $subscriptionId, string $expectedHash, ?int $adminId): void
    {
        if (preg_match('/\A[a-f0-9]{64}\z/D', $expectedHash) !== 1) {
            throw new \InvalidArgumentException('subscription_link_changed');
        }
        $before = (new SubscriptionRepository())->rotateAccessToken($subscriptionId, $expectedHash, $adminId);
        // Access is revoked in the database before removing optional cached renders.
        // Cache failure must not misreport a committed token change as a failed operation.
        try {
            (new VpnSubscriptionCache())->invalidate((string)$before['subscription_token'], (int)$before['revision']);
            (new QrCodeService())->invalidateToken((string)$before['subscription_token']);
        } catch (\Throwable $exception) {
            error_log('VPN Manager access link cache cleanup: ' . $exception::class);
        }
    }
}
