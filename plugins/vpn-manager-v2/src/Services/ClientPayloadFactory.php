<?php

namespace Fireball\VpnManagerV2\Services;

final class ClientPayloadFactory
{
    public function build(array $subscription, array $node): array
    {
        $protocol = strtolower(trim((string)($node['protocol'] ?? '')));
        $clientId = (new RemoteClientCredentialService())->credential($node);
        $expiresAt = trim((string)($subscription['expires_at'] ?? ''));
        $expiryTime = 0;
        if ($expiresAt !== '') {
            $timestamp = strtotime($expiresAt);
            $expiryTime = $timestamp !== false ? $timestamp * 1000 : 0;
        }

        $status = strtolower(trim((string)($subscription['status'] ?? 'active')));
        $payload = [
            'flow' => trim((string)($node['flow'] ?? '')),
            // This is 3x-ui's per-client cipher field, not inbound TLS/Reality security.
            // Inbound security remains stored separately on the local node.
            'security' => 'auto',
            'email' => trim((string)($node['client_email'] ?? '')),
            'limitIp' => max(0, (int)($node['device_limit'] ?? $subscription['device_limit'] ?? 0)),
            'totalGB' => max(0, (int)($node['traffic_limit_bytes'] ?? $subscription['traffic_limit_bytes'] ?? 0)),
            'expiryTime' => $expiryTime,
            'enable' => array_key_exists('desired_enabled', $node)
                ? !empty($node['desired_enabled'])
                : !in_array($status, ['suspended', 'expired', 'traffic_exceeded', 'deleting', 'delete_failed'], true),
            'tgId' => 0,
            'group' => '',
            'comment' => '',
            'reset' => 0,
        ];

        if ($this->requiresSubId($protocol) && trim((string)($node['client_sub_id'] ?? '')) !== '') {
            $payload['subId'] = (string)$node['client_sub_id'];
        }

        if ((new RemoteClientCredentialService())->usesPassword($protocol)) {
            $payload['password'] = $clientId;
        } else {
            $payload['id'] = $clientId;
        }

        return $payload;
    }

    public function mergeForUpdate(array $remoteClient, array $expected): array
    {
        $payload = array_replace($remoteClient, $expected);
        // 3x-ui treats reset as an explicit command. Ordinary edits must always preserve counters.
        $payload['reset'] = 0;

        return $payload;
    }

    public function requiresSubId(string $protocol): bool
    {
        return strtolower(trim($protocol)) === 'vless';
    }
}
