<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\Clients\ThreeXuiClient;
use Fireball\VpnManagerV2\Clients\ThreeXuiClientInterface;
use Fireball\VpnManagerV2\Exceptions\ProvisioningException;
use Fireball\VpnManagerV2\Exceptions\ValidationException;
use Fireball\VpnManagerV2\Repositories\ServerRepository;
use Fireball\VpnManagerV2\Repositories\SubscriptionRepository;

final class RemoteClientSyncService
{
    public function __construct(
        private readonly ?SubscriptionRepository $repository = null,
        private readonly ?ClientPayloadFactory $payloadFactory = null,
        private readonly ?ClientVerifier $verifier = null,
        private readonly ?VpnFlowResolver $flowResolver = null,
        private readonly ?\Closure $clientFactory = null,
    ) {
    }

    public function push(array $node, array $subscription, array $nodeOverrides = []): array
    {
        $context = $this->context($node);
        $desiredNode = array_replace($node, $nodeOverrides);
        $resolver = $this->flowResolver ?? new VpnFlowResolver();
        $flow = $resolver->normalizeFlow($desiredNode['flow'] ?? null);
        if (!$resolver->isFlowCompatible($flow, $context['inbound'])) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_plan_flow_incompatible'));
        }
        $desiredNode['flow'] = $flow;

        $factory = $this->payloadFactory ?? new ClientPayloadFactory();
        $expected = $factory->build($subscription, $desiredNode);
        $client = $this->client($context['server'], $context['inbound'], $node);
        $remoteInboundId = (int)$context['inbound']['remote_inbound_id'];
        return $this->applyClientState($client, $remoteInboundId, $node, $expected);
    }

    /** Shared read/merge/update/read path for provisioning and every later change. */
    public function applyClientState(
        ThreeXuiClientInterface $client, int $remoteInboundId, array $node, array $expected,
        ?array $beforeInbound = null
    ): array {
        $beforeInbound ??= $client->getInbound($remoteInboundId);
        $verifier = $this->verifier ?? new ClientVerifier();
        $before = $this->readClientState($client, $beforeInbound, $node);
        if ($before === null) {
            throw new ProvisioningException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_client_not_found_for_update'));
        }
        $verifier->assertStableCredential($before, $expected);
        $changedFields = $verifier->changedFields($before, $expected);
        if ($changedFields !== []) {
            $credential = (new RemoteClientCredentialService())->credential($node);
            $payload = ($this->payloadFactory ?? new ClientPayloadFactory())->mergeForUpdate($before, $expected);
            // Resolve by stable credential, then address the panel's current
            // email. The local desired email may already contain the new name.
            if (method_exists($client, 'updateClientAtEmail')) {
                $client->updateClientAtEmail($remoteInboundId, $credential, (string)$before['email'], $payload);
            } else {
                $client->updateClient($remoteInboundId, $credential, $payload);
            }
        }
        $confirmedInbound = $changedFields !== [] ? $client->getInbound($remoteInboundId) : $beforeInbound;
        $confirmed = $changedFields !== [] ? $this->readClientState($client, $confirmedInbound, $node) : $before;
        if ($confirmed === null) {
            throw new ProvisioningException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_client_not_confirmed'));
        }
        // Confirm every owned field, including HWID stored outside inbound settings.
        $verifier->verify($confirmed, $expected);

        return [
            'remote_updated' => $changedFields !== [],
            'changed_fields' => $changedFields,
            'flow' => ($this->flowResolver ?? new VpnFlowResolver())->normalizeFlow($expected['flow'] ?? null),
            'enable' => (bool)$expected['enable'],
            // FIREBALL_VPN_HWID_RECONCILIATION_FINISH_V1: values confirmed in 3x-ui.
            'device_limit' => max(0, (int)($expected['limitHwid'] ?? 0)),
            'ip_limit' => max(0, (int)($expected['limitIp'] ?? 0)),
            'expiry_time' => max(0, (int)($expected['expiryTime'] ?? 0)),
            'traffic_limit_bytes' => (int)($expected['totalGB'] ?? 0) > 0 ? (int)$expected['totalGB'] : null,
            'traffic_used_bytes' => $this->trafficUsed($confirmedInbound, $confirmed, (string)$node['client_email']),
        ];
    }

    public function readClientState(ThreeXuiClientInterface $client, array $inbound, array $node): ?array
    {
        $verifier = $this->verifier ?? new ClientVerifier();
        $remote = $verifier->findInInbound($inbound, (new RemoteClientCredentialService())->credential($node),
            (string)$node['client_email'], (string)($node['remote_client_id'] ?? ''), (string)($node['client_sub_id'] ?? ''));
        if ($remote !== null && method_exists($client, 'findGlobalClient')) {
            $global = $client->findGlobalClient((string)$remote['email']);
            if ($global !== null) {
                $record = $global['client'];
                $verifier->assertStableCredential($record, $remote);
                // Current 3x-ui keeps these values on the global client record.
                // The inbound JSON is a projection and may lag behind it.
                foreach (['limitHwid', 'limitIp', 'totalGB', 'expiryTime', 'enable', 'flow',
                    'reset', 'resetDay', 'resetWeekday', 'resetMax'] as $field) {
                    if (array_key_exists($field, $record)) {
                        $remote[$field] = $record[$field];
                    }
                }
            }
        }
        return $remote;
    }

    /** A factual read only. No updates, creation, device registration or deletion. */
    public function inspect(array $node, array $subscription, array $nodeOverrides = []): array
    {
        $context = $this->context($node);
        $expected = ($this->payloadFactory ?? new ClientPayloadFactory())->build($subscription, array_replace($node, $nodeOverrides));
        $client = $this->client($context['server'], $context['inbound'], $node);
        $remote = $this->readClientState($client, $client->getInbound((int)$context['inbound']['remote_inbound_id']), $node);
        if ($remote === null) {
            return ['missing' => true, 'changed_fields' => [], 'differences' => []];
        }
        $verifier = $this->verifier ?? new ClientVerifier();
        $verifier->assertStableCredential($remote, $expected);
        $fields = $verifier->changedFields($remote, $expected);
        $differences = [];
        foreach ($fields as $field) {
            // Do not persist remote strings, identifiers, credentials or metadata in preview.
            $differences[$field] = in_array($field, ['limitHwid', 'limitIp', 'totalGB', 'expiryTime', 'enable'], true)
                ? ['actual' => isset($remote[$field]) ? (int)$remote[$field] : null, 'expected' => (int)$expected[$field]]
                : ['changed' => true];
        }
        return ['missing' => false, 'changed_fields' => $fields, 'differences' => $differences];
    }

    public function pull(array $node): array
    {
        $context = $this->context($node);
        $client = $this->client($context['server'], $context['inbound'], $node);
        $inbound = $client->getInbound((int)$context['inbound']['remote_inbound_id']);
        $verifier = $this->verifier ?? new ClientVerifier($this->flowResolver ?? new VpnFlowResolver());
        $credential = (new RemoteClientCredentialService())->credential($node);
        $remote = $this->readClientState($client, $inbound, $node);
        if ($remote === null) {
            throw new ProvisioningException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_client_not_found_for_update'));
        }
        $identity = (new ClientPayloadFactory())->build($this->subscriptionState($node), $node);
        $verifier->assertIdentity($remote, $identity);

        $resolver = $this->flowResolver ?? new VpnFlowResolver();
        $flow = $resolver->normalizeFlow($remote['flow'] ?? null);
        if (!$resolver->isFlowCompatible($flow, $context['inbound'])) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_plan_flow_incompatible'));
        }
        $limit = (int)($remote['totalGB'] ?? 0);

        return [
            'flow' => $flow,
            'traffic_limit_bytes' => $limit > 0 ? $limit : null,
            'traffic_used_bytes' => $this->trafficUsed($inbound, $remote, (string)$node['client_email']),
            'enable' => $remote['enable'] ?? false,
            'expiry_time' => (int)($remote['expiryTime'] ?? 0),
            'device_limit' => max(0, (int)($remote['limitHwid'] ?? 0)),
            'ip_limit' => max(0, (int)($remote['limitIp'] ?? 0)),
        ];
    }

    private function context(array $node): array
    {
        if (db()->inTransaction()) {
            throw new ProvisioningException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_http_inside_transaction'));
        }
        $repository = $this->repository ?? new SubscriptionRepository();
        $server = (new ServerRepository())->findWithSecrets((int)($node['server_id'] ?? 0));
        $inbound = $repository->inbound((int)($node['inbound_id'] ?? 0));
        if (!$server || !$inbound || (int)$inbound['server_id'] !== (int)$server['id']) {
            throw new ProvisioningException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_connection_topology'));
        }

        return ['server' => $server, 'inbound' => $inbound];
    }

    private function client(array $server, array $inbound, array $node): ThreeXuiClientInterface
    {
        if ($this->clientFactory !== null) {
            $client = ($this->clientFactory)($server, $inbound, $node);
            if (!$client instanceof ThreeXuiClientInterface) {
                throw new \LogicException('Invalid ThreeXuiClient factory result.');
            }

            return $client;
        }

        return new ThreeXuiClient((new ServerSecretService())->clientConfig($server));
    }

    private function subscriptionState(array $node): array
    {
        return [
            'expires_at' => $node['expires_at'] ?? null,
            'status' => $node['subscription_status'] ?? 'active',
            'device_limit' => $node['device_limit'] ?? 0,
            'ip_limit' => $node['ip_limit'] ?? 0,
            'traffic_limit_bytes' => $node['subscription_traffic_limit_bytes'] ?? null,
        ];
    }

    private function trafficUsed(array $inbound, array $client, string $email): ?int
    {
        foreach ((array)($inbound['clientStats'] ?? []) as $stats) {
            if (!is_array($stats) || trim((string)($stats['email'] ?? '')) !== $email) {
                continue;
            }

            return max(0, (int)($stats['up'] ?? 0)) + max(0, (int)($stats['down'] ?? 0));
        }
        if (array_key_exists('up', $client) || array_key_exists('down', $client)) {
            return max(0, (int)($client['up'] ?? 0)) + max(0, (int)($client['down'] ?? 0));
        }

        return null;
    }
}
