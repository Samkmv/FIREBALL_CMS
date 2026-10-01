<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\Support\SubscriptionAccessPolicy;
use Fireball\VpnManagerV2\Exceptions\ValidationException;

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
            if ($timestamp === false || $timestamp <= 0) {
                throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_subscription_edit_expiry'));
            }
            $expiryTime = $timestamp * 1000;
        }

        $payload = [
            'flow' => trim((string)($node['flow'] ?? '')),
            // This is 3x-ui's per-client cipher field, not inbound TLS/Reality security.
            // Inbound security remains stored separately on the local node.
            'security' => 'auto',
            'email' => trim((string)($node['client_email'] ?? '')),
            'limitHwid' => max(0, (int)($subscription['device_limit'] ?? $node['device_limit'] ?? 0)),
            'limitIp' => max(0, (int)($subscription['ip_limit'] ?? $node['ip_limit'] ?? 0)),
            'totalGB' => max(0, (int)($node['traffic_limit_bytes'] ?? $subscription['traffic_limit_bytes'] ?? 0)),
            'expiryTime' => $expiryTime,
            'enable' => SubscriptionAccessPolicy::enabled($subscription, $node),
            'tgId' => 0,
            'group' => '',
            'comment' => '',
            'reset' => 0,
            'resetDay' => 0,
            'resetWeekday' => 0,
            'resetMax' => 0,
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
        // Creation defaults are not CMS-owned metadata on an existing client.
        foreach (['security', 'tgId', 'group', 'comment'] as $field) {
            if (array_key_exists($field, $remoteClient)) {
                unset($expected[$field]);
            }
        }
        // These fields belong to 3x-ui's automatic traffic-renewal feature.
        // FIREBALL does not expose that feature, so an ordinary limit/expiry
        // synchronization must preserve the values configured in the panel.
        foreach (['reset', 'resetDay', 'resetWeekday', 'resetMax'] as $field) {
            unset($expected[$field]);
        }
        $payload = array_replace($remoteClient, $expected);

        return $payload;
    }

    public function requiresSubId(string $protocol): bool
    {
        return strtolower(trim($protocol)) === 'vless';
    }
}
