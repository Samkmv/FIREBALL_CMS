<?php

namespace Fireball\VpnManagerV2\DTO;

final readonly class SubscriptionRequestData
{
    public function __construct(
        public int $userId,
        public int $planId,
        public string $startsAt,
    ) {
    }
}
