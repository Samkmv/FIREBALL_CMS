<?php

namespace Fireball\VpnManagerV2\DTO;

final readonly class ConnectionEditData
{
    public function __construct(
        public ?string $flow,
        public ?int $trafficLimitBytes,
    ) {
    }

    public function toArray(): array
    {
        return [
            'flow' => $this->flow,
            'traffic_limit_bytes' => $this->trafficLimitBytes,
        ];
    }
}
