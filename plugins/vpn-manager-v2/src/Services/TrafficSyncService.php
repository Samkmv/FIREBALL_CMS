<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\Clients\ThreeXuiClient;
use Fireball\VpnManagerV2\Clients\ThreeXuiClientInterface;
use Fireball\VpnManagerV2\Exceptions\ThreeXuiResponseException;
use Fireball\VpnManagerV2\Exceptions\VpnManagerV2Exception;
use Fireball\VpnManagerV2\Repositories\AutomationRepository;
use Fireball\VpnManagerV2\Repositories\ServerRepository;
use Fireball\VpnManagerV2\Repositories\SubscriptionRepository;

final class TrafficSyncService
{
    public function __construct(
        private readonly ?AutomationRepository $repository = null,
        private readonly ?SettingsService $settings = null,
        private readonly ?\Closure $clientFactory = null,
        private readonly ?VpnNotificationService $notificationService = null,
        private readonly ?\Closure $nodeProvider = null,
    ) {
    }

    public function syncActiveNodes(): array
    {
        $settings = ($this->settings ?? new SettingsService())->current();
        if (empty($settings['sync_enabled'])) {
            return ['synced' => 0, 'failed' => 0, 'unchanged' => 0, 'subscriptions' => 0, 'skipped' => true];
        }

        $repository = $this->repository ?? new AutomationRepository();
        $events = new SubscriptionRepository();
        $synced = 0;
        $failed = 0;
        $unchanged = 0;
        $touchedSubscriptions = [];
        $trafficCursorKey = 'vpn-v2:traffic-sync-cursor';
        $cursor = $this->nodeProvider !== null ? 0 : max(0, (int)cache()->get($trafficCursorKey, 0));
        $nodes = $this->nodeProvider !== null
            ? ($this->nodeProvider)()
            : $repository->activeNodesForTrafficSync(500, $cursor);
        if ($this->nodeProvider === null && $nodes === [] && $cursor > 0) {
            $cursor = 0;
            $nodes = $repository->activeNodesForTrafficSync(500, 0);
        }
        if (!is_array($nodes)) {
            throw new \LogicException('Invalid traffic node provider result.');
        }
        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }
            $nodeId = (int)$node['id'];
            $subscriptionId = (int)$node['subscription_id'];
            try {
                if (db()->inTransaction()) {
                    throw new \RuntimeException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_http_inside_transaction'));
                }
                $identifier = trim((string)($node['client_email'] ?? ''));
                if ($identifier === '') {
                    $identifier = (new RemoteClientCredentialService())->credential($node);
                }
                $response = $this->client($node)->getClientTraffic($identifier);
                $traffic = self::trafficFromResponse($response, $identifier);
                $remoteUsed = $traffic['total'];
                $result = $repository->recordNodeTraffic(
                    $nodeId,
                    $remoteUsed,
                    $traffic['upload'],
                    $traffic['download']
                );
                $synced++;
                $unchanged += (int)$result['stored_bytes'] === (int)$result['previous_bytes'] ? 1 : 0;
                $touchedSubscriptions[$subscriptionId] = true;
                $events->logEvent('traffic.synced', $subscriptionId, $nodeId, (int)$node['server_id'],
                    (int)$node['user_id'], null, [
                        'previous_bytes' => (int)$result['previous_bytes'],
                        'remote_bytes' => (int)$result['remote_bytes'],
                        'stored_bytes' => (int)$result['stored_bytes'],
                    ]);
            } catch (\Throwable $exception) {
                $failed++;
                $safeError = $this->safeError($exception);
                $repository->recordNodeTrafficFailure($nodeId, $safeError);
                $events->logEvent('traffic.sync_failed', $subscriptionId, $nodeId, (int)$node['server_id'],
                    (int)$node['user_id'], null, ['error_type' => $this->errorType($exception)]);
                try {
                    ($this->notificationService ?? new VpnNotificationService())->notifyCritical(
                        $subscriptionId,
                        'traffic-sync'
                    );
                } catch (\Throwable) {
                    // Notification delivery must never overwrite confirmed traffic state.
                }
            }
        }
        if ($this->nodeProvider === null && $nodes !== []) {
            $last = end($nodes);
            cache()->set($trafficCursorKey, max(0, (int)($last['id'] ?? $cursor)), 86400 * 30);
        }

        foreach (array_keys($touchedSubscriptions) as $subscriptionId) {
            $repository->recalculateSubscriptionTraffic((int)$subscriptionId);
        }

        return [
            'synced' => $synced,
            'failed' => $failed,
            'unchanged' => $unchanged,
            'subscriptions' => count($touchedSubscriptions),
            'skipped' => false,
        ];
    }

    public static function trafficUsedFromResponse(array $response, string $clientIdentifier = ''): int
    {
        return self::trafficFromResponse($response, $clientIdentifier)['total'];
    }

    public static function trafficFromResponse(array $response, string $clientIdentifier = ''): array
    {
        $payload = $response['obj'] ?? $response;
        if (!is_array($payload)) {
            throw new ThreeXuiResponseException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_client_traffic_response'));
        }
        if (array_is_list($payload)) {
            $match = null;
            foreach ($payload as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $email = trim((string)($row['email'] ?? ''));
                if ($clientIdentifier !== '' && $email !== '' && hash_equals($email, $clientIdentifier)) {
                    $match = $row;
                    break;
                }
            }
            if ($match === null) {
                throw new ThreeXuiResponseException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_client_traffic_missing'));
            }
            $payload = $match;
        }

        if (!array_key_exists('up', $payload) && !array_key_exists('down', $payload)) {
            throw new ThreeXuiResponseException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_client_traffic_missing'));
        }
        $up = self::bytes($payload['up'] ?? 0);
        $down = self::bytes($payload['down'] ?? 0);
        $total = $up > PHP_INT_MAX - $down ? PHP_INT_MAX : $up + $down;

        return ['upload' => $up, 'download' => $down, 'total' => $total];
    }

    private static function bytes(mixed $value): int
    {
        if (!is_int($value) && !is_float($value) && !(is_string($value) && is_numeric($value))) {
            throw new ThreeXuiResponseException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_client_traffic_response'));
        }

        return max(0, (int)$value);
    }

    private function client(array $node): ThreeXuiClientInterface
    {
        $server = (new ServerRepository())->findWithSecrets((int)$node['server_id']);
        if (!$server || empty($server['is_enabled'])) {
            throw new \RuntimeException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_provisioning_server_unavailable'));
        }
        if ($this->clientFactory !== null) {
            $client = ($this->clientFactory)($server, $node);
            if (!$client instanceof ThreeXuiClientInterface) {
                throw new \LogicException('Invalid ThreeXuiClient factory result.');
            }

            return $client;
        }

        return new ThreeXuiClient((new ServerSecretService())->clientConfig($server));
    }

    private function safeError(\Throwable $exception): string
    {
        return $exception instanceof VpnManagerV2Exception
            ? mb_substr(trim($exception->getMessage()), 0, 1000)
            : \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_traffic_sync_generic');
    }

    private function errorType(\Throwable $exception): string
    {
        $class = get_class($exception);
        $position = strrpos($class, '\\');

        return mb_substr($position === false ? $class : substr($class, $position + 1), 0, 120);
    }
}
