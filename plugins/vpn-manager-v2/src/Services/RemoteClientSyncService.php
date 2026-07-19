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
        $beforeInbound = $client->getInbound($remoteInboundId);
        $verifier = $this->verifier ?? new ClientVerifier($resolver);
        $credential = (new RemoteClientCredentialService())->credential($node);
        $before = $verifier->findInInbound(
            $beforeInbound,
            $credential,
            (string)$node['client_email']
        );
        if ($before === null) {
            throw new ProvisioningException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_client_not_found_for_update'));
        }
        // UUID/password is the stable identity; email is CMS-owned and may need to be restored.
        $verifier->assertStableCredential($before, $expected);
        $changedFields = $verifier->changedFields($before, $expected);

        if ($changedFields !== []) {
            $client->updateClient(
                $remoteInboundId,
                $credential,
                $factory->mergeForUpdate($before, $expected)
            );
        }

        // A second read is mandatory after every actual update; a no-op already has a fresh factual read.
        $confirmedInbound = $changedFields !== [] ? $client->getInbound($remoteInboundId) : $beforeInbound;
        $confirmed = $verifier->findInInbound(
            $confirmedInbound,
            $credential,
            (string)$node['client_email']
        );
        if ($confirmed === null) {
            throw new ProvisioningException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_client_not_confirmed'));
        }
        $verifier->verifyFields($confirmed, $expected, $changedFields);

        return [
            'remote_updated' => $changedFields !== [],
            'changed_fields' => $changedFields,
            'flow' => $flow,
            'traffic_limit_bytes' => (int)($expected['totalGB'] ?? 0) > 0 ? (int)$expected['totalGB'] : null,
            'traffic_used_bytes' => $this->trafficUsed($confirmedInbound, $confirmed, (string)$node['client_email']),
        ];
    }

    public function pull(array $node): array
    {
        $context = $this->context($node);
        $client = $this->client($context['server'], $context['inbound'], $node);
        $inbound = $client->getInbound((int)$context['inbound']['remote_inbound_id']);
        $verifier = $this->verifier ?? new ClientVerifier($this->flowResolver ?? new VpnFlowResolver());
        $credential = (new RemoteClientCredentialService())->credential($node);
        $remote = $verifier->findInInbound($inbound, $credential, (string)$node['client_email']);
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
            'device_limit' => max(0, (int)($remote['limitIp'] ?? 0)),
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
