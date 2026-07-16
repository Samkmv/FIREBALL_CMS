<?php

namespace Fireball\VpnManagerV2\DTO;

final readonly class InboundSyncResult
{
    public function __construct(
        public int $received,
        public int $created,
        public int $updated,
        public int $missing,
        public bool $configChanged = true,
    ) {
    }
}
