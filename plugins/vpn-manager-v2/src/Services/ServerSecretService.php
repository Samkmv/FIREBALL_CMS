<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\DTO\ThreeXuiServerConfig;
use Fireball\VpnManagerV2\Support\SecretCipher;

final class ServerSecretService
{
    private const INPUT_TO_COLUMN = [
        'username' => 'encrypted_username',
        'password' => 'encrypted_password',
        'token' => 'encrypted_token',
    ];

    public function encryptedUpdates(array $input): array
    {
        $updates = [];
        foreach (self::INPUT_TO_COLUMN as $inputKey => $column) {
            $plainText = (string)($input[$inputKey] ?? '');
            if (!SecretCipher::shouldReplace($plainText)) {
                continue;
            }

            $updates[$column] = SecretCipher::encrypt($plainText);
        }

        return $updates;
    }

    public function clientConfig(array $server, ?int $connectTimeout = null, ?int $readTimeout = null): ThreeXuiServerConfig
    {
        $authType = (string)($server['auth_type'] ?? 'token');

        return new ThreeXuiServerConfig(
            panelUrl: (string)$server['panel_url'],
            panelPath: (string)($server['panel_path'] ?? ''),
            authType: $authType,
            username: $authType === 'password' ? SecretCipher::decrypt($server['encrypted_username'] ?? null) : '',
            password: $authType === 'password' ? SecretCipher::decrypt($server['encrypted_password'] ?? null) : '',
            token: $authType === 'token' ? SecretCipher::decrypt($server['encrypted_token'] ?? null) : '',
            connectTimeout: $connectTimeout ?? (int)($server['connect_timeout'] ?? 5),
            readTimeout: $readTimeout ?? (int)($server['read_timeout'] ?? 15),
            apiUrl: isset($server['api_url']) ? (string)$server['api_url'] : null,
            verifySsl: !array_key_exists('verify_ssl', $server) || !empty($server['verify_ssl']),
            allowPrivateNetwork: !empty($server['allow_private_network']),
        );
    }

    public function submittedOrStored(array $input, array $server, string $key): bool
    {
        $plainText = (string)($input[$key] ?? '');
        if (SecretCipher::shouldReplace($plainText)) {
            return true;
        }

        $column = self::INPUT_TO_COLUMN[$key] ?? '';

        return $column !== '' && trim((string)($server[$column] ?? '')) !== '';
    }
}
