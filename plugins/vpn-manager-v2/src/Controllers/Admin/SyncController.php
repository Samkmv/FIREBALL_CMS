<?php

namespace Fireball\VpnManagerV2\Controllers\Admin;

use Fireball\VpnManagerV2\Repositories\OperationQueueRepository;
use Fireball\VpnManagerV2\Repositories\ConfigurationSyncRepository;
use Fireball\VpnManagerV2\Repositories\PlanReconciliationRepository;
use Fireball\VpnManagerV2\Repositories\SubscriptionRepository;
use Fireball\VpnManagerV2\Repositories\SyncAuditRepository;
use Fireball\VpnManagerV2\Support\Permissions;

final class SyncController
{
    public function operations(): string
    {
        Permissions::authorize(Permissions::VIEW);

        return plugin_view(\FireballPluginVpnManagerV2::SLUG, 'admin/operations', \FireballPluginVpnManagerV2::viewData('operations', [
            'title' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_operations_title'),
            'subtitle' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_operations_subtitle'),
            'operations' => (new OperationQueueRepository())->recent(),
        ]));
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
            $this->json([
                'status' => (string)$operation['status'],
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
        Permissions::authorize(Permissions::VIEW);

        return plugin_view(\FireballPluginVpnManagerV2::SLUG, 'admin/sync-logs', \FireballPluginVpnManagerV2::viewData('sync-logs', [
            'title' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_sync_logs_title'),
            'subtitle' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_sync_logs_subtitle'),
            'logs' => (new SyncAuditRepository())->recent(),
        ]));
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
        $this->json([
            'status' => 'queued',
            'message' => sprintf(\FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_operations_retried'), $count),
            'retried' => $count,
        ]);
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
        $this->json([
            'status' => (string)$operation['status'],
            'operation_id' => $operationId,
            'created' => !empty($operation['created']),
            'message' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_sync_queued'),
            'progress_url' => base_href('/admin/plugins/vpn-manager-v2/operations/' . $operationId),
        ], 202);
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
