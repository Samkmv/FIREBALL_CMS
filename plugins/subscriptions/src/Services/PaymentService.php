<?php

namespace Fireball\Subscriptions\Services;

use Fireball\Subscriptions\Payments\RobokassaGateway;
use Fireball\Subscriptions\Support\Money;

final class PaymentService
{
    public const TIMEOUT_ERROR = 'Payment timeout';

    public function visibleHistoryStats(): array
    {
        return [
            'paid_total_minor' => (int)db()->query("SELECT COALESCE(SUM(amount_minor), 0) FROM subscription_payments WHERE cleared_at IS NULL AND status = 'paid'")->getColumn(),
            'failed' => (int)db()->query("SELECT COUNT(*) FROM subscription_payments WHERE cleared_at IS NULL AND status = 'failed'")->getColumn(),
        ];
    }

    public function clearHistory(): int
    {
        $now = date('Y-m-d H:i:s');

        // Hide every status from the admin history, retaining records for callbacks, audit and renewals.
        return db()->query(
            'UPDATE subscription_payments SET cleared_at = ?, updated_at = ? WHERE cleared_at IS NULL',
            [$now, $now]
        )->rowCount();
    }

    public function expirePending(?int $userId = null, int $limit = 100): int
    {
        $now = date('Y-m-d H:i:s');
        $limit = max(1, min(500, $limit));
        $userWhere = $userId === null ? '' : ' AND p.user_id = ?';
        $rows = db()->query(
            "SELECT p.id FROM subscription_payments p
             INNER JOIN subscription_orders o ON o.id = p.order_id
             WHERE p.status IN ('created', 'pending') AND p.signature_verified = 0 AND o.status IN ('created', 'pending')
               AND o.expires_at IS NOT NULL AND o.expires_at <= ? {$userWhere}
               AND NOT EXISTS (SELECT 1 FROM subscription_webhook_events we
                   WHERE we.provider = p.provider AND we.invoice_id = p.invoice_id AND we.signature_verified = 1)
             ORDER BY p.id ASC LIMIT {$limit}",
            $userId === null ? [$now] : [$now, $userId]
        )->get() ?: [];
        $expired = 0;
        foreach ($rows as $row) {
            db()->beginTransaction();
            try {
                // Same lock order as successful ResultURL processing. A late verified callback may still mark it paid.
                $payment = db()->query('SELECT * FROM subscription_payments WHERE id = ? LIMIT 1 FOR UPDATE', [(int)$row['id']])->getOne();
                if (!$payment || !in_array($payment['status'], ['created', 'pending'], true) || !empty($payment['signature_verified'])) {
                    db()->commit();
                    continue;
                }
                $order = db()->query('SELECT * FROM subscription_orders WHERE id = ? LIMIT 1 FOR UPDATE', [(int)$payment['order_id']])->getOne();
                $verified = db()->query('SELECT id FROM subscription_webhook_events WHERE provider = ? AND invoice_id = ? AND signature_verified = 1 LIMIT 1', [$payment['provider'], $payment['invoice_id']])->getOne();
                if (!$order || !in_array($order['status'], ['created', 'pending'], true)
                    || empty($order['expires_at']) || $order['expires_at'] > $now || $verified) {
                    db()->commit();
                    continue;
                }
                db()->query("UPDATE subscription_payments SET status = 'failed', failed_at = ?, error_message = ?, updated_at = ? WHERE id = ?", [$now, self::TIMEOUT_ERROR, $now, (int)$payment['id']]);
                db()->query("UPDATE subscription_orders SET status = 'failed', updated_at = ? WHERE id = ?", [$now, (int)$order['id']]);
                db()->commit();
                $expired++;
            } catch (\Throwable $exception) {
                if (db()->inTransaction()) db()->rollBack();
                throw $exception;
            }
        }

        return $expired;
    }

    public function retryFailedWebhooks(int $limit = 25): array
    {
        $limit = max(1, min(100, $limit));
        $rows = db()->query(
            "SELECT DISTINCT p.id FROM subscription_payments p
             INNER JOIN subscription_webhook_events we ON we.provider = p.provider AND we.invoice_id = p.invoice_id
             WHERE p.status <> 'paid' AND we.signature_verified = 1 AND we.processing_status = 'failed'
             ORDER BY p.id DESC LIMIT {$limit}"
        )->get() ?: [];
        $result = ['processed' => 0, 'failed' => 0];
        foreach ($rows as $row) {
            try {
                $this->retryVerifiedWebhook((int)$row['id']);
                $result['processed']++;
            } catch (\Throwable $exception) {
                $result['failed']++;
                log_error_details('Stored subscription ResultURL retry failed', ['payment_id' => (int)$row['id']], $exception);
            }
        }

        return $result;
    }

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

        return $this->processVerifiedResult($payload, $invoiceId, $eventHash, $gateway);
    }

    public function retryVerifiedWebhook(int $paymentId): string
    {
        $payment = db()->query(
            'SELECT id, provider, invoice_id, status FROM subscription_payments WHERE id = ? LIMIT 1',
            [$paymentId]
        )->getOne();
        if (!$payment || (string)$payment['provider'] !== 'robokassa') {
            throw new \RuntimeException('Robokassa payment was not found.');
        }

        $invoiceId = (int)$payment['invoice_id'];
        $gateway = new RobokassaGateway();
        if ((string)$payment['status'] === 'paid') {
            return $gateway->expectedResultResponse($invoiceId);
        }

        $event = db()->query(
            "SELECT event_hash, payload
             FROM subscription_webhook_events
             WHERE provider = 'robokassa' AND invoice_id = ? AND signature_verified = 1 AND processing_status = 'failed'
             ORDER BY id DESC
             LIMIT 1",
            [$invoiceId]
        )->getOne();
        if (!$event) {
            throw new \RuntimeException('No failed, signature-verified ResultURL is available for retry.');
        }

        $payload = json_decode((string)($event['payload'] ?? ''), true);
        if (!is_array($payload)) {
            throw new \RuntimeException('Stored ResultURL payload is invalid.');
        }
        $payloadInvoiceId = (int)($payload['InvId'] ?? $payload['InvoiceID'] ?? 0);
        if ($payloadInvoiceId !== $invoiceId) {
            throw new \RuntimeException('Stored ResultURL invoice does not match the payment.');
        }

        $eventHash = (string)$event['event_hash'];
        db()->query(
            "UPDATE subscription_webhook_events SET processing_status = 'processing', error_message = NULL WHERE provider = ? AND event_hash = ? AND signature_verified = 1",
            ['robokassa', $eventHash]
        );

        return $this->processVerifiedResult($payload, $invoiceId, $eventHash, $gateway);
    }

    private function processVerifiedResult(array $payload, int $invoiceId, string $eventHash, RobokassaGateway $gateway): string
    {
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
                "UPDATE subscription_payments SET status = 'paid', signature_verified = 1, provider_payload = ?, provider_transaction = ?, error_message = NULL, failed_at = NULL, paid_at = ?, updated_at = ? WHERE id = ?",
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
            $errorMessage = mb_substr($exception->getMessage(), 0, 2000);
            db()->query("UPDATE subscription_webhook_events SET processing_status = 'failed', error_message = ? WHERE provider = ? AND event_hash = ?", [$errorMessage, 'robokassa', $eventHash]);
            db()->query(
                "UPDATE subscription_payments SET error_message = ?, updated_at = ? WHERE provider = 'robokassa' AND invoice_id = ? AND status <> 'paid'",
                [$errorMessage, date('Y-m-d H:i:s'), $invoiceId]
            );
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
