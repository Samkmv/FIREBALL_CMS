<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/core/Database.php';

final class FireballPluginVpnManagerV2
{
    public const SLUG = 'vpn-manager-v2';

    public static function jobs(): array
    {
        return array_fill(0, 5, []);
    }
}

final class OverviewFakeDatabase extends FBL\Database
{
    private mixed $result = null;

    public function __construct(
        private readonly array $tables,
        private readonly array $columns,
        private readonly array $migrations,
    ) {
    }

    public function query(string $query, array $params = []): static
    {
        $sql = preg_replace('/\s+/', ' ', trim($query)) ?? '';
        if (str_contains($sql, 'FROM information_schema.TABLES')) {
            $this->result = array_map(static fn(string $table): array => ['TABLE_NAME' => $table], $this->tables);
        } elseif (str_contains($sql, 'FROM information_schema.COLUMNS')) {
            $this->result = array_map(static function (string $column): array {
                [$table, $name] = explode('.', $column, 2);
                return ['TABLE_NAME' => $table, 'COLUMN_NAME' => $name];
            }, $this->columns);
        } elseif (str_contains($sql, 'FROM plugin_migrations')) {
            $this->result = array_map(
                static fn(string $migration): array => [
                    'migration' => $migration,
                    'executed_at' => '2026-07-16 10:00:00',
                ],
                $this->migrations
            );
        } elseif (str_contains($sql, 'FROM plugins WHERE slug')) {
            $this->result = ['version' => '0.12.1', 'status' => 'active'];
        } elseif (str_starts_with($sql, 'SELECT COUNT(*) FROM vpn_v2_')) {
            $this->result = str_contains($sql, "status IN ('offline', 'error')") ? 1 : 3;
        } else {
            throw new RuntimeException('Unexpected overview query: ' . $sql);
        }

        return $this;
    }

    public function get(): false|array
    {
        return is_array($this->result) ? $this->result : [];
    }

    public function getOne(): mixed
    {
        return $this->result;
    }

    public function getColumn(): mixed
    {
        return $this->result;
    }
}

function db(): FBL\Database
{
    return $GLOBALS['overview_fake_db'];
}

require dirname(__DIR__) . '/src/Repositories/OverviewRepository.php';

$tables = [
    'vpn_v2_servers',
    'vpn_v2_inbounds',
    'vpn_v2_plans',
    'vpn_v2_plan_nodes',
    'vpn_v2_subscriptions',
    'vpn_v2_subscription_nodes',
    'vpn_v2_events',
    'vpn_v2_notifications',
];
$columns = [
    'vpn_v2_servers.auth_type',
    'vpn_v2_subscriptions.revision',
    'vpn_v2_subscriptions.subscription_token_hash',
    'vpn_v2_subscriptions.internal_comment',
    'vpn_v2_subscriptions.traffic_used_bytes',
    'vpn_v2_subscription_nodes.flow',
    'vpn_v2_subscription_nodes.sort_order',
    'vpn_v2_subscription_nodes.traffic_used_bytes',
    'vpn_v2_subscription_nodes.encrypted_client_credential',
    'vpn_v2_notifications.occurrence_key',
];
$migrationFiles = array_map('basename', glob(dirname(__DIR__) . '/migrations/*.sql') ?: []);

$GLOBALS['overview_fake_db'] = new OverviewFakeDatabase($tables, $columns, $migrationFiles);
$ready = (new Fireball\VpnManagerV2\Repositories\OverviewRepository())->diagnostics();
if (!$ready['is_ready'] || $ready['migrations']['pending'] !== []
    || count($ready['schema']['present_columns']) !== count($columns)
    || $ready['jobs_count'] !== 5
    || $ready['data']['servers']['total'] !== 3) {
    throw new RuntimeException('Ready overview diagnostics are invalid.');
}

$GLOBALS['overview_fake_db'] = new OverviewFakeDatabase(
    array_values(array_diff($tables, ['vpn_v2_notifications'])),
    array_values(array_diff($columns, ['vpn_v2_subscriptions.traffic_used_bytes', 'vpn_v2_notifications.occurrence_key'])),
    array_slice($migrationFiles, 0, -1),
);
$incomplete = (new Fireball\VpnManagerV2\Repositories\OverviewRepository())->diagnostics();
if ($incomplete['is_ready']
    || $incomplete['migrations']['pending'] === []
    || !in_array('vpn_v2_notifications', $incomplete['schema']['missing_tables'], true)
    || !in_array('vpn_v2_subscriptions.traffic_used_bytes', $incomplete['schema']['missing_columns'], true)) {
    throw new RuntimeException('Incomplete overview diagnostics are invalid.');
}

echo json_encode([
    'status' => 'ok',
    'ready_schema' => true,
    'incomplete_schema_detected' => true,
    'pending_migration_detected' => true,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), PHP_EOL;
