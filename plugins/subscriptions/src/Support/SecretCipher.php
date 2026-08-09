<?php

namespace Fireball\Subscriptions\Support;

final class SecretCipher
{
    private const PREFIX = 'subscriptions:v1:';
    private const CIPHER = 'aes-256-gcm';

    public static function encrypt(string $plainText): string
    {
        if ($plainText === '') {
            return '';
        }

        $iv = random_bytes(openssl_cipher_iv_length(self::CIPHER));
        $tag = '';
        $cipherText = openssl_encrypt(
            $plainText,
            self::CIPHER,
            self::key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            self::PREFIX,
            16
        );
        if ($cipherText === false) {
            throw new \RuntimeException('Could not encrypt the payment secret.');
        }

        return self::PREFIX . base64_encode($iv . $tag . $cipherText);
    }

    public static function decrypt(string $payload): string
    {
        if ($payload === '') {
            return '';
        }
        if (!str_starts_with($payload, self::PREFIX)) {
            return '';
        }

        $decoded = base64_decode(substr($payload, strlen(self::PREFIX)), true);
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        if ($decoded === false || strlen($decoded) <= $ivLength + 16) {
            return '';
        }

        $plainText = openssl_decrypt(
            substr($decoded, $ivLength + 16),
            self::CIPHER,
            self::key(),
            OPENSSL_RAW_DATA,
            substr($decoded, 0, $ivLength),
            substr($decoded, $ivLength, 16),
            self::PREFIX
        );

        return $plainText === false ? '' : $plainText;
    }

    private static function key(): string
    {
        $master = defined('CHAT_ENCRYPTION_KEY') ? (string)CHAT_ENCRYPTION_KEY : '';
        if ($master === '' || $master === 'change-this-chat-key-in-production') {
            throw new \RuntimeException('Configure CHAT_ENCRYPTION_KEY before saving Robokassa secrets.');
        }

        return hash_hmac('sha256', 'fireball-subscriptions-secrets', $master, true);
    }
}
