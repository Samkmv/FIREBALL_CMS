<?php

namespace Fireball\VpnManagerV2\DTO;

final readonly class PlanUpdateResult
{
    public function __construct(
        public int $planId,
        public array $addedPlanNodes,
        public array $removedPlanNodes,
        public array $unchangedPlanNodes,
        public array $changedPlanNodes,
        public int $affectedSubscriptions = 0,
        public ?ReconcileResult $reconciliation = null,
    ) {
    }

    public function hasAddedNodes(): bool
    {
        return $this->addedPlanNodes !== [];
    }
}
