<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\Repositories\AutomationRepository;

final class RetryFailedOperationsService
{
    public function __construct(
        private readonly ?AutomationRepository $repository = null,
        private readonly ?SettingsService $settings = null,
        private readonly ?SubscriptionProvisioningService $provisioning = null,
        private readonly ?SubscriptionDeletionService $deletion = null,
        private readonly ?SubscriptionAutomationService $automation = null,
        private readonly ?VpnNotificationService $notifications = null,
    ) {
    }

    public function retry(): array
    {
        $settings = ($this->settings ?? new SettingsService())->current();
        if (empty($settings['retry_failed_operations'])) {
            return [
                'retried' => 0,
                'recovered' => 0,
                'failed' => 0,
                'notifications_retried' => 0,
                'notifications_sent' => 0,
                'skipped' => true,
            ];
        }

        $repository = $this->repository ?? new AutomationRepository();
        $retried = 0;
        $recovered = 0;
        $failed = 0;

        foreach ($repository->failedDeletionSubscriptions() as $row) {
            $retried++;
            try {
                $result = ($this->deletion ?? new SubscriptionDeletionService())->deleteForever((int)$row['id'], 0);
                if ($result->successful()) {
                    $recovered++;
                } else {
                    $failed++;
                }
            } catch (\Throwable) {
                $failed++;
            }
        }

        foreach ($repository->failedProvisioningNodes() as $row) {
            $retried++;
            try {
                $result = ($this->provisioning ?? new SubscriptionProvisioningService())->retryNode((int)$row['id']);
                if ($result->successful()) {
                    $recovered++;
                } else {
                    $failed++;
                }
            } catch (\Throwable) {
                $failed++;
            }
        }

        foreach ($repository->failedSyncNodes() as $row) {
            $retried++;
            try {
                if (($this->automation ?? new SubscriptionAutomationService())->retryNode((int)$row['id'])) {
                    $recovered++;
                } else {
                    $failed++;
                }
            } catch (\Throwable) {
                $failed++;
            }
        }

        try {
            $notificationResult = ($this->notifications ?? new VpnNotificationService())->retryFailed();
        } catch (\Throwable) {
            $notificationResult = ['retried' => 0, 'sent' => 0, 'failed' => 1];
        }

        return [
            'retried' => $retried,
            'recovered' => $recovered,
            'failed' => $failed,
            'notifications_retried' => (int)($notificationResult['retried'] ?? 0),
            'notifications_sent' => (int)($notificationResult['sent'] ?? 0),
            'notifications_failed' => (int)($notificationResult['failed'] ?? 0),
            'skipped' => false,
        ];
    }
}
