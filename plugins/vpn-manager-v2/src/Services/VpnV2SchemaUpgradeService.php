<?php

namespace Fireball\VpnManagerV2\Services;

use App\Services\SqlFileRunner;
use Fireball\VpnManagerV2\Repositories\MigrationStatusRepository;
use Fireball\VpnManagerV2\Support\Uuid;

final class VpnV2SchemaUpgradeService
{
    public function ensureCurrent(): void
    {
        $root = dirname(__DIR__, 2) . '/migrations';
        $files = glob($root . '/*.sql') ?: [];
        sort($files);
        if ($files === []) {
            return;
        }
        $repository = new MigrationStatusRepository();
        $applied = $this->appliedMigrations();
        $schemaRepairRequired = $repository->missingTables() !== [];
        if (!$schemaRepairRequired && $this->pendingMigrations($files, $applied) === []) {
            $this->syncVersion();
            return;
        }

        $lockName = 'fblpm:' . substr(hash('sha256', \FireballPluginVpnManagerV2::SLUG), 0, 48);
        if ((int)db()->query('SELECT GET_LOCK(?, 15)', [$lockName])->getColumn() !== 1) {
            throw new \RuntimeException('Could not acquire VPN V2 migration lock.');
        }
        try {
            $applied = $this->appliedMigrations();
            $pending = $this->pendingMigrations($files, $applied);
            $schemaRepairRequired = $repository->missingTables() !== [];
            if (!$schemaRepairRequired && $pending === []) {
                $this->syncVersion();
                return;
            }

            // Every migration is repeat-safe. Replaying the complete chain creates
            // only missing tables and upgrades a restored table to the current shape.
            $migrations = $schemaRepairRequired ? $files : $pending;
            $runner = new SqlFileRunner();
            foreach ($migrations as $file) {
                $migration = basename($file);
                $sql = (string)file_get_contents($file);
                if (trim($sql) !== '') {
                    $runner->executeDatabase($sql);
                }
                if (in_array($migration, [
                    '007_add_bidirectional_sync.sql',
                    '010_repair_bidirectional_credential_columns.sql',
                ], true)) {
                    $this->backfillLegacyPasswordCredentials();
                }
                if (!isset($applied[$migration])) {
                    db()->query(
                        'INSERT INTO plugin_migrations (plugin_slug, migration, executed_at) VALUES (?, ?, ?)',
                        [\FireballPluginVpnManagerV2::SLUG, $migration, date('Y-m-d H:i:s')]
                    );
                    $applied[$migration] = true;
                }
            }

            $missing = $repository->missingTables();
            if ($missing !== []) {
                throw new \RuntimeException(
                    'VPN V2 schema repair did not create required tables: ' . implode(', ', $missing)
                );
            }
            $this->syncVersion();
        } finally {
            try {
                db()->query('SELECT RELEASE_LOCK(?)', [$lockName]);
            } catch (\Throwable) {
                // MySQL releases the advisory lock when this connection closes.
            }
        }
    }

    private function appliedMigrations(): array
    {
        $rows = db()->query(
            'SELECT migration FROM plugin_migrations WHERE plugin_slug = ?',
            [\FireballPluginVpnManagerV2::SLUG]
        )->get() ?: [];

        return array_fill_keys(array_column($rows, 'migration'), true);
    }

    private function pendingMigrations(array $files, array $applied): array
    {
        return array_values(array_filter(
            $files,
            static fn(string $file): bool => !isset($applied[basename($file)])
        ));
    }

    private function backfillLegacyPasswordCredentials(): void
    {
        $rows = db()->query(
            "SELECT id, protocol, client_uuid, remote_client_id
             FROM vpn_v2_subscription_nodes
             WHERE protocol IN ('trojan', 'shadowsocks')
               AND encrypted_client_credential IS NULL AND client_uuid <> ''
             ORDER BY id ASC"
        )->get() ?: [];
        if ($rows === []) {
            return;
        }
        $credentials = new RemoteClientCredentialService();
        foreach ($rows as $row) {
            $plainText = trim((string)($row['client_uuid'] ?? ''));
            if ($plainText === '') {
                continue;
            }
            $encrypted = $credentials->encryptForStorage((string)$row['protocol'], $plainText);
            if ($encrypted === null || $encrypted === '') {
                throw new \RuntimeException('Could not encrypt a legacy VPN client credential.');
            }
            $remoteId = trim((string)($row['remote_client_id'] ?? ''));
            db()->query(
                'UPDATE vpn_v2_subscription_nodes
                 SET client_uuid = ?, encrypted_client_credential = ?,
                     remote_client_id = ?, updated_at = ?
                 WHERE id = ? AND encrypted_client_credential IS NULL',
                [
                    Uuid::v4(),
                    $encrypted,
                    $remoteId !== '' && !hash_equals($remoteId, $plainText) ? $remoteId : null,
                    date('Y-m-d H:i:s'),
                    (int)$row['id'],
                ]
            );
        }
    }

    private function syncVersion(): void
    {
        $metadataPath = dirname(__DIR__, 2) . '/plugin.json';
        $metadata = is_file($metadataPath)
            ? json_decode((string)file_get_contents($metadataPath), true)
            : null;
        $version = is_array($metadata) ? trim((string)($metadata['version'] ?? '')) : '';
        if ($version === '') {
            return;
        }
        db()->query(
            'UPDATE plugins SET version = ?, updated_at = ? WHERE slug = ? AND version <> ?',
            [$version, date('Y-m-d H:i:s'), \FireballPluginVpnManagerV2::SLUG, $version]
        );
    }
}
