<?php

namespace Fireball\VpnManager\Services;

use Fireball\VpnManager\Repositories\VpnRepository;

final class SubscriptionAutomationService
{
    private VpnRepository $repo;

    public function __construct(?VpnRepository $repo = null)
    {
        $this->repo = $repo ?: new VpnRepository();
    }

    public function expireDueSubscriptions(): array
    {
        $settings = SettingsService::settings();
        $expired = 0;
        $disabled = 0;
        $failed = 0;

        foreach ($this->repo->expiredActiveSubscriptions() as $subscription) {
            $subscriptionId = (int)$subscription['id'];
            $this->repo->setSubscriptionExpired($subscriptionId);
            (new NotificationScheduler())->queueSubscriptionNotification($subscriptionId, 'subscription_expired');
            $expired++;

            if (empty($settings['auto_disable_expired_subscriptions'])) {
                $this->repo->logEvent('subscription.expired.auto_disable_skipped', 'VPN subscription expired; automatic client disable is off.', [], (int)$subscription['user_id'], $subscriptionId);
                continue;
            }

            $result = $this->disableNodes($subscriptionId, 'subscription.auto_disabled_expired');
            $disabled += $result['success'];
            $failed += $result['failed'];
        }

        return ['expired' => $expired, 'disabled' => $disabled, 'failed' => $failed];
    }

    public function checkTrafficLimits(): array
    {
        $settings = SettingsService::settings();
        $exceeded = 0;
        $disabled = 0;
        $failed = 0;

        foreach ($this->repo->activeSubscriptionsForTrafficLimitCheck() as $subscription) {
            $subscriptionId = (int)$subscription['id'];
            $limit = (int)$subscription['traffic_limit_bytes'];
            if ($limit <= 0) {
                continue;
            }

            $nodes = $this->repo->subscriptionNodes($subscriptionId);
            if ((string)$subscription['traffic_mode'] === 'per_node') {
                $overNodes = array_values(array_filter($nodes, static fn(array $node): bool => (int)($node['traffic_used_bytes'] ?? 0) >= $limit));
                if (!$overNodes) {
                    continue;
                }

                $exceeded++;
                if (!empty($settings['auto_disable_traffic_exceeded'])) {
                    foreach ($overNodes as $node) {
                        $result = $this->disableNode((int)$node['id'], 'subscription.auto_disabled_traffic_node');
                        $disabled += $result ? 1 : 0;
                        $failed += $result ? 0 : 1;
                    }
                } else {
                    $this->repo->logEvent('subscription.traffic_exceeded.disable_skipped', 'VPN node traffic limit exceeded; automatic disable is off.', [], (int)$subscription['user_id'], $subscriptionId);
                }

                $freshNodes = $this->repo->subscriptionNodes($subscriptionId);
                $activeNodes = array_filter($freshNodes, static fn(array $node): bool => !in_array((string)($node['status'] ?? ''), ['disabled', 'deleted'], true));
                if (!$activeNodes) {
                    $this->repo->setSubscriptionTrafficExceeded($subscriptionId);
                }
                continue;
            }

            if ((int)$subscription['traffic_used_bytes'] < $limit) {
                continue;
            }

            $this->repo->setSubscriptionTrafficExceeded($subscriptionId);
            $exceeded++;
            if (!empty($settings['auto_disable_traffic_exceeded'])) {
                $result = $this->disableNodes($subscriptionId, 'subscription.auto_disabled_traffic');
                $disabled += $result['success'];
                $failed += $result['failed'];
            } else {
                $this->repo->logEvent('subscription.traffic_exceeded.disable_skipped', 'VPN subscription traffic limit exceeded; automatic disable is off.', [], (int)$subscription['user_id'], $subscriptionId);
            }
        }

        return ['exceeded' => $exceeded, 'disabled' => $disabled, 'failed' => $failed];
    }

    public function extend(int $subscriptionId, int $days, bool $resetTraffic, bool $enableClients): array
    {
        $expiresAt = $this->repo->extendSubscription($subscriptionId, $days, false);
        $reset = ['success' => 0, 'failed' => 0];
        $enabled = ['success' => 0, 'failed' => 0];

        if ($resetTraffic) {
            $reset = $this->resetTraffic($subscriptionId);
            if (empty($reset['failed'])) {
                $this->repo->deleteTrafficNotifications($subscriptionId);
            }
        }

        if ($enableClients) {
            $enabled = $this->enableNodes($subscriptionId, 'subscription.enabled_after_extend');
            $this->repo->updateSubscriptionProvisioningStatus($subscriptionId, 'active');
        }

        return [
            'expires_at' => $expiresAt,
            'reset_success' => $reset['success'],
            'reset_failed' => $reset['failed'],
            'enabled' => $enabled['success'],
            'failed' => $reset['failed'] + $enabled['failed'],
        ];
    }

    public function suspend(int $subscriptionId): array
    {
        $this->repo->setSubscriptionStatus($subscriptionId, 'suspended');
        $result = $this->disableNodes($subscriptionId, 'subscription.disabled_manual');
        $this->repo->logEvent('subscription.disabled_manual', 'VPN subscription disabled manually.', [], null, $subscriptionId);

        return $result;
    }

    public function activate(int $subscriptionId): array
    {
        $result = $this->enableNodes($subscriptionId, 'subscription.enabled_manual');
        $this->repo->setSubscriptionStatus($subscriptionId, 'active');
        $this->repo->logEvent('subscription.enabled_manual', 'VPN subscription enabled manually.', [], null, $subscriptionId);

        return $result;
    }

    public function resetTraffic(int $subscriptionId): array
    {
        $success = 0;
        $failed = 0;
        $connection = new ConnectionActionService($this->repo);
        foreach ($this->repo->subscriptionNodes($subscriptionId) as $node) {
            if ((string)($node['status'] ?? '') === 'deleted') {
                continue;
            }

            try {
                $connection->resetTraffic((int)$node['id']);
                $success++;
            } catch (\Throwable) {
                $failed++;
            }
        }

        if ($failed === 0) {
            $this->repo->resetSubscriptionTraffic($subscriptionId);
        } else {
            $this->repo->recalculateSubscriptionTraffic($subscriptionId);
            $this->repo->logEvent('subscription.traffic_reset.partial_failed', 'VPN subscription traffic reset finished with errors.', [
                'success' => $success,
                'failed' => $failed,
            ], null, $subscriptionId);
        }

        return ['success' => $success, 'failed' => $failed];
    }

    private function disableNodes(int $subscriptionId, string $eventType): array
    {
        $success = 0;
        $failed = 0;
        foreach ($this->repo->subscriptionNodes($subscriptionId) as $node) {
            if ((string)($node['status'] ?? '') === 'deleted') {
                continue;
            }

            $result = $this->disableNode((int)$node['id'], $eventType);
            $success += $result ? 1 : 0;
            $failed += $result ? 0 : 1;
        }

        return ['success' => $success, 'failed' => $failed];
    }

    private function enableNodes(int $subscriptionId, string $eventType): array
    {
        $success = 0;
        $failed = 0;
        $connection = new ConnectionActionService($this->repo);
        foreach ($this->repo->subscriptionNodes($subscriptionId) as $node) {
            if ((string)($node['status'] ?? '') === 'deleted') {
                continue;
            }

            try {
                $connection->enable((int)$node['id']);
                $this->repo->logEvent($eventType, 'VPN client enabled in 3x-ui.', [], null, $subscriptionId, (int)$node['id'], (int)$node['server_id']);
                $success++;
            } catch (\Throwable) {
                $failed++;
            }
        }

        return ['success' => $success, 'failed' => $failed];
    }

    private function disableNode(int $nodeId, string $eventType): bool
    {
        $node = $this->repo->connection($nodeId);
        if (!$node) {
            return false;
        }
        $subscriptionId = (int)($node['subscription_id'] ?? 0);
        try {
            (new ConnectionActionService($this->repo))->disable($nodeId);
            $this->repo->logEvent($eventType, 'VPN client disabled in 3x-ui.', [], (int)($node['user_id'] ?? 0), $subscriptionId, $nodeId, (int)($node['server_id'] ?? 0));

            return true;
        } catch (\Throwable $exception) {
            $this->repo->logEvent($eventType . '.failed', 'VPN client disable failed.', ['error' => $exception->getMessage()], (int)($node['user_id'] ?? 0), $subscriptionId, $nodeId, (int)($node['server_id'] ?? 0));

            return false;
        }
    }
}
