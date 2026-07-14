<?php

namespace Fireball\VpnManagerV2\Support;

final class CountryFlag
{
    public static function emoji(?string $countryCode): string
    {
        $code = strtoupper(trim((string)$countryCode));
        if (preg_match('/^[A-Z]{2}$/', $code) !== 1) {
            return '';
        }

        return mb_chr(127397 + ord($code[0]), 'UTF-8') . mb_chr(127397 + ord($code[1]), 'UTF-8');
    }

    private function __construct()
    {
    }
}
