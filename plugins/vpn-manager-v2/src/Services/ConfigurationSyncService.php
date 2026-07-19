<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\Clients\ThreeXuiCapabilities;
use Fireball\VpnManagerV2\Clients\ThreeXuiClient;
use Fireball\VpnManagerV2\Clients\ThreeXuiClientInterface;
use Fireball\VpnManagerV2\Clients\ThreeXuiResponseMapper;
use Fireball\VpnManagerV2\Exceptions\VpnManagerV2Exception;
use Fireball\VpnManagerV2\Repositories\ConfigurationSyncRepository;
use Fireball\VpnManagerV2\Repositories\InboundRepository;
use Fireball\VpnManagerV2\Repositories\OperationQueueRepository;
use Fireball\VpnManagerV2\Repositories\ServerRepository;
use Fireball\VpnManagerV2\Repositories\SyncAuditRepository;

final class ConfigurationSyncService
{
    public function __construct(
        private readonly ?ConfigurationSyncRepository $repository = null,
        private readonly ?InboundRepository $inbounds = null,
        private readonly ?ServerRepository $servers = null,
        private readonly ?ServerSecretService $secrets = null,
        private readonly ?InboundParser $parser = null,
        private readonly ?ConfigurationSnapshotService $snapshots = null,
        private readonly ?RemoteClientNameGenerator $names = null,
        private readonly ?RemoteClientIdentityService $identities = null,
        private readonly ?OperationQueueRepository $operations = null,
        private readonly ?SyncAuditRepository $audit = null,
        private readonly ?\Closure $clientFactory = null,
        private readonly ?VpnSubscriptionRevisionService $revisions = null,
    ) {
    }

    public function syncServer(int $serverId, string $source = 'reconciliation', ?string $operationId = null): array
    {
        $started = microtime(true);
        $repository = $this->repository ?? new ConfigurationSyncRepository();
        $server = ($this->servers ?? new ServerRepository())->findWithSecrets($serverId);
        if (!$server || empty($server['is_enabled'])) {
            throw new \RuntimeException('VPN server is unavailable for synchronization.');
        }

        try {
            $client = $this->client($server);
            $remoteInbounds = $client->listInbounds();
            $parsed = [];
            foreach ($remoteInbounds as $remoteInbound) {
                $parsed[] = ($this->parser ?? new InboundParser())->parse($remoteInbound);
            }
            ($this->inbounds ?? new InboundRepository())->syncServer($serverId, $parsed);
            $repository->beginRemoteInventorySync($serverId);
            $localInbounds = $this->indexInbounds($repository->inboundsForServer($serverId));
            $remoteClients = $this->remoteClients($remoteInbounds, $localInbounds);
            $nodes = $repository->nodesForServer($serverId);
            $matchedRemote = [];
            $changedSubscriptions = [];
            $counts = [
                'servers' => 1,
                'inbounds' => count($remoteInbounds),
                'clients' => count($remoteClients),
                'matched' => 0,
                'changed' => 0,
                'missing' => 0,
                'conflicts' => 0,
                'queued' => 0,
                'unmanaged' => 0,
                'errors' => 0,
            ];

            foreach ($nodes as $node) {
                $match = $this->match($node, $remoteClients);
                if ($match['conflict']) {
                    $counts['conflicts']++;
                    ($this->audit ?? new SyncAuditRepository())->conflict([
                        'conflict_type' => 'ambiguous_match',
                        'server_id' => $serverId,
                        'subscription_id' => (int)$node['subscription_id'],
                        'connection_id' => (int)$node['id'],
                        'local_value' => $this->maskedIdentity($node),
                        'remote_value' => 'multiple_candidates',
                        'recommended_action' => 'manual_link',
                        'operation_id' => $operationId,
                    ]);
                    $this->log($node, $source, $operationId, 'sync_conflict', $started, 'ambiguous_match');
                    continue;
                }
                if (!is_array($match['remote'])) {
                    $counts['missing']++;
                    $repository->markMissingRemote($node, $operationId);
                    if ($this->shouldRestore($node)) {
                        ($this->operations ?? new OperationQueueRepository())->enqueue(
                            'create_client',
                            $source,
                            $serverId,
                            (int)$node['subscription_id'],
                            (int)$node['id'],
                            [],
                            null
                        );
                        $counts['queued']++;
                    }
                    $this->log($node, $source, $operationId, 'missing_remote', $started, 'client_missing');
                    continue;
                }

                $remote = $match['remote'];
                if (isset($matchedRemote[(string)$remote['key']])) {
                    $counts['conflicts']++;
                    ($this->audit ?? new SyncAuditRepository())->conflict([
                        'conflict_type' => 'remote_already_bound',
                        'server_id' => $serverId,
                        'subscription_id' => (int)$node['subscription_id'],
                        'connection_id' => (int)$node['id'],
                        'local_value' => $this->maskedIdentity($node),
                        'remote_value' => 'already_matched',
                        'recommended_action' => 'resolve_subscription_overlap',
                        'operation_id' => $operationId,
                    ]);
                    $this->log($node, $source, $operationId, 'sync_conflict', $started, 'remote_already_bound');
                    continue;
                }
                $matchedRemote[(string)$remote['key']] = true;
                $counts['matched']++;
                if ((int)$remote['inbound']['id'] !== (int)$node['inbound_id']) {
                    if (!$repository->moveNodeInbound((int)$node['id'], (int)$remote['inbound']['id'])) {
                        $counts['conflicts']++;
                        ($this->audit ?? new SyncAuditRepository())->conflict([
                            'conflict_type' => 'inbound_move_conflict',
                            'server_id' => $serverId,
                            'subscription_id' => (int)$node['subscription_id'],
                            'connection_id' => (int)$node['id'],
                            'local_value' => 'inbound#' . (int)$node['inbound_id'],
                            'remote_value' => 'inbound#' . (int)$remote['inbound']['id'],
                            'recommended_action' => 'resolve_duplicate_target',
                            'operation_id' => $operationId,
                        ]);
                        continue;
                    }
                    $node['inbound_id'] = (int)$remote['inbound']['id'];
                }

                $identity = ($this->identities ?? new RemoteClientIdentityService())->forSubscription(
                    ['id' => (int)$node['subscription_id'], 'user_id' => (int)$node['user_id']],
                    ['country_code' => (string)$server['country_code'], 'protocol' => (string)$node['protocol']]
                );
                $credentials = new RemoteClientCredentialService();
                if ($credentials->usesPassword((string)$node['protocol'])) {
                    // Password protocols keep a non-secret logical UUID locally;
                    // the factual remote password is encrypted separately below.
                    $node['client_uuid'] = (string)$identity['client_uuid'];
                }
                $expectedName = (string)$identity['remote_client_name'];
                $remoteName = trim((string)($remote['client']['email'] ?? ''));
                if ($remoteName !== $expectedName) {
                    $repository->setExpectedRemoteName((int)$node['id'], $expectedName, (string)$server['country_code']);
                    $node['client_email'] = $expectedName;
                    $node['remote_client_name'] = $expectedName;
                    ($this->operations ?? new OperationQueueRepository())->enqueue(
                        'rename_client',
                        'three_x_ui',
                        $serverId,
                        (int)$node['subscription_id'],
                        (int)$node['id'],
                        [],
                        null
                    );
                    $counts['queued']++;
                }

                $snapshotClient = $remote['client'];
                $snapshotClient['email'] = $expectedName;
                $snapshot = ($this->snapshots ?? new ConfigurationSnapshotService())->fromRemote(
                    $server,
                    $remote['raw_inbound'],
                    $snapshotClient,
                    $node
                );
                $validationError = ($this->snapshots ?? new ConfigurationSnapshotService())->validate($snapshot);
                if ($validationError !== null) {
                    $repository->markInvalidSnapshot((int)$node['id'], $validationError);
                    $counts['errors']++;
                    $this->log($node, $source, $operationId, 'invalid_snapshot', $started, $validationError);
                    continue;
                }
                $hash = ($this->snapshots ?? new ConfigurationSnapshotService())->hash($snapshot);
                $remoteCredential = $credentials->remoteCredential((string)$node['protocol'], $remote['client']);
                $stored = $repository->storeSnapshot(
                    $node,
                    $snapshot,
                    $hash,
                    $source,
                    $operationId,
                    $remoteCredential
                );
                $repository->upsertRemoteClient(
                    $serverId,
                    (int)$remote['inbound']['id'],
                    $remote['client'],
                    (string)$node['protocol'],
                    (string)$remote['hash'],
                    (int)$node['id']
                );
                if (!empty($stored['changed'])) {
                    $counts['changed']++;
                    $changedSubscriptions[(int)$node['subscription_id']] = true;
                }
                if ($this->remotePolicyMismatch($node, $remote['client'], $expectedName)) {
                    ($this->operations ?? new OperationQueueRepository())->enqueue(
                        'update_client',
                        'reconciliation',
                        $serverId,
                        (int)$node['subscription_id'],
                        (int)$node['id'],
                        [],
                        null
                    );
                    $counts['queued']++;
                }
                $changedFields = ($this->snapshots ?? new ConfigurationSnapshotService())->changedFields(
                    (array)($stored['previous_snapshot'] ?? []),
                    $snapshot
                );
                $this->log(
                    $node,
                    $source,
                    $operationId,
                    !empty($stored['changed']) ? 'changed' : 'synced',
                    $started,
                    null,
                    $stored['previous_hash'] ?? null,
                    $hash,
                    $changedFields
                );
            }

            foreach ($remoteClients as $remote) {
                if (isset($matchedRemote[(string)$remote['key']])) {
                    continue;
                }
                $repository->upsertRemoteClient(
                    $serverId,
                    (int)$remote['inbound']['id'],
                    $remote['client'],
                    (string)($remote['inbound']['protocol'] ?? ''),
                    (string)$remote['hash'],
                    null
                );
                $counts['unmanaged']++;
            }

            foreach (array_keys($changedSubscriptions) as $subscriptionId) {
                ($this->revisions ?? new VpnSubscriptionRevisionService())->touchConfig((int)$subscriptionId);
            }
            $capabilities = (new ThreeXuiCapabilities())->detect($remoteInbounds);
            $repository->markServerSynced($serverId, $capabilities, count($remoteInbounds));

            return $counts;
        } catch (\Throwable $exception) {
            $safeError = $this->safeError($exception);
            $repository->markServerUnavailable($serverId, $safeError);
            ($this->audit ?? new SyncAuditRepository())->log([
                'operation_id' => $operationId,
                'operation_type' => 'sync_server',
                'source' => $source,
                'server_id' => $serverId,
                'status' => 'remote_unavailable',
                'error_code' => $this->errorCode($exception),
                'safe_error' => $safeError,
                'duration_ms' => (int)round((microtime(true) - $started) * 1000),
            ]);
            throw $exception;
        }
    }

    public function syncAll(int $afterServerId = 0, int $limit = 20, string $source = 'reconciliation'): array
    {
        $result = ['servers' => 0, 'changed' => 0, 'errors' => 0, 'last_server_id' => $afterServerId];
        foreach (($this->repository ?? new ConfigurationSyncRepository())->enabledServers($afterServerId, $limit) as $server) {
            $result['servers']++;
            $result['last_server_id'] = (int)$server['id'];
            try {
                $sync = $this->syncServer((int)$server['id'], $source);
                $result['changed'] += (int)($sync['changed'] ?? 0);
            } catch (\Throwable) {
                $result['errors']++;
            }
        }

        return $result;
    }

    public function syncAllPages(int $maximumServers = 1000, int $batchSize = 100, string $source = 'reconciliation'): array
    {
        $maximumServers = max(1, min(10000, $maximumServers));
        $batchSize = max(1, min(100, $batchSize));
        $aggregate = ['servers' => 0, 'changed' => 0, 'errors' => 0, 'last_server_id' => 0];
        do {
            $remaining = $maximumServers - $aggregate['servers'];
            if ($remaining <= 0) {
                break;
            }
            $pageLimit = min($batchSize, $remaining);
            $page = $this->syncAll($aggregate['last_server_id'], $pageLimit, $source);
            $aggregate['servers'] += (int)$page['servers'];
            $aggregate['changed'] += (int)$page['changed'];
            $aggregate['errors'] += (int)$page['errors'];
            $aggregate['last_server_id'] = (int)$page['last_server_id'];
        } while ((int)$page['servers'] === $pageLimit);

        $aggregate['processed'] = $aggregate['servers'];
        $aggregate['total'] = $aggregate['servers'];

        return $aggregate;
    }

    private function client(array $server): ThreeXuiClientInterface
    {
        if ($this->clientFactory !== null) {
            $client = ($this->clientFactory)($server);
            if (!$client instanceof ThreeXuiClientInterface) {
                throw new \RuntimeException('Invalid 3x-ui client factory result.');
            }

            return $client;
        }

        return new ThreeXuiClient(($this->secrets ?? new ServerSecretService())->clientConfig($server));
    }

    private function indexInbounds(array $inbounds): array
    {
        $indexed = [];
        foreach ($inbounds as $inbound) {
            $indexed[(string)$inbound['remote_inbound_id']] = $inbound;
        }

        return $indexed;
    }

    private function remoteClients(array $remoteInbounds, array $localInbounds): array
    {
        $mapper = new ThreeXuiResponseMapper();
        $snapshots = $this->snapshots ?? new ConfigurationSnapshotService();
        $items = [];
        foreach ($remoteInbounds as $remoteInbound) {
            $remoteInboundId = (string)($remoteInbound['id'] ?? '');
            $inbound = $localInbounds[$remoteInboundId] ?? null;
            if (!is_array($inbound)) {
                continue;
            }
            foreach ($mapper->clients($remoteInbound) as $position => $client) {
                $protocol = strtolower(trim((string)($inbound['protocol'] ?? $remoteInbound['protocol'] ?? '')));
                $credentials = new RemoteClientCredentialService();
                $usesPassword = $credentials->usesPassword($protocol);
                $credential = $credentials->remoteCredential($protocol, $client);
                $identity = [
                    'remote_client_id' => $usesPassword
                        ? ''
                        : trim((string)($client['remote_client_id'] ?? $client['id'] ?? $client['uuid'] ?? '')),
                    'uuid' => $credential,
                    'name' => trim((string)($client['email'] ?? '')),
                    'sub_id' => trim((string)($client['subId'] ?? $client['subid'] ?? '')),
                ];
                $hash = $snapshots->hash($client);
                $items[] = [
                    'key' => $remoteInboundId . ':' . $position . ':' . $hash,
                    'inbound' => $inbound,
                    'raw_inbound' => $remoteInbound,
                    'client' => $client,
                    'identity' => $identity,
                    'hash' => $hash,
                ];
            }
        }

        return $items;
    }

    private function match(array $node, array $remoteClients): array
    {
        $candidates = [];
        $localCredential = (new RemoteClientCredentialService())->credential($node);
        foreach ($remoteClients as $remote) {
            $identity = $remote['identity'];
            $score = 0;
            if (trim((string)($node['remote_client_id'] ?? '')) !== ''
                && hash_equals((string)$node['remote_client_id'], (string)$identity['remote_client_id'])) {
                $score = 100;
            } elseif ($localCredential !== ''
                && hash_equals($localCredential, (string)$identity['uuid'])) {
                $score = 90;
            } elseif (trim((string)($node['client_sub_id'] ?? '')) !== ''
                && hash_equals((string)$node['client_sub_id'], (string)$identity['sub_id'])) {
                $score = 80;
            } elseif (trim((string)($node['remote_client_name'] ?? $node['client_email'] ?? '')) !== ''
                && hash_equals((string)($node['remote_client_name'] ?? $node['client_email']), (string)$identity['name'])) {
                $score = 60;
            }
            if ($score > 0 && (int)$remote['inbound']['id'] === (int)$node['inbound_id']) {
                $score += 5;
            }
            if ($score > 0) {
                $candidates[] = ['score' => $score, 'remote' => $remote];
            }
        }
        if ($candidates === []) {
            return ['remote' => null, 'conflict' => false];
        }
        usort($candidates, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
        $best = (int)$candidates[0]['score'];
        $bestCandidates = array_values(array_filter($candidates, static fn(array $item): bool => (int)$item['score'] === $best));

        return [
            'remote' => count($bestCandidates) === 1 ? $bestCandidates[0]['remote'] : null,
            'conflict' => count($bestCandidates) !== 1,
        ];
    }

    private function remotePolicyMismatch(array $node, array $client, string $expectedName): bool
    {
        $expiry = isset($node['expires_at']) && $node['expires_at'] !== null
            ? strtotime((string)$node['expires_at']) * 1000
            : 0;
        $limit = $node['subscription_traffic_limit_bytes'] !== null
            ? (int)$node['subscription_traffic_limit_bytes']
            : 0;
        $enabled = !in_array((string)$node['subscription_status'], [
            'suspended', 'expired', 'traffic_exceeded', 'deleting', 'delete_failed', 'deleted',
        ], true);

        return trim((string)($client['email'] ?? '')) !== $expectedName
            || (int)($client['expiryTime'] ?? 0) !== max(0, (int)$expiry)
            || (int)($client['totalGB'] ?? 0) !== max(0, $limit)
            || (int)($client['limitIp'] ?? 0) !== max(0, (int)$node['device_limit'])
            || (bool)($client['enable'] ?? false) !== $enabled;
    }

    private function shouldRestore(array $node): bool
    {
        return in_array((string)$node['subscription_status'], [
            'active', 'provisioning', 'provisioning_failed', 'partial_sync', 'sync_error', 'suspended',
        ], true)
            && (strtotime((string)($node['starts_at'] ?? '')) ?: 0) <= time()
            && (empty($node['expires_at']) || (strtotime((string)$node['expires_at']) ?: 0) > time());
    }

    private function log(
        array $node,
        string $source,
        ?string $operationId,
        string $status,
        float $started,
        ?string $errorCode = null,
        ?string $previousHash = null,
        ?string $newHash = null,
        array $changedFields = []
    ): void {
        ($this->audit ?? new SyncAuditRepository())->log([
            'operation_id' => $operationId,
            'operation_type' => 'sync_client',
            'source' => $source,
            'server_id' => (int)$node['server_id'],
            'subscription_id' => (int)$node['subscription_id'],
            'user_id' => (int)$node['user_id'],
            'connection_id' => (int)$node['id'],
            'previous_hash' => $previousHash,
            'new_hash' => $newHash,
            'changed_fields' => $changedFields,
            'status' => $status,
            'error_code' => $errorCode,
            'duration_ms' => (int)round((microtime(true) - $started) * 1000),
        ]);
    }

    private function maskedIdentity(array $node): string
    {
        $value = trim((string)($node['remote_client_name'] ?? $node['client_email'] ?? ''));

        return $value !== '' ? mb_substr($value, 0, 8) . '…' : 'connection#' . (int)$node['id'];
    }

    private function safeError(\Throwable $exception): string
    {
        return $exception instanceof VpnManagerV2Exception
            ? mb_substr(trim($exception->getMessage()), 0, 1000)
            : \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_sync_generic');
    }

    private function errorCode(\Throwable $exception): string
    {
        $class = get_class($exception);
        $position = strrpos($class, '\\');

        return mb_substr($position === false ? $class : substr($class, $position + 1), 0, 120);
    }
}
