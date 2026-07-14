<?php

namespace Fireball\VpnManagerV2\DTO;

final readonly class PlanData
{
    public function __construct(
        public string $name,
        public ?string $description,
        public int $durationDays,
        public ?int $trafficLimitBytes,
        public int $deviceLimit,
        public bool $isActive,
        public array $nodes,
    ) {
    }
}
