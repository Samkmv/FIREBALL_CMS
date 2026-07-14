<?php

namespace Fireball\VpnManagerV2\DTO;

final readonly class SubscriptionEndpointResponse
{
    public function __construct(
        public int $status,
        public string $body,
        public array $headers,
        public int $configCount = 0,
        public bool $cacheHit = false,
    ) {
    }
}
