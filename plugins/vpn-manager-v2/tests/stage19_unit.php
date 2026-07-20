<?php

$root = dirname(__DIR__, 3);
$pluginRoot = $root . '/plugins/vpn-manager-v2';
$languages = [];
foreach (glob($pluginRoot . '/lang/*.php') ?: [] as $file) {
    $languages[basename($file, '.php')] = require $file;
}

if (!class_exists('FireballPluginVpnManagerV2', false)) {
    final class FireballPluginVpnManagerV2
    {
        public static array $dictionary = [];

        public static function t(string $key): string
        {
            return self::$dictionary[$key] ?? $key;
        }
    }
}

require_once $pluginRoot . '/src/Support/LocalizedValue.php';
require_once $pluginRoot . '/src/Support/ProvisioningStatus.php';

use Fireball\VpnManagerV2\Support\LocalizedValue;
use Fireball\VpnManagerV2\Support\ProvisioningStatus;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assert(count($languages) === 4, 'All four plugin languages must be present.');
$baseKeys = array_keys($languages['ru'] ?? []);
foreach ($languages as $name => $dictionary) {
    $assert(array_keys($dictionary) === $baseKeys, 'Translation key order or parity differs for ' . $name . '.');
}

$operationTypes = [
    'create_client', 'update_client', 'rename_client', 'enable_client', 'disable_client',
    'delete_client', 'move_client', 'sync_client', 'sync_inbound', 'sync_server',
    'sync_subscription', 'full_reconcile', 'reset_traffic',
    'cascade_disable_children', 'cascade_enable_children',
    'detach_child_subscription', 'detach_child_connection', 'recalculate_effective_status',
];
$operationStatuses = [
    'pending', 'retry', 'running', 'completed', 'completed_partial', 'failed', 'cancelled',
    'changed', 'synced', 'sync_conflict', 'missing_remote', 'invalid_snapshot',
    'remote_unavailable', 'created', 'deleted', 'skipped',
];
$operationSources = ['cms', 'three_x_ui', 'reconciliation', 'retry', 'manual_sync'];
foreach ($languages as $name => $dictionary) {
    foreach ($operationTypes as $value) {
        $assert(isset($dictionary['vpn_manager_v2_operation_type_value_' . $value]),
            'Missing operation type translation for ' . $value . ' in ' . $name . '.');
    }
    foreach ($operationStatuses as $value) {
        $assert(isset($dictionary['vpn_manager_v2_operation_status_value_' . $value]),
            'Missing operation status translation for ' . $value . ' in ' . $name . '.');
    }
    foreach ($operationSources as $value) {
        $assert(isset($dictionary['vpn_manager_v2_operation_source_value_' . $value]),
            'Missing operation source translation for ' . $value . ' in ' . $name . '.');
    }
}

FireballPluginVpnManagerV2::$dictionary = $languages['ru'];
$assert(LocalizedValue::operationType('sync_server') === 'Синхронизация сервера',
    'Operation type was not localized.');
$assert(LocalizedValue::operationSource('manual_sync') === 'Ручная синхронизация',
    'Operation source was not localized.');
$assert(LocalizedValue::operationStatus('pending') === 'Ожидает выполнения',
    'Operation status was not localized.');
$assert(LocalizedValue::inactiveReason('parent_subscription_expired') === 'Срок основной подписки истёк',
    'Inactive reason was not localized.');
$assert(ProvisioningStatus::label('inactive') === 'Неактивна', 'Inactive status was not localized.');

$operationsView = file_get_contents($pluginRoot . '/views/admin/operations.php');
$logsView = file_get_contents($pluginRoot . '/views/admin/sync-logs.php');
$conflictsView = file_get_contents($pluginRoot . '/views/admin/conflicts.php');
$controller = file_get_contents($pluginRoot . '/src/Controllers/Admin/SyncController.php');
$queue = file_get_contents($pluginRoot . '/src/Repositories/OperationQueueRepository.php');
$processor = file_get_contents($pluginRoot . '/src/Services/RemoteOperationProcessor.php');
$job = file_get_contents($pluginRoot . '/src/Jobs/VpnV2SyncConfigurationJob.php');
$routes = file_get_contents($pluginRoot . '/routes/admin.php');
$script = file_get_contents($pluginRoot . '/assets/vpn-manager-v2.js');
$plugin = json_decode((string)file_get_contents($pluginRoot . '/plugin.json'), true, 512, JSON_THROW_ON_ERROR);

$assert(str_contains($operationsView, 'LocalizedValue::operationType')
    && str_contains($operationsView, 'LocalizedValue::operationSource')
    && str_contains($operationsView, 'LocalizedValue::operationStatus'),
    'Operations table still exposes raw codes.');
$assert(str_contains($logsView, "[LocalizedValue::class, 'changedField']")
    && str_contains($conflictsView, 'LocalizedValue::conflictType'),
    'Audit or conflict values are not localized.');
$assert(str_contains($queue, 'claimByOperationId') && str_contains($processor, 'processOperation'),
    'Exact queued-operation processing is missing.');
$assert(substr_count($controller, 'processOperation($operationId)') >= 2
    && str_contains($controller, 'processPending'),
    'Manual operations are not executed immediately or cannot recover pending work.');
$assert(str_contains($job, "'full_reconcile'") && str_contains($routes, '/operations/process'),
    'Full reconcile or the recovery endpoint is missing from queue processing.');
$assert(str_contains($script, 'data.status_label'), 'Live operation progress is not localized.');
$assert(($plugin['version'] ?? '') === '0.18.0', 'The plugin version was not bumped to 0.18.0.');

echo json_encode([
    'status' => 'ok',
    'cases' => [
        'translation_parity',
        'operation_type_source_status_localization',
        'dependency_and_conflict_localization',
        'exact_manual_queue_processing',
        'pending_operation_recovery',
        'localized_live_progress',
        'plugin_version',
    ],
], JSON_UNESCAPED_SLASHES) . PHP_EOL;
