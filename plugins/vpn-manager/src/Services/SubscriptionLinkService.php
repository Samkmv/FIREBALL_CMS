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

    public function subscriptionPath(string $token, string $format = 'plain'): string
    {
        $path = '/vpn-subscription.php?token=' . rawurlencode($token);

        return $this->appendFormat($path, $format);
    }

    public function subscriptionUrlForToken(string $token, string $format = 'plain'): string
    {
        $token = trim($token);
        if ($token === '') {
            return '';
        }

        $settings = SettingsService::settings();
        $baseUrl = trim((string)($settings['subscription_public_base_url'] ?? ''));
        if ($baseUrl !== '') {
            return rtrim($baseUrl, '/') . $this->subscriptionPath($token, $format);
        }

        return base_url($this->subscriptionPath($token, $format));
    }

    public function configName(string $serverName): string
    {
        $settings = SettingsService::settings();
        $template = (string)($settings['server_name_template'] ?? '{flag} {server}');

        return $this->cleanConfigName(str_replace(
            ['{flag}', '{service}', '{country}', '{country_code}', '{city}', '{server}', '{protocol}'],
            ['', (string)$settings['service_name'], '', '', '', $serverName, ''],
            $template
        ));
    }

    public function configNameForServer(array $server, string $protocol = ''): string
    {
        $settings = SettingsService::settings();
        $flagsEnabled = !empty($settings['show_country_flags'])
            && !empty($server['show_flag']);
        $flagService = new CountryFlagService();
        $countryCode = $flagService->normalizeCountryCode((string)($server['country_code'] ?? ''));
        $flag = $flagsEnabled && $flagService->isValidCountryCode($countryCode)
            ? $flagService->flagFromCountryCode($countryCode)
            : '';
        $country = trim((string)($server['country_name'] ?? $server['country'] ?? ''));
        if ($country === '' && $countryCode !== '') {
            $country = $flagService->countryName($countryCode);
        }
        $serverName = trim((string)($server['server_name'] ?? $server['name'] ?? $server['code'] ?? 'VPN'));
        $template = (string)($settings['server_name_template'] ?? '{flag} {server}');

        return $this->cleanConfigName(str_replace(
            ['{flag}', '{service}', '{country}', '{country_code}', '{city}', '{server}', '{protocol}'],
            [
                $flag,
                (string)$settings['service_name'],
                $country,
                $countryCode,
                trim((string)($server['city'] ?? '')),
                $serverName,
                strtoupper(trim($protocol)),
            ],
            $template
        ));
    }

    public function subscriptionUrl(array $subscription, string $format = 'plain'): string
    {
        $encrypted = (string)($subscription['subscription_token_encrypted'] ?? '');
        $token = Crypto::decrypt($encrypted);
        if ($token === '') {
            $token = trim((string)($subscription['subscription_token'] ?? ''));
        }
        if ($token === '') {
            return $this->appendFormat(trim((string)($subscription['subscription_url'] ?? '')), $format);
        }

        return $this->subscriptionUrlForToken($token, $format);
    }

    public function isLocalUrl(string $url): bool
    {
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));

        return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }

    private function appendFormat(string $url, string $format): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $format = strtolower(trim($format));
        if (!in_array($format, ['plain', 'base64'], true)) {
            return $url;
        }

        if (str_contains($url, 'format=')) {
            return $url;
        }

        if ($format === 'plain') {
            return $url;
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . 'format=base64';
    }

    private function cleanConfigName(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
        $value = preg_replace('/^((?:[\x{1F1E6}-\x{1F1FF}]{2}))\s+\1\s+/u', '$1 ', $value) ?? $value;
        $value = preg_replace('/\s+([,;:])/u', '$1', $value) ?? $value;

        return trim($value) !== '' ? trim($value) : 'VPN';
    }
}
