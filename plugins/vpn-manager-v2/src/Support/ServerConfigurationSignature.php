<?php

namespace Fireball\VpnManagerV2\Support;

final class ServerConfigurationSignature
{
    public static function hash(array $server): string
    {
        return hash('sha256', json_encode(array_intersect_key($server, array_flip([
            'panel_url', 'panel_path', 'api_url', 'auth_type', 'encrypted_token',
            'encrypted_username', 'encrypted_password', 'is_enabled', 'maintenance_mode', 'allow_new_connections',
            'verify_ssl', 'allow_private_network', 'connect_timeout', 'read_timeout',
        ])), JSON_THROW_ON_ERROR));
    }
}
