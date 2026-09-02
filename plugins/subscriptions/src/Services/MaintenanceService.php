<?php

namespace Fireball\Subscriptions\Services;

final class MaintenanceService
{
    public function run(): array
    {
        $now = date('Y-m-d H:i:s');
        $recurring = (new RecurringService())->processDue();
        $activated = db()->query(
            "UPDATE subscriptions SET status = 'active', updated_at = ? WHERE archived_at IS NULL AND status = 'pending' AND starts_at <= ? AND ends_at > ?",
            [$now, $now, $now]
        )->rowCount();
        $expired = db()->query(
            "UPDATE subscriptions SET status = 'expired', auto_renew = 0, next_billing_at = NULL, updated_at = ? WHERE archived_at IS NULL AND status IN ('active', 'cancelled', 'grace_period', 'past_due') AND COALESCE(grace_ends_at, ends_at) <= ?",
            [$now, $now]
        )->rowCount();
        $stalePayments = db()->query(
            "UPDATE subscription_payments p INNER JOIN subscription_orders o ON o.id = p.order_id SET p.status = 'failed', p.failed_at = ?, p.error_message = 'Payment timeout', p.updated_at = ?, o.status = 'failed', o.updated_at = ? WHERE p.status IN ('created', 'pending') AND o.expires_at IS NOT NULL AND o.expires_at <= ?",
            [$now, $now, $now, $now]
        )->rowCount();
        $notifications = $this->sendExpiryNotifications();

        return compact('recurring', 'activated', 'expired', 'stalePayments', 'notifications');
    }

    private function sendExpiryNotifications(): int
    {
        $rows = db()->query(
            "SELECT s.id, s.user_id, s.ends_at, p.name AS plan_name,
                    DATEDIFF(s.ends_at, NOW()) AS days_left
             FROM subscriptions s
             INNER JOIN subscription_plans p ON p.id = s.plan_id
             WHERE s.archived_at IS NULL
               AND s.status IN ('active', 'cancelled')
               AND DATEDIFF(s.ends_at, NOW()) IN (1, 3)"
        )->get() ?: [];
        $sent = 0;
        foreach ($rows as $row) {
            $eventKey = 'subscription.expiry_notice.' . (int)$row['days_left'];
            $exists = db()->query(
                'SELECT id FROM subscription_events WHERE subscription_id = ? AND event_key = ? LIMIT 1',
                [(int)$row['id'], $eventKey]
            )->getOne();
            if ($exists) {
                continue;
            }
            try {
                notification_create([
                    'user_id' => (int)$row['user_id'],
                    'title' => \FireballPluginSubscriptions::t('subscriptions_notification_expiring_title'),
                    'message' => str_replace(':days', (string)(int)$row['days_left'], \FireballPluginSubscriptions::t('subscriptions_notification_expiring_message')),
                    'type' => 'subscription',
                    'action_url' => base_href('/account/subscription'),
                    'icon' => 'ci-clock',
                    'source' => 'subscriptions',
                ]);
                (new SubscriptionService())->event($eventKey, (int)$row['id'], null, (int)$row['user_id'], (string)$row['days_left'], (string)$row['days_left']);
                $sent++;
            } catch (\Throwable $exception) {
                log_error_details('Subscription expiry notification failed', ['subscription_id' => (int)$row['id']], $exception);
            }
        }

        return $sent;
    }
}
