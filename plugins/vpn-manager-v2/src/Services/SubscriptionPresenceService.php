<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\Clients\ThreeXuiClient;
use Fireball\VpnManagerV2\Repositories\ServerRepository;
use Fireball\VpnManagerV2\Repositories\SubscriptionRepository;
use Fireball\VpnManagerV2\Support\ServerConfigurationSignature;

final class SubscriptionPresenceService
{
    public function online(): array
    {
        $candidates = [];
        foreach ((new SubscriptionRepository())->presenceCandidates() as $node) {
            $candidates[(int)$node['server_id']][] = $node;
        }

        $servers = new ServerRepository();
        $online = [];
        $unavailable = 0;
        foreach ($candidates as $serverId => $nodes) {
            try {
                $server = $servers->findWithSecrets($serverId);
                if (!$server || empty($server['is_enabled']) || !empty($server['maintenance_mode'])) {
                    $unavailable++;
                    continue;
                }
                $key = 'vpn-v2:presence:' . $serverId . ':' . ServerConfigurationSignature::hash($server);
                $names = cache()->get($key);
                if (!is_array($names)) {
                    $config = (new ServerSecretService())->clientConfig($server, 2, 3);
                    $names = array_fill_keys((new ThreeXuiClient($config))->onlineClientNames(), true);
                    cache()->set($key, $names, 10);
                }
                foreach ($nodes as $node) {
                    if (isset($names[$node['client_email']])) {
                        $online[(int)$node['subscription_id']] = true;
                    }
                }
            } catch (\Throwable $exception) {
                $unavailable++;
                error_log('VPN presence check failed for server #' . $serverId . ': ' . $exception::class);
            }
        }

        return ['ids' => array_keys($online), 'unavailable' => $unavailable];
    }
}
