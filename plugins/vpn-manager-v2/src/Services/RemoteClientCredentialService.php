<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\Support\SecretCipher;

final class RemoteClientCredentialService
{
    public function credential(array $node): string
    {
        if ($this->usesPassword((string)($node['protocol'] ?? ''))) {
            $encrypted = trim((string)($node['encrypted_client_credential'] ?? ''));
            if ($encrypted !== '') {
                return SecretCipher::decrypt($encrypted);
            }
        }

        // Legacy rows used client_uuid as the generic credential. Retain that
        // fallback so upgrades do not rewrite a working remote client.
        return trim((string)($node['client_uuid'] ?? ''));
    }

    public function encryptForStorage(string $protocol, ?string $credential): ?string
    {
        $credential = trim((string)$credential);

        return $this->usesPassword($protocol) && $credential !== ''
            ? SecretCipher::encrypt($credential)
            : null;
    }

    public function remoteCredential(string $protocol, array $client): string
    {
        if ($this->usesPassword($protocol)) {
            return trim((string)($client['password'] ?? ''));
        }

        return trim((string)($client['id'] ?? $client['uuid'] ?? ''));
    }

    public function usesPassword(string $protocol): bool
    {
        return in_array(strtolower(trim($protocol)), ['trojan', 'shadowsocks'], true);
    }
}
