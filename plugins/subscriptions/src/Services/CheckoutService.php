<?php

namespace Fireball\Subscriptions\Services;

use Fireball\Subscriptions\Payments\RobokassaGateway;
use Fireball\Subscriptions\Repositories\PlanRepository;
use Fireball\Subscriptions\Repositories\ProfileRepository;

final class CheckoutService
{
    public function create(int $userId, int $planId, array $consents): array
    {
        $plan = (new PlanRepository())->find($planId, true);
        if (!$plan) {
            throw new \RuntimeException(\FireballPluginSubscriptions::t('subscriptions_error_plan_not_found'));
        }
        if (empty($consents['offer']) || empty($consents['privacy'])) {
            throw new \RuntimeException(\FireballPluginSubscriptions::t('subscriptions_error_consents'));
        }
        if (!empty($plan['is_recurring']) && !empty($consents['auto_renew']) && empty($consents['recurring'])) {
            throw new \RuntimeException(\FireballPluginSubscriptions::t('subscriptions_error_recurring_consent'));
        }

        $profiles = new ProfileRepository();
        $snapshot = $profiles->snapshot($userId, $planId);
        $planSnapshot = (new PlanRepository())->purchaseSnapshot($plan);
        $profile = $profiles->profileForUser($userId, false) ?: [];
        $settingsService = new SettingsService();
        $settings = $settingsService->current();
        if ((string)$plan['currency'] !== (string)$settings['currency']) {
            throw new \RuntimeException(\FireballPluginSubscriptions::t('subscriptions_error_currency_mismatch'));
        }
        $settingsService->assertGatewayReady();
        $now = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', time() + (int)$settings['payment_timeout_minutes'] * 60);
        $invoiceId = $this->uniqueInvoiceId();

        db()->beginTransaction();
        try {
            db()->query(
                'INSERT INTO subscription_orders (invoice_id, user_id, plan_id, amount_minor, currency, plan_snapshot, customer_snapshot, consent_snapshot, status, expires_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $invoiceId, $userId, $planId, (int)$plan['price_minor'], (string)$plan['currency'],
                    json_encode($planSnapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    json_encode($consents, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    'pending', $expiresAt, $now, $now,
                ]
            );
            $orderId = (int)db()->getInsertId();
            db()->query(
                "INSERT INTO subscription_payments (order_id, user_id, plan_id, provider, invoice_id, amount_minor, currency, status, payment_type, created_at, updated_at) VALUES (?, ?, ?, 'robokassa', ?, ?, ?, 'pending', 'initial', ?, ?)",
                [$orderId, $userId, $planId, $invoiceId, (int)$plan['price_minor'], (string)$plan['currency'], $now, $now]
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
            $url = (new RobokassaGateway())->checkoutUrl($order, $plan, $profile);
        } catch (\Throwable $exception) {
            db()->query("UPDATE subscription_orders SET status = 'failed', updated_at = ? WHERE id = ?", [date('Y-m-d H:i:s'), $orderId]);
            db()->query("UPDATE subscription_payments SET status = 'failed', error_message = ?, failed_at = ?, updated_at = ? WHERE id = ?", [$exception->getMessage(), date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $paymentId]);
            throw $exception;
        }

        return ['order' => $order, 'plan' => $plan, 'payment_id' => $paymentId, 'url' => $url];
    }

    private function uniqueInvoiceId(): int
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $invoiceId = random_int(100000, 2147483646);
            if (!db()->query('SELECT id FROM subscription_orders WHERE invoice_id = ? LIMIT 1', [$invoiceId])->getOne()) {
                return $invoiceId;
            }
        }

        throw new \RuntimeException('Could not allocate a payment invoice ID.');
    }
}
