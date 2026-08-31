<?php

namespace Fireball\Calendar\Jobs;

use Fireball\Calendar\Services\ReminderDispatchService;

final class DispatchRemindersJob
{
    public function handle(): array
    {
        return (new ReminderDispatchService())->run();
    }

    public function __invoke(): array
    {
        return $this->handle();
    }
}
