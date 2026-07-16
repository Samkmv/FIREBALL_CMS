<?php

namespace Fireball\VpnManagerV2\DTO;

final readonly class SubscriptionEditData
{
    public function __construct(
        public ?string $expiresAt,
        public ?int $trafficLimitBytes,
        public string $status,
        public ?string $internalComment,
    ) {
    }

    public function toArray(): array
    {
        return [
            'expires_at' => $this->expiresAt,
            'traffic_limit_bytes' => $this->trafficLimitBytes,
            'status' => $this->status,
            'internal_comment' => $this->internalComment,
        ];
    }
}
