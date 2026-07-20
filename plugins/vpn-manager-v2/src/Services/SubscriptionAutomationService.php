<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\Exceptions\VpnManagerV2Exception;
use Fireball\VpnManagerV2\Repositories\AutomationRepository;
use Fireball\VpnManagerV2\Repositories\SubscriptionRepository;

final class SubscriptionAutomationService
{
    public function __construct(
        private readonly ?AutomationRepository $repository = null,
        private readonly ?SubscriptionRepository $subscriptions = null,
        private readonly ?RemoteClientSyncService $remoteSync = null,
        private readonly ?VpnSubscriptionRevisionService $revisionService = null,
        private readonly ?VpnNotificationService $notificationService = null,
        private readonly ?\Closure $remotePush = null,
        private readonly ?\Closure $expirationProvider = null,
        private readonly ?\Closure $trafficLimitProvider = null,
        private readonly ?VpnV2SubscriptionDependencyService $dependencies = null,
    ) {
    }

    public function checkExpirations(): array
    {
        $expired = 0;
        $synced = 0;
        $failed = 0;
        $subscriptions = $this->expirationProvider !== null
            ? ($this->expirationProvider)()
            : $this->repository()->dueSubscriptions();
        if (!is_array($subscriptions)) {
            throw new \LogicException('Invalid expiration provider result.');
        }
        foreach ($subscriptions as $subscription) {
            if (!is_array($subscription)) {
                continue;
            }
            $subscriptionId = (int)$subscription['id'];
            $this->repository()->updateSubscriptionStatus($subscriptionId, 'expired');
            ($this->revisionService ?? new VpnSubscriptionRevisionService())->touchConfig($subscriptionId);
            $result = $this->syncDisabledState($subscription, 'expired', 'expiration');
            $cascade = ($this->dependencies ?? new VpnV2SubscriptionDependencyService())->cascadeDisable(
                $subscriptionId,
                'parent_subscription_expired'
            );
            $result['synced'] += (int)($cascade['synced'] ?? 0);
            $result['failed'] += (int)($cascade['failed'] ?? 0);
            $synced += $result['synced'];
            $failed += $result['failed'];
            $expired++;
            $this->subscriptions()->logEvent(
                $result['failed'] > 0 ? 'subscription.expired_partial' : 'subscription.expired',
                $subscriptionId,
                null,
                null,
                (int)$subscription['user_id'],
                null,
                ['synced' => $result['synced'], 'failed' => $result['failed']]
            );
        }

        return ['expired' => $expired, 'synced' => $synced, 'failed' => $failed];
    }

    public function checkTrafficLimits(): array
    {
        $notifications = $this->notifications();
        $queued = $notifications->queueTrafficNotifications();
        $delivered = $notifications->dispatch([
            VpnNotificationService::TRAFFIC_80,
            VpnNotificationService::TRAFFIC_100,
        ]);

        $exceeded = 0;
        $synced = 0;
        $failed = 0;
        $subscriptions = $this->trafficLimitProvider !== null
            ? ($this->trafficLimitProvider)()
            : $this->repository()->subscriptionsForTrafficLimit();
        if (!is_array($subscriptions)) {
            throw new \LogicException('Invalid traffic limit provider result.');
        }
        foreach ($subscriptions as $subscription) {
            if (!is_array($subscription)) {
                continue;
            }
            $subscriptionId = (int)$subscription['id'];
            $this->repository()->updateSubscriptionStatus($subscriptionId, 'traffic_exceeded');
            ($this->revisionService ?? new VpnSubscriptionRevisionService())->touchConfig($subscriptionId);
            $result = $this->syncDisabledState($subscription, 'traffic_exceeded', 'traffic-limit');
            $cascade = ($this->dependencies ?? new VpnV2SubscriptionDependencyService())->cascadeDisable(
                $subscriptionId,
                'parent_subscription_limit_exceeded'
            );
            $result['synced'] += (int)($cascade['synced'] ?? 0);
            $result['failed'] += (int)($cascade['failed'] ?? 0);
            $synced += $result['synced'];
            $failed += $result['failed'];
            $exceeded++;
            $this->subscriptions()->logEvent(
                $result['failed'] > 0 ? 'subscription.traffic_limit_partial' : 'subscription.traffic_limit_exceeded',
                $subscriptionId,
                null,
                null,
                (int)$subscription['user_id'],
                null,
                [
                    'traffic_used_bytes' => (int)$subscription['traffic_used_bytes'],
                    'traffic_limit_bytes' => (int)$subscription['traffic_limit_bytes'],
                    'synced' => $result['synced'],
                    'failed' => $result['failed'],
                ]
            );
        }

        return [
            'exceeded' => $exceeded,
            'synced' => $synced,
            'failed' => $failed,
            'notifications_queued' => (int)$queued['queued'],
            'notifications_sent' => (int)$delivered['sent'],
            'notifications_failed' => (int)$delivered['failed'],
        ];
    }

    public function retryNode(int $nodeId): bool
    {
        $node = $this->subscriptions()->connectionForProvisioning($nodeId);
        if (!$node || (string)$node['status'] !== 'sync_error') {
            return false;
        }
        $subscription = $this->repository()->subscription((int)$node['subscription_id']);
        if (!$subscription || !in_array((string)$subscription['status'], ['active', 'suspended', 'expired', 'traffic_exceeded'], true)) {
            return false;
        }

        try {
            $result = $this->push($node, $subscription, [
                'flow' => $this->flow($node['flow'] ?? null),
                'traffic_limit_bytes' => $this->limit($node['traffic_limit_bytes'] ?? null),
            ]);
            $this->repository()->recordAutomationNodeSuccess(
                $nodeId,
                isset($result['traffic_used_bytes']) ? (int)$result['traffic_used_bytes'] : null,
                (string)$subscription['status'] === 'active'
            );
            $this->repository()->recalculateSubscriptionTraffic((int)$node['subscription_id']);
            $this->subscriptions()->logEvent('node.automation_retry_confirmed', (int)$node['subscription_id'],
                $nodeId, (int)$node['server_id'], (int)$node['user_id'], null,
                ['remote_updated' => !empty($result['remote_updated'])]);

            return true;
        } catch (\Throwable $exception) {
            $safeError = $this->safeError($exception);
            $this->repository()->recordAutomationNodeFailure($nodeId, $safeError);
            $this->subscriptions()->logEvent('node.automation_retry_failed', (int)$node['subscription_id'],
                $nodeId, (int)$node['server_id'], (int)$node['user_id'], null,
                ['error_type' => $this->errorType($exception)]);
            $this->critical((int)$node['subscription_id'], 'retry-sync');

            return false;
        }
    }

    private function syncDisabledState(array $subscription, string $status, string $operation): array
    {
        $subscriptionId = (int)$subscription['id'];
        $desired = array_replace($subscription, ['status' => $status]);
        $synced = 0;
        $failed = 0;
        $firstError = null;
        foreach ($this->subscriptions()->nodeIdsForSubscription($subscriptionId) as $nodeId) {
            $node = $this->subscriptions()->connectionForProvisioning($nodeId);
            if (!$node || (string)$node['status'] === 'deleted') {
                continue;
            }
            if (($this->dependencies ?? new VpnV2SubscriptionDependencyService())
                ->countActiveConsumers($nodeId, $subscriptionId) > 0) {
                $this->subscriptions()->logEvent('node.disable_skipped_shared_consumer', $subscriptionId,
                    $nodeId, (int)$node['server_id'], (int)$subscription['user_id'], null,
                    ['source' => $operation]);
                continue;
            }
            try {
                $result = $this->push($node, $desired, [
                    'flow' => $this->flow($node['flow'] ?? null),
                    'traffic_limit_bytes' => $this->limit($node['traffic_limit_bytes'] ?? null),
                    'desired_enabled' => false,
                ]);
                $this->repository()->recordAutomationNodeSuccess(
                    $nodeId,
                    isset($result['traffic_used_bytes']) ? (int)$result['traffic_used_bytes'] : null,
                    false
                );
                $synced++;
            } catch (\Throwable $exception) {
                $failed++;
                $safeError = $this->safeError($exception);
                $firstError ??= $safeError;
                $this->repository()->recordAutomationNodeFailure($nodeId, $safeError);
                $this->subscriptions()->logEvent('node.' . $operation . '.failed', $subscriptionId,
                    $nodeId, (int)$node['server_id'], (int)$subscription['user_id'], null,
                    ['error_type' => $this->errorType($exception)]);
            }
        }
        $this->repository()->recalculateSubscriptionTraffic($subscriptionId);
        $this->repository()->updateSubscriptionStatus($subscriptionId, $status, $firstError);
        if ($failed > 0) {
            $this->critical($subscriptionId, $operation);
        }

        return ['synced' => $synced, 'failed' => $failed];
    }

    private function push(array $node, array $subscription, array $overrides): array
    {
        if (db()->inTransaction()) {
            throw new \RuntimeException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_http_inside_transaction'));
        }
        if ($this->remotePush !== null) {
            $result = ($this->remotePush)($node, $subscription, $overrides);
            if (!is_array($result)) {
                throw new \LogicException('Invalid remote synchronization result.');
            }

            return $result;
        }

        return ($this->remoteSync ?? new RemoteClientSyncService())->push($node, $subscription, $overrides);
    }

    private function critical(int $subscriptionId, string $operation): void
    {
        try {
            $this->notifications()->notifyCritical($subscriptionId, $operation);
        } catch (\Throwable) {
            // Automation state is authoritative even if a notification transport is unavailable.
        }
    }

    private function safeError(\Throwable $exception): string
    {
        return $exception instanceof VpnManagerV2Exception
            ? mb_substr(trim($exception->getMessage()), 0, 1000)
            : \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_automation_sync_generic');
    }

    private function errorType(\Throwable $exception): string
    {
        $class = get_class($exception);
        $position = strrpos($class, '\\');

        return mb_substr($position === false ? $class : substr($class, $position + 1), 0, 120);
    }

    private function flow(mixed $value): ?string
    {
        $value = trim((string)$value);

        return $value !== '' ? $value : null;
    }

    private function limit(mixed $value): ?int
    {
        return $value !== null && (int)$value > 0 ? (int)$value : null;
    }

    private function repository(): AutomationRepository
    {
        return $this->repository ?? new AutomationRepository();
    }

    private function subscriptions(): SubscriptionRepository
    {
        return $this->subscriptions ?? new SubscriptionRepository();
    }

    private function notifications(): VpnNotificationService
    {
        return $this->notificationService ?? new VpnNotificationService();
    }
}
