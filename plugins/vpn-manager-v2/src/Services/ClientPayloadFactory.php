<?php

namespace Fireball\VpnManagerV2\Services;

final class ClientPayloadFactory
{
    public function build(array $subscription, array $node): array
    {
        $protocol = strtolower(trim((string)($node['protocol'] ?? '')));
        $clientId = trim((string)($node['client_uuid'] ?? ''));
        $expiresAt = trim((string)($subscription['expires_at'] ?? ''));
        $expiryTime = 0;
        if ($expiresAt !== '') {
            $timestamp = strtotime($expiresAt);
            $expiryTime = $timestamp !== false ? $timestamp * 1000 : 0;
        }

        $payload = [
            'flow' => trim((string)($node['flow'] ?? '')),
            // This is 3x-ui's per-client cipher field, not inbound TLS/Reality security.
            // Inbound security remains stored separately on the local node.
            'security' => 'auto',
            'email' => trim((string)($node['client_email'] ?? '')),
            'limitIp' => max(0, (int)($node['device_limit'] ?? $subscription['device_limit'] ?? 0)),
            'totalGB' => max(0, (int)($node['traffic_limit_bytes'] ?? $subscription['traffic_limit_bytes'] ?? 0)),
            'expiryTime' => $expiryTime,
            'enable' => true,
            'tgId' => 0,
            'group' => '',
            'comment' => '',
            'reset' => 0,
        ];

        if ($this->requiresSubId($protocol) && trim((string)($node['client_sub_id'] ?? '')) !== '') {
            $payload['subId'] = (string)$node['client_sub_id'];
        }

        if ($protocol === 'trojan') {
            $payload['password'] = $clientId;
        } else {
            $payload['id'] = $clientId;
        }

        return $payload;
    }

    public function requiresSubId(string $protocol): bool
    {
        return strtolower(trim($protocol)) === 'vless';
    }
}
