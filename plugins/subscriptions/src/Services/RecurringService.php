<?php

namespace Fireball\Subscriptions\Services;

use Fireball\Subscriptions\Payments\RobokassaGateway;
use Fireball\Subscriptions\Repositories\PlanRepository;
use Fireball\Subscriptions\Repositories\ProfileRepository;

final class RecurringService
{
    public function processDue(int $limit = 25): array
    {
        if (!(new SettingsService())->current()['recurring_enabled']) {
            return ['initiated' => 0, 'failed' => 0];
        }
        $limit = max(1, min(100, $limit));
        $rows = db()->query(
            "SELECT id FROM subscriptions
             WHERE archived_at IS NULL
               AND auto_renew = 1
               AND status IN ('active', 'grace_period', 'past_due')
               AND next_billing_at IS NOT NULL
               AND next_billing_at <= NOW()
             ORDER BY next_billing_at ASC, id ASC LIMIT {$limit}"
        )->get() ?: [];
        $result = ['initiated' => 0, 'failed' => 0];
        foreach ($rows as $row) {
            try {
                if ($this->initiate((int)$row['id'])) {
                    $result['initiated']++;
                }
            } catch (\Throwable $exception) {
                $result['failed']++;
                log_error_details('Subscription recurring payment failed', ['subscription_id' => (int)$row['id']], $exception);
            }
        }

        return $result;
    }

    public function initiate(int $subscriptionId): bool
    {
        $preflight = db()->query('SELECT id, user_id, status FROM subscriptions WHERE id = ? LIMIT 1', [$subscriptionId])->getOne();
        if (!$preflight) {
            return false;
        }
        try {
            (new SubscriptionEligibilityService())->assertEligible((int)$preflight['user_id'], 'recurring_preflight');
        } catch (\DomainException) {
            $now = date('Y-m-d H:i:s');
            db()->query(
                'UPDATE subscriptions SET auto_renew = 0, next_billing_at = NULL, updated_at = ? WHERE id = ?',
                [$now, $subscriptionId]
            );
            (new SubscriptionService())->event(
                'subscription.auto_renew_blocked_by_address',
                $subscriptionId,
                null,
                (int)$preflight['user_id'],
                (string)$preflight['status'],
                (string)$preflight['status'],
                ['reason' => SubscriptionEligibilityService::REASON_ADDRESS_INCLUDED_IN_UTILITIES]
            );

            return false;
        }

        db()->beginTransaction();
        try {
            $subscription = db()->query('SELECT * FROM subscriptions WHERE id = ? LIMIT 1 FOR UPDATE', [$subscriptionId])->getOne();
            if (!$subscription || empty($subscription['auto_renew']) || empty($subscription['next_billing_at']) || strtotime((string)$subscription['next_billing_at']) > time()) {
                db()->commit();
                return false;
            }
            $periodStart = (string)$subscription['next_billing_at'];
            $existing = db()->query(
                "SELECT id FROM subscription_payments WHERE subscription_id = ? AND payment_type = 'recurring' AND billing_period_start = ? LIMIT 1",
                [$subscriptionId, $periodStart]
            )->getOne();
            if ($existing) {
                db()->commit();
                return false;
            }
            $plans = new PlanRepository();
            $plan = $plans->find((int)$subscription['plan_id']);
            $parent = db()->query("SELECT * FROM subscription_payments WHERE id = ? AND status = 'paid' LIMIT 1", [(int)$subscription['parent_payment_id']])->getOne();
            if (!$plan || !$parent) {
                throw new \RuntimeException('A paid parent payment is required for recurring billing.');
            }
            $parentOrder = db()->query('SELECT * FROM subscription_orders WHERE id = ? LIMIT 1', [(int)$parent['order_id']])->getOne();
            try {
                $snapshot = (new ProfileRepository())->snapshot((int)$subscription['user_id'], (int)$subscription['plan_id']);
                $snapshotJson = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                $snapshotJson = (string)($parentOrder['customer_snapshot'] ?? '{}');
            }
            (new SubscriptionEligibilityService())->assertEligible((int)$subscription['user_id'], 'recurring_before_order');
            $invoiceId = $this->uniqueInvoiceId();
            $now = date('Y-m-d H:i:s');
            $renewalPlanSnapshot = $plans->purchaseSnapshot($plan);
            $renewalPlanSnapshot['auto_renew_enabled'] = !empty($subscription['auto_renew']);
            $renewalPlanSnapshot['is_recurring'] = !empty($subscription['auto_renew']);
            db()->query(
                "INSERT INTO subscription_orders (invoice_id, user_id, plan_id, subscription_id, amount_minor, currency, plan_snapshot, customer_snapshot, consent_snapshot, status, expires_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?)",
                [
                    $invoiceId, (int)$subscription['user_id'], (int)$subscription['plan_id'], $subscriptionId,
                    (int)$plan['price_minor'], (string)$plan['currency'],
                    json_encode($renewalPlanSnapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    $snapshotJson,
                    (string)($parentOrder['consent_snapshot'] ?? '{}'), date('Y-m-d H:i:s', time() + 86400), $now, $now,
                ]
            );
            $orderId = (int)db()->getInsertId();
            db()->query(
                "INSERT INTO subscription_payments (order_id, subscription_id, user_id, plan_id, provider, invoice_id, parent_payment_id, parent_invoice_id, amount_minor, currency, status, payment_type, billing_period_start, created_at, updated_at) VALUES (?, ?, ?, ?, 'robokassa', ?, ?, ?, ?, ?, 'pending', 'recurring', ?, ?, ?)",
                [
                    $orderId, $subscriptionId, (int)$subscription['user_id'], (int)$subscription['plan_id'],
                    $invoiceId, (int)$parent['id'], (int)$parent['invoice_id'], (int)$plan['price_minor'], (string)$plan['currency'],
                    $periodStart, $now, $now,
                ]
            );
            $paymentId = (int)db()->getInsertId();
            db()->commit();
        } catch (\Throwable $exception) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            throw $exception;
        }

        $order = db()->query('SELECT * FROM subscription_orders WHERE id = ?', [$orderId])->getOne() ?: [];
        try {
            $response = (new RobokassaGateway())->initiateRecurring($order, $plan, $parent);
            $graceDays = max(0, (int)$plan['grace_period_days']);
            $graceEnd = $graceDays > 0
                ? (new \DateTimeImmutable((string)$subscription['ends_at']))->add(new \DateInterval('P' . $graceDays . 'D'))->format('Y-m-d H:i:s')
                : null;
            $pendingStatus = $graceEnd !== null && strtotime($graceEnd) > time() ? 'grace_period' : 'past_due';
            db()->query('UPDATE subscription_payments SET provider_transaction = ?, updated_at = ? WHERE id = ?', [$response, date('Y-m-d H:i:s'), $paymentId]);
            db()->query('UPDATE subscriptions SET status = ?, grace_ends_at = ?, updated_at = ? WHERE id = ?', [$pendingStatus, $graceEnd, date('Y-m-d H:i:s'), $subscriptionId]);
            (new SubscriptionService())->event('payment.recurring_initiated', $subscriptionId, $paymentId, (int)$subscription['user_id'], null, 'pending', ['invoice_id' => $invoiceId]);
            return true;
        } catch (\Throwable $exception) {
            if ($exception instanceof \DomainException
                && $exception->getMessage() === \FireballPluginSubscriptions::t('subscriptions_address_included_in_utilities')) {
                $this->markEligibilityBlocked($subscription, $paymentId, $orderId, $exception);
            } else {
                $this->markFailed($subscription, $plan, $paymentId, $orderId, $exception);
            }
            throw $exception;
        }
    }

    private function markEligibilityBlocked(array $subscription, int $paymentId, int $orderId, \Throwable $exception): void
    {
        $now = date('Y-m-d H:i:s');
        db()->query("UPDATE subscription_payments SET status = 'failed', error_message = ?, failed_at = ?, updated_at = ? WHERE id = ?", [mb_substr($exception->getMessage(), 0, 2000), $now, $now, $paymentId]);
        db()->query("UPDATE subscription_orders SET status = 'failed', updated_at = ? WHERE id = ?", [$now, $orderId]);
        db()->query('UPDATE subscriptions SET auto_renew = 0, next_billing_at = NULL, updated_at = ? WHERE id = ?', [$now, (int)$subscription['id']]);
        (new SubscriptionService())->event(
            'subscription.auto_renew_blocked_by_address',
            (int)$subscription['id'],
            $paymentId,
            (int)$subscription['user_id'],
            (string)$subscription['status'],
            (string)$subscription['status'],
            ['reason' => SubscriptionEligibilityService::REASON_ADDRESS_INCLUDED_IN_UTILITIES]
        );
    }

    private function markFailed(array $subscription, array $plan, int $paymentId, int $orderId, \Throwable $exception): void
    {
        $now = date('Y-m-d H:i:s');
        $graceDays = max(0, (int)$plan['grace_period_days']);
        $graceEnd = $graceDays > 0
            ? (new \DateTimeImmutable((string)$subscription['ends_at']))->add(new \DateInterval('P' . $graceDays . 'D'))->format('Y-m-d H:i:s')
            : null;
        $status = $graceEnd !== null && strtotime($graceEnd) > time() ? 'grace_period' : 'past_due';
        db()->query("UPDATE subscription_payments SET status = 'failed', error_message = ?, failed_at = ?, updated_at = ? WHERE id = ?", [mb_substr($exception->getMessage(), 0, 2000), $now, $now, $paymentId]);
        db()->query("UPDATE subscription_orders SET status = 'failed', updated_at = ? WHERE id = ?", [$now, $orderId]);
        db()->query('UPDATE subscriptions SET status = ?, grace_ends_at = ?, updated_at = ? WHERE id = ?', [$status, $graceEnd, $now, (int)$subscription['id']]);
        (new SubscriptionService())->event('payment.recurring_failed', (int)$subscription['id'], $paymentId, (int)$subscription['user_id'], (string)$subscription['status'], $status, ['error_class' => get_class($exception)]);
        try {
            notification_create([
                'user_id' => (int)$subscription['user_id'],
                'title' => \FireballPluginSubscriptions::t('subscriptions_notification_recurring_failed_title'),
                'message' => \FireballPluginSubscriptions::t('subscriptions_notification_recurring_failed_message'),
                'type' => 'subscription',
                'action_url' => base_href('/account/subscription'),
                'icon' => 'ci-alert-triangle',
                'source' => 'subscriptions',
                'priority' => 'high',
            ]);
        } catch (\Throwable $notificationException) {
            log_error_details('Recurring failure notification failed', ['subscription_id' => (int)$subscription['id']], $notificationException);
        }
    }

    private function uniqueInvoiceId(): int
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $invoiceId = random_int(100000, 2147483646);
            if (!db()->query('SELECT id FROM subscription_orders WHERE invoice_id = ? LIMIT 1', [$invoiceId])->getOne()) {
                return $invoiceId;
            }
        }
        throw new \RuntimeException('Could not allocate a recurring invoice ID.');
    }
}
