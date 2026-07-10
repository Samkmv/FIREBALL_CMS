<?php

namespace Fireball\VpnManager\Services;

use Fireball\VpnManager\Support\Crypto;

final class SubscriptionLinkService
{
    public function generateToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public function encryptToken(string $token): ?string
    {
        return Crypto::encrypt($token);
    }

    public function tokenHash(string $token): string
    {
        return hash('sha256', $token);
    }

    public function tokenPreview(string $token): string
    {
        return substr($token, 0, 16);
    }

    public function configName(string $serverName): string
    {
        $settings = SettingsService::settings();
        $template = (string)($settings['server_name_template'] ?? '{service} — {server}');

        return str_replace(
            ['{service}', '{server}'],
            [(string)$settings['service_name'], $serverName],
            $template
        );
    }

    public function subscriptionUrl(array $subscription): string
    {
        $encrypted = (string)($subscription['subscription_token_encrypted'] ?? '');
        $token = Crypto::decrypt($encrypted);
        if ($token === '') {
            return trim((string)($subscription['subscription_url'] ?? ''));
        }

        return base_href('/vpn/subscription/' . rawurlencode($token));
    }
}
