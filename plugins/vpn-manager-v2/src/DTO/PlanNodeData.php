<?php

namespace Fireball\VpnManagerV2\DTO;

final readonly class PlanNodeData
{
    public function __construct(
        public int $serverId,
        public int $inboundId,
        public ?string $flowOverride,
        public int $sortOrder,
    ) {
    }
}
