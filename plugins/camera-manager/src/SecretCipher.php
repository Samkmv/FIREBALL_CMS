<?php

namespace Fireball\CameraManager;

use RuntimeException;

final class SecretCipher
{
    private const VERSION = 'v1';
    private const METHOD = 'aes-256-gcm';
    private const AAD = 'fireball-camera-manager|v1';

    public static function encrypt(string $plainText): string
    {
        if ($plainText === '') {
            return '';
        }

        if (!function_exists('openssl_encrypt')) {
            throw new RuntimeException('OpenSSL is required to store RTSP credentials.');
        }

        $iv = random_bytes(openssl_cipher_iv_length(self::METHOD));
        $tag = '';
        $cipherText = openssl_encrypt(
            $plainText,
            self::METHOD,
            self::key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            self::AAD,
            16
        );

        if ($cipherText === false) {
            throw new RuntimeException('Unable to encrypt the RTSP password.');
        }

        return implode('.', [
            self::VERSION,
            base64_encode($iv),
            base64_encode($tag),
            base64_encode($cipherText),
        ]);
    }

    public static function decrypt(string $payload): string
    {
        if ($payload === '') {
            return '';
        }

        $parts = explode('.', $payload, 4);
        if (count($parts) !== 4 || $parts[0] !== self::VERSION) {
            throw new RuntimeException('The stored RTSP password has an unsupported format.');
        }

        $iv = base64_decode($parts[1], true);
        $tag = base64_decode($parts[2], true);
        $cipherText = base64_decode($parts[3], true);
        if ($iv === false || $tag === false || $cipherText === false) {
            throw new RuntimeException('The stored RTSP password is damaged.');
        }

        $plainText = openssl_decrypt(
            $cipherText,
            self::METHOD,
            self::key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            self::AAD
        );

        if ($plainText === false) {
            throw new RuntimeException('Unable to decrypt the RTSP password. Check CHAT_ENCRYPTION_KEY.');
        }

        return $plainText;
    }

    private static function key(): string
    {
        if (!defined('CHAT_ENCRYPTION_KEY') || trim((string)CHAT_ENCRYPTION_KEY) === '') {
            throw new RuntimeException('CHAT_ENCRYPTION_KEY must be configured before saving RTSP credentials.');
        }

        return hash('sha256', 'camera-manager|' . (string)CHAT_ENCRYPTION_KEY, true);
    }
}
