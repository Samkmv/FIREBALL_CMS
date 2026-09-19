<?php

namespace Fireball\VpnManagerV2\Repositories;

use Fireball\VpnManagerV2\Services\RemoteClientCredentialService;

final class ConfigurationSyncRepository
{
    public function enabledServers(int $afterId = 0, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));

        return db()->query(
            'SELECT * FROM vpn_v2_servers
             WHERE id > ? AND is_enabled = 1 ORDER BY id ASC LIMIT ' . $limit,
            [$afterId]
        )->get() ?: [];
    }

    public function nodesForServer(int $serverId): array
    {
        return db()->query(
            'SELECT n.*, sub.user_id, sub.plan_id, sub.status AS subscription_status,
                    sub.starts_at, sub.expires_at, sub.device_limit,
                    sub.traffic_limit_bytes AS subscription_traffic_limit_bytes,
                    u.name AS cms_user_name, u.login AS cms_user_login,
                    s.name AS server_name, s.code AS server_code, s.panel_url, s.country_code AS server_country_code,
                    s.country_name, s.city, s.show_flag,
                    i.remote_inbound_id, i.name AS inbound_name, i.remark AS inbound_remark,
                    i.protocol AS inbound_protocol, i.port, i.network AS inbound_network,
                    i.security AS inbound_security, i.settings_json, i.stream_settings_json
             FROM vpn_v2_subscription_nodes n
             INNER JOIN vpn_v2_subscriptions sub ON sub.id = n.subscription_id
             INNER JOIN users u ON u.id = sub.user_id
             INNER JOIN vpn_v2_servers s ON s.id = n.server_id
             INNER JOIN vpn_v2_inbounds i ON i.id = n.inbound_id
             WHERE n.server_id = ? AND n.status NOT IN (\'deleted\', \'deleting\')
             ORDER BY n.id ASC',
            [$serverId]
        )->get() ?: [];
    }

    public function inboundsForServer(int $serverId): array
    {
        return db()->query(
            'SELECT * FROM vpn_v2_inbounds WHERE server_id = ? ORDER BY id ASC',
            [$serverId]
        )->get() ?: [];
    }

    public function beginRemoteInventorySync(int $serverId): void
    {
        db()->query(
            "UPDATE vpn_v2_remote_clients
             SET management_status = CASE
                    WHEN connection_id IS NULL THEN 'stale_remote' ELSE 'stale_managed' END,
                 updated_at = ? WHERE server_id = ?",
            [date('Y-m-d H:i:s'), $serverId]
        );
    }

    public function storeSnapshot(
        array $node,
        array $snapshot,
        string $hash,
        string $source,
        ?string $operationId = null,
        ?string $remoteCredential = null
    ): array {
        $database = db();
        $database->beginTransaction();
        try {
            $current = $database->query(
                'SELECT id, subscription_id, server_id, inbound_id, client_uuid,
                        encrypted_client_credential, desired_enabled, status,
                        lkg_snapshot_json, lkg_snapshot_hash, lkg_snapshot_version
                 FROM vpn_v2_subscription_nodes WHERE id = ? FOR UPDATE',
                [(int)$node['id']]
            )->getOne();
            if (!is_array($current)) {
                throw new \RuntimeException('VPN connection disappeared during synchronization.');
            }
            $previousHash = trim((string)($current['lkg_snapshot_hash'] ?? ''));
            $changed = $previousHash === '' || !hash_equals($previousHash, $hash);
            $now = date('Y-m-d H:i:s');
            $snapshotJson = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $status = !empty($current['desired_enabled']) ? 'active' : 'disabled';
            $protocol = strtolower(trim((string)($snapshot['protocol'] ?? $node['protocol'] ?? '')));
            $credentials = new RemoteClientCredentialService();
            $usesPassword = $credentials->usesPassword($protocol);
            $remoteCredential = trim((string)$remoteCredential);
            $clientUuid = $usesPassword
                ? trim((string)($node['client_uuid'] ?? $current['client_uuid'] ?? ''))
                : ($remoteCredential !== '' ? $remoteCredential : trim((string)($snapshot['client_uuid'] ?? '')));
            $remoteClientId = $usesPassword ? null : ($remoteCredential !== '' ? $remoteCredential : null);
            $encryptedCredential = $credentials->encryptForStorage($protocol, $remoteCredential);
            $remoteName = trim((string)($snapshot['client_email'] ?? $node['remote_client_name'] ?? ''));

            if ($changed) {
                $version = (int)$current['lkg_snapshot_version'] + 1;
                $database->query(
                    'INSERT INTO vpn_v2_connection_snapshots
                        (connection_id, subscription_id, server_id, inbound_id, snapshot_version,
                         config_hash, snapshot_json, source, validity, operation_id,
                         received_at, confirmed_at, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'valid\', ?, ?, ?, ?)',
                    [
                        (int)$current['id'],
                        (int)$current['subscription_id'],
                        (int)$current['server_id'],
                        (int)$current['inbound_id'],
                        $version,
                        $hash,
                        $snapshotJson,
                        mb_substr($source, 0, 40),
                        $operationId,
                        $now,
                        $now,
                        $now,
                    ]
                );
                $database->query(
                    'UPDATE vpn_v2_subscription_nodes
                     SET remote_client_id = ?, client_uuid = ?, encrypted_client_credential = COALESCE(?, encrypted_client_credential),
                         client_email = ?, remote_client_name = ?,
                         protocol = ?, network = ?, security = ?, flow = ?, status = ?, sync_status = \'synced\',
                         sync_error = NULL, last_remote_hash = ?, last_local_hash = ?, last_seen_remote_at = ?,
                         last_operation_id = ?, lkg_snapshot_json = ?, lkg_snapshot_hash = ?,
                         lkg_snapshot_version = ?, lkg_received_at = ?, lkg_confirmed_at = ?,
                         lkg_source = ?, lkg_validity = \'valid\', last_sync_at = ?, last_error = NULL, updated_at = ?
                     WHERE id = ?',
                    [
                        $remoteClientId,
                        $clientUuid,
                        $encryptedCredential,
                        $remoteName,
                        $remoteName,
                        $snapshot['protocol'] ?? $node['protocol'],
                        $snapshot['network'] ?? null,
                        $snapshot['security'] ?? null,
                        $snapshot['flow'] ?? null,
                        $status,
                        $hash,
                        $hash,
                        $now,
                        $operationId,
                        $snapshotJson,
                        $hash,
                        $version,
                        $now,
                        $now,
                        mb_substr($source, 0, 40),
                        $now,
                        $now,
                        (int)$current['id'],
                    ]
                );
            } else {
                $database->query(
                    'UPDATE vpn_v2_subscription_nodes
                     SET remote_client_id = ?, client_uuid = ?,
                         encrypted_client_credential = COALESCE(?, encrypted_client_credential),
                         status = ?, sync_status = \'synced\', sync_error = NULL, last_seen_remote_at = ?,
                         last_operation_id = ?, last_sync_at = ?, last_error = NULL, updated_at = ? WHERE id = ?',
                    [
                        $remoteClientId,
                        $clientUuid,
                        $encryptedCredential,
                        $status,
                        $now,
                        $operationId,
                        $now,
                        $now,
                        (int)$current['id'],
                    ]
                );
            }
            $database->commit();

            return [
                'changed' => $changed,
                'previous_hash' => $previousHash !== '' ? $previousHash : null,
                'new_hash' => $hash,
                'previous_snapshot' => $this->decode($current['lkg_snapshot_json'] ?? null),
            ];
        } catch (\Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    public function markInvalidSnapshot(int $nodeId, string $safeError): void
    {
        db()->query(
            "UPDATE vpn_v2_subscription_nodes
             SET status = CASE WHEN lkg_snapshot_hash IS NULL THEN 'invalid_snapshot' ELSE status END,
                 sync_status = 'invalid_snapshot', sync_error = ?, lkg_validity = CASE
                    WHEN lkg_snapshot_hash IS NULL THEN 'invalid' ELSE lkg_validity END,
                 updated_at = ? WHERE id = ?",
            [mb_substr(trim($safeError), 0, 1000), date('Y-m-d H:i:s'), $nodeId]
        );
    }

    public function moveNodeInbound(int $nodeId, int $inboundId): bool
    {
        $node = db()->query(
            'SELECT subscription_id, server_id FROM vpn_v2_subscription_nodes WHERE id = ? LIMIT 1',
            [$nodeId]
        )->getOne();
        if (!is_array($node)) {
            return false;
        }
        $conflict = db()->query(
            'SELECT id FROM vpn_v2_subscription_nodes
             WHERE subscription_id = ? AND server_id = ? AND inbound_id = ? AND id <> ? LIMIT 1',
            [(int)$node['subscription_id'], (int)$node['server_id'], $inboundId, $nodeId]
        )->getOne();
        if (is_array($conflict)) {
            return false;
        }
        db()->query(
            'UPDATE vpn_v2_subscription_nodes SET inbound_id = ?, updated_at = ? WHERE id = ?',
            [$inboundId, date('Y-m-d H:i:s'), $nodeId]
        );

        return db()->rowCount() === 1;
    }

    public function setExpectedRemoteName(int $nodeId, string $name, string $countryCode): void
    {
        db()->query(
            'UPDATE vpn_v2_subscription_nodes
             SET client_email = ?, remote_client_name = ?, country_code = ?,
                 sync_status = \'identity_mismatch\', updated_at = ? WHERE id = ?',
            [mb_substr($name, 0, 190), mb_substr($name, 0, 190), strtoupper($countryCode), date('Y-m-d H:i:s'), $nodeId]
        );
    }

    public function serverIdsForSubscription(int $subscriptionId): array
    {
        $rows = db()->query(
            'SELECT DISTINCT server_id FROM vpn_v2_subscription_nodes
             WHERE subscription_id = ? AND status NOT IN (\'deleted\', \'deleting\') ORDER BY server_id',
            [$subscriptionId]
        )->get() ?: [];

        return array_values(array_filter(array_map(
            static fn(array $row): int => (int)($row['server_id'] ?? 0),
            $rows
        )));
    }

    public function markMissingRemote(array $node, ?string $operationId = null): void
    {
        $now = date('Y-m-d H:i:s');
        db()->query(
            "UPDATE vpn_v2_subscription_nodes
             SET status = 'missing_remote', sync_status = 'missing_remote',
                 sync_error = ?, last_operation_id = ?, last_sync_at = ?, updated_at = ? WHERE id = ?",
            [
                \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_remote_client_missing'),
                $operationId,
                $now,
                $now,
                (int)$node['id'],
            ]
        );
    }

    public function markServerUnavailable(int $serverId, string $safeError): void
    {
        $safeError = mb_substr(trim($safeError), 0, 1000);
        $now = date('Y-m-d H:i:s');
        db()->query(
            "UPDATE vpn_v2_servers SET status = 'offline', last_check_at = ?, last_error = ?, updated_at = ? WHERE id = ?",
            [$now, $safeError, $now, $serverId]
        );
        db()->query(
            "UPDATE vpn_v2_subscription_nodes
             SET sync_status = 'remote_unavailable', sync_error = ?, updated_at = ?
             WHERE server_id = ? AND status NOT IN ('deleted', 'deleting')",
            [$safeError, $now, $serverId]
        );
    }

    public function markServerSynced(int $serverId, array $capabilities, int $inboundCount): void
    {
        $now = date('Y-m-d H:i:s');
        db()->query(
            "UPDATE vpn_v2_servers
             SET status = 'online', last_check_at = ?, last_success_at = ?, last_auth_at = ?,
                 last_sync_at = ?, capabilities_json = ?, last_error = NULL, updated_at = ? WHERE id = ?",
            [
                $now,
                $now,
                $now,
                $now,
                json_encode($capabilities, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $now,
                $serverId,
            ]
        );
        (new SubscriptionRepository())->logEvent(
            'server.configuration_synced',
            null,
            null,
            $serverId,
            null,
            null,
            ['inbound_count' => $inboundCount]
        );
    }

    public function upsertRemoteClient(
        int $serverId,
        int $inboundId,
        array $client,
        string $protocol,
        string $hash,
        ?int $connectionId
    ): void {
        $now = date('Y-m-d H:i:s');
        $credentials = new RemoteClientCredentialService();
        $usesPassword = $credentials->usesPassword($protocol);
        $credential = $credentials->remoteCredential($protocol, $client);
        $remoteId = $usesPassword
            ? ''
            : trim((string)($client['remote_client_id'] ?? $client['id'] ?? $client['uuid'] ?? ''));
        $uuid = $usesPassword ? '' : $credential;
        $encryptedCredential = $credentials->encryptForStorage($protocol, $credential);
        $name = trim((string)($client['email'] ?? ''));
        $json = json_encode($this->redact($client), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        db()->query(
            'INSERT INTO vpn_v2_remote_clients
                (server_id, inbound_id, remote_client_id, remote_client_name, client_uuid, encrypted_client_credential,
                 remote_hash, normalized_json, management_status, connection_id,
                 first_seen_at, last_seen_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE remote_client_id = VALUES(remote_client_id),
                 remote_client_name = VALUES(remote_client_name), client_uuid = VALUES(client_uuid),
                 encrypted_client_credential = VALUES(encrypted_client_credential),
                 normalized_json = VALUES(normalized_json), management_status = VALUES(management_status),
                 connection_id = VALUES(connection_id), last_seen_at = VALUES(last_seen_at),
                 updated_at = VALUES(updated_at)',
            [
                $serverId,
                $inboundId,
                $remoteId !== '' ? mb_substr($remoteId, 0, 120) : null,
                $name !== '' ? mb_substr($name, 0, 190) : null,
                $uuid !== '' ? mb_substr($uuid, 0, 64) : null,
                $encryptedCredential,
                $hash,
                $json,
                $connectionId !== null ? 'managed' : 'unmanaged_remote',
                $connectionId,
                $now,
                $now,
                $now,
                $now,
            ]
        );
    }

    public function provisionableNodes(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));

        return db()->query(
            "SELECT n.id FROM vpn_v2_subscription_nodes n
             INNER JOIN vpn_v2_subscriptions s ON s.id = n.subscription_id
             WHERE n.status IN ('creating', 'create_failed', 'missing_remote')
               AND s.status IN ('active', 'provisioning', 'provisioning_failed', 'partial_sync', 'sync_error', 'suspended')
               AND s.starts_at <= NOW() AND (s.expires_at IS NULL OR s.expires_at > NOW())
             ORDER BY n.updated_at ASC, n.id ASC LIMIT " . $limit
        )->get() ?: [];
    }

    public function unmanagedRemoteClients(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));

        return db()->query(
            "SELECT r.id, r.server_id, r.inbound_id, r.remote_client_name,
                    CASE WHEN r.encrypted_client_credential IS NOT NULL AND r.encrypted_client_credential <> '' THEN '••••••••'
                         WHEN r.client_uuid IS NULL OR r.client_uuid = '' THEN NULL
                         ELSE CONCAT(LEFT(r.client_uuid, 8), '…', RIGHT(r.client_uuid, 4)) END AS credential_preview,
                    r.last_seen_at, s.name AS server_name, i.name AS inbound_name
             FROM vpn_v2_remote_clients r
             INNER JOIN vpn_v2_servers s ON s.id = r.server_id
             INNER JOIN vpn_v2_inbounds i ON i.id = r.inbound_id
             WHERE r.management_status = 'unmanaged_remote' AND r.connection_id IS NULL
             ORDER BY r.last_seen_at DESC, r.id DESC LIMIT " . $limit
        )->get() ?: [];
    }

    /**
     * Explicit administrator resolution for an otherwise ambiguous remote client.
     * The next queued sync still performs the normal remote read and validation.
     */
    public function linkRemoteClient(int $remoteClientId, int $connectionId): array
    {
        $database = db();
        $database->beginTransaction();
        try {
            $remote = $database->query(
                'SELECT * FROM vpn_v2_remote_clients WHERE id = ? FOR UPDATE',
                [$remoteClientId]
            )->getOne();
            $node = $database->query(
                'SELECT id, subscription_id, server_id, inbound_id, protocol, client_uuid,
                        encrypted_client_credential FROM vpn_v2_subscription_nodes
                 WHERE id = ? AND status NOT IN (\'deleted\', \'deleting\') FOR UPDATE',
                [$connectionId]
            )->getOne();
            if (!is_array($remote) || !is_array($node)) {
                throw new \RuntimeException('The remote client or local connection was not found.');
            }
            if ((int)$remote['server_id'] !== (int)$node['server_id']) {
                throw new \RuntimeException('The remote client and local connection belong to different servers.');
            }
            if (!empty($remote['connection_id']) && (int)$remote['connection_id'] !== $connectionId) {
                throw new \RuntimeException('The remote client is already linked.');
            }
            $targetConflict = $database->query(
                'SELECT id FROM vpn_v2_subscription_nodes
                 WHERE subscription_id = ? AND server_id = ? AND inbound_id = ? AND id <> ? LIMIT 1',
                [(int)$node['subscription_id'], (int)$node['server_id'], (int)$remote['inbound_id'], $connectionId]
            )->getOne();
            if (is_array($targetConflict)) {
                throw new \RuntimeException('The subscription already has a connection for the remote inbound.');
            }
            $credential = trim((string)($remote['client_uuid'] ?? ''));
            $encryptedCredential = trim((string)($remote['encrypted_client_credential'] ?? ''));
            $remoteIdentity = $encryptedCredential !== ''
                ? null
                : (trim((string)($remote['remote_client_id'] ?? '')) ?: null);
            $now = date('Y-m-d H:i:s');
            $database->query(
                "UPDATE vpn_v2_subscription_nodes
                 SET inbound_id = ?, remote_client_id = ?,
                     client_uuid = COALESCE(NULLIF(?, ''), client_uuid),
                     encrypted_client_credential = COALESCE(NULLIF(?, ''), encrypted_client_credential),
                     sync_status = 'pending',
                     sync_error = NULL, updated_at = ? WHERE id = ?",
                [
                    (int)$remote['inbound_id'],
                    $remoteIdentity,
                    $credential,
                    $encryptedCredential,
                    $now,
                    $connectionId,
                ]
            );
            $database->query(
                "UPDATE vpn_v2_remote_clients
                 SET management_status = 'managed', connection_id = ?, updated_at = ? WHERE id = ?",
                [$connectionId, $now, $remoteClientId]
            );
            $database->commit();

            return [
                'server_id' => (int)$node['server_id'],
                'subscription_id' => (int)$node['subscription_id'],
                'connection_id' => $connectionId,
            ];
        } catch (\Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    private function decode(mixed $json): array
    {
        $decoded = is_string($json) ? json_decode($json, true) : null;

        return is_array($decoded) ? $decoded : [];
    }

    private function redact(array $value): array
    {
        foreach ($value as $key => $item) {
            if (in_array(strtolower((string)$key), [
                'password', 'passwd', 'secret', 'token', 'access_token', 'session', 'cookie',
                'privatekey', 'private_key', 'mldsa65seed', 'mldsa65_seed',
            ], true)) {
                $value[$key] = '[redacted]';
            } elseif (is_array($item)) {
                $value[$key] = $this->redact($item);
            }
        }

        return $value;
    }
}
