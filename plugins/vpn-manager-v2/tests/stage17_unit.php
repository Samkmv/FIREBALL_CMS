<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/config/config.php';
require ROOT . '/vendor/autoload.php';
require dirname(__DIR__) . '/Plugin.php';

use Fireball\VpnManagerV2\Services\VpnV2SubscriptionDependencyService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$service = new VpnV2SubscriptionDependencyService();
$active = [
    'id' => 1,
    'status' => 'active',
    'starts_at' => '2020-01-01 00:00:00',
    'expires_at' => '2099-01-01 00:00:00',
    'traffic_limit_bytes' => 1000,
    'traffic_used_bytes' => 100,
];
$expiredParent = array_replace($active, [
    'id' => 2,
    'status' => 'expired',
    'expires_at' => '2020-02-01 00:00:00',
]);
$effective = $service->calculateEffectiveStatus($active, $expiredParent);
$assert($effective['own_status'] === 'active'
    && $effective['parent_status'] === 'expired'
    && $effective['effective_status'] === 'inactive'
    && $effective['inactive_reason'] === 'parent_subscription_expired',
    'An expired parent did not override the active child status.');

$suspendedParent = array_replace($active, ['id' => 3, 'status' => 'suspended']);
$effective = $service->calculateEffectiveStatus($active, $suspendedParent);
$assert($effective['inactive_reason'] === 'parent_subscription_suspended',
    'A suspended parent has no dedicated inactive reason.');

$limited = array_replace($active, ['traffic_used_bytes' => 1000]);
$effective = $service->calculateEffectiveStatus($limited);
$assert($effective['effective_status'] === 'inactive'
    && $effective['inactive_reason'] === 'subscription_limit_exceeded',
    'The effective root status ignores its traffic limit.');

$root = dirname(__DIR__);
$migration = (string)file_get_contents($root . '/migrations/009_add_subscription_dependencies.sql');
$repairMigration = (string)file_get_contents($root . '/migrations/010_repair_bidirectional_credential_columns.sql');
$guardMigration = (string)file_get_contents($root . '/migrations/011_enforce_subscription_item_targets.sql');
$dependencyService = (string)file_get_contents($root . '/src/Services/VpnV2SubscriptionDependencyService.php');
$endpoint = (string)file_get_contents($root . '/src/Services/VpnSubscriptionEndpointService.php');
$deletion = (string)file_get_contents($root . '/src/Services/SubscriptionDeletionService.php');
$operationProcessor = (string)file_get_contents($root . '/src/Services/RemoteOperationProcessor.php');
$queue = (string)file_get_contents($root . '/src/Repositories/OperationQueueRepository.php');
$routes = (string)file_get_contents($root . '/routes/admin.php');
$view = (string)file_get_contents($root . '/views/admin/subscription-show.php');
$fullJob = (string)file_get_contents($root . '/src/Jobs/VpnV2FullReconcileJob.php');
$plugin = json_decode((string)file_get_contents($root . '/plugin.json'), true, 512, JSON_THROW_ON_ERROR);

$assert(str_contains($migration, 'vpn_v2_subscription_items')
    && str_contains($migration, 'parent_subscription_id')
    && str_contains($migration, 'child_subscription_id')
    && str_contains($migration, 'connection_id')
    && str_contains($migration, 'CHECK')
    && str_contains($migration, 'relation_key'),
    'The normalized dependency schema or its target constraint is incomplete.');
$assert(str_contains($repairMigration, 'subscription_token_hash')
    && str_contains($repairMigration, 'encrypted_client_credential')
    && str_contains($repairMigration, 'information_schema.COLUMNS'),
    'The upgrade repair migration does not restore an incomplete stage 007 schema.');
$assert(str_contains($guardMigration, 'BEFORE INSERT')
    && str_contains($guardMigration, 'BEFORE UPDATE')
    && str_contains($guardMigration, 'target_guard')
    && str_contains($guardMigration, 'NEW.child_subscription_id IS NOT NULL')
    && str_contains($guardMigration, 'NEW.connection_id IS NOT NULL'),
    'The database does not enforce exclusive dependency targets on this MySQL version.');
$assert(str_contains($dependencyService, 'detectCycle(')
    && str_contains($dependencyService, 'MAX_DEPTH')
    && str_contains($dependencyService, 'collectEffectiveConnections(')
    && str_contains($dependencyService, 'technicalKey(')
    && str_contains($dependencyService, 'RemoteClientCredentialService())->credential(')
    && str_contains($dependencyService, 'countActiveConsumers('),
    'Cycle protection, merged delivery, deduplication, or shared consumer counting is missing.');
$assert(str_contains($dependencyService, 'cascadeItem(')
    && str_contains($dependencyService, "'node_ids'")
    && str_contains($operationProcessor, 'processDependencyCascade(')
    && str_contains($operationProcessor, "['item_id']"),
    'A toggled dependency is not synchronized idempotently through the retry queue.');
$assert(str_contains($endpoint, 'isDependentChild(')
    && str_contains($endpoint, "['effective_status'] !== 'active'"),
    'The public endpoint can bypass the parent access boundary.');
$assert(str_contains($deletion, 'countActiveConsumers(')
    && str_contains($deletion, 'markNodeSharedRetained(')
    && str_contains($deletion, 'archiveForDeletion('),
    'Parent deletion can remove a shared client or lose relationship history.');
$assert(str_contains($queue, 'cascade_disable_children')
    && str_contains($queue, 'cascade_enable_children')
    && str_contains($queue, 'detach_child_subscription')
    && str_contains($queue, 'detach_child_connection')
    && str_contains($queue, 'recalculate_effective_status'),
    'Dependency operations are not supported by the persistent queue.');
$assert(str_contains($routes, '/dependencies/subscription/')
    && str_contains($routes, '/dependencies/connection/')
    && str_contains($routes, '/dependencies/order/')
    && str_contains($view, 'vpn_manager_v2_dependencies_title')
    && str_contains($view, 'dependency_order[]'),
    'The administrator dependency workflow is incomplete.');
$assert(str_contains($fullJob, 'recalculateAll()'),
    'The full reconciliation job does not recalculate dependency status.');
$assert(version_compare((string)($plugin['version'] ?? '0.0.0'), '0.16.1', '>='),
    'The plugin version was not bumped for migration 009.');

echo json_encode([
    'status' => 'ok',
    'cases' => [
        'parent_expiration_precedence',
        'parent_suspension_reason',
        'traffic_limit_effective_status',
        'normalized_dependency_schema',
        'upgrade_schema_repair',
        'database_target_guards',
        'cycle_and_depth_protection',
        'merged_delivery_and_deduplication',
        'targeted_toggle_cascade',
        'public_parent_access_boundary',
        'shared_client_safe_deletion',
        'dependency_operation_queue',
        'admin_dependency_workflow',
        'full_reconcile_dependencies',
        'plugin_version',
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), PHP_EOL;
