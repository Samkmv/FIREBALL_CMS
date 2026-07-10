<?php

namespace Fireball\VpnManager\Support;

final class Crypto
{
    public static function encrypt(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($value, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new \RuntimeException('Unable to encrypt VPN secret.');
        }

        return 'v1:' . base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(?string $payload): string
    {
        $payload = trim((string)$payload);
        if ($payload === '') {
            return '';
        }

        if (!str_starts_with($payload, 'v1:')) {
            return '';
        }

        $raw = base64_decode(substr($payload, 3), true);
        if ($raw === false || strlen($raw) < 29) {
            return '';
        }

        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $value = openssl_decrypt($cipher, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);

        return is_string($value) ? $value : '';
    }

    public static function mask(?string $payload): string
    {
        return trim((string)$payload) !== '' ? '••••••••••••' : '';
    }

    private static function key(): string
    {
        $secret = defined('CHAT_ENCRYPTION_KEY') ? (string)CHAT_ENCRYPTION_KEY : '';
        if ($secret === '' || $secret === 'change-this-chat-key-in-production') {
            $secret = json_encode(DB_SETTINGS, JSON_UNESCAPED_SLASHES) ?: ROOT;
        }

        return hash('sha256', $secret . '|vpn-manager-secrets', true);
    }
}
