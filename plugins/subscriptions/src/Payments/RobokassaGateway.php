<?php

namespace Fireball\Subscriptions\Payments;

use Fireball\Subscriptions\Services\SettingsService;
use Fireball\Subscriptions\Support\Money;

final class RobokassaGateway implements PaymentGatewayInterface
{
    private const PAYMENT_URL = 'https://auth.robokassa.ru/Merchant/Index.aspx';
    private const RECURRING_URL = 'https://auth.robokassa.ru/Merchant/Recurring';

    public function __construct(private readonly SettingsService $settings = new SettingsService())
    {
    }

    public function checkoutUrl(array $order, array $plan, array $profile): string
    {
        $config = $this->settings->assertGatewayReady();
        $invoiceId = (string)(int)$order['invoice_id'];
        $outSum = Money::decimal((int)$order['amount_minor']);
        $shp = [
            'Shp_order' => (string)(int)$order['id'],
            'Shp_user' => (string)(int)$order['user_id'],
        ];
        ksort($shp, SORT_STRING);

        $params = [
            'MerchantLogin' => $config['merchant_login'],
            'OutSum' => $outSum,
            'InvId' => $invoiceId,
            'Description' => mb_substr((string)$plan['name'], 0, 100),
            'Culture' => $this->culture(),
            'Encoding' => 'utf-8',
            'Email' => (string)$profile['email'],
        ];

        $signatureParts = [$config['merchant_login'], $outSum, $invoiceId];
        if (!empty($config['receipt_enabled'])) {
            $receipt = $this->receipt($plan, $order, $config);
            $params['Receipt'] = $receipt;
            $signatureParts[] = rawurlencode($receipt);
        }
        $signatureParts[] = $config['password1'];
        foreach ($shp as $key => $value) {
            $signatureParts[] = $key . '=' . $value;
        }

        $params['SignatureValue'] = $this->hash(implode(':', $signatureParts), $config['hash_algorithm']);
        $params += $shp;
        if (!empty($config['test_mode'])) {
            $params['IsTest'] = '1';
        }
        $consents = json_decode((string)($order['consent_snapshot'] ?? ''), true);
        if (!empty($config['recurring_enabled']) && !empty($plan['is_recurring'])
            && !empty($consents['recurring']) && !empty($consents['auto_renew'])) {
            $params['Recurring'] = 'true';
        }

        return self::PAYMENT_URL . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public function verifyResult(array $payload): bool
    {
        $config = $this->settings->assertGatewayReady();

        return $this->verifyCallback($payload, (string)$config['password2'], (string)$config['hash_algorithm']);
    }

    public function verifySuccess(array $payload): bool
    {
        $config = $this->settings->assertGatewayReady();

        return $this->verifyCallback($payload, (string)$config['password1'], (string)$config['hash_algorithm']);
    }

    public function expectedResultResponse(int|string $invoiceId): string
    {
        return 'OK' . (int)$invoiceId;
    }

    public function initiateRecurring(array $order, array $plan, array $parentPayment): string
    {
        $config = $this->settings->assertGatewayReady();
        if (empty($config['recurring_enabled'])) {
            throw new \RuntimeException('Recurring payments are disabled.');
        }
        $invoiceId = (string)(int)$order['invoice_id'];
        $outSum = Money::decimal((int)$order['amount_minor']);
        $shp = [
            'Shp_order' => (string)(int)$order['id'],
            'Shp_user' => (string)(int)$order['user_id'],
        ];
        ksort($shp, SORT_STRING);
        $signatureParts = [$config['merchant_login'], $outSum, $invoiceId, $config['password1']];
        foreach ($shp as $key => $value) {
            $signatureParts[] = $key . '=' . $value;
        }
        $params = [
            'MerchantLogin' => $config['merchant_login'],
            'OutSum' => $outSum,
            'InvoiceID' => $invoiceId,
            'PreviousInvoiceID' => (string)(int)$parentPayment['invoice_id'],
            'Description' => mb_substr((string)$plan['name'], 0, 100),
            'SignatureValue' => $this->hash(implode(':', $signatureParts), $config['hash_algorithm']),
        ] + $shp;

        $body = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        if (function_exists('curl_init')) {
            $curl = curl_init(self::RECURRING_URL);
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            ]);
            $response = curl_exec($curl);
            $error = curl_error($curl);
            $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            curl_close($curl);
            if (!is_string($response) || $response === '' || $status < 200 || $status >= 300) {
                throw new \RuntimeException('Robokassa recurring request failed' . ($error !== '' ? ': ' . $error : '.'));
            }
        } else {
            $context = stream_context_create(['http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $body,
                'timeout' => 30,
                'ignore_errors' => true,
            ]]);
            $response = @file_get_contents(self::RECURRING_URL, false, $context);
            if (!is_string($response) || $response === '') {
                throw new \RuntimeException('Robokassa recurring request failed.');
            }
        }
        $response = trim($response);
        if ($response !== 'OK+' . $invoiceId && $response !== 'OK' . $invoiceId) {
            throw new \RuntimeException('Robokassa rejected the recurring request.');
        }

        return $response;
    }

    private function verifyCallback(array $payload, string $password, string $algorithm): bool
    {
        $outSum = trim((string)($payload['OutSum'] ?? $payload['outSum'] ?? ''));
        $invoiceId = trim((string)($payload['InvId'] ?? $payload['InvoiceID'] ?? $payload['invoiceID'] ?? ''));
        $received = strtolower(trim((string)($payload['SignatureValue'] ?? $payload['signatureValue'] ?? '')));
        if ($outSum === '' || !ctype_digit($invoiceId) || $received === '') {
            return false;
        }

        $parts = [$outSum, $invoiceId, $password];
        foreach ($this->shp($payload) as $key => $value) {
            $parts[] = $key . '=' . $value;
        }
        $expected = $this->hash(implode(':', $parts), $algorithm);

        return hash_equals($expected, $received);
    }

    private function shp(array $payload): array
    {
        $shp = [];
        foreach ($payload as $key => $value) {
            if (is_string($key) && str_starts_with($key, 'Shp_') && is_scalar($value)) {
                $shp[$key] = (string)$value;
            }
        }
        ksort($shp, SORT_STRING);

        return $shp;
    }

    private function receipt(array $plan, array $order, array $config): string
    {
        $payload = [
            'items' => [[
                'name' => mb_substr((string)$plan['name'], 0, 128),
                'quantity' => 1,
                'sum' => Money::decimal((int)$order['amount_minor']),
                'tax' => $config['receipt_tax'],
                'payment_method' => $config['receipt_payment_method'],
                'payment_object' => $config['receipt_payment_object'],
            ]],
        ];

        return (string)json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function hash(string $value, string $algorithm): string
    {
        return strtolower(hash($algorithm, $value));
    }

    private function culture(): string
    {
        return str_starts_with(strtolower((string)current_locale()), 'ru') ? 'ru' : 'en';
    }
}
