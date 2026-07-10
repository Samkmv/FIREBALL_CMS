<?php

namespace Fireball\VpnManager\Services;

use Fireball\VpnManager\Clients\ThreeXuiClient;
use Fireball\VpnManager\Repositories\VpnRepository;

final class TrafficSyncService
{
    private VpnRepository $repo;

    public function __construct(?VpnRepository $repo = null)
    {
        $this->repo = $repo ?: new VpnRepository();
    }

    public function syncActiveNodes(): array
    {
        $settings = SettingsService::settings();
        if (empty($settings['traffic_sync_enabled'])) {
            return ['synced' => 0, 'failed' => 0, 'skipped' => true];
        }

        $synced = 0;
        $failed = 0;
        foreach ($this->repo->activeNodesForTrafficSync() as $node) {
            try {
                $this->syncNode($node);
                $synced++;
            } catch (\Throwable $exception) {
                $this->repo->markNodeError((int)$node['id'], $exception->getMessage());
                $this->repo->logEvent('traffic.sync_failed', 'VPN traffic synchronization failed.', [
                    'node_id' => (int)$node['id'],
                    'error' => $exception->getMessage(),
                ], (int)($node['user_id'] ?? 0), (int)$node['subscription_id'], (int)$node['id'], (int)$node['server_id']);
                $failed++;
            }
        }

        return ['synced' => $synced, 'failed' => $failed, 'skipped' => false];
    }

    public function syncNode(array $node): int
    {
        $response = (new ThreeXuiClient($node))->getClientTraffic((string)($node['client_email'] ?: $node['client_uuid']));
        $traffic = self::trafficFromResponse($response);

        return $this->repo->updateNodeTrafficDetailed(
            (int)$node['id'],
            $traffic['upload_bytes'],
            $traffic['download_bytes'],
            $traffic['total_bytes']
        );
    }

    public static function trafficFromResponse(array $response): array
    {
        $obj = is_array($response['obj'] ?? null) ? $response['obj'] : $response;
        $upload = (int)($obj['up'] ?? $obj['upload'] ?? $obj['upload_bytes'] ?? 0);
        $download = (int)($obj['down'] ?? $obj['download'] ?? $obj['download_bytes'] ?? 0);
        $total = (int)($obj['total'] ?? $obj['total_bytes'] ?? 0);
        if ($total <= 0) {
            $total = $upload + $download;
        }

        return [
            'upload_bytes' => max(0, $upload),
            'download_bytes' => max(0, $download),
            'total_bytes' => max(0, $total),
        ];
    }
}
