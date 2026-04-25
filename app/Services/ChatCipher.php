<?php

namespace App\Services;

class ChatCipher
{

    protected const CIPHER_METHOD = 'AES-256-CBC';

    public static function encrypt(string $plainText): string
    {
        $plainText = trim($plainText);
        if ($plainText === '') {
            return '';
        }

        $key = self::getKey();
        $ivLength = openssl_cipher_iv_length(self::CIPHER_METHOD);
        $iv = random_bytes($ivLength);

        $cipherText = openssl_encrypt($plainText, self::CIPHER_METHOD, $key, OPENSSL_RAW_DATA, $iv);
        if ($cipherText === false) {
            return '';
        }

        return base64_encode($iv . $cipherText);
    }

    public static function decrypt(string $payload): string
    {
        $payload = trim($payload);
        if ($payload === '') {
            return '';
        }

        $decoded = base64_decode($payload, true);
        if ($decoded === false) {
            return '';
        }

        $ivLength = openssl_cipher_iv_length(self::CIPHER_METHOD);
        if (strlen($decoded) <= $ivLength) {
            return '';
        }

        $iv = substr($decoded, 0, $ivLength);
        $cipherText = substr($decoded, $ivLength);

        $plainText = openssl_decrypt($cipherText, self::CIPHER_METHOD, self::getKey(), OPENSSL_RAW_DATA, $iv);

        return $plainText === false ? '' : $plainText;
    }

    protected static function getKey(): string
    {
        return hash('sha256', CHAT_ENCRYPTION_KEY, true);
    }

}
