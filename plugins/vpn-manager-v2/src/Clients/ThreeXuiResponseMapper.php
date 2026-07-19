<?php

namespace Fireball\VpnManagerV2\Clients;

use Fireball\VpnManagerV2\Exceptions\ThreeXuiResponseException;

final class ThreeXuiResponseMapper
{
    public function inbounds(array $response): array
    {
        $items = $response['obj'] ?? $response['inbounds'] ?? $response;
        if (!is_array($items) || !array_is_list($items)) {
            throw new ThreeXuiResponseException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_invalid_inbounds_response'));
        }

        return array_values(array_map(function (mixed $item): array {
            if (!is_array($item)) {
                throw new ThreeXuiResponseException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_invalid_inbounds_response'));
            }
            $item['settings'] = $this->json($item['settings'] ?? []);
            $item['streamSettings'] = $this->json($item['streamSettings'] ?? $item['stream_settings'] ?? []);

            return $item;
        }, $items));
    }

    public function inbound(array $response): array
    {
        $inbound = $response['obj'] ?? $response;
        if (!is_array($inbound) || array_is_list($inbound)) {
            throw new ThreeXuiResponseException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_invalid_inbound_response'));
        }
        $inbound['settings'] = $this->json($inbound['settings'] ?? []);
        $inbound['streamSettings'] = $this->json($inbound['streamSettings'] ?? $inbound['stream_settings'] ?? []);

        return $inbound;
    }

    public function clients(array $inbound): array
    {
        $settings = $this->json($inbound['settings'] ?? []);

        return array_values(array_filter(
            (array)($settings['clients'] ?? []),
            static fn(mixed $client): bool => is_array($client)
        ));
    }

    private function json(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        $decoded = is_string($value) ? json_decode($value, true) : null;

        return is_array($decoded) ? $decoded : [];
    }
}
