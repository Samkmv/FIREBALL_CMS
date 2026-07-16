<?php

namespace Fireball\VpnManagerV2\DTO;

final readonly class ReconcileResult
{
    public function __construct(
        public int $planId,
        public int $subscriptionsChecked = 0,
        public int $created = 0,
        public int $reused = 0,
        public int $failed = 0,
        public int $syncErrors = 0,
        public int $skipped = 0,
        public int $obsolete = 0,
        public int $changedSubscriptions = 0,
        public ?string $operationId = null,
        public bool $queued = false,
        public int $removed = 0,
    ) {
    }

    public function successful(): bool
    {
        return $this->failed === 0 && $this->syncErrors === 0;
    }

    public function changed(): bool
    {
        return $this->created > 0 || $this->reused > 0 || $this->removed > 0
            || $this->changedSubscriptions > 0;
    }

    public function noChanges(): bool
    {
        return !$this->queued && !$this->changed() && $this->failed === 0 && $this->syncErrors === 0;
    }
}
