<?php

namespace Fireball\VpnManager\Services;

final class SubscriptionLinkService
{
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
        $tokenPreview = trim((string)($subscription['subscription_token_preview'] ?? ''));
        if ($tokenPreview === '') {
            return '';
        }

        return base_href('/vpn/subscription/' . rawurlencode($tokenPreview));
    }
}
