<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\Clients\ThreeXuiClient;
use Fireball\VpnManagerV2\Exceptions\ProvisioningException;
use Fireball\VpnManagerV2\Repositories\ServerRepository;
use Fireball\VpnManagerV2\Repositories\SubscriptionRepository;
use Fireball\VpnManagerV2\Support\Permissions;

final class SubscriptionDeviceService
{
    public function __construct(private readonly ?\Closure $clientFactory = null) {}

    public function devices(int $subscriptionId): array
    {
        Permissions::authorize(Permissions::VIEW);
        $repository = new SubscriptionRepository();
        $subscription = $repository->findForProvisioning($subscriptionId);
        if (!$subscription || $subscription['status'] === 'deleted') { abort('', 404); }
        $groups = [];
        foreach ($repository->nodeIdsForSubscription($subscriptionId) as $nodeId) {
            $node = $repository->connectionForProvisioning($nodeId);
            if (!$node || in_array($node['status'], ['deleted', 'deleting'], true)) { continue; }
            $group = ['node_id' => $nodeId, 'server_name' => (string)($node['server_name'] ?? ('#' . $node['server_id'])),
                'limit' => (int)$subscription['device_limit'], 'devices' => [], 'error' => null];
            try {
                [$client, , $remote] = $this->context($subscriptionId, $nodeId);
                $group['limit'] = (int)$remote['limitHwid'];
                $group['devices'] = $this->normalize($client->listClientDevices((string)$node['client_email']));
                $group['over_limit'] = $group['limit'] > 0 && count($group['devices']) > $group['limit'];
            } catch (\Throwable $exception) {
                $group['error'] = $this->safeError($exception);
            }
            $groups[] = $group;
        }
        return ['subscription' => $subscription, 'groups' => $groups];
    }

    public function remove(int $subscriptionId, int $nodeId, ?int $deviceId): void
    {
        Permissions::authorize(Permissions::MANAGE_SUBSCRIPTIONS);
        // All remote identifiers and credentials come from the checked local connection.
        [$client, $node] = $this->context($subscriptionId, $nodeId);
        $metadata = ['plan_id' => (int)$node['plan_id'], 'client_reference' => 'connection#' . $nodeId,
            'operation' => $deviceId === null ? 'clear_devices' : 'delete_device', 'device_id' => $deviceId];
        try {
            if ($deviceId === null) {
                $client->clearClientDevices((string)$node['client_email']);
            } else {
                $ids = array_column($client->listClientDevices((string)$node['client_email']), 'id');
                if ($deviceId <= 0 || !in_array($deviceId, array_map('intval', $ids), true)) {
                    throw new ProvisioningException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_device_not_found'));
                }
                $client->deleteClientDevice((string)$node['client_email'], $deviceId);
            }
            $this->log($node, $metadata + ['success' => true]);
        } catch (\Throwable $exception) {
            $this->log($node, $metadata + ['success' => false, 'error_type' => (new \ReflectionClass($exception))->getShortName()]);
            throw new ProvisioningException($this->safeError($exception));
        }
    }

    private function context(int $subscriptionId, int $nodeId): array
    {
        $repository = new SubscriptionRepository();
        $node = $repository->connectionForProvisioning($nodeId);
        if (!$node || (int)$node['subscription_id'] !== $subscriptionId
            || in_array($node['status'], ['deleted', 'deleting'], true) || $node['subscription_status'] === 'deleted') {
            throw new ProvisioningException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_device_not_found'));
        }
        if (db()->inTransaction()) {
            throw new ProvisioningException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_http_inside_transaction'));
        }
        $server = (new ServerRepository())->findWithSecrets((int)$node['server_id']);
        $inbound = $repository->inbound((int)$node['inbound_id']);
        if (!$server || !$inbound || (int)$inbound['server_id'] !== (int)$server['id']) {
            throw new ProvisioningException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_connection_topology'));
        }
        $client = $this->clientFactory !== null ? ($this->clientFactory)($server, $inbound, $node)
            : new ThreeXuiClient((new ServerSecretService())->clientConfig($server));
        foreach (['listClientDevices', 'deleteClientDevice', 'clearClientDevices'] as $method) {
            if (!method_exists($client, $method)) {
                throw new ProvisioningException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_devices_unsupported'));
            }
        }
        $remote = (new RemoteClientSyncService())->readClientState($client, $client->getInbound((int)$inbound['remote_inbound_id']), $node);
        if (!$remote || !array_key_exists('limitHwid', $remote)) {
            throw new ProvisioningException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_devices_unsupported'));
        }
        (new ClientVerifier())->assertIdentity($remote, (new ClientPayloadFactory())->build([
            'expires_at' => $node['expires_at'] ?? null, 'status' => $node['subscription_status'],
        ], $node));
        return [$client, $node, $remote];
    }

    private function normalize(array $rows): array
    {
        $devices = [];
        foreach ($rows as $row) {
            if (!is_array($row) || (int)($row['id'] ?? 0) <= 0) { continue; }
            $device = ['id' => (int)$row['id']];
            foreach (['deviceModel', 'deviceOs', 'osVersion', 'userAgent', 'fingerprint'] as $key) {
                $device[$key] = mb_substr(trim((string)($row[$key] ?? '')), 0, $key === 'fingerprint' ? 12 : 160);
            }
            foreach (['firstSeen', 'lastSeen'] as $key) {
                $time = max(0, (int)($row[$key] ?? 0));
                $device[$key] = $time > 0 ? date('d.m.Y H:i', (int)floor($time / 1000)) : '—';
            }
            $devices[] = $device;
        }
        return $devices;
    }

    private function safeError(\Throwable $exception): string
    {
        return \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_devices_generic')
            . ' (' . (new \ReflectionClass($exception))->getShortName() . ')';
    }

    private function log(array $node, array $metadata): void
    {
        (new SubscriptionRepository())->logEvent('client.devices_updated', (int)$node['subscription_id'],
            (int)$node['id'], (int)$node['server_id'], (int)$node['user_id'], (int)(get_user()['id'] ?? 0), $metadata);
    }
}
