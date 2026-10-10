<?php

namespace Fireball\VpnManagerV2\Services;

final class VpnSubscriptionMetadataService
{
    public function profileTitle(array $subscription, array $settings): string
    {
        // Keep the profile title stable. Expiration is still exposed through
        // subscription-userinfo and remains fully enforced by the endpoint.
        return trim((string)($settings['subscription_name'] ?? 'VPN V2')) ?: 'VPN V2';
    }

    public function headers(array $subscription, array $settings, array $traffic = []): array
    {
        $storedUsed = max(0, (int)($subscription['traffic_used_bytes'] ?? 0));
        $upload = max(0, (int)($traffic['upload_bytes'] ?? 0));
        $download = max(0, (int)($traffic['download_bytes'] ?? 0));
        $confirmedUsed = max($storedUsed, (int)($traffic['traffic_used_bytes'] ?? 0), $upload + $download);
        if (($upload + $download) < $confirmedUsed) {
            // Older nodes may only have the aggregate counter. Preserve the
            // factual total without inventing additional upload traffic.
            $download += $confirmedUsed - ($upload + $download);
        }

        $total = max(0, (int)($subscription['traffic_limit_bytes'] ?? 0));
        $expire = $this->timestamp($subscription['expires_at'] ?? null) ?? 0;
        $intervalMinutes = max(1, (int)($settings['sync_interval_minutes'] ?? 60));
        $headers = [
            'subscription-userinfo' => 'upload=' . $upload
                . '; download=' . $download
                . '; total=' . $total
                . '; expire=' . $expire,
            'profile-update-interval' => (string)max(1, (int)ceil($intervalMinutes / 60)),
        ];

        $supportUrl = trim((string)($settings['support_url'] ?? ''));
        if ($this->httpUrl($supportUrl)) {
            $headers['support-url'] = $supportUrl;
        }
        // Advanced Happ parameter: requires Provider ID even with Smart Connect disabled.
        // This restricts the app UI, not tunnel authentication.
        $visibility = match ($settings['happ_server_settings_policy'] ?? 'default') {
            'hide' => '1',
            'show' => '0',
            default => null,
        };
        $smartConnect = (new \Fireball\VpnManagerV2\Validators\SmartConnectValidator())->validate($settings, false);
        $providerId = $smartConnect['smart_connect_happ_provider_id'];
        if ($visibility !== null && $providerId !== '') {
            $headers['providerid'] = $providerId;
            $headers['hide-settings'] = $visibility;
        }

        return array_replace($headers,
            (new \Fireball\VpnManagerV2\Support\HappRoutingProfile())->headers($settings),
            (new \Fireball\VpnManagerV2\Support\HappSmartConnect())->headers($settings));
    }

    private function timestamp(mixed $value): ?int
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }
        $timestamp = strtotime($value);

        return $timestamp !== false && $timestamp > 0 ? $timestamp : null;
    }

    private function httpUrl(string $url): bool
    {
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        return in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }
}
