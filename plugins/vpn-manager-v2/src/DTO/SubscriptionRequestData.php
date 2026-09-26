<?php

namespace Fireball\VpnManagerV2\DTO;

final readonly class SubscriptionRequestData
{
    public function __construct(
        public ?int $userId,
        public ?string $manualCustomerName,
        public int $planId,
        public string $startsAt,
        public ?string $expiresAt = null,
        public bool $lifetime = false,
    ) {
    }
}
