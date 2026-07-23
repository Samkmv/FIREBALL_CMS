<?php

declare(strict_types=1);

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$root = dirname(__DIR__);
$pluginSource = (string)file_get_contents($root . '/Plugin.php');
$repositorySource = (string)file_get_contents(
    $root . '/src/Repositories/MigrationStatusRepository.php'
);
$upgradeSource = (string)file_get_contents(
    $root . '/src/Services/VpnV2SchemaUpgradeService.php'
);
$reconciliationMigration = (string)file_get_contents(
    $root . '/migrations/005_add_plan_reconciliation.sql'
);

$assert(substr_count($pluginSource, 'VpnV2SchemaUpgradeService())->ensureCurrent()') >= 3,
    'Install, activation and boot do not all verify the VPN V2 schema.');
$assert(str_contains($repositorySource, "'vpn_v2_reconcile_operations'"),
    'The reconciliation queue table is absent from the required-table inventory.');
$assert(str_contains($repositorySource, 'public function missingTables(): array'),
    'The schema repository cannot report individually missing tables.');
$assert(str_contains($upgradeSource, '$schemaRepairRequired ? $files : $pending'),
    'A partially missing schema does not replay the repeat-safe migration chain.');
$assert(str_contains($upgradeSource, 'schema repair did not create required tables'),
    'Activation can report success while required VPN V2 tables are still absent.');
$assert(!str_contains($upgradeSource, 'DELETE FROM plugin_migrations'),
    'Schema recovery destroys the existing migration journal.');
$assert(substr_count($reconciliationMigration, 'information_schema.COLUMNS') >= 8
    && substr_count($reconciliationMigration, 'information_schema.STATISTICS') >= 2,
    'The reconciliation migration is not safe to replay against existing tables.');

$plugin = json_decode(
    (string)file_get_contents($root . '/plugin.json'),
    true,
    512,
    JSON_THROW_ON_ERROR
);
$assert(version_compare((string)($plugin['version'] ?? '0.0.0'), '0.19.3', '>='),
    'The partial-schema recovery release was not versioned.');

echo json_encode([
    'status' => 'ok',
    'cases' => [
        'activation_schema_check',
        'complete_required_table_inventory',
        'individual_missing_table_detection',
        'partial_schema_repair',
        'post_repair_verification',
        'migration_history_preserved',
        'repeat_safe_reconciliation_migration',
        'plugin_version',
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
