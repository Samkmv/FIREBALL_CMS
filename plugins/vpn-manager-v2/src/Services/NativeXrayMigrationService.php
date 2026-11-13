<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\Clients\NativeXrayClient;
use Fireball\VpnManagerV2\Exceptions\ValidationException;
use Fireball\VpnManagerV2\Repositories\ServerRepository;
use Fireball\VpnManagerV2\Repositories\SubscriptionRepository;

final class NativeXrayMigrationService
{
    public function inspect(int $serverId, bool $apply = false, string $fingerprint = ''): array
    {
        $manifest = $this->manifest($serverId);
        $result = $this->client($serverId)->adopt($manifest, $apply, $fingerprint);
        if ($apply) {
            (new SubscriptionRepository())->logEvent('xray.server_adopted', null, null, $serverId,
                null, (int)(get_user()['id'] ?? 0), [
                    'matched' => (int)($result['matched'] ?? 0), 'missing' => (int)($result['missing'] ?? 0),
                ]);
        }
        return $result;
    }

    public function client(int $serverId): NativeXrayClient
    {
        $server = (new ServerRepository())->findWithSecrets($serverId);
        if (!$server || ($server['auth_type'] ?? '') !== 'xray') {
            throw new ValidationException('Выберите для сервера способ управления Xray-core · FIREBALL Agent.');
        }
        return new NativeXrayClient((new ServerSecretService())->clientConfig($server, 5, 90));
    }

    public function manifest(int $serverId): array
    {
        $inbounds = db()->query(
            'SELECT id, name, remote_inbound_id, protocol, port, stream_settings_json FROM vpn_v2_inbounds WHERE server_id = ? ORDER BY id',
            [$serverId]
        )->get() ?: [];
        $repository = new SubscriptionRepository();
        $factory = new ClientPayloadFactory();
        $groups = [];
        foreach ($inbounds as $inbound) {
            $group = [
                'inbound_id' => (int)$inbound['remote_inbound_id'], 'protocol' => $inbound['protocol'], 'name' => $inbound['name'],
                'port' => (int)$inbound['port'], 'clients' => [],
                'expected_stream' => json_decode((string)$inbound['stream_settings_json'], true, flags: JSON_THROW_ON_ERROR),
            ];
            $nodes = db()->query(
                "SELECT id, upload_bytes, download_bytes, traffic_used_bytes FROM vpn_v2_subscription_nodes
                 WHERE server_id = ? AND inbound_id = ? AND status <> 'deleted' ORDER BY id",
                [$serverId, (int)$inbound['id']]
            )->get() ?: [];
            foreach ($nodes as $summary) {
                $node = $repository->connectionForProvisioning((int)$summary['id']);
                if (!$node) {
                    throw new ValidationException('Состав подключений изменился. Повторите проверку.');
                }
                $subscription = $repository->findForProvisioning((int)$node['subscription_id']);
                if (!$subscription) {
                    throw new ValidationException('Подписка не найдена. Повторите проверку.');
                }
                $payload = $factory->build($subscription, $node);
                if (!empty($node['is_obsolete']) || in_array($node['status'], [
                    'deleting', 'pending_remote_delete', 'delete_failed',
                ], true)) {
                    $payload['enable'] = false;
                }
                $payload['upload_bytes'] = max(0, (int)$summary['upload_bytes']);
                $payload['download_bytes'] = max(0, (int)$summary['download_bytes'],
                    (int)$summary['traffic_used_bytes'] - $payload['upload_bytes']);
                $group['clients'][] = $payload;
            }
            // Historical, unused inbound rows need no remote target on a replacement server.
            if ($group['clients'] !== []) {
                $groups[] = $group;
            }
        }
        return $groups;
    }
}
