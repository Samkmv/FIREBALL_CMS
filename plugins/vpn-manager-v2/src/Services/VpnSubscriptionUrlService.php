<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\Exceptions\VpnConfigValidationException;

final class VpnSubscriptionUrlService
{
    public function forToken(string $token): string
    {
        $token = strtolower(trim($token));
        if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            throw new VpnConfigValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_subscription_url'));
        }

        return base_url('/vpn-v2/subscription/' . rawurlencode($token));
    }

    public function isPublicSubscriptionUrl(string $url): bool
    {
        $path = (string)(parse_url(trim($url), PHP_URL_PATH) ?? '');

        return preg_match('~/vpn-v2/subscription/[a-f0-9]{64}/?$~', $path) === 1;
    }
}
