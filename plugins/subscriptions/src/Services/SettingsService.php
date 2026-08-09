<?php

namespace Fireball\Subscriptions\Services;

use Fireball\Subscriptions\Support\SecretCipher;

final class SettingsService
{
    public const SLUG = 'subscriptions';
    private const SECRET_KEYS = ['password1', 'password2'];

    public function defaults(): array
    {
        return [
            'merchant_login' => '',
            'password1' => '',
            'password2' => '',
            'hash_algorithm' => 'sha256',
            'test_mode' => true,
            'currency' => 'RUB',
            'payment_timeout_minutes' => 60,
            'recurring_enabled' => false,
            'receipt_enabled' => false,
            'receipt_tax' => 'none',
            'receipt_payment_method' => 'full_payment',
            'receipt_payment_object' => 'service',
            'result_url' => base_url('/subscriptions/robokassa/result'),
            'success_url' => base_url('/subscriptions/robokassa/success'),
            'fail_url' => base_url('/subscriptions/robokassa/fail'),
            'media_token_ttl' => 300,
        ];
    }

    public function ensureDefaults(): void
    {
        foreach ($this->defaults() as $key => $value) {
            if (plugin_setting(self::SLUG, $key, null) === null) {
                plugin_setting_set(self::SLUG, $key, $value);
            }
        }
    }

    public function current(bool $includeSecrets = false): array
    {
        $settings = [];
        foreach ($this->defaults() as $key => $default) {
            $value = plugin_setting(self::SLUG, $key, $default);
            if (in_array($key, self::SECRET_KEYS, true)) {
                $value = SecretCipher::decrypt((string)$value);
                $settings[$key . '_configured'] = $value !== '';
                $settings[$key] = $includeSecrets ? $value : '';
                continue;
            }
            $settings[$key] = $value;
        }

        $settings['hash_algorithm'] = $this->algorithm((string)$settings['hash_algorithm']);
        $settings['currency'] = preg_match('/^[A-Z]{3}$/', strtoupper((string)$settings['currency']))
            ? strtoupper((string)$settings['currency'])
            : 'RUB';
        $settings['payment_timeout_minutes'] = max(5, min(10080, (int)$settings['payment_timeout_minutes']));
        $settings['media_token_ttl'] = max(60, min(1800, (int)$settings['media_token_ttl']));
        foreach (['test_mode', 'recurring_enabled', 'receipt_enabled'] as $flag) {
            $settings[$flag] = (bool)$settings[$flag];
        }

        return $settings;
    }

    public function save(array $data): void
    {
        $settings = [
            'merchant_login' => mb_substr(trim((string)($data['merchant_login'] ?? '')), 0, 190),
            'hash_algorithm' => $this->algorithm((string)($data['hash_algorithm'] ?? 'sha256')),
            'test_mode' => !empty($data['test_mode']),
            'currency' => strtoupper(trim((string)($data['currency'] ?? 'RUB'))),
            'payment_timeout_minutes' => max(5, min(10080, (int)($data['payment_timeout_minutes'] ?? 60))),
            'recurring_enabled' => !empty($data['recurring_enabled']),
            'receipt_enabled' => !empty($data['receipt_enabled']),
            'receipt_tax' => $this->choice((string)($data['receipt_tax'] ?? 'none'), ['none', 'vat0', 'vat5', 'vat7', 'vat10', 'vat20', 'vat105', 'vat107', 'vat110', 'vat120'], 'none'),
            'receipt_payment_method' => $this->choice((string)($data['receipt_payment_method'] ?? 'full_payment'), ['full_payment', 'prepayment', 'prepayment_full', 'advance'], 'full_payment'),
            'receipt_payment_object' => $this->choice((string)($data['receipt_payment_object'] ?? 'service'), ['service', 'commodity', 'payment', 'another'], 'service'),
            'result_url' => base_url('/subscriptions/robokassa/result'),
            'success_url' => base_url('/subscriptions/robokassa/success'),
            'fail_url' => base_url('/subscriptions/robokassa/fail'),
            'media_token_ttl' => max(60, min(1800, (int)($data['media_token_ttl'] ?? 300))),
        ];
        if (preg_match('/^[A-Z]{3}$/', $settings['currency']) !== 1) {
            throw new \InvalidArgumentException('Currency must be a three-letter ISO code.');
        }

        foreach ($settings as $key => $value) {
            plugin_setting_set(self::SLUG, $key, $value);
        }

        foreach (self::SECRET_KEYS as $key) {
            $plainText = (string)($data[$key] ?? '');
            if ($plainText !== '') {
                plugin_setting_set(self::SLUG, $key, SecretCipher::encrypt($plainText));
            }
        }
    }

    public function assertGatewayReady(): array
    {
        $settings = $this->current(true);
        if ($settings['merchant_login'] === '' || $settings['password1'] === '' || $settings['password2'] === '') {
            throw new \RuntimeException('Robokassa credentials are not configured.');
        }

        return $settings;
    }

    private function algorithm(string $value): string
    {
        $value = strtolower(trim($value));

        return in_array($value, ['md5', 'ripemd160', 'sha1', 'sha256', 'sha384', 'sha512'], true) ? $value : 'sha256';
    }

    private function choice(string $value, array $allowed, string $fallback): string
    {
        return in_array($value, $allowed, true) ? $value : $fallback;
    }
}
