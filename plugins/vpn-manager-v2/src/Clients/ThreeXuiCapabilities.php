<?php

namespace Fireball\VpnManagerV2\Clients;

final class ThreeXuiCapabilities
{
    public function detect(array $inbounds): array
    {
        $protocols = [];
        $transports = [];
        foreach ($inbounds as $inbound) {
            if (!is_array($inbound)) {
                continue;
            }
            $protocol = strtolower(trim((string)($inbound['protocol'] ?? '')));
            if ($protocol !== '') {
                $protocols[$protocol] = true;
            }
            $stream = $inbound['streamSettings'] ?? [];
            if (is_string($stream)) {
                $stream = json_decode($stream, true);
            }
            $network = is_array($stream) ? strtolower(trim((string)($stream['network'] ?? ''))) : '';
            if ($network !== '') {
                $transports[$network] = true;
            }
        }

        return [
            'modern_client_api' => true,
            'legacy_inbound_api' => true,
            'protocols' => array_keys($protocols),
            'transports' => array_keys($transports),
            'detected_at' => date(DATE_ATOM),
        ];
    }
}
