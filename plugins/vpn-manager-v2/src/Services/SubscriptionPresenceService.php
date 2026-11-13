<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\Clients\ServerClientFactory;
use Fireball\VpnManagerV2\Repositories\ServerRepository;
use Fireball\VpnManagerV2\Repositories\SubscriptionRepository;
use Fireball\VpnManagerV2\Support\ServerConfigurationSignature;

final class SubscriptionPresenceService
{
    public function __construct(
        private readonly ?\Closure $nodeProvider = null,
        private readonly ?\Closure $serverProvider = null,
        private readonly ?\Closure $clientFactory = null,
    ) {
    }

    public function online(): array
    {
        $snapshot = $this->snapshot();
        return ['ids' => $snapshot['ids'], 'unavailable' => $snapshot['summary']['unavailable']];
    }

    public function snapshot(): array
    {
        $candidates = [];
        $repository = new SubscriptionRepository();
        $nodes = $this->nodeProvider !== null ? ($this->nodeProvider)() : $repository->presenceCandidates();
        foreach ($nodes as $node) {
            $candidates[(int)$node['server_id']][] = $node;
        }

        $servers = new ServerRepository();
        $online = [];
        $connections = [];
        $serverStates = [];
        $users = $onlineServers = [];
        $unavailable = 0;
        foreach ($candidates as $serverId => $nodes) {
            $state = ['id' => $serverId, 'name' => (string)($nodes[0]['server_name'] ?? '#' . $serverId),
                'available' => false, 'checked_at' => null, 'online' => 0];
            $sample = null;
            try {
                $server = $this->serverProvider !== null ? ($this->serverProvider)($serverId) : $servers->findWithSecrets($serverId);
                if (!$server || empty($server['is_enabled']) || !empty($server['maintenance_mode'])) {
                    throw new \RuntimeException('VPN server is unavailable.');
                }
                $state['name'] = (string)$server['name'];
                $key = 'vpn-v2:presence:v2:' . $serverId . ':' . ServerConfigurationSignature::hash($server);
                $sample = cache()->get($key);
                if (!is_array($sample) || !is_array($sample['names'] ?? null) || empty($sample['checked_at'])) {
                    $config = (new ServerSecretService())->clientConfig($server, 2, 3);
                    $client = $this->clientFactory !== null ? ($this->clientFactory)($server, $config) : ServerClientFactory::create($config);
                    $names = $client->onlineClientNames();
                    if (!is_array($names) || !array_is_list($names)) {
                        throw new \UnexpectedValueException('Invalid presence response.');
                    }
                    foreach ($names as $name) {
                        if (!is_string($name) || $name === '') {
                            throw new \UnexpectedValueException('Invalid presence identifier.');
                        }
                    }
                    $sample = ['names' => array_fill_keys($names, true), 'checked_at' => date('Y-m-d H:i:s')];
                    cache()->set($key, $sample, 10);
                }
                $state['available'] = true;
                $state['checked_at'] = $sample['checked_at'];
            } catch (\Throwable $exception) {
                $unavailable++;
                error_log('VPN presence check failed for server #' . $serverId . ': ' . $exception::class);
            }
            $confirmedIds = [];
            foreach ($nodes as $node) {
                $isOnline = $state['available'] && isset($sample['names'][$node['client_email']]);
                $subscriptionId = (int)$node['subscription_id'];
                $lastSeen = trim((string)($node['presence_last_online_at'] ?? ''));
                if ($isOnline) {
                    $online[$subscriptionId] = true;
                    $users[(int)($node['user_id'] ?? 0) > 0 ? 'user:' . (int)$node['user_id'] : 'manual:' . $subscriptionId] = true;
                    $onlineServers[$serverId] = true;
                    $confirmedIds[] = (int)$node['connection_id'];
                    $lastSeen = max($lastSeen, $sample['checked_at']);
                    $state['online']++;
                }
                $connections[] = [
                    'connection_id' => (int)$node['connection_id'], 'subscription_id' => $subscriptionId,
                    'user_name' => (string)($node['user_name'] ?? ''), 'user_login' => (string)($node['user_login'] ?? ''),
                    'client_name' => (string)($node['client_display_name'] ?? ''), 'plan_name' => (string)($node['plan_name'] ?? ''),
                    'server_id' => $serverId, 'server_name' => $state['name'],
                    'inbound_name' => (string)($node['inbound_name'] ?? ''), 'protocol' => (string)($node['protocol'] ?? ''),
                    'port' => (int)($node['port'] ?? 0), 'last_seen_at' => $lastSeen,
                    'presence' => $state['available'] ? ($isOnline ? 'online' : 'offline') : 'unknown',
                ];
            }
            if ($confirmedIds !== []) {
                $repository->recordPresence($confirmedIds, $sample['checked_at']);
            }
            $serverStates[] = $state;
        }

        return [
            'ids' => array_keys($online), 'connections' => $connections, 'servers' => $serverStates,
            'summary' => ['users' => count($users), 'subscriptions' => count($online), 'servers' => count($onlineServers),
                'connections' => array_sum(array_column($serverStates, 'online')), 'unavailable' => $unavailable,
                'checked' => count($serverStates) - $unavailable, 'total_servers' => count($serverStates)],
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }
}
