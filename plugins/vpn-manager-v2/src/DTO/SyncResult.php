<?php

namespace Fireball\VpnManagerV2\DTO;

final readonly class SyncResult
{
    public function __construct(
        public int $subscriptionId,
        public int $synced,
        public int $failed,
        public int $revision,
        public bool $changed,
        public bool $remoteRequest,
    ) {
    }

    public function successful(): bool
    {
        return $this->failed === 0;
    }
}
