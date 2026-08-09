<?php

namespace Fireball\Subscriptions\Services;

use Fireball\Subscriptions\Payments\RobokassaGateway;
use Fireball\Subscriptions\Support\Money;

final class PaymentService
{
    public function processRobokassaResult(array $payload): string
    {
        $gateway = new RobokassaGateway();
        $invoiceId = (int)($payload['InvId'] ?? $payload['InvoiceID'] ?? $payload['invoiceID'] ?? 0);
        $eventHash = hash('sha256', 'robokassa|' . $invoiceId . '|' . strtolower((string)($payload['SignatureValue'] ?? '')) . '|' . (string)($payload['OutSum'] ?? ''));

        $existingEvent = db()->query(
            'SELECT processing_status FROM subscription_webhook_events WHERE provider = ? AND event_hash = ? LIMIT 1',
            ['robokassa', $eventHash]
        )->getOne();
        if ($existingEvent && (string)$existingEvent['processing_status'] === 'processed') {
            return $gateway->expectedResultResponse($invoiceId);
        }

        $verified = false;
        try {
            $verified = $gateway->verifyResult($payload);
        } catch (\Throwable $exception) {
            $this->recordWebhook($invoiceId, $eventHash, false, 'failed', $payload, $exception->getMessage());
            throw $exception;
        }
        if (!$verified) {
            $this->recordWebhook($invoiceId, $eventHash, false, 'rejected', $payload, 'Invalid signature.');
            throw new \RuntimeException('Invalid Robokassa signature.');
        }
        $this->recordWebhook($invoiceId, $eventHash, true, 'processing', $payload);

        db()->beginTransaction();
        try {
            $payment = db()->query(
                "SELECT * FROM subscription_payments WHERE provider = 'robokassa' AND invoice_id = ? LIMIT 1 FOR UPDATE",
                [$invoiceId]
            )->getOne();
            if (!$payment) {
                throw new \RuntimeException('Payment invoice was not found.');
            }
            if ((string)$payment['status'] === 'paid') {
                db()->query("UPDATE subscription_webhook_events SET processing_status = 'processed', processed_at = ? WHERE provider = ? AND event_hash = ?", [date('Y-m-d H:i:s'), 'robokassa', $eventHash]);
                db()->commit();
                return $gateway->expectedResultResponse($invoiceId);
            }
            $order = db()->query('SELECT * FROM subscription_orders WHERE id = ? LIMIT 1 FOR UPDATE', [(int)$payment['order_id']])->getOne();
            if (!$order || (int)$order['invoice_id'] !== $invoiceId) {
                throw new \RuntimeException('Payment order mismatch.');
            }
            if ((int)$payment['user_id'] !== (int)$order['user_id'] || (int)$payment['plan_id'] !== (int)$order['plan_id']) {
                throw new \RuntimeException('Payment ownership mismatch.');
            }

            $amountMinor = Money::toMinor((string)($payload['OutSum'] ?? ''));
            if ($amountMinor !== (int)$payment['amount_minor'] || $amountMinor !== (int)$order['amount_minor']) {
                throw new \RuntimeException('Payment amount mismatch.');
            }
            $currency = strtoupper(trim((string)($payload['Currency'] ?? $order['currency'])));
            if ($currency !== (string)$payment['currency'] || $currency !== (string)$order['currency']) {
                throw new \RuntimeException('Payment currency mismatch.');
            }
            if (isset($payload['Shp_order']) && (int)$payload['Shp_order'] !== (int)$order['id']) {
                throw new \RuntimeException('Payment order parameter mismatch.');
            }
            if (isset($payload['Shp_user']) && (int)$payload['Shp_user'] !== (int)$order['user_id']) {
                throw new \RuntimeException('Payment user parameter mismatch.');
            }

            $now = date('Y-m-d H:i:s');
            $safePayload = $this->safePayload($payload);
            db()->query(
                "UPDATE subscription_payments SET status = 'paid', signature_verified = 1, provider_payload = ?, provider_transaction = ?, paid_at = ?, updated_at = ? WHERE id = ?",
                [$safePayload, trim((string)($payload['PaymentMethod'] ?? $payload['IncCurrLabel'] ?? '')), $now, $now, (int)$payment['id']]
            );
            db()->query("UPDATE subscription_orders SET status = 'paid', updated_at = ? WHERE id = ?", [$now, (int)$order['id']]);
            $subscription = (new SubscriptionService())->activatePaidOrder($order, (int)$payment['id']);
            db()->query("UPDATE subscription_webhook_events SET processing_status = 'processed', processed_at = ? WHERE provider = ? AND event_hash = ?", [$now, 'robokassa', $eventHash]);
            db()->commit();
        } catch (\Throwable $exception) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            db()->query("UPDATE subscription_webhook_events SET processing_status = 'failed', error_message = ? WHERE provider = ? AND event_hash = ?", [mb_substr($exception->getMessage(), 0, 2000), 'robokassa', $eventHash]);
            throw $exception;
        }

        try {
            notification_create([
                'user_id' => (int)$order['user_id'],
                'title' => \FireballPluginSubscriptions::t('subscriptions_notification_activated_title'),
                'message' => \FireballPluginSubscriptions::t('subscriptions_notification_activated_message'),
                'type' => 'subscription',
                'action_url' => base_href('/account/subscription'),
                'icon' => 'ci-check-circle',
                'source' => 'subscriptions',
                'metadata' => ['subscription_id' => (int)($subscription['id'] ?? 0)],
            ]);
        } catch (\Throwable $exception) {
            log_error_details('Subscription activation notification failed', ['invoice_id' => $invoiceId], $exception);
        }

        return $gateway->expectedResultResponse($invoiceId);
    }

    private function recordWebhook(int $invoiceId, string $eventHash, bool $verified, string $status, array $payload, ?string $error = null): void
    {
        db()->query(
            'INSERT INTO subscription_webhook_events (provider, invoice_id, event_hash, signature_verified, processing_status, payload, error_message, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE signature_verified = VALUES(signature_verified), processing_status = VALUES(processing_status), payload = VALUES(payload), error_message = VALUES(error_message)',
            ['robokassa', $invoiceId ?: null, $eventHash, $verified ? 1 : 0, $status, $this->safePayload($payload), $error ? mb_substr($error, 0, 2000) : null, date('Y-m-d H:i:s')]
        );
    }

    private function safePayload(array $payload): string
    {
        $allowed = [
            'OutSum', 'InvId', 'InvoiceID', 'Currency', 'PaymentMethod', 'IncCurrLabel',
            'Shp_order', 'Shp_user',
        ];
        $safe = [];
        foreach ($allowed as $key) {
            if (isset($payload[$key]) && is_scalar($payload[$key])) {
                $safe[$key] = mb_substr((string)$payload[$key], 0, 500);
            }
        }
        $signature = trim((string)($payload['SignatureValue'] ?? $payload['signatureValue'] ?? ''));
        if ($signature !== '') {
            $safe['SignatureValue'] = substr($signature, 0, 12) . '…';
        }

        return (string)json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
