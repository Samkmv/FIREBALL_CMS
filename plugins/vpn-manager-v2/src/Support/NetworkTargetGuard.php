<?php

namespace Fireball\VpnManagerV2\Support;

use Fireball\VpnManagerV2\Exceptions\ThreeXuiTransportException;
use Fireball\VpnManagerV2\Exceptions\ValidationException;

final class NetworkTargetGuard
{
    public function assertConfigurationHost(string $host, bool $allowPrivate): void
    {
        $host = strtolower(trim($host, "[] \t\n\r\0\x0B"));
        if ($host === '' || (!$allowPrivate && ($host === 'localhost' || str_ends_with($host, '.localhost')))) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_private_panel_url'));
        }
        if (!$allowPrivate && filter_var($host, FILTER_VALIDATE_IP) !== false && !$this->isPublicIp($host)) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_private_panel_url'));
        }
    }

    public function assertRequestUrl(string $url, bool $allowPrivate): void
    {
        $this->validatedRequestAddresses($url, $allowPrivate);
    }

    public function validatedRequestAddresses(string $url, bool $allowPrivate): array
    {
        $host = (string)(parse_url($url, PHP_URL_HOST) ?: '');
        try {
            $this->assertConfigurationHost($host, $allowPrivate);
        } catch (ValidationException $exception) {
            throw new ThreeXuiTransportException($exception->getMessage(), 0, $exception);
        }
        $addresses = $this->addresses($host);
        if ($addresses === []) {
            throw new ThreeXuiTransportException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_panel_dns'));
        }
        if (!$allowPrivate) {
            foreach ($addresses as $address) {
                if (!$this->isPublicIp($address)) {
                    throw new ThreeXuiTransportException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_private_panel_url'));
                }
            }
        }

        return $addresses;
    }

    private function addresses(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }
        $addresses = [];
        foreach ((array)@dns_get_record($host, DNS_A | DNS_AAAA) as $record) {
            $address = (string)($record['ip'] ?? $record['ipv6'] ?? '');
            if ($address !== '') {
                $addresses[$address] = true;
            }
        }
        foreach ((array)@gethostbynamel($host) as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP) !== false) {
                $addresses[(string)$address] = true;
            }
        }

        return array_keys($addresses);
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }
}
