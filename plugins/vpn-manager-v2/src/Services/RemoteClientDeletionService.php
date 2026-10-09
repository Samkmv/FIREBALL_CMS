<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\Clients\ThreeXuiClient;
use Fireball\VpnManagerV2\Clients\ThreeXuiClientInterface;
use Fireball\VpnManagerV2\Exceptions\ClientVerificationException;
use Fireball\VpnManagerV2\Exceptions\ProvisioningException;
use Fireball\VpnManagerV2\Repositories\ServerRepository;
use Fireball\VpnManagerV2\Repositories\SubscriptionRepository;

final class RemoteClientDeletionService
{
    public function __construct(
        private readonly ?SubscriptionRepository $repository = null,
        private readonly ?\Closure $clientFactory = null,
    ) {
    }

    public function delete(array $node): array
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

        $client = $this->client($server, $inbound, $node);
        $remoteInboundId = (int)$inbound['remote_inbound_id'];
        $before = $client->getInbound($remoteInboundId);
        $matches = $this->matches($before, $node);
        if ($matches === []) {
            return ['deleted' => true, 'already_absent' => true];
        }
        if (count($matches) !== 1) {
            throw new ClientVerificationException(
                \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_delete_identity_ambiguous')
            );
        }
        $this->assertExactIdentity($matches[0], $node);

        $client->deleteClient(
            $remoteInboundId,
            (new RemoteClientCredentialService())->credential($node),
            (string)$node['client_email']
        );
        $after = $client->getInbound($remoteInboundId);
        if ($this->matches($after, $node) !== []) {
            throw new ClientVerificationException(
                \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_client_delete_not_confirmed')
            );
        }

        return ['deleted' => true, 'already_absent' => false];
    }

    public function matches(array $inbound, array $node): array
    {
        $settings = $inbound['settings'] ?? [];
        if (is_string($settings)) {
            $decoded = json_decode($settings, true);
            $settings = $decoded;
        }
        if (!is_array($settings) || !isset($settings['clients']) || !is_array($settings['clients'])) {
            throw new ClientVerificationException(
                \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_invalid_client_response')
            );
        }
        $uuid = (new RemoteClientCredentialService())->credential($node);
        $email = trim((string)($node['client_email'] ?? ''));
        if ($uuid === '' || $email === '') {
            throw new ClientVerificationException(
                \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_client_identity_changed')
            );
        }
        $verifier = new ClientVerifier();
        $matches = [];
        foreach ($settings['clients'] as $candidate) {
            if (!is_array($candidate)) {
                throw new ClientVerificationException(
                    \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_invalid_client_response')
                );
            }
            $remoteEmail = trim((string)($candidate['email'] ?? ''));
            // subId identifies a subscription group and can belong to other clients.
            // A numeric REST id also differs from the uuid/password credential.
            if ($verifier->credentialMatches($candidate, $uuid)
                || ($email !== '' && $remoteEmail !== '' && hash_equals($email, $remoteEmail))) {
                $matches[] = $candidate;
            }
        }

        return $matches;
    }

    private function assertExactIdentity(array $remote, array $node): void
    {
        (new ClientVerifier())->assertIdentity($remote, [
            'id' => (new RemoteClientCredentialService())->credential($node),
            'email' => (string)($node['client_email'] ?? ''),
        ]);
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
}
