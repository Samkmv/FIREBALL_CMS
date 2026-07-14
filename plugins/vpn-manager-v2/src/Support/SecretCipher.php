<?php

namespace Fireball\VpnManagerV2\Support;

use App\Services\ChatCipher;
use Fireball\VpnManagerV2\Exceptions\SecretException;

final class SecretCipher
{
    public static function encrypt(string $plainText): string
    {
        $plainText = trim($plainText);
        if ($plainText === '' || self::isMask($plainText)) {
            return '';
        }

        $encrypted = ChatCipher::encrypt($plainText);
        if ($encrypted === '') {
            throw new SecretException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_secret_encrypt'));
        }

        return $encrypted;
    }

    public static function decrypt(?string $encrypted): string
    {
        $encrypted = trim((string)$encrypted);
        if ($encrypted === '') {
            return '';
        }

        $plainText = ChatCipher::decrypt($encrypted);
        if ($plainText === '') {
            throw new SecretException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_secret_decrypt'));
        }

        return $plainText;
    }

    public static function shouldReplace(?string $input): bool
    {
        $input = trim((string)$input);

        return $input !== '' && !self::isMask($input);
    }

    public static function isMask(string $value): bool
    {
        $value = trim($value);
        if ($value === '' || str_contains($value, '•')) {
            return $value !== '';
        }

        return preg_match('/^[*xX_-]{6,}$/', $value) === 1;
    }

    private function __construct()
    {
    }
}
