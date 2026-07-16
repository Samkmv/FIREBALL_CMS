<?php

namespace Fireball\VpnManagerV2\Services;

use App\Services\SqlFileRunner;

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
        $applied = db()->query(
            'SELECT migration FROM plugin_migrations WHERE plugin_slug = ?',
            [\FireballPluginVpnManagerV2::SLUG]
        )->get() ?: [];
        $applied = array_fill_keys(array_column($applied, 'migration'), true);
        $pending = array_values(array_filter(
            $files,
            static fn(string $file): bool => !isset($applied[basename($file)])
        ));
        if ($pending === []) {
            $this->syncVersion();
            return;
        }

        $lockName = 'fblpm:' . substr(hash('sha256', \FireballPluginVpnManagerV2::SLUG), 0, 48);
        if ((int)db()->query('SELECT GET_LOCK(?, 15)', [$lockName])->getColumn() !== 1) {
            throw new \RuntimeException('Could not acquire VPN V2 migration lock.');
        }
        try {
            $runner = new SqlFileRunner();
            foreach ($pending as $file) {
                $migration = basename($file);
                if (db()->query(
                    'SELECT id FROM plugin_migrations WHERE plugin_slug = ? AND migration = ? LIMIT 1',
                    [\FireballPluginVpnManagerV2::SLUG, $migration]
                )->getOne()) {
                    continue;
                }
                $sql = (string)file_get_contents($file);
                if (trim($sql) !== '') {
                    $runner->executeDatabase($sql);
                }
                db()->query(
                    'INSERT INTO plugin_migrations (plugin_slug, migration, executed_at) VALUES (?, ?, ?)',
                    [\FireballPluginVpnManagerV2::SLUG, $migration, date('Y-m-d H:i:s')]
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
