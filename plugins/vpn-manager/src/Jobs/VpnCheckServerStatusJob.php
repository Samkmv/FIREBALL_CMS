<?php

namespace Fireball\VpnManager\Jobs;

use Fireball\VpnManager\Clients\ThreeXuiClient;
use Fireball\VpnManager\Repositories\VpnRepository;

final class VpnCheckServerStatusJob
{
    public function handle(): array
    {
        $repo = new VpnRepository();
        $checked = 0;
        foreach ($repo->enabledServers() as $server) {
            $result = (new ThreeXuiClient($server))->testConnection();
            $repo->updateServerCheck((int)$server['id'], !empty($result['success']), (string)($result['message'] ?? ''));
            $checked++;
        }

        return ['checked' => $checked];
    }
}
