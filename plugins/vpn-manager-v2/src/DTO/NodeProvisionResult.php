<?php

namespace Fireball\VpnManagerV2\DTO;

final readonly class NodeProvisionResult
{
    public function __construct(
        public int $subscriptionId,
        public int $nodeId,
        public string $status,
        public bool $created = false,
        public bool $reused = false,
        public bool $changed = false,
        public bool $flowError = false,
    ) {
    }

    public function successful(): bool
    {
        return in_array($this->status, ['active', 'disabled', 'created', 'reused'], true);
    }
}
