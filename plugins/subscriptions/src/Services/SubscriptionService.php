<?php

namespace Fireball\Subscriptions\Services;

final class SubscriptionService
{
    public function activatePaidOrder(array $order, int $paymentId): array
    {
        $userId = (int)$order['user_id'];
        $planId = (int)$order['plan_id'];
        $plan = db()->query('SELECT * FROM subscription_plans WHERE id = ? LIMIT 1', [$planId])->getOne();
        if (!$plan) {
            throw new \RuntimeException('Subscription plan no longer exists.');
        }
        $planSnapshot = json_decode((string)($order['plan_snapshot'] ?? ''), true);
        if (is_array($planSnapshot) && $planSnapshot !== []) {
            if ((int)($planSnapshot['id'] ?? 0) !== $planId
                || (int)($planSnapshot['price_minor'] ?? -1) !== (int)$order['amount_minor']
                || (string)($planSnapshot['currency'] ?? '') !== (string)$order['currency']) {
                throw new \RuntimeException('Subscription plan snapshot mismatch.');
            }
            $terms = $planSnapshot;
        } else {
            // Compatibility for orders created before plan snapshots were introduced.
            $terms = $plan;
        }

        $now = new \DateTimeImmutable('now');
        $current = db()->query(
            "SELECT * FROM subscriptions
             WHERE user_id = ? AND status IN ('active', 'grace_period', 'past_due', 'cancelled')
             ORDER BY ends_at DESC, id DESC LIMIT 1 FOR UPDATE",
            [$userId]
        )->getOne();
        $duration = $this->durationInterval((string)$terms['duration_unit'], (int)$terms['duration_value']);
        $consents = json_decode((string)($order['consent_snapshot'] ?? ''), true);
        $autoRenew = !empty($terms['is_recurring'])
            && (new SettingsService())->current()['recurring_enabled']
            && !empty($consents['auto_renew'])
            && !empty($consents['recurring']);

        if ($current && (int)$current['plan_id'] === $planId
            && (strtotime((string)$current['ends_at']) > $now->getTimestamp() || in_array((string)$current['status'], ['grace_period', 'past_due'], true))) {
            $startsAt = new \DateTimeImmutable((string)$current['starts_at']);
            $baseEnd = strtotime((string)$current['ends_at']) > $now->getTimestamp()
                ? new \DateTimeImmutable((string)$current['ends_at'])
                : $now;
            $endsAt = $baseEnd->add($duration);
            $subscriptionId = (int)$current['id'];
            $oldStatus = (string)$current['status'];
            db()->query(
                "UPDATE subscriptions SET status = 'active', source = 'robokassa', ends_at = ?, grace_ends_at = NULL, auto_renew = ?, next_billing_at = ?, parent_payment_id = COALESCE(parent_payment_id, ?), updated_at = ? WHERE id = ?",
                [
                    $endsAt->format('Y-m-d H:i:s'), $autoRenew ? 1 : 0,
                    $autoRenew ? $endsAt->format('Y-m-d H:i:s') : null,
                    $paymentId, $now->format('Y-m-d H:i:s'), $subscriptionId,
                ]
            );
            $event = 'subscription.renewed';
        } else {
            $futureStart = $current && strtotime((string)$current['ends_at']) > $now->getTimestamp()
                ? new \DateTimeImmutable((string)$current['ends_at'])
                : $now;
            $startsAt = $futureStart;
            $endsAt = $futureStart->add($duration);
            $status = $futureStart > $now ? 'pending' : 'active';
            $oldStatus = null;
            db()->query(
                'INSERT INTO subscriptions (user_id, plan_id, status, starts_at, ends_at, auto_renew, next_billing_at, parent_payment_id, source, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $userId, $planId, $status, $startsAt->format('Y-m-d H:i:s'), $endsAt->format('Y-m-d H:i:s'),
                    $autoRenew ? 1 : 0, $autoRenew ? $endsAt->format('Y-m-d H:i:s') : null,
                    $paymentId, 'robokassa', $now->format('Y-m-d H:i:s'), $now->format('Y-m-d H:i:s'),
                ]
            );
            $subscriptionId = (int)db()->getInsertId();
            $event = 'subscription.activated';
        }

        db()->query('UPDATE subscription_orders SET subscription_id = ?, updated_at = ? WHERE id = ?', [$subscriptionId, $now->format('Y-m-d H:i:s'), (int)$order['id']]);
        db()->query('UPDATE subscription_payments SET subscription_id = ?, updated_at = ? WHERE id = ?', [$subscriptionId, $now->format('Y-m-d H:i:s'), $paymentId]);
        $this->event($event, $subscriptionId, $paymentId, $userId, $oldStatus, 'active', [
            'order_id' => (int)$order['id'],
            'plan_id' => $planId,
            'starts_at' => $startsAt->format(DATE_ATOM),
            'ends_at' => $endsAt->format(DATE_ATOM),
        ]);

        return db()->query('SELECT * FROM subscriptions WHERE id = ?', [$subscriptionId])->getOne() ?: [];
    }

    public function setAutoRenew(int $userId, bool $enabled): void
    {
        $subscription = (new AccessService())->activeSubscription($userId);
        if (!$subscription) {
            throw new \RuntimeException(\FireballPluginSubscriptions::t('subscriptions_error_no_active_subscription'));
        }
        if ($enabled && !(new SettingsService())->current()['recurring_enabled']) {
            throw new \RuntimeException(\FireballPluginSubscriptions::t('subscriptions_error_recurring_disabled'));
        }
        if ($enabled) {
            $parent = db()->query(
                'SELECT p.id, p.status, o.consent_snapshot, plans.is_recurring FROM subscription_payments p INNER JOIN subscription_orders o ON o.id = p.order_id INNER JOIN subscription_plans plans ON plans.id = p.plan_id WHERE p.id = ? AND p.status = ? LIMIT 1',
                [(int)$subscription['parent_payment_id'], 'paid']
            )->getOne();
            $consents = json_decode((string)($parent['consent_snapshot'] ?? ''), true);
            if (!$parent || empty($parent['is_recurring']) || empty($consents['recurring']) || empty($consents['auto_renew'])) {
                throw new \RuntimeException(\FireballPluginSubscriptions::t('subscriptions_error_recurring_checkout_required'));
            }
        }
        db()->query(
            'UPDATE subscriptions SET auto_renew = ?, cancelled_at = ?, status = ?, updated_at = ? WHERE id = ?',
            [
                $enabled ? 1 : 0,
                $enabled ? null : date('Y-m-d H:i:s'),
                $enabled ? 'active' : 'cancelled',
                date('Y-m-d H:i:s'),
                (int)$subscription['id'],
            ]
        );
        $this->event(
            $enabled ? 'subscription.auto_renew_enabled' : 'subscription.auto_renew_disabled',
            (int)$subscription['id'], null, $userId, (string)$subscription['status'], $enabled ? 'active' : 'cancelled'
        );
    }

    public function grant(int $userId, int $planId, int $durationValue, string $durationUnit, int $actorId, string $comment = '', string $source = 'manual'): int
    {
        $plan = db()->query('SELECT id FROM subscription_plans WHERE id = ?', [$planId])->getOne();
        if (!$plan || !db()->query('SELECT id FROM users WHERE id = ?', [$userId])->getOne()) {
            throw new \InvalidArgumentException('Invalid user or plan.');
        }
        $now = new \DateTimeImmutable('now');
        $endsAt = $now->add($this->durationInterval($durationUnit, max(1, $durationValue)));
        $source = in_array($source, ['manual', 'external'], true) ? $source : 'manual';
        db()->query(
            "INSERT INTO subscriptions (user_id, plan_id, status, starts_at, ends_at, auto_renew, admin_comment, source, created_at, updated_at) VALUES (?, ?, 'active', ?, ?, 0, ?, ?, ?, ?)",
            [$userId, $planId, $now->format('Y-m-d H:i:s'), $endsAt->format('Y-m-d H:i:s'), trim($comment), $source, $now->format('Y-m-d H:i:s'), $now->format('Y-m-d H:i:s')]
        );
        $id = (int)db()->getInsertId();
        $this->event('subscription.granted_by_admin', $id, null, $userId, null, 'active', ['comment' => $comment, 'source' => $source], $actorId);

        return $id;
    }

    public function updateManaged(int $subscriptionId, int $planId, string $status, string $endsAt, int $actorId): void
    {
        $subscription = db()->query('SELECT * FROM subscriptions WHERE id = ? LIMIT 1', [$subscriptionId])->getOne();
        if (!$subscription || !db()->query('SELECT id FROM subscription_plans WHERE id = ? LIMIT 1', [$planId])->getOne()) {
            throw new \InvalidArgumentException(\FireballPluginSubscriptions::t('subscriptions_error_subscription_not_found'));
        }
        $status = in_array($status, ['active', 'disabled'], true) ? $status : 'disabled';
        try {
            $end = new \DateTimeImmutable($endsAt);
        } catch (\Throwable) {
            throw new \InvalidArgumentException(\FireballPluginSubscriptions::t('subscriptions_error_end_date'));
        }
        $now = date('Y-m-d H:i:s');
        db()->query(
            'UPDATE subscriptions SET plan_id = ?, status = ?, ends_at = ?, auto_renew = IF(? = \'active\', auto_renew, 0), next_billing_at = IF(? = \'active\', next_billing_at, NULL), updated_at = ? WHERE id = ?',
            [$planId, $status, $end->format('Y-m-d H:i:s'), $status, $status, $now, $subscriptionId]
        );
        $this->event('subscription.updated_by_admin', $subscriptionId, null, (int)$subscription['user_id'], (string)$subscription['status'], $status, [
            'old_plan_id' => (int)$subscription['plan_id'],
            'plan_id' => $planId,
            'ends_at' => $end->format(DATE_ATOM),
        ], $actorId);
    }

    public function event(string $key, ?int $subscriptionId, ?int $paymentId, ?int $userId, ?string $oldStatus, ?string $newStatus, array $metadata = [], ?int $actorId = null): void
    {
        db()->query(
            'INSERT INTO subscription_events (subscription_id, payment_id, user_id, actor_user_id, event_key, old_status, new_status, metadata, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $subscriptionId, $paymentId, $userId, $actorId, $key, $oldStatus, $newStatus,
                json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), date('Y-m-d H:i:s'),
            ]
        );
    }

    private function durationInterval(string $unit, int $value): \DateInterval
    {
        $value = max(1, min(1200, $value));

        return new \DateInterval($unit === 'months' ? 'P' . $value . 'M' : 'P' . $value . 'D');
    }
}
