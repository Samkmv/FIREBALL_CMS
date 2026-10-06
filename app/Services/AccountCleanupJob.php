<?php
namespace App\Services;

final class AccountCleanupJob
{
    public function handle(): array
    {
        return ['sessions' => UserSessionService::available() ? (new UserSessionService())->cleanup() : 0];
    }
}
