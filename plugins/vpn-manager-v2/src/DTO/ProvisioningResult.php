<?php

namespace Fireball\VpnManagerV2\DTO;

final readonly class ProvisioningResult
{
    public function __construct(
        public int $subscriptionId,
        public int $created,
        public int $reused,
        public int $failed,
        public int $syncErrors,
        public bool $flowError,
        public string $status,
    ) {
    }

    public function successful(): bool
    {
        return $this->status === 'active' && $this->failed === 0 && $this->syncErrors === 0;
    }
}
