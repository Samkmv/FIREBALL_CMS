<?php

namespace Fireball\VpnManagerV2\Controllers\Admin;

use FBL\Pagination;
use Fireball\VpnManagerV2\Repositories\OperationQueueRepository;
use Fireball\VpnManagerV2\Repositories\ConfigurationSyncRepository;
use Fireball\VpnManagerV2\Repositories\PlanReconciliationRepository;
use Fireball\VpnManagerV2\Repositories\SubscriptionRepository;
use Fireball\VpnManagerV2\Repositories\SyncAuditRepository;
use Fireball\VpnManagerV2\Services\RemoteOperationProcessor;
use Fireball\VpnManagerV2\Support\LocalizedValue;
use Fireball\VpnManagerV2\Support\Permissions;

final class SyncController
{
    public function operations(): string
    {
        Permissions::authorize(Permissions::VIEW);

        $repository = new OperationQueueRepository();
        $total = $repository->countAll();
        $lastPage = max(1, (int)ceil($total / 20));
        request()->get['page'] = (string)max(1, min($lastPage, (int)request()->get('page', 1)));
        $pagination = new Pagination($total, 20);

        return plugin_view(\FireballPluginVpnManagerV2::SLUG, 'admin/operations', \FireballPluginVpnManagerV2::viewData('operations', [
            'title' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_operations_title'),
            'subtitle' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_operations_subtitle'),
            'operations' => $repository->page(20, $pagination->getOffset()),
            'pagination' => $pagination,
        ]));
    }

    public function clearOperations(): void
    {
        Permissions::authorize(Permissions::RECONCILE);
        if (!hash_equals('clear_vpn_operations', trim((string)request()->post('confirmation', '')))) {
            session()->setFlash('error', \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_clear_operations_confirmation'));
            response()->redirect(base_href('/admin/plugins/vpn-manager-v2/operations'));
            return;
        }
        try {
            $count = (new OperationQueueRepository())->clearNotRunning();
            session()->setFlash('success', sprintf(
                \FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_operations_cleared'), $count));
        } catch (\Throwable $exception) {
            log_error_details('VPN Manager V2 clear operations failed', [], $exception);
            session()->setFlash('error', \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_clear_operations'));
        }
        response()->redirect(base_href('/admin/plugins/vpn-manager-v2/operations'));
    }

    public function conflicts(): string
    {
        Permissions::authorize(Permissions::VIEW);

        return plugin_view(\FireballPluginVpnManagerV2::SLUG, 'admin/conflicts', \FireballPluginVpnManagerV2::viewData('conflicts', [
            'title' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_conflicts_title'),
            'subtitle' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_conflicts_subtitle'),
            'conflicts' => (new SyncAuditRepository())->conflicts(),
            'unmanagedClients' => (new ConfigurationSyncRepository())->unmanagedRemoteClients(),
            'connections' => (new SubscriptionRepository())->connections(),
        ]));
    }

    public function linkRemoteClient(): never
    {
        Permissions::authorize(Permissions::RECONCILE);
        $remoteClientId = filter_input(INPUT_POST, 'remote_client_id', FILTER_VALIDATE_INT);
        $connectionId = filter_input(INPUT_POST, 'connection_id', FILTER_VALIDATE_INT);
        if (!$remoteClientId || !$connectionId) {
            $this->json(['error' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_manual_link_required')], 422);
        }
        try {
            $linked = (new ConfigurationSyncRepository())->linkRemoteClient((int)$remoteClientId, (int)$connectionId);
            (new SyncAuditRepository())->resolveForConnection((int)$connectionId, $this->adminId());
            $operation = (new OperationQueueRepository())->enqueue(
                'sync_client',
                'manual_sync',
                (int)$linked['server_id'],
                (int)$linked['subscription_id'],
                (int)$linked['connection_id'],
                [],
                $this->adminId()
            );
            $operationId = (string)$operation['operation_id'];
            (new RemoteOperationProcessor())->processOperation($operationId);
            $progress = (new OperationQueueRepository())->progress($operationId) ?? $operation;
            $this->json([
                'status' => (string)$progress['status'],
                'status_label' => LocalizedValue::operationStatus($progress['status'] ?? ''),
                'operation_id' => $operationId,
                'message' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_remote_client_linked'),
                'progress_url' => base_href('/admin/plugins/vpn-manager-v2/operations/' . $operationId),
            ], 202);
        } catch (\Throwable $exception) {
            log_error_details('VPN Manager V2 manual client link failed', [], $exception);
            $this->json(['error' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_manual_link_failed')], 409);
        }
    }

    public function logs(): string
    {
        Permissions::authorize(Permissions::VIEW_EVENTS);

        $repository = new SyncAuditRepository();

        return plugin_view(\FireballPluginVpnManagerV2::SLUG, 'admin/sync-logs', \FireballPluginVpnManagerV2::viewData('sync-logs', [
            'title' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_sync_logs_title'),
            'subtitle' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_sync_logs_subtitle'),
            'logs' => $repository->recent(),
            'logCounts' => $repository->counts(),
        ]));
    }

    public function clearLogs(): void
    {
        Permissions::authorize(Permissions::MANAGE_SETTINGS);
        if (!hash_equals('clear_all_vpn_logs', trim((string)request()->post('confirmation', '')))) {
            session()->setFlash('error', \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_clear_logs_confirmation'));
            response()->redirect(base_href('/admin/plugins/vpn-manager-v2/sync-logs'));
            return;
        }

        try {
            $counts = (new SyncAuditRepository())->clearAllLogs();
            session()->setFlash('success', sprintf(
                \FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_logs_cleared'),
                (int)$counts['total']
            ));
        } catch (\Throwable $exception) {
            log_error_details('VPN Manager V2 clear logs failed', [], $exception);
            session()->setFlash('error', \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_clear_logs'));
        }

        response()->redirect(base_href('/admin/plugins/vpn-manager-v2/sync-logs'));
    }

    public function server(): never
    {
        Permissions::authorize(Permissions::RECONCILE);
        $id = (int)get_route_param('id');
        $server = (new \Fireball\VpnManagerV2\Repositories\ServerRepository())->find($id);
        if (!$server) {
            $this->json(['error' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_server_not_found')], 404);
        }
        $this->queued('sync_server', $id, null, null);
    }

    public function subscription(): never
    {
        Permissions::authorize(Permissions::RECONCILE);
        $id = (int)get_route_param('id');
        if (!(new PlanReconciliationRepository())->subscription($id)) {
            $this->json(['error' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_subscription_not_found')], 404);
        }
        $this->queued('sync_subscription', null, $id, null);
    }

    public function connection(): never
    {
        Permissions::authorize(Permissions::RECONCILE);
        $id = (int)get_route_param('id');
        $node = (new PlanReconciliationRepository())->node($id);
        if (!$node) {
            $this->json(['error' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_connection_not_found')], 404);
        }
        $this->queued('sync_client', (int)$node['server_id'], (int)$node['subscription_id'], $id);
    }

    public function resetTraffic(): never
    {
        Permissions::authorize(Permissions::RECONCILE);
        $id = (int)get_route_param('id');
        $node = (new PlanReconciliationRepository())->node($id);
        if (!$node) {
            $this->json(['error' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_connection_not_found')], 404);
        }
        $this->queued('reset_traffic', (int)$node['server_id'], (int)$node['subscription_id'], $id);
    }

    public function full(): never
    {
        Permissions::authorize(Permissions::RECONCILE);
        $this->queued('full_reconcile', null, null, null);
    }

    public function retry(): never
    {
        Permissions::authorize(Permissions::RECONCILE);
        $count = (new OperationQueueRepository())->retryFailed();
        $result = $count > 0 ? $this->processDueOperations(min(10, $count)) : [];
        $response = $this->batchResult($result);
        $response['retried'] = $count;
        $response['message'] = sprintf(\FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_operations_retried'), $count)
            . ' ' . $response['message'];
        $this->json($response);
    }

    public function processPending(): never
    {
        Permissions::authorize(Permissions::RECONCILE);
        $result = $this->processDueOperations(10);
        $this->json($this->batchResult($result));
    }

    private function batchResult(array $result): array
    {
        $result += ['processed' => 0, 'success' => 0, 'failure' => 0, 'cancelled' => 0];
        $status = (int)$result['failure'] > 0 || ((int)$result['cancelled'] > 0 && (int)$result['success'] > 0)
            ? 'completed_partial'
            : ((int)$result['cancelled'] > 0 ? 'cancelled' : 'completed');
        return [
            'status' => $status,
            'status_label' => LocalizedValue::operationStatus($status),
            'message' => sprintf(
                \FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_operations_result'),
                (int)$result['processed'], (int)$result['success'], (int)$result['failure'], (int)$result['cancelled']
            ),
            'processed' => (int)$result['processed'],
            'success' => (int)$result['success'],
            'failure' => (int)$result['failure'],
            'cancelled' => (int)$result['cancelled'],
        ];
    }

    public function cancel(): never
    {
        Permissions::authorize(Permissions::RECONCILE);
        $operationId = strtolower(trim((string)get_route_param('operation')));
        if (!(new OperationQueueRepository())->cancel($operationId)) {
            $this->json(['error' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_operation_cancel')], 409);
        }
        $this->json([
            'status' => 'cancelled',
            'message' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_operation_cancelled'),
        ]);
    }

    public function progress(): never
    {
        Permissions::authorize(Permissions::VIEW);
        $operationId = strtolower(trim((string)get_route_param('operation')));
        if (preg_match('/^[a-f0-9-]{36}$/', $operationId) !== 1) {
            $this->json(['error' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_operation_not_found')], 404);
        }
        $operation = (new OperationQueueRepository())->progress($operationId);
        if (!$operation) {
            $this->json(['error' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_operation_not_found')], 404);
        }
        $operation['status_label'] = LocalizedValue::operationStatus($operation['status'] ?? '');
        $operation['operation_type_label'] = LocalizedValue::operationType($operation['operation_type'] ?? '');
        $this->json($operation);
    }

    private function queued(string $type, ?int $serverId, ?int $subscriptionId, ?int $connectionId): never
    {
        $operation = (new OperationQueueRepository())->enqueue(
            $type,
            'manual_sync',
            $serverId,
            $subscriptionId,
            $connectionId,
            [],
            $this->adminId()
        );
        $operationId = (string)$operation['operation_id'];
        (new RemoteOperationProcessor())->processOperation($operationId);
        $progress = (new OperationQueueRepository())->progress($operationId) ?? $operation;
        $completed = in_array((string)($progress['status'] ?? ''), ['completed', 'completed_partial'], true);
        $this->json([
            'status' => (string)$progress['status'],
            'status_label' => LocalizedValue::operationStatus($progress['status'] ?? ''),
            'operation_id' => $operationId,
            'created' => !empty($operation['created']),
            'processed_count' => (int)($progress['processed_count'] ?? 0),
            'total_count' => (int)($progress['total_count'] ?? 0),
            'last_error' => (string)($progress['last_error'] ?? ''),
            'message' => (string)$progress['status'] === 'cancelled'
                ? ((string)($progress['last_error'] ?? '') ?: \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_operation_obsolete'))
                : (in_array((string)$progress['status'], ['failed', 'retry'], true)
                    ? LocalizedValue::operationStatus((string)$progress['status'])
                    : \FireballPluginVpnManagerV2::t($completed
                    ? 'vpn_manager_v2_flash_sync_completed'
                    : 'vpn_manager_v2_flash_sync_queued')),
            'progress_url' => base_href('/admin/plugins/vpn-manager-v2/operations/' . $operationId),
        ], 202);
    }

    private function processDueOperations(int $limit): array
    {
        return (new RemoteOperationProcessor())->processDue($limit);
    }

    private function adminId(): ?int
    {
        $user = get_user();
        $id = is_array($user) ? (int)($user['id'] ?? 0) : 0;

        return $id > 0 ? $id : null;
    }

    private function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        exit;
    }
}
