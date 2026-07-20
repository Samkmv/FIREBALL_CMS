<?php

namespace Fireball\VpnManagerV2\Repositories;

use Fireball\VpnManagerV2\Support\SecretCipher;

final class ExternalSourceRepository
{
    public function itemsForParent(int $parentId): array
    {
        return db()->query(
            'SELECT id, parent_subscription_id, source_type, name, source_preview, source_hash,
                    config_count, is_enabled, sort_order, sync_status, last_sync_at, last_error,
                    created_at, updated_at
             FROM vpn_v2_external_sources
             WHERE parent_subscription_id = ? AND deleted_at IS NULL
             ORDER BY sort_order ASC, id ASC',
            [$parentId]
        )->get() ?: [];
    }

    public function find(int $parentId, int $id): ?array
    {
        $row = db()->query(
            'SELECT * FROM vpn_v2_external_sources
             WHERE id = ? AND parent_subscription_id = ? AND deleted_at IS NULL LIMIT 1',
            [$id, $parentId]
        )->getOne();

        return is_array($row) ? $row : null;
    }

    public function create(
        int $parentId,
        string $type,
        string $name,
        string $encryptedSource,
        string $sourceHash,
        string $preview,
        string $encryptedSnapshot,
        string $snapshotHash,
        int $configCount,
        ?int $adminId
    ): int {
        $database = db();
        $database->beginTransaction();
        try {
            $duplicate = $database->query(
                'SELECT id FROM vpn_v2_external_sources
                 WHERE parent_subscription_id = ? AND relation_key = ? LIMIT 1 FOR UPDATE',
                [$parentId, $sourceHash]
            )->getOne();
            if (is_array($duplicate)) {
                throw new \InvalidArgumentException('external_source_duplicate');
            }
            $sortOrder = (int)$database->query(
                'SELECT COALESCE(MAX(sort_order), 0) + 10 FROM vpn_v2_external_sources
                 WHERE parent_subscription_id = ? AND deleted_at IS NULL FOR UPDATE',
                [$parentId]
            )->getColumn();
            $now = date('Y-m-d H:i:s');
            $database->query(
                'INSERT INTO vpn_v2_external_sources
                    (parent_subscription_id, source_type, name, encrypted_source, source_hash,
                     source_preview, encrypted_snapshot, snapshot_hash, config_count, is_enabled,
                     sort_order, sync_status, last_sync_at, relation_key, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, \'synced\', ?, ?, ?, ?, ?)',
                [
                    $parentId,
                    $type,
                    $name,
                    $encryptedSource,
                    $sourceHash,
                    $preview,
                    $encryptedSnapshot,
                    $snapshotHash,
                    $configCount,
                    max(10, $sortOrder),
                    $now,
                    $sourceHash,
                    $adminId !== null && $adminId > 0 ? $adminId : null,
                    $now,
                    $now,
                ]
            );
            $id = (int)$database->getInsertId();
            $database->commit();

            return $id;
        } catch (\Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    public function source(array $item): string
    {
        return SecretCipher::decrypt((string)($item['encrypted_source'] ?? ''));
    }

    public function updateSnapshot(int $id, array $uris): void
    {
        $json = json_encode(array_values($uris), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $now = date('Y-m-d H:i:s');
        db()->query(
            'UPDATE vpn_v2_external_sources
             SET encrypted_snapshot = ?, snapshot_hash = ?, config_count = ?, sync_status = \'synced\',
                 last_sync_at = ?, last_error = NULL, updated_at = ?
             WHERE id = ? AND deleted_at IS NULL',
            [SecretCipher::encrypt($json), hash('sha256', $json), count($uris), $now, $now, $id]
        );
    }

    public function recordFailure(int $id, string $error): void
    {
        db()->query(
            'UPDATE vpn_v2_external_sources
             SET sync_status = \'sync_error\', last_error = ?, updated_at = ?
             WHERE id = ? AND deleted_at IS NULL',
            [mb_substr(trim($error), 0, 1000), date('Y-m-d H:i:s'), $id]
        );
    }

    public function setEnabled(int $parentId, int $id, bool $enabled): bool
    {
        db()->query(
            'UPDATE vpn_v2_external_sources
             SET is_enabled = ?,
                 sync_status = IF(? = 1, IF(encrypted_snapshot IS NULL, \'pending\', \'synced\'), \'disabled\'),
                 updated_at = ?
             WHERE id = ? AND parent_subscription_id = ? AND deleted_at IS NULL',
            [$enabled ? 1 : 0, $enabled ? 1 : 0, date('Y-m-d H:i:s'), $id, $parentId]
        );

        return db()->rowCount() === 1;
    }

    public function detach(int $parentId, int $id): bool
    {
        $now = date('Y-m-d H:i:s');
        db()->query(
            'UPDATE vpn_v2_external_sources
             SET is_enabled = 0, sync_status = \'detached\', relation_key = NULL,
                 deleted_at = ?, updated_at = ?
             WHERE id = ? AND parent_subscription_id = ? AND deleted_at IS NULL',
            [$now, $now, $id, $parentId]
        );

        return db()->rowCount() === 1;
    }

    public function activeUris(int $parentId): array
    {
        $rows = db()->query(
            'SELECT encrypted_snapshot FROM vpn_v2_external_sources
             WHERE parent_subscription_id = ? AND is_enabled = 1 AND deleted_at IS NULL
               AND encrypted_snapshot IS NOT NULL AND encrypted_snapshot <> \'\'
             ORDER BY sort_order ASC, id ASC',
            [$parentId]
        )->get() ?: [];
        $uris = [];
        foreach ($rows as $row) {
            $json = SecretCipher::decrypt((string)($row['encrypted_snapshot'] ?? ''));
            $snapshot = json_decode($json, true);
            if (is_array($snapshot)) {
                array_push($uris, ...array_values(array_filter($snapshot, 'is_string')));
            }
        }

        return $uris;
    }

    public function activeConfigCount(int $parentId): int
    {
        return (int)db()->query(
            'SELECT COALESCE(SUM(config_count), 0) FROM vpn_v2_external_sources
             WHERE parent_subscription_id = ? AND is_enabled = 1 AND deleted_at IS NULL
               AND encrypted_snapshot IS NOT NULL AND encrypted_snapshot <> \'\'',
            [$parentId]
        )->getColumn();
    }

    public function syncCandidates(int $limit = 20): array
    {
        return db()->query(
            'SELECT * FROM vpn_v2_external_sources
             WHERE source_type = \'subscription_url\' AND is_enabled = 1 AND deleted_at IS NULL
             ORDER BY COALESCE(last_sync_at, created_at) ASC, id ASC LIMIT ' . max(1, min(200, $limit))
        )->get() ?: [];
    }

    public function archiveForSubscription(int $subscriptionId): int
    {
        $now = date('Y-m-d H:i:s');
        db()->query(
            'UPDATE vpn_v2_external_sources
             SET is_enabled = 0, sync_status = \'parent_deleted\', relation_key = NULL,
                 deleted_at = ?, updated_at = ?
             WHERE parent_subscription_id = ? AND deleted_at IS NULL',
            [$now, $now, $subscriptionId]
        );

        return db()->rowCount();
    }
}
