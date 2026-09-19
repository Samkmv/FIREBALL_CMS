<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\Repositories\NotificationRepository;

/**
 * Notification-only maintenance fallback.
 *
 * It intentionally does NOT contact 3xUI. Traffic threshold notifications use
 * counters already synchronized by the existing VPN synchronization.
 */
final class NotificationMaintenanceService
{
    private const INTERVAL_SECONDS = 300;
    private const STALE_SENDING_MINUTES = 15;

    public function runDue(bool $force = false): array
    {
        $lock = $this->lock();
        if ($lock === null) {
            return [
                'status' => 'skipped',
                'reason' => 'already_running',
                'steps' => [],
            ];
        }

        try {
            if (!$force && !$this->claimDue()) {
                return [
                    'status' => 'skipped',
                    'reason' => 'not_due',
                    'steps' => [],
                ];
            }

            $settings = (new SettingsService())->current();
            $notifications = new VpnNotificationService();
            $repository = new NotificationRepository();
            $steps = [];

            $steps['recover_stale'] = $this->step(
                'recover_stale',
                static fn(): array => [
                    'recovered' => $repository->recoverStaleSending(
                        self::STALE_SENDING_MINUTES
                    ),
                ]
            );

            if (!empty($settings['retry_failed_operations'])) {
                $steps['retry_failed_notifications'] = $this->step(
                    'retry_failed_notifications',
                    static fn(): array => $notifications->retryFailed()
                );
            }

            $steps['queue_expiration'] = $this->step(
                'queue_expiration',
                static fn(): array => $notifications->queueExpirationNotifications()
            );

            // Uses only local counters that were already synchronized by the
            // existing 3xUI synchronization mechanism.
            $steps['queue_traffic'] = $this->step(
                'queue_traffic',
                static fn(): array => $notifications->queueTrafficNotifications()
            );

            // Also flushes pending provisioned/critical notifications created
            // by inline operations before this maintenance pass.
            $steps['deliver_pending'] = $this->step(
                'deliver_pending',
                static fn(): array => $notifications->dispatch()
            );

            return [
                'status' => 'ok',
                'steps' => $steps,
            ];
        } finally {
            $this->unlock($lock);
        }
    }

    private function step(string $name, \Closure $callback): array
    {
        try {
            $result = $callback();

            return [
                'status' => 'ok',
                'result' => is_array($result) ? $result : [],
            ];
        } catch (\Throwable $exception) {
            log_error_details(
                'VPN Manager V2 notification maintenance step failed',
                [
                    'Step' => $name,
                    'Error Class' => get_class($exception),
                ],
                $exception
            );

            return [
                'status' => 'error',
                'error' => get_class($exception),
            ];
        }
    }

    private function claimDue(): bool
    {
        $stamp = $this->runtimePath('stamp');
        $lastRun = is_file($stamp) ? (int)@filemtime($stamp) : 0;

        if ($lastRun > 0 && (time() - $lastRun) < self::INTERVAL_SECONDS) {
            return false;
        }

        @touch($stamp);

        return true;
    }

    /**
     * @return resource|null
     */
    private function lock()
    {
        $handle = @fopen($this->runtimePath('lock'), 'c');
        if (!is_resource($handle)) {
            return null;
        }

        if (!@flock($handle, LOCK_EX | LOCK_NB)) {
            @fclose($handle);
            return null;
        }

        return $handle;
    }

    /**
     * @param resource $handle
     */
    private function unlock($handle): void
    {
        @flock($handle, LOCK_UN);
        @fclose($handle);
    }

    private function runtimePath(string $suffix): string
    {
        $root = defined('ROOT') ? (string)ROOT : dirname(__DIR__, 4);
        $scope = substr(hash('sha256', $root), 0, 16);

        return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'fireball-vpn-v2-notifications-' . $scope . '-' . $suffix;
    }
}
