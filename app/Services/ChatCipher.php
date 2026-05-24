<?php

namespace App\Services;

class ChatCipher
{

    protected const CURRENT_VERSION = 'v2';
    protected const CURRENT_CIPHER_METHOD = 'aes-256-gcm';
    protected const LEGACY_CIPHER_METHOD = 'AES-256-CBC';
    protected const GCM_TAG_LENGTH = 16;

    public static function encrypt(string $plainText): string
    {
        $plainText = trim($plainText);
        if ($plainText === '') {
            return '';
        }

        $keyDate = date('Y-m-d');
        $key = self::deriveKey($keyDate);
        $ivLength = openssl_cipher_iv_length(self::CURRENT_CIPHER_METHOD);
        $iv = random_bytes($ivLength);
        $aad = self::buildAad($keyDate);
        $tag = '';

        $cipherText = openssl_encrypt(
            $plainText,
            self::CURRENT_CIPHER_METHOD,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aad,
            self::GCM_TAG_LENGTH
        );
        if ($cipherText === false) {
            return '';
        }

        return implode('.', [
            self::CURRENT_VERSION,
            $keyDate,
            base64_encode($iv),
            base64_encode($tag),
            base64_encode($cipherText),
        ]);
    }

    public static function decrypt(string $payload): string
    {
        $payload = trim($payload);
        if ($payload === '') {
            return '';
        }

        if (str_starts_with($payload, self::CURRENT_VERSION . '.')) {
            return self::decryptCurrentPayload($payload);
        }

        return self::decryptLegacyPayload($payload);
    }

    protected static function decryptCurrentPayload(string $payload): string
    {
        $parts = explode('.', $payload, 5);
        if (count($parts) !== 5) {
            return '';
        }

        [$version, $keyDate, $ivEncoded, $tagEncoded, $cipherTextEncoded] = $parts;
        if ($version !== self::CURRENT_VERSION || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $keyDate)) {
            return '';
        }

        $iv = base64_decode($ivEncoded, true);
        $tag = base64_decode($tagEncoded, true);
        $cipherText = base64_decode($cipherTextEncoded, true);

        if ($iv === false || $tag === false || $cipherText === false) {
            return '';
        }

        $plainText = openssl_decrypt(
            $cipherText,
            self::CURRENT_CIPHER_METHOD,
            self::deriveKey($keyDate),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            self::buildAad($keyDate)
        );

        return $plainText === false ? '' : $plainText;
    }

    protected static function decryptLegacyPayload(string $payload): string
    {
        $decoded = base64_decode($payload, true);
        if ($decoded === false) {
            return '';
        }

        $ivLength = openssl_cipher_iv_length(self::LEGACY_CIPHER_METHOD);
        if (strlen($decoded) <= $ivLength) {
            return '';
        }

        $iv = substr($decoded, 0, $ivLength);
        $cipherText = substr($decoded, $ivLength);

        $plainText = openssl_decrypt($cipherText, self::LEGACY_CIPHER_METHOD, self::getLegacyKey(), OPENSSL_RAW_DATA, $iv);

        return $plainText === false ? '' : $plainText;
    }

    protected static function deriveKey(string $keyDate): string
    {
        $masterKey = self::getLegacyKey();
        $info = 'fireball-chat:' . $keyDate;

        if (function_exists('hash_hkdf')) {
            return hash_hkdf('sha256', $masterKey, 32, $info);
        }

        return hash_hmac('sha256', $info, $masterKey, true);
    }

    protected static function buildAad(string $keyDate): string
    {
        return 'fireball-chat|' . self::CURRENT_VERSION . '|' . $keyDate;
    }

    protected static function getLegacyKey(): string
    {
        return hash('sha256', CHAT_ENCRYPTION_KEY, true);
    }

}
