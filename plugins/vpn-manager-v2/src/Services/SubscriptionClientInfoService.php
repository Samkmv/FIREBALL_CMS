<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\Clients\ThreeXuiClient;
use Fireball\VpnManagerV2\Clients\ThreeXuiClientInterface;
use Fireball\VpnManagerV2\Exceptions\ProvisioningException;
use Fireball\VpnManagerV2\Exceptions\ValidationException;
use Fireball\VpnManagerV2\Repositories\ServerRepository;
use Fireball\VpnManagerV2\Repositories\SubscriptionRepository;
use Fireball\VpnManagerV2\Support\TrafficFormatter;

/** Read-only inspection: never reconcile, enqueue, reset counters or save panel state. */
final class SubscriptionClientInfoService
{
    public function __construct(
        private readonly ?SubscriptionRepository $repository = null,
        private readonly ?ServerRepository $servers = null,
        private readonly ?\Closure $clientFactory = null,
    ) {}

    public function read(int $subscriptionId, int $nodeId): array
    {
        $repository = $this->repository ?? new SubscriptionRepository();
        $node = $repository->connectionForProvisioning($nodeId);
        if (!$node || (int)$node['subscription_id'] !== $subscriptionId
            || (string)$node['subscription_status'] === 'deleted'
            || in_array((string)$node['status'], ['deleted', 'deleting', 'pending_remote_delete', 'delete_failed'], true)) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_connection_not_found'));
        }
        if (db()->inTransaction()) {
            throw new \LogicException('Client inspection cannot run inside a database transaction.');
        }
        $server = ($this->servers ?? new ServerRepository())->findWithSecrets((int)$node['server_id']);
        $inbound = $repository->inbound((int)$node['inbound_id']);
        if (!$server || empty($server['is_enabled']) || !$inbound
            || (int)$inbound['server_id'] !== (int)$node['server_id']) {
            throw new ProvisioningException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_connection_topology'));
        }
        $client = $this->clientFactory !== null ? ($this->clientFactory)($server, $inbound, $node)
            : new ThreeXuiClient((new ServerSecretService())->clientConfig($server, 3, 5));
        if (!$client instanceof ThreeXuiClientInterface) { throw new \LogicException('Invalid client inspection factory.'); }
        $remote = (new RemoteClientSyncService())->readClientState(
            $client, $client->getInbound((int)$inbound['remote_inbound_id']), $node
        );
        $credentials = new RemoteClientCredentialService();
        if (!$remote) { throw new ProvisioningException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_client_not_found_for_update')); }
        (new ClientVerifier())->assertStableCredential($remote, [
            $credentials->usesPassword((string)$node['protocol']) ? 'password' : 'id' => $credentials->credential($node),
        ]);
        $email = trim((string)($remote['email'] ?? ''));
        if ($email === '') { throw new ProvisioningException('Missing client identity.'); }
        $response = $client->getClientTraffic($email);
        $traffic = TrafficSyncService::trafficFromResponse($response, $email);
        $record = $response['obj'] ?? $response;
        if (is_array($record) && array_is_list($record)) {
            $record = array_values(array_filter($record, static fn($row): bool => is_array($row)
                && (string)($row['email'] ?? '') === $email))[0] ?? [];
        }
        if (is_array($record) && isset($record['email']) && (string)$record['email'] !== $email) {
            throw new ProvisioningException('Traffic belongs to a different client.');
        }
        $limit = isset($remote['totalGB']) && is_numeric($remote['totalGB']) ? max(0, (int)$remote['totalGB']) : null;
        $enabled = isset($remote['enable']) && $remote['enable'] !== ''
            ? filter_var($remote['enable'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null;
        $fields = [
            ['label' => 'vpn_manager_v2_col_client_email', 'value' => mb_substr($email, 0, 256)],
            ['label' => 'vpn_manager_v2_client_panel_access', 'value' => $enabled === null ? '—'
                : \FireballPluginVpnManagerV2::t($enabled ? 'vpn_manager_v2_overview_enabled' : 'vpn_manager_v2_provisioning_status_disabled')],
            ['label' => 'vpn_manager_v2_profile_traffic_used', 'value' => TrafficFormatter::bytes($traffic['total'])],
            ['label' => 'vpn_manager_v2_client_upload', 'value' => TrafficFormatter::bytes($traffic['upload'])],
            ['label' => 'vpn_manager_v2_client_download', 'value' => TrafficFormatter::bytes($traffic['download'])],
            ['label' => 'vpn_manager_v2_profile_traffic_limit', 'value' => $limit === null ? '—' : TrafficFormatter::localizedLimit($limit)],
            ['label' => 'vpn_manager_v2_profile_traffic_remaining', 'value' => $limit === null ? '—'
                : ($limit > 0 ? TrafficFormatter::bytes(max(0, $limit - $traffic['total'])) : \FireballPluginVpnManagerV2::t('vpn_manager_v2_unlimited'))],
            ['label' => 'vpn_manager_v2_field_device_limit', 'value' => $this->limit($remote['limitHwid'] ?? null)],
            ['label' => 'vpn_manager_v2_field_ip_limit', 'value' => $this->limit($remote['limitIp'] ?? null)],
            ['label' => 'vpn_manager_v2_client_panel_expiry', 'value' => isset($remote['expiryTime']) && is_numeric($remote['expiryTime'])
                && (int)$remote['expiryTime'] === 0 ? \FireballPluginVpnManagerV2::t('vpn_manager_v2_lifetime_short')
                : $this->timestamp($remote['expiryTime'] ?? null)],
            ['label' => 'vpn_manager_v2_client_last_activity', 'value' => $this->timestamp($record['lastOnline'] ?? null)],
        ];
        // HWID support varies by panel version. Missing support is unknown, not zero devices.
        $devices = null;
        if (method_exists($client, 'listClientDevices')) {
            try {
                $devices = count(array_filter($client->listClientDevices($email), static fn($device): bool =>
                    is_array($device) && (int)($device['id'] ?? 0) > 0));
            } catch (\Throwable) { /* The client/traffic inspection remains usable without HWID support. */ }
        }
        $fields[] = ['label' => 'vpn_manager_v2_client_device_count', 'value' => $devices === null ? '—' : (string)$devices];
        foreach ($fields as &$field) { $field['label'] = \FireballPluginVpnManagerV2::t($field['label']); }
        unset($field);
        return ['connection_id' => $nodeId, 'subscription_id' => $subscriptionId,
            'server_id' => (int)$node['server_id'], 'traffic' => $traffic,
            'checked_at' => date('Y-m-d H:i:s'), 'fields' => $fields];
    }

    private function limit(mixed $value): string
    {
        return $value === null || !is_numeric($value) ? '—'
            : ((int)$value > 0 ? (string)(int)$value : \FireballPluginVpnManagerV2::t('vpn_manager_v2_unlimited'));
    }

    private function timestamp(mixed $value): string
    {
        if (!is_numeric($value) || (float)$value <= 0) { return '—'; }
        $seconds = (float)$value > 100000000000 ? (int)floor((float)$value / 1000) : (int)$value;
        return date('Y-m-d H:i:s', $seconds);
    }
}
