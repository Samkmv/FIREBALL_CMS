<?php

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Generates and verifies RFC 6238 TOTP codes without external services.
 */
class TwoFactorService
{
    protected const CIPHER = 'aes-256-gcm';
    protected const PERIOD = 30;
    protected const DIGITS = 6;
    protected const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(int $bytes = 20): string
    {
        return $this->base32Encode(random_bytes(max(16, $bytes)));
    }

    public function provisioningUri(string $secret, string $account, string $issuer): string
    {
        $issuer = trim($issuer) ?: 'FIREBALL CMS';
        $label = rawurlencode($issuer . ':' . trim($account));

        return sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
            $label,
            rawurlencode($this->normalizeSecret($secret)),
            rawurlencode($issuer),
            self::DIGITS,
            self::PERIOD
        );
    }

    public function qrCodeDataUri(string $provisioningUri): string
    {
        if (trim($provisioningUri) === '') {
            return '';
        }

        try {
            $renderer = new ImageRenderer(
                new RendererStyle(280, 12),
                new SvgImageBackEnd()
            );
            $svg = (new Writer($renderer))->writeString($provisioningUri);

            return 'data:image/svg+xml;base64,' . base64_encode($svg);
        } catch (\Throwable) {
            return '';
        }
    }

    public function verifyCode(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\D+/', '', $code) ?? '';
        if (strlen($code) !== self::DIGITS) {
            return false;
        }

        $counter = intdiv(time(), self::PERIOD);
        for ($offset = -max(0, $window); $offset <= max(0, $window); $offset++) {
            if (hash_equals($this->codeAtCounter($secret, $counter + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    public function codeAtTimestamp(string $secret, int $timestamp): string
    {
        return $this->codeAtCounter($secret, intdiv($timestamp, self::PERIOD));
    }

    public function encryptSecret(string $secret): string
    {
        $iv = random_bytes(openssl_cipher_iv_length(self::CIPHER));
        $tag = '';
        $cipherText = openssl_encrypt(
            $this->normalizeSecret($secret),
            self::CIPHER,
            $this->encryptionKey(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            'fireball-two-factor',
            16
        );

        if ($cipherText === false) {
            throw new \RuntimeException('Unable to encrypt the two-factor secret.');
        }

        return implode('.', [
            'v1',
            base64_encode($iv),
            base64_encode($tag),
            base64_encode($cipherText),
        ]);
    }

    public function decryptSecret(string $payload): string
    {
        $parts = explode('.', trim($payload), 4);
        if (count($parts) !== 4 || $parts[0] !== 'v1') {
            return '';
        }

        $iv = base64_decode($parts[1], true);
        $tag = base64_decode($parts[2], true);
        $cipherText = base64_decode($parts[3], true);
        if ($iv === false || $tag === false || $cipherText === false) {
            return '';
        }

        $secret = openssl_decrypt(
            $cipherText,
            self::CIPHER,
            $this->encryptionKey(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            'fireball-two-factor'
        );

        return $secret === false ? '' : $this->normalizeSecret($secret);
    }

    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        while (count($codes) < max(1, $count)) {
            $value = strtoupper(bin2hex(random_bytes(5)));
            $codes[] = substr($value, 0, 5) . '-' . substr($value, 5, 5);
        }

        return $codes;
    }

    public function hashRecoveryCode(string $code): string
    {
        return hash_hmac('sha256', $this->normalizeRecoveryCode($code), $this->encryptionKey());
    }

    public function normalizeRecoveryCode(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]/i', '', $code) ?? '');
    }

    protected function codeAtCounter(string $secret, int $counter): string
    {
        $key = $this->base32Decode($secret);
        if ($key === '') {
            return str_repeat('0', self::DIGITS);
        }

        $binaryCounter = pack('N2', intdiv($counter, 4294967296), $counter % 4294967296);
        $hash = hash_hmac('sha1', $binaryCounter, $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $value = (
            ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff)
        ) % (10 ** self::DIGITS);

        return str_pad((string)$value, self::DIGITS, '0', STR_PAD_LEFT);
    }

    protected function base32Encode(string $binary): string
    {
        $bits = '';
        foreach (str_split($binary) as $character) {
            $bits .= str_pad(decbin(ord($character)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $encoded .= self::BASE32_ALPHABET[bindec($chunk)];
        }

        return $encoded;
    }

    protected function base32Decode(string $secret): string
    {
        $secret = $this->normalizeSecret($secret);
        $bits = '';
        foreach (str_split($secret) as $character) {
            $position = strpos(self::BASE32_ALPHABET, $character);
            if ($position === false) {
                return '';
            }
            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $decoded = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $decoded .= chr(bindec($chunk));
            }
        }

        return $decoded;
    }

    protected function normalizeSecret(string $secret): string
    {
        return strtoupper(preg_replace('/[^A-Z2-7]/i', '', $secret) ?? '');
    }

    protected function encryptionKey(): string
    {
        return hash('sha256', (string)CHAT_ENCRYPTION_KEY . '|fireball-two-factor', true);
    }
}
