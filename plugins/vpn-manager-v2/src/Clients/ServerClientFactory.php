<?php

namespace Fireball\VpnManagerV2\Clients;

use Fireball\VpnManagerV2\DTO\ThreeXuiServerConfig;

final class ServerClientFactory
{
    public static function create(ThreeXuiServerConfig $config): ThreeXuiClientInterface
    {
        return $config->authType === 'xray'
            ? new NativeXrayClient($config)
            : new ThreeXuiClient($config);
    }
}
