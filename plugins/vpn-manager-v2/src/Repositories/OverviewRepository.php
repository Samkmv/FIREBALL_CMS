<?php

namespace Fireball\VpnManagerV2\Repositories;

final class OverviewRepository
{
    private const TABLES = [
        'vpn_v2_servers',
        'vpn_v2_inbounds',
        'vpn_v2_plans',
        'vpn_v2_plan_nodes',
        'vpn_v2_subscriptions',
        'vpn_v2_subscription_nodes',
        'vpn_v2_events',
        'vpn_v2_notifications',
    ];

    private const REQUIRED_COLUMNS = [
        'vpn_v2_servers.auth_type',
        'vpn_v2_subscriptions.revision',
        'vpn_v2_subscriptions.internal_comment',
        'vpn_v2_subscriptions.traffic_used_bytes',
        'vpn_v2_subscription_nodes.flow',
        'vpn_v2_subscription_nodes.traffic_used_bytes',
        'vpn_v2_notifications.occurrence_key',
    ];

    public function diagnostics(): array
    {
        $presentTables = $this->presentTables();
        $presentColumns = $this->presentColumns();
        $migrationFiles = $this->migrationFiles();
        $appliedMigrations = $this->appliedMigrations();
        $pendingMigrations = array_values(array_diff($migrationFiles, array_keys($appliedMigrations)));
        $missingColumns = array_values(array_diff(self::REQUIRED_COLUMNS, $presentColumns));
        $missingTables = array_values(array_diff(self::TABLES, $presentTables));
        $plugin = $this->pluginMetadata();

        return [
            'available' => true,
            'is_ready' => $missingTables === [] && $missingColumns === [] && $pendingMigrations === [],
            'version' => $plugin['disk_version'],
            'installed_version' => $plugin['installed_version'],
            'plugin_status' => $plugin['status'],
            'jobs_count' => count(\FireballPluginVpnManagerV2::jobs()),
            'schema' => [
                'required_columns' => self::REQUIRED_COLUMNS,
                'present_columns' => $presentColumns,
                'missing_columns' => $missingColumns,
                'missing_tables' => $missingTables,
            ],
            'migrations' => [
                'files_count' => count($migrationFiles),
                'applied_count' => count(array_intersect($migrationFiles, array_keys($appliedMigrations))),
                'pending' => $pendingMigrations,
                'last_executed_at' => $this->lastMigrationDate($appliedMigrations),
            ],
            'data' => $this->dataSummary($presentTables),
        ];
    }

    public static function unavailable(): array
    {
        return [
            'available' => false,
            'is_ready' => false,
            'version' => '—',
            'installed_version' => '—',
            'plugin_status' => 'unknown',
            'jobs_count' => count(\FireballPluginVpnManagerV2::jobs()),
            'schema' => [
                'required_columns' => self::REQUIRED_COLUMNS,
                'present_columns' => [],
                'missing_columns' => [],
                'missing_tables' => [],
            ],
            'migrations' => [
                'files_count' => 0,
                'applied_count' => 0,
                'pending' => [],
                'last_executed_at' => null,
            ],
            'data' => self::emptyDataSummary(),
        ];
    }

    private function presentTables(): array
    {
        $rows = db()->query(
            'SELECT TABLE_NAME
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME IN (?, ?, ?, ?, ?, ?, ?, ?)',
            self::TABLES
        )->get() ?: [];
        $tables = array_values(array_filter(array_map(
            static fn(array $row): string => (string)($row['TABLE_NAME'] ?? $row['table_name'] ?? ''),
            $rows
        )));
        sort($tables);

        return $tables;
    }

    private function presentColumns(): array
    {
        $rows = db()->query(
            'SELECT TABLE_NAME, COLUMN_NAME
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME IN (?, ?, ?, ?, ?, ?, ?, ?)',
            self::TABLES
        )->get() ?: [];
        $columns = [];
        foreach ($rows as $row) {
            $table = (string)($row['TABLE_NAME'] ?? $row['table_name'] ?? '');
            $column = (string)($row['COLUMN_NAME'] ?? $row['column_name'] ?? '');
            if ($table !== '' && $column !== '') {
                $columns[] = $table . '.' . $column;
            }
        }

        return array_values(array_intersect(self::REQUIRED_COLUMNS, $columns));
    }

    private function migrationFiles(): array
    {
        $files = glob(dirname(__DIR__, 2) . '/migrations/*.sql') ?: [];
        $migrations = array_map('basename', $files);
        sort($migrations);

        return $migrations;
    }

    private function appliedMigrations(): array
    {
        $rows = db()->query(
            'SELECT migration, executed_at
             FROM plugin_migrations
             WHERE plugin_slug = ?
             ORDER BY id ASC',
            [\FireballPluginVpnManagerV2::SLUG]
        )->get() ?: [];
        $migrations = [];
        foreach ($rows as $row) {
            $name = (string)($row['migration'] ?? '');
            if ($name !== '') {
                $migrations[$name] = (string)($row['executed_at'] ?? '');
            }
        }

        return $migrations;
    }

    private function lastMigrationDate(array $migrations): ?string
    {
        $dates = array_values(array_filter($migrations, static fn(string $date): bool => $date !== ''));
        if ($dates === []) {
            return null;
        }
        rsort($dates);

        return $dates[0];
    }

    private function pluginMetadata(): array
    {
        $metadataPath = dirname(__DIR__, 2) . '/plugin.json';
        $metadata = is_file($metadataPath)
            ? json_decode((string)file_get_contents($metadataPath), true)
            : null;
        $row = db()->query(
            'SELECT version, status FROM plugins WHERE slug = ? LIMIT 1',
            [\FireballPluginVpnManagerV2::SLUG]
        )->getOne() ?: [];

        return [
            'disk_version' => is_array($metadata) ? (string)($metadata['version'] ?? '—') : '—',
            'installed_version' => (string)($row['version'] ?? '—'),
            'status' => (string)($row['status'] ?? 'unknown'),
        ];
    }

    private function dataSummary(array $presentTables): array
    {
        $data = self::emptyDataSummary();
        if (in_array('vpn_v2_servers', $presentTables, true)) {
            $data['servers'] = [
                'total' => $this->safeCount('SELECT COUNT(*) FROM vpn_v2_servers'),
                'active' => $this->safeCount("SELECT COUNT(*) FROM vpn_v2_servers WHERE status = 'online'"),
                'enabled' => $this->safeCount('SELECT COUNT(*) FROM vpn_v2_servers WHERE is_enabled = 1'),
                'errors' => $this->safeCount("SELECT COUNT(*) FROM vpn_v2_servers WHERE status IN ('offline', 'error')"),
            ];
        }
        if (in_array('vpn_v2_inbounds', $presentTables, true)) {
            $data['inbounds'] = [
                'total' => $this->safeCount('SELECT COUNT(*) FROM vpn_v2_inbounds'),
                'active' => $this->safeCount("SELECT COUNT(*) FROM vpn_v2_inbounds WHERE status = 'active'"),
                'enabled' => $this->safeCount('SELECT COUNT(*) FROM vpn_v2_inbounds WHERE is_enabled = 1'),
                'errors' => $this->safeCount("SELECT COUNT(*) FROM vpn_v2_inbounds WHERE status IN ('sync_missing', 'error')"),
            ];
        }
        if (in_array('vpn_v2_plans', $presentTables, true)) {
            $data['plans'] = [
                'total' => $this->safeCount('SELECT COUNT(*) FROM vpn_v2_plans'),
                'active' => $this->safeCount('SELECT COUNT(*) FROM vpn_v2_plans WHERE is_active = 1'),
                'enabled' => null,
                'errors' => null,
            ];
        }
        if (in_array('vpn_v2_subscriptions', $presentTables, true)) {
            $data['subscriptions'] = [
                'total' => $this->safeCount('SELECT COUNT(*) FROM vpn_v2_subscriptions'),
                'active' => $this->safeCount("SELECT COUNT(*) FROM vpn_v2_subscriptions WHERE status = 'active'"),
                'enabled' => null,
                'errors' => $this->safeCount("SELECT COUNT(*) FROM vpn_v2_subscriptions WHERE status IN ('provisioning_failed', 'sync_error', 'delete_failed')"),
            ];
        }
        if (in_array('vpn_v2_subscription_nodes', $presentTables, true)) {
            $data['connections'] = [
                'total' => $this->safeCount('SELECT COUNT(*) FROM vpn_v2_subscription_nodes'),
                'active' => $this->safeCount("SELECT COUNT(*) FROM vpn_v2_subscription_nodes WHERE status = 'active'"),
                'enabled' => null,
                'errors' => $this->safeCount("SELECT COUNT(*) FROM vpn_v2_subscription_nodes WHERE status IN ('create_failed', 'sync_error', 'delete_failed')"),
            ];
        }

        return $data;
    }

    private function safeCount(string $sql): ?int
    {
        try {
            return (int)db()->query($sql)->getColumn();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function emptyDataSummary(): array
    {
        $empty = ['total' => null, 'active' => null, 'enabled' => null, 'errors' => null];

        return [
            'servers' => $empty,
            'inbounds' => $empty,
            'plans' => $empty,
            'subscriptions' => $empty,
            'connections' => $empty,
        ];
    }
}
