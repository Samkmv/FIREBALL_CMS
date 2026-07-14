<?php

namespace Fireball\VpnManagerV2\Support;

final class SubscriptionToken
{
    public static function generate(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function preview(string $token): string
    {
        $token = trim($token);
        if (strlen($token) <= 12) {
            return str_repeat('•', max(4, strlen($token)));
        }

        return substr($token, 0, 4) . '…' . substr($token, -4);
    }

    private function __construct()
    {
    }
}
