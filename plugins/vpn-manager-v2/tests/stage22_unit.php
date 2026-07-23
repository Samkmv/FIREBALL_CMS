<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/config/config.php';
require ROOT . '/vendor/autoload.php';

use FBL\Database;
use Fireball\VpnManagerV2\Services\VpnV2SchemaUpgradeService;

if (!class_exists('FireballPluginVpnManagerV2', false)) {
    final class FireballPluginVpnManagerV2
    {
        public const SLUG = 'vpn-manager-v2';
    }
}

final class SchemaRecoveryFakeDatabase extends Database
{
    public mixed $result = null;
    public array $applied;
    public array $tables;
    public array $newlyCreated = [];
    public array $executed = [];
    public bool $journalReset = false;

    public function __construct(array $applied, array $tables)
    {
        $this->applied = array_fill_keys($applied, true);
        $this->tables = array_fill_keys($tables, true);
    }

    public function query(string $query, array $params = []): static
    {
        $sql = preg_replace('/\s+/', ' ', trim($query)) ?? '';
        if ($sql === 'SHOW TABLES') {
            $this->result = array_map(
                static fn(string $table): array => ['Tables_in_test' => $table],
                array_keys($this->tables)
            );
        } elseif (str_starts_with($sql, 'SELECT migration FROM plugin_migrations')) {
            $this->result = array_map(
                static fn(string $migration): array => ['migration' => $migration],
                array_keys($this->applied)
            );
        } elseif (str_starts_with($sql, 'SELECT GET_LOCK')) {
            $this->result = 1;
        } elseif (str_starts_with($sql, 'SELECT RELEASE_LOCK')) {
            $this->result = 1;
        } elseif (str_starts_with($sql, 'DELETE FROM plugin_migrations')) {
            $this->applied = [];
            $this->journalReset = true;
            $this->result = true;
        } elseif (str_starts_with($sql, 'SELECT id FROM plugin_migrations')) {
            $migration = (string)($params[1] ?? '');
            $this->result = isset($this->applied[$migration]) ? ['id' => 1] : false;
        } elseif (str_starts_with($sql, 'INSERT INTO plugin_migrations')) {
            $this->applied[(string)($params[1] ?? '')] = true;
            $this->result = true;
        } elseif (str_contains($sql, 'FROM vpn_v2_subscription_nodes')) {
            $this->result = [];
        } elseif (preg_match('/^CREATE TABLE IF NOT EXISTS (vpn_v2_[a-z_]+)/i', $sql, $matches) === 1) {
            $table = strtolower($matches[1]);
            if (!isset($this->tables[$table])) {
                $this->newlyCreated[] = $table;
                $this->tables[$table] = true;
            }
            $this->executed[] = $sql;
            $this->result = true;
        } else {
            $this->executed[] = $sql;
            $this->result = true;
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

final class ToySchemaRecoveryFakeDatabase extends Database
{
    public mixed $result = null;
    public array $tables = [];
    public array $columns = [];
    public array $executed = [];

    public function __construct()
    {
    }

    public function query(string $query, array $params = []): static
    {
        $sql = preg_replace('/\s+/', ' ', trim($query)) ?? '';
        if (str_contains($sql, 'FROM information_schema.TABLES')) {
            $this->result = isset($this->tables[(string)($params[0] ?? '')]) ? 1 : 0;
        } elseif (preg_match('/^CREATE TABLE IF NOT EXISTS (toy_rental_[a-z_]+)/i', $sql, $matches) === 1) {
            $table = strtolower($matches[1]);
            $this->tables[$table] = true;
            if ($table === 'toy_rental_rides') {
                $this->columns = array_fill_keys([
                    'id', 'car_id', 'customer_name', 'customer_phone', 'started_at', 'planned_end_at',
                    'ended_at', 'duration_minutes', 'payment_amount', 'payment_method', 'payment_status',
                    'status', 'notes', 'created_at', 'updated_at',
                ], true);
            }
            $this->executed[] = $sql;
            $this->result = true;
        } elseif ($sql === 'SHOW COLUMNS FROM toy_rental_rides') {
            $this->result = array_map(static fn(string $column): array => ['Field' => $column], array_keys($this->columns));
        } elseif (preg_match('/^ALTER TABLE toy_rental_rides ADD COLUMN ([a-z_]+)/i', $sql, $matches) === 1) {
            $this->columns[strtolower($matches[1])] = true;
            $this->executed[] = $sql;
            $this->result = true;
        } else {
            $this->executed[] = $sql;
            $this->result = true;
        }

        return $this;
    }

    public function get(): false|array
    {
        return is_array($this->result) ? $this->result : [];
    }

    public function getColumn(): mixed
    {
        return $this->result;
    }
}

function db(): Database
{
    return $GLOBALS['schema_recovery_db'];
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$pluginRoot = dirname(__DIR__);
$migrationNames = array_map('basename', glob($pluginRoot . '/migrations/*.sql') ?: []);
sort($migrationNames);
require_once $pluginRoot . '/src/Repositories/MigrationStatusRepository.php';
require_once $pluginRoot . '/src/Services/VpnV2SchemaUpgradeService.php';
$requiredTables = [
    'vpn_v2_servers',
    'vpn_v2_inbounds',
    'vpn_v2_plans',
    'vpn_v2_plan_nodes',
    'vpn_v2_subscriptions',
    'vpn_v2_subscription_nodes',
    'vpn_v2_subscription_items',
    'vpn_v2_events',
    'vpn_v2_notifications',
    'vpn_v2_reconcile_operations',
    'vpn_v2_profiles',
    'vpn_v2_operations',
    'vpn_v2_connection_snapshots',
    'vpn_v2_sync_conflicts',
    'vpn_v2_sync_logs',
    'vpn_v2_remote_clients',
];
$GLOBALS['schema_recovery_db'] = new SchemaRecoveryFakeDatabase($migrationNames, $requiredTables);
(new VpnV2SchemaUpgradeService())->ensureCurrent();
$vpnDb = $GLOBALS['schema_recovery_db'];
$assert(!$vpnDb->journalReset, 'Partial VPN schema recovery cleared the migration journal.');
$assert(array_keys($vpnDb->applied) === $migrationNames,
    'Partial schema recovery changed the applied migration journal.');
$assert($vpnDb->newlyCreated === ['vpn_v2_external_sources'],
    'Partial schema recovery did not create exactly the missing VPN table.');

require_once dirname($pluginRoot) . '/toy-car-rental/Plugin.php';
$GLOBALS['schema_recovery_db'] = new ToySchemaRecoveryFakeDatabase();
$reflection = new ReflectionClass(FireballPluginToyCarRental::class);
$ensureToySchema = $reflection->getMethod('ensureDatabaseSchema');
$ensureToySchema->setAccessible(true);
$ensureToySchema->invoke(null);
$toyDb = $GLOBALS['schema_recovery_db'];
$assert(isset($toyDb->tables['toy_rental_cars'], $toyDb->tables['toy_rental_rides']),
    'Toy Car Rental did not recreate its missing base tables.');
foreach (['billing_type', 'price_per_minute', 'estimated_minutes', 'final_amount'] as $column) {
    $assert(isset($toyDb->columns[$column]), 'Toy Car Rental did not restore billing column ' . $column . '.');
}

$vpnPlugin = (string)file_get_contents($pluginRoot . '/Plugin.php');
$toyPlugin = (string)file_get_contents(dirname($pluginRoot) . '/toy-car-rental/Plugin.php');
$toyBillingMigration = (string)file_get_contents(
    dirname($pluginRoot) . '/toy-car-rental/migrations/003_add_billing_fields_to_toy_rental_rides.sql'
);
$assert(substr_count($vpnPlugin, '(new VpnV2SchemaUpgradeService())->ensureCurrent();') >= 3,
    'VPN schema recovery is not invoked during install, activation and boot.');
$assert(str_contains($toyPlugin, 'public function activate(): void')
    && substr_count($toyPlugin, 'self::ensureDatabaseSchema();') >= 3,
    'Toy Car Rental schema recovery is not invoked during install, activation and boot.');
$assert(substr_count($toyBillingMigration, 'information_schema.COLUMNS') === 4
    && substr_count($toyBillingMigration, "'SELECT 1'") === 4,
    'The Toy Car Rental billing migration is not safe to replay after partial schema recovery.');

echo json_encode([
    'status' => 'ok',
    'cases' => [
        'vpn_migration_journal_preserved',
        'partial_vpn_schema_replay',
        'vpn_install_activate_boot_recovery',
        'toy_base_table_recovery',
        'toy_billing_column_recovery',
        'toy_idempotent_billing_migration',
        'toy_install_activate_boot_recovery',
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), PHP_EOL;
