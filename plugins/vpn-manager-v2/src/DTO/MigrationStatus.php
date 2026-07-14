<?php

namespace Fireball\VpnManagerV2\DTO;

final readonly class MigrationStatus
{
    public function __construct(
        public array $expectedTables,
        public array $presentTables,
        public array $missingTables,
        public array $migrations,
    ) {
    }

    public function isReady(): bool
    {
        return $this->missingTables === [] && $this->migrations !== [];
    }

    public function toArray(): array
    {
        return [
            'expected_tables' => $this->expectedTables,
            'present_tables' => $this->presentTables,
            'missing_tables' => $this->missingTables,
            'migrations' => $this->migrations,
            'is_ready' => $this->isReady(),
        ];
    }
}
