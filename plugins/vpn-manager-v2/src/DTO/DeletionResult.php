<?php

namespace Fireball\VpnManagerV2\DTO;

final readonly class DeletionResult
{
    public function __construct(
        public int $subscriptionId,
        public int $deletedNodes,
        public int $alreadyAbsentNodes,
        public int $failedNodes,
        public bool $deleted,
        public bool $alreadyDeleted = false,
    ) {
    }

    public function successful(): bool
    {
        return $this->deleted && $this->failedNodes === 0;
    }
}
