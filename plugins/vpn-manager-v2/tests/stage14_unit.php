<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/config/config.php';
require ROOT . '/vendor/autoload.php';
require ROOT . '/helpers/helpers.php';
require dirname(__DIR__) . '/Plugin.php';

use Fireball\VpnManagerV2\DTO\ReconcileResult;
use Fireball\VpnManagerV2\Services\ClientPayloadFactory;
use Fireball\VpnManagerV2\Services\PlanManagerService;
use Fireball\VpnManagerV2\Services\TrafficSyncService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$manager = new PlanManagerService();
$before = [[
    'id' => 10,
    'server_id' => 1,
    'inbound_id' => 1,
    'flow_override' => null,
    'is_enabled' => 1,
]];
$after = [[
    'id' => 20,
    'server_id' => 1,
    'inbound_id' => 1,
    'flow_override' => null,
    'is_enabled' => 1,
], [
    'id' => 21,
    'server_id' => 2,
    'inbound_id' => 9,
    'flow_override' => 'xtls-rprx-vision',
    'is_enabled' => 1,
]];
$diff = $manager->diffPlanNodes($before, $after);
$assert(count($diff['added']) === 1 && count($diff['unchanged']) === 1
    && $diff['removed'] === [] && $diff['changed'] === [],
    'Plan target diff treats rewritten row IDs as topology changes.');

$flowAfter = $before;
$flowAfter[0]['id'] = 30;
$flowAfter[0]['flow_override'] = '';
$flowDiff = $manager->diffPlanNodes($before, $flowAfter);
$assert($flowDiff['added'] === [] && count($flowDiff['changed']) === 1,
    'A flow change was incorrectly classified as a new connection.');

$payload = (new ClientPayloadFactory())->build([
    'status' => 'suspended',
    'expires_at' => '2030-01-01 00:00:00',
    'device_limit' => 3,
    'traffic_limit_bytes' => 1024,
], [
    'protocol' => 'vless',
    'client_uuid' => '00000000-0000-4000-8000-000000000001',
    'client_email' => 'safe@example.invalid',
    'client_sub_id' => 'abcdef0123456789',
    'desired_enabled' => 0,
]);
$assert($payload['enable'] === false && $payload['expiryTime'] > 0
    && $payload['totalGB'] === 1024 && $payload['limitIp'] === 3,
    'Suspended reconciliation payload does not preserve current subscription state.');

$traffic = TrafficSyncService::trafficFromResponse(['obj' => ['up' => 120, 'down' => 80]]);
$assert($traffic === ['upload' => 120, 'download' => 80, 'total' => 200],
    'Traffic direction counters are not isolated.');

$noChanges = new ReconcileResult(1, 2, skipped: 2);
$assert($noChanges->noChanges() && !$noChanges->changed(),
    'An idempotent reconciliation result is reported as changed.');

$migration = (string)file_get_contents(dirname(__DIR__) . '/migrations/005_add_plan_reconciliation.sql');
$snapshotMigration = (string)file_get_contents(dirname(__DIR__) . '/migrations/006_add_reconciliation_node_snapshots.sql');
$reconciler = (string)file_get_contents(dirname(__DIR__) . '/src/Services/VpnPlanSubscriptionReconciler.php');
$repository = (string)file_get_contents(dirname(__DIR__) . '/src/Repositories/PlanReconciliationRepository.php');
$job = (string)file_get_contents(dirname(__DIR__) . '/src/Jobs/VpnV2ReconcilePlanSubscriptionsJob.php');
$provisioning = (string)file_get_contents(dirname(__DIR__) . '/src/Services/SubscriptionProvisioningService.php');
$endpointRepository = (string)file_get_contents(dirname(__DIR__) . '/src/Repositories/SubscriptionConfigRepository.php');
$routes = (string)file_get_contents(dirname(__DIR__) . '/routes/admin.php');
$profile = (string)file_get_contents(dirname(__DIR__) . '/src/Repositories/ProfileVpnRepository.php');

$assert(str_contains($migration, 'vpn_v2_reconcile_operations')
    && str_contains($migration, 'desired_enabled')
    && str_contains($migration, 'traffic_sync_status'),
    'Reconciliation operation or local snapshots are absent from the migration.');
$assert(str_contains($snapshotMigration, "COLUMN_NAME = 'expires_at'")
    && str_contains($snapshotMigration, "COLUMN_NAME = 'device_limit'"),
    'The upgrade-safe node snapshot migration is incomplete.');
$assert(str_contains($reconciler, 'provisionMissingNode')
    && str_contains($reconciler, 'touchConfig($subscriptionId)')
    && str_contains($repository, "status = 'creating'")
    && str_contains($repository, 'FOR UPDATE'),
    'Local-first reconciliation or revision aggregation is incomplete.');
$assert(str_contains($provisioning, 'getInbound($remoteInboundId)')
    && str_contains($provisioning, 'verify($confirmed, $payload)'),
    'Provisioning does not perform the mandatory post-create inbound verification.');
$assert(str_contains($endpointRepository, "n.status = 'active'")
    && !str_contains($endpointRepository, "n.status IN ('creating'"),
    'Unconfirmed connections can leak into the public subscription endpoint.');
$assert(str_contains($routes, '/preview/') && str_contains($routes, '/reconcile/')
    && str_contains($routes, '/create-missing/'),
    'Reconciliation POST actions are not registered.');
$assert(str_contains($profile, 'serversForUserSubscription')
    && str_contains($profile, 'n.status IN')
    && !str_contains($profile, "n.status <> \\'deleted\\'"),
    'The customer dashboard exposes unconfirmed connections.');
$assert(str_contains($job, "remove_obsolete")
    && str_contains($job, 'operationProgress')
    && str_contains($job, 'Permissions::DELETE_CONNECTIONS'),
    'The batch job does not preserve cumulative progress or protect obsolete removal.');

echo json_encode([
    'status' => 'ok',
    'cases' => [
        'logical_plan_diff',
        'flow_change_not_addition',
        'suspended_payload_disabled',
        'traffic_direction_counters',
        'idempotent_result',
        'operation_migration',
        'upgrade_safe_snapshot_migration',
        'local_first_provisioning',
        'post_create_confirmation',
        'confirmed_endpoint_only',
        'post_csrf_routes',
        'confirmed_profile_only',
        'batch_removal_permissions_and_progress',
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), PHP_EOL;
