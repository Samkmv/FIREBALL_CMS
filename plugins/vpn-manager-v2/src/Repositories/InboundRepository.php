<?php

namespace Fireball\VpnManagerV2\Repositories;

use Fireball\VpnManagerV2\DTO\InboundSyncResult;
use Fireball\VpnManagerV2\DTO\ParsedInbound;
use Fireball\VpnManagerV2\Exceptions\InboundSyncException;

final class InboundRepository
{
    public function all(): array
    {
        return db()->query(
            'SELECT i.id, i.server_id, i.remote_inbound_id, i.name, i.remark, i.protocol, i.port,
                    i.network, i.security, i.default_flow, i.status, i.is_enabled, i.synced_at,
                    i.created_at, i.updated_at, s.name AS server_name, s.code AS server_code
             FROM vpn_v2_inbounds i
             INNER JOIN vpn_v2_servers s ON s.id = i.server_id
             ORDER BY i.id ASC'
        )->get() ?: [];
    }

    public function forServer(int $serverId): array
    {
        return db()->query(
            'SELECT id, server_id, remote_inbound_id, name, remark, protocol, port, network, security,
                    default_flow, status, is_enabled, synced_at, created_at, updated_at
             FROM vpn_v2_inbounds WHERE server_id = ? ORDER BY id ASC',
            [$serverId]
        )->get() ?: [];
    }

    public function syncServer(int $serverId, array $inbounds): InboundSyncResult
    {
        $database = db();
        $database->beginTransaction();

        try {
            $existingRows = $database->query(
                'SELECT id, remote_inbound_id FROM vpn_v2_inbounds WHERE server_id = ?',
                [$serverId]
            )->get() ?: [];
            $existing = [];
            foreach ($existingRows as $row) {
                $existing[(string)$row['remote_inbound_id']] = (int)$row['id'];
            }

            $seen = [];
            $created = 0;
            $updated = 0;
            $now = date('Y-m-d H:i:s');
            foreach ($inbounds as $inbound) {
                if (!$inbound instanceof ParsedInbound) {
                    throw new InboundSyncException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_inbound_sync_data'));
                }
                if (isset($seen[$inbound->remoteInboundId])) {
                    throw new InboundSyncException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_inbound_duplicate_remote'));
                }
                $seen[$inbound->remoteInboundId] = true;
                $data = $inbound->toRepositoryArray();

                $database->query(
                    'INSERT INTO vpn_v2_inbounds
                        (server_id, remote_inbound_id, name, remark, protocol, port, network, security,
                         default_flow, settings_json, stream_settings_json, status, is_enabled,
                         synced_at, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE
                        name = VALUES(name), remark = VALUES(remark), protocol = VALUES(protocol),
                        port = VALUES(port), network = VALUES(network), security = VALUES(security),
                        default_flow = VALUES(default_flow), settings_json = VALUES(settings_json),
                        stream_settings_json = VALUES(stream_settings_json), status = VALUES(status),
                        is_enabled = VALUES(is_enabled), synced_at = VALUES(synced_at),
                        updated_at = VALUES(updated_at)',
                    [
                        $serverId,
                        $data['remote_inbound_id'],
                        $data['name'],
                        $data['remark'],
                        $data['protocol'],
                        $data['port'],
                        $data['network'],
                        $data['security'],
                        $data['default_flow'],
                        $data['settings_json'],
                        $data['stream_settings_json'],
                        $data['status'],
                        $data['is_enabled'],
                        $now,
                        $now,
                        $now,
                    ]
                );

                if (isset($existing[$inbound->remoteInboundId])) {
                    $updated++;
                } else {
                    $created++;
                }
            }

            $missingIds = array_diff(array_keys($existing), array_keys($seen));
            if ($seen !== []) {
                $placeholders = implode(',', array_fill(0, count($seen), '?'));
                $database->query(
                    "UPDATE vpn_v2_inbounds
                     SET status = 'sync_missing', updated_at = ?
                     WHERE server_id = ? AND remote_inbound_id NOT IN ({$placeholders})",
                    array_merge([$now, $serverId], array_keys($seen))
                );
            } else {
                $database->query(
                    "UPDATE vpn_v2_inbounds SET status = 'sync_missing', updated_at = ? WHERE server_id = ?",
                    [$now, $serverId]
                );
            }

            $result = new InboundSyncResult(count($seen), $created, $updated, count($missingIds));
            $this->logEvent($serverId, $result);
            $database->commit();

            return $result;
        } catch (\Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    private function logEvent(int $serverId, InboundSyncResult $result): void
    {
        $user = get_user();
        $adminId = is_array($user) ? (int)($user['id'] ?? 0) : 0;
        $context = json_encode([
            'received' => $result->received,
            'created' => $result->created,
            'updated' => $result->updated,
            'missing' => $result->missing,
        ], JSON_UNESCAPED_SLASHES);

        db()->query(
            'INSERT INTO vpn_v2_events (event_type, server_id, admin_id, context_json, created_at)
             VALUES (?, ?, ?, ?, ?)',
            ['inbounds.synced', $serverId, $adminId > 0 ? $adminId : null, $context ?: null, date('Y-m-d H:i:s')]
        );
    }
}
