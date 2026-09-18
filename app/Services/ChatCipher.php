<?php
namespace App\Services;
// FIREBALL_CHAT2_FOUNDATION
class ChatCipher
{
    protected const CURRENT_VERSION = 'v3';
    protected const LEGACY_GCM_VERSION = 'v2';
    protected const CURRENT_CIPHER_METHOD = 'aes-256-gcm';
    protected const LEGACY_CIPHER_METHOD = 'AES-256-CBC';
    protected const GCM_TAG_LENGTH = 16;
    protected const LEGACY_DEFAULT_MASTER_KEY = 'change-this-chat-key-in-production';

    public static function encrypt(string $plainText): string
    {
        $plainText = trim($plainText);
        if ($plainText === '') return '';
        $secret = self::currentSecret();
        $keyId = self::keyId($secret);
        $date = date('Y-m-d');
        $key = self::deriveV3($date, $keyId, $secret);
        $iv = random_bytes(openssl_cipher_iv_length(self::CURRENT_CIPHER_METHOD));
        $tag = '';
        $cipher = openssl_encrypt(
            $plainText, self::CURRENT_CIPHER_METHOD, $key, OPENSSL_RAW_DATA,
            $iv, $tag, self::aadV3($keyId, $date), self::GCM_TAG_LENGTH
        );
        if ($cipher === false) throw new \RuntimeException('Could not encrypt chat message.');
        return implode('.', [
            self::CURRENT_VERSION, $keyId, $date,
            base64_encode($iv), base64_encode($tag), base64_encode($cipher)
        ]);
    }

    public static function decrypt(string $payload): string
    {
        $payload = trim($payload);
        if ($payload === '') return '';
        if (str_starts_with($payload, self::CURRENT_VERSION . '.')) return self::decryptV3($payload);
        if (str_starts_with($payload, self::LEGACY_GCM_VERSION . '.')) return self::decryptV2($payload);
        return self::decryptLegacy($payload);
    }

    public static function currentKeyId(): string
    {
        return self::keyId(self::currentSecret());
    }

    private static function decryptV3(string $payload): string
    {
        $parts = explode('.', $payload, 6);
        if (count($parts) !== 6) return '';
        [$version, $keyId, $date, $ivEncoded, $tagEncoded, $cipherEncoded] = $parts;
        if ($version !== self::CURRENT_VERSION
            || !preg_match('/^[a-f0-9]{16}$/D', $keyId)
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)) return '';
        $iv = base64_decode($ivEncoded, true);
        $tag = base64_decode($tagEncoded, true);
        $cipher = base64_decode($cipherEncoded, true);
        if ($iv === false || $tag === false || $cipher === false) return '';
        foreach (self::secrets() as $secret) {
            if (!hash_equals($keyId, self::keyId($secret))) continue;
            $plain = openssl_decrypt(
                $cipher, self::CURRENT_CIPHER_METHOD, self::deriveV3($date, $keyId, $secret),
                OPENSSL_RAW_DATA, $iv, $tag, self::aadV3($keyId, $date)
            );
            if ($plain !== false) return $plain;
        }
        return '';
    }

    private static function decryptV2(string $payload): string
    {
        $parts = explode('.', $payload, 5);
        if (count($parts) !== 5) return '';
        [$version, $date, $ivEncoded, $tagEncoded, $cipherEncoded] = $parts;
        if ($version !== self::LEGACY_GCM_VERSION || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)) return '';
        $iv = base64_decode($ivEncoded, true);
        $tag = base64_decode($tagEncoded, true);
        $cipher = base64_decode($cipherEncoded, true);
        if ($iv === false || $tag === false || $cipher === false) return '';
        foreach (self::secrets() as $secret) {
            $plain = openssl_decrypt(
                $cipher, self::CURRENT_CIPHER_METHOD, self::deriveV2($date, $secret),
                OPENSSL_RAW_DATA, $iv, $tag, self::aadV2($date)
            );
            if ($plain !== false) return $plain;
        }
        return '';
    }

    private static function decryptLegacy(string $payload): string
    {
        $decoded = base64_decode($payload, true);
        if ($decoded === false) return '';
        $ivLength = openssl_cipher_iv_length(self::LEGACY_CIPHER_METHOD);
        if (strlen($decoded) <= $ivLength) return '';
        $iv = substr($decoded, 0, $ivLength);
        $cipher = substr($decoded, $ivLength);
        foreach (self::secrets() as $secret) {
            $plain = openssl_decrypt(
                $cipher, self::LEGACY_CIPHER_METHOD, self::legacyKey($secret), OPENSSL_RAW_DATA, $iv
            );
            if ($plain !== false) return $plain;
        }
        return '';
    }

    private static function deriveV3(string $date, string $keyId, string $secret): string
    {
        $master = self::legacyKey($secret);
        $info = 'fireball-chat:v3:' . $keyId . ':' . $date;
        return function_exists('hash_hkdf')
            ? hash_hkdf('sha256', $master, 32, $info)
            : hash_hmac('sha256', $info, $master, true);
    }

    private static function deriveV2(string $date, string $secret): string
    {
        $master = self::legacyKey($secret);
        $info = 'fireball-chat:' . $date;
        return function_exists('hash_hkdf')
            ? hash_hkdf('sha256', $master, 32, $info)
            : hash_hmac('sha256', $info, $master, true);
    }

    private static function aadV3(string $keyId, string $date): string
    {
        return 'fireball-chat|v3|' . $keyId . '|' . $date;
    }
    private static function aadV2(string $date): string
    {
        return 'fireball-chat|v2|' . $date;
    }
    private static function legacyKey(string $secret): string
    {
        return hash('sha256', $secret, true);
    }
    private static function keyId(string $secret): string
    {
        return substr(hash('sha256', $secret), 0, 16);
    }
    private static function currentSecret(): string
    {
        if (!defined('CHAT_ENCRYPTION_KEY')) throw new \RuntimeException('CHAT_ENCRYPTION_KEY is not defined.');
        $secret = trim((string)CHAT_ENCRYPTION_KEY);
        if ($secret === '' || hash_equals(self::LEGACY_DEFAULT_MASTER_KEY, $secret)) {
            throw new \RuntimeException('CHAT_ENCRYPTION_KEY must be unique for this installation.');
        }
        return $secret;
    }
    private static function secrets(): array
    {
        $secrets = [];
        if (defined('CHAT_ENCRYPTION_KEY') && trim((string)CHAT_ENCRYPTION_KEY) !== '') {
            $secrets[] = trim((string)CHAT_ENCRYPTION_KEY);
        }
        $secrets[] = self::LEGACY_DEFAULT_MASTER_KEY;
        return array_values(array_unique($secrets));
    }
}
