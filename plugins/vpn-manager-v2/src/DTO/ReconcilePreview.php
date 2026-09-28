<?php

namespace Fireball\VpnManagerV2\DTO;

final readonly class ReconcilePreview
{
    public function __construct(
        public int $planId,
        public int $subscriptionsChecked,
        public int $missingConnections,
        public int $obsoleteConnections,
        public int $matchingSubscriptions,
        public array $unavailableServers = [],
        public array $disabledInbounds = [],
        public array $conflicts = [],
        public array $parameterCounts = [],
        public array $details = [],
        public array $errors = [],
        public ?string $checkedAt = null,
    ) {
    }

    public function hasDifferences(): bool
    {
        return $this->missingConnections > 0
            || $this->obsoleteConnections > 0
            || $this->matchingSubscriptions < $this->subscriptionsChecked
            || $this->unavailableServers !== []
            || $this->disabledInbounds !== []
            || $this->conflicts !== []
            || $this->errors !== []
            || array_sum($this->parameterCounts) > 0;
    }
}
