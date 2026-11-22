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

    public static function fromRow(array $row): self
    {
        return new self((int)$row['server_id'], (int)$row['inbound_id'], $row['flow_override'], (int)$row['sort_order']);
    }
}
