<?php

namespace Fireball\VpnManager\Services;

use Fireball\VpnManager\Clients\ThreeXuiClient;
use Fireball\VpnManager\Repositories\VpnRepository;

final class SubscriptionProvisioningService
{
    private VpnRepository $repo;

    public function __construct(?VpnRepository $repo = null)
    {
        $this->repo = $repo ?: new VpnRepository();
    }

    public function provision(int $subscriptionId): array
    {
        $subscription = $this->repo->subscription($subscriptionId);
        if (!$subscription) {
            throw new \RuntimeException(\FireballPluginVpnManager::t('vpn_manager_error_subscription_not_found'));
        }

        $planId = (int)($subscription['plan_id'] ?? 0);
        $items = $this->repo->planItems($planId);
        if (!$items) {
            $this->repo->updateSubscriptionProvisioningStatus($subscriptionId, 'provisioning_failed');
            $this->repo->logEvent('provisioning_failed', 'VPN subscription provisioning failed: plan has no active inbounds.', [
                'subscription_id' => $subscriptionId,
                'plan_id' => $planId,
                'error_code' => 'plan_has_no_inbounds',
                'error_message' => \FireballPluginVpnManager::t('vpn_manager_error_plan_has_no_inbounds'),
            ], (int)$subscription['user_id'], $subscriptionId);

            throw new \RuntimeException(\FireballPluginVpnManager::t('vpn_manager_error_plan_has_no_inbounds'));
        }

        $this->repo->logEvent('provisioning_started', 'VPN subscription provisioning started.', [
            'subscription_id' => $subscriptionId,
            'plan_id' => $planId,
            'active_plan_items' => count($items),
        ], (int)$subscription['user_id'], $subscriptionId);

        $created = 0;
        $skipped = 0;
        $failed = 0;
        foreach ($items as $item) {
            $node = $this->repo->ensureSubscriptionNode($subscription, $item);
            if (($node['status'] ?? '') === 'active' && !empty($node['remote_client_id'])) {
                $this->repo->logEvent('provisioning_skipped', 'VPN node already exists, provisioning skipped.', [
                    'node_id' => (int)$node['id'],
                    'server_id' => (int)$item['server_id'],
                    'inbound_id' => (int)$item['inbound_id'],
                ], (int)$subscription['user_id'], $subscriptionId, (int)$node['id'], (int)$item['server_id']);
                $skipped++;
                continue;
            }

            try {
                $client = new ThreeXuiClient($item);
                if ($client->clientExists((string)$item['remote_inbound_id'], (string)$node['client_email'], (string)$node['client_uuid'])) {
                    $this->repo->markNodeProvisioned((int)$node['id'], (string)$node['client_uuid']);
                    $this->repo->logEvent('provisioning_existing_client', 'VPN client already exists in 3x-ui; local node synchronized.', [
                        'node_id' => (int)$node['id'],
                        'server_id' => (int)$item['server_id'],
                        'inbound_id' => (int)$item['inbound_id'],
                    ], (int)$subscription['user_id'], $subscriptionId, (int)$node['id'], (int)$item['server_id']);
                    $skipped++;
                    continue;
                }

                $clientData = $this->clientData($subscription, $node, $item);
                $client->addClient((string)$item['remote_inbound_id'], $clientData);
                if (!$client->clientExists((string)$item['remote_inbound_id'], (string)$node['client_email'], (string)$node['client_uuid'])) {
                    throw new \RuntimeException(\FireballPluginVpnManager::t('vpn_manager_error_client_not_confirmed'));
                }

                $this->repo->markNodeProvisioned((int)$node['id'], (string)$node['client_uuid']);
                $this->repo->logEvent('provisioning_success', 'VPN client created in 3x-ui.', [
                    'node_id' => (int)$node['id'],
                    'server_id' => (int)$item['server_id'],
                    'inbound_id' => (int)$item['inbound_id'],
                ], (int)$subscription['user_id'], $subscriptionId, (int)$node['id'], (int)$item['server_id']);
                $created++;
            } catch (\Throwable $exception) {
                $this->repo->markNodeFailed((int)$node['id'], $exception->getMessage());
                $this->repo->logEvent('node_create_failed', 'VPN client creation failed.', [
                    'node_id' => (int)$node['id'],
                    'server_id' => (int)$item['server_id'],
                    'inbound_id' => (int)$item['inbound_id'],
                    'error_code' => get_class($exception),
                    'error_message' => $exception->getMessage(),
                ], (int)$subscription['user_id'], $subscriptionId, (int)$node['id'], (int)$item['server_id']);
                $this->repo->logEvent('provisioning_failed', 'VPN client provisioning failed.', [
                    'node_id' => (int)$node['id'],
                    'server_id' => (int)$item['server_id'],
                    'inbound_id' => (int)$item['inbound_id'],
                    'error_code' => get_class($exception),
                    'error_message' => $exception->getMessage(),
                ], (int)$subscription['user_id'], $subscriptionId, (int)$node['id'], (int)$item['server_id']);
                $failed++;
            }
        }

        $this->repo->updateSubscriptionProvisioningStatus($subscriptionId, $failed === 0 ? 'active' : 'provisioning_failed');

        return [
            'created' => $created,
            'skipped' => $skipped,
            'failed' => $failed,
        ];
    }

    private function clientData(array $subscription, array $node, array $item): array
    {
        $expiresAt = strtotime((string)($subscription['expires_at'] ?? '')) ?: 0;
        $expiryMs = $expiresAt > 0 ? $expiresAt * 1000 : 0;
        $clientId = (string)$node['client_uuid'];
        $template = $this->firstClientTemplate($item);

        $data = [
            'flow' => (string)($template['flow'] ?? ''),
            'email' => (string)$node['client_email'],
            'limitIp' => (int)($subscription['device_limit'] ?? 1),
            'totalGB' => (int)($node['traffic_limit_bytes'] ?? $subscription['traffic_limit_bytes'] ?? 0),
            'expiryTime' => $expiryMs,
            'enable' => true,
            'tgId' => 0,
            'subId' => (string)($subscription['subscription_token_preview'] ?? ''),
            'comment' => (string)($node['client_remark'] ?? ''),
        ];

        $protocol = strtolower((string)($item['protocol'] ?? ''));
        if ($protocol === 'trojan') {
            $data['password'] = $clientId;
        } else {
            $data['id'] = $clientId;
            if (!empty($template['encryption'])) {
                $data['encryption'] = (string)$template['encryption'];
            }
        }

        return $data;
    }

    private function firstClientTemplate(array $item): array
    {
        $settings = json_decode((string)($item['settings_json'] ?? ''), true);
        if (!is_array($settings)) {
            return [];
        }

        foreach ((array)($settings['clients'] ?? []) as $client) {
            if (is_array($client)) {
                return $client;
            }
        }

        return [];
    }
}
