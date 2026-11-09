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
            if (!is_array($item) || !$this->validInboundId($item)) {
                throw new ThreeXuiResponseException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_invalid_inbounds_response'));
            }
            $item['settings'] = $this->json($item['settings'] ?? null);
            $item['streamSettings'] = $this->json($item['streamSettings'] ?? $item['stream_settings'] ?? []);
            $this->clients($item);

            return $item;
        }, $items));
    }

    public function inbound(array $response): array
    {
        $inbound = $response['obj'] ?? $response;
        if (!is_array($inbound) || !$this->validInboundId($inbound)) {
            throw new ThreeXuiResponseException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_invalid_inbound_response'));
        }
        $inbound['settings'] = $this->json($inbound['settings'] ?? null);
        $inbound['streamSettings'] = $this->json($inbound['streamSettings'] ?? $inbound['stream_settings'] ?? []);
        $this->clients($inbound);

        return $inbound;
    }

    public function clients(array $inbound): array
    {
        $settings = $this->json($inbound['settings'] ?? []);

        $clients = $settings['clients'] ?? [];
        if (!is_array($clients) || !array_is_list($clients)) {
            throw new ThreeXuiResponseException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_invalid_client_response'));
        }
        foreach ($clients as $client) {
            if (!is_array($client) || array_is_list($client)) {
                throw new ThreeXuiResponseException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_invalid_client_response'));
            }
        }

        return $clients;
    }

    private function validInboundId(array $inbound): bool
    {
        return filter_var($inbound['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false;
    }

    private function json(mixed $value): array
    {
        if ($value === '') {
            return [];
        }
        if (is_array($value)) {
            return $value;
        }
        $decoded = is_string($value) ? json_decode($value, true) : null;

        if (!is_array($decoded)) {
            throw new ThreeXuiResponseException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_invalid_inbound_response'));
        }

        return $decoded;
    }
}
