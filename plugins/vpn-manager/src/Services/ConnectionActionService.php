<?php

namespace Fireball\VpnManager\Services;

use Fireball\VpnManager\Clients\ThreeXuiClient;
use Fireball\VpnManager\Repositories\VpnRepository;

final class ConnectionActionService
{
    private VpnRepository $repo;

    public function __construct(?VpnRepository $repo = null)
    {
        $this->repo = $repo ?: new VpnRepository();
    }

    public function sync(int $nodeId): int
    {
        $node = $this->node($nodeId);
        try {
            $response = (new ThreeXuiClient($node))->getClientTraffic((string)($node['client_email'] ?: $node['client_uuid']));
            $traffic = TrafficSyncService::trafficFromResponse($response);

            return $this->repo->updateNodeTrafficDetailed($nodeId, $traffic['upload_bytes'], $traffic['download_bytes'], $traffic['total_bytes']);
        } catch (\Throwable $exception) {
            $this->repo->markNodeError($nodeId, $exception->getMessage());
            throw $exception;
        }
    }

    public function enable(int $nodeId): int
    {
        return $this->setEnabled($nodeId, true);
    }

    public function disable(int $nodeId): int
    {
        return $this->setEnabled($nodeId, false);
    }

    public function resetTraffic(int $nodeId): int
    {
        $node = $this->node($nodeId);
        try {
            (new ThreeXuiClient($node))->resetClientTraffic((string)$node['remote_inbound_id'], (string)($node['client_email'] ?: $node['client_uuid']));

            return $this->repo->updateNodeTraffic($nodeId, 0);
        } catch (\Throwable $exception) {
            $this->repo->markNodeError($nodeId, $exception->getMessage());
            throw $exception;
        }
    }

    public function delete(int $nodeId): int
    {
        $node = $this->node($nodeId);
        try {
            (new ThreeXuiClient($node))->deleteClient((string)$node['remote_inbound_id'], (string)$node['client_uuid']);

            return $this->repo->setNodeStatus($nodeId, 'deleted', 'connection.deleted');
        } catch (\Throwable $exception) {
            $this->repo->markNodeError($nodeId, $exception->getMessage());
            throw $exception;
        }
    }

    private function setEnabled(int $nodeId, bool $enabled): int
    {
        $node = $this->node($nodeId);
        try {
            $client = new ThreeXuiClient($node);
            if ($enabled) {
                $client->enableClient((string)$node['remote_inbound_id'], (string)$node['client_uuid']);
            } else {
                $client->disableClient((string)$node['remote_inbound_id'], (string)$node['client_uuid']);
            }

            return $this->repo->setNodeStatus($nodeId, $enabled ? 'active' : 'disabled');
        } catch (\Throwable $exception) {
            $this->repo->markNodeError($nodeId, $exception->getMessage());
            throw $exception;
        }
    }

    private function node(int $nodeId): array
    {
        $node = $this->repo->connection($nodeId);
        if (!$node) {
            throw new \RuntimeException(\FireballPluginVpnManager::t('vpn_manager_error_connection_not_found'));
        }
        if (empty($node['remote_inbound_id']) || empty($node['client_uuid'])) {
            throw new \RuntimeException(\FireballPluginVpnManager::t('vpn_manager_error_client_not_confirmed'));
        }

        return $node;
    }

}
