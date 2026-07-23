<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\DTO\MigrationStatus;
use Fireball\VpnManagerV2\Repositories\MigrationStatusRepository;

final class MigrationStatusService
{
    public function __construct(private readonly ?MigrationStatusRepository $repository = null)
    {
    }

    public function status(): MigrationStatus
    {
        $repository = $this->repository ?? new MigrationStatusRepository();
        $expected = $repository->expectedTables();
        $present = $repository->presentTables();

        return new MigrationStatus(
            expectedTables: $expected,
            presentTables: $present,
            missingTables: $repository->missingTables(),
            migrations: $repository->migrations(),
        );
    }
}
