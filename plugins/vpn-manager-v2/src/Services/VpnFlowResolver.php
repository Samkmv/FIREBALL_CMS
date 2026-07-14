<?php

namespace Fireball\VpnManagerV2\Services;

final class VpnFlowResolver
{
    public const VISION = 'xtls-rprx-vision';

    public function resolveDefaultFlow(array $inbound): ?string
    {
        return in_array(self::VISION, $this->allowedFlows($inbound), true) ? self::VISION : null;
    }

    public function allowedFlows(array $inbound): array
    {
        [$protocol, $network, $security] = $this->dimensions($inbound);
        if ($protocol === 'vless'
            && in_array($network, ['tcp', 'raw'], true)
            && $security === 'reality') {
            return [null, self::VISION];
        }

        return [null];
    }

    public function isFlowCompatible(?string $flow, array $inbound): bool
    {
        return in_array($this->normalizeFlow($flow), $this->allowedFlows($inbound), true);
    }

    public function normalizeFlow(?string $flow): ?string
    {
        $flow = strtolower(trim((string)$flow));

        return $flow !== '' ? $flow : null;
    }

    private function dimensions(array $inbound): array
    {
        $protocol = strtolower(trim((string)($inbound['protocol'] ?? '')));
        $network = strtolower(trim((string)($inbound['network'] ?? '')));
        $security = strtolower(trim((string)($inbound['security'] ?? '')));

        if ($network === '' || $security === '') {
            $rawStream = $inbound['streamSettings'] ?? $inbound['stream_settings'] ?? $inbound['stream_settings_json'] ?? null;
            if (is_string($rawStream)) {
                $decoded = json_decode($rawStream, true);
                $rawStream = is_array($decoded) ? $decoded : [];
            }
            if (is_array($rawStream)) {
                $network = $network !== '' ? $network : strtolower(trim((string)($rawStream['network'] ?? 'tcp')));
                $security = $security !== '' ? $security : strtolower(trim((string)($rawStream['security'] ?? 'none')));
            }
        }

        return [$protocol, $network !== '' ? $network : 'tcp', $security !== '' ? $security : 'none'];
    }
}
