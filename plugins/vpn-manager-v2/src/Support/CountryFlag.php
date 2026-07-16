<?php

namespace Fireball\VpnManagerV2\Support;

use Fireball\VpnManagerV2\Services\CountryFlagService;

final class CountryFlag
{
    public static function emoji(?string $countryCode): string
    {
        return (new CountryFlagService())->emoji($countryCode);
    }

    private function __construct()
    {
    }
}
