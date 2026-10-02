<?php

namespace Fireball\VpnManagerV2\Controllers\Admin;

use Fireball\VpnManagerV2\Exceptions\VpnManagerV2Exception;
use Fireball\VpnManagerV2\Repositories\InboundRepository;
use Fireball\VpnManagerV2\Repositories\OperationQueueRepository;
use Fireball\VpnManagerV2\Repositories\ServerRecoveryRepository;
use Fireball\VpnManagerV2\Repositories\ServerRepository;
use Fireball\VpnManagerV2\Services\InboundSyncService;
use Fireball\VpnManagerV2\Services\RemoteOperationProcessor;
use Fireball\VpnManagerV2\Support\LocalizedValue;
use Fireball\VpnManagerV2\Support\Permissions;
use Fireball\VpnManagerV2\Support\ServerConfigurationSignature;

final class ServerRecoveryController
{
    public function index(): string
    {
        Permissions::authorize(Permissions::VIEW);
        $id = (int)get_route_param('id');
        $server = (new ServerRepository())->find($id);
        if (!$server) { abort('', 404); }
        $repository = new ServerRecoveryRepository();
        return plugin_view(\FireballPluginVpnManagerV2::SLUG, 'admin/server-recovery', \FireballPluginVpnManagerV2::viewData('servers', [
            'title' => sprintf(\FireballPluginVpnManagerV2::t('vpn_manager_v2_recovery_title'), $server['name']),
            'subtitle' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_recovery_help'),
            'server' => $server,
            'targets' => $repository->targets($id),
            'inbounds' => (new InboundRepository())->forServer($id),
            'subscriptions' => $repository->subscriptions($id),
            'dependencies' => (new ServerRepository())->dependencies($id),
            'recoveryOperations' => $repository->operations($id),
            'recoverySignature' => $this->signature($id),
            'inspected' => $this->inspected($id),
        ]));
    }

    public function inspect(): void
    {
        Permissions::authorize(Permissions::RECONCILE);
        $id = (int)get_route_param('id');
        try {
            (new InboundSyncService())->sync($id);
            session()->set('vpn_recovery_' . $id, ['signature' => $this->signature($id), 'at' => time()]);
            session()->setFlash('success', \FireballPluginVpnManagerV2::t('vpn_manager_v2_recovery_inspected'));
        } catch (\Throwable $exception) {
            session()->remove('vpn_recovery_' . $id);
            session()->setFlash('error', $this->safeError($exception));
        }
        response()->redirect(base_href('/admin/plugins/vpn-manager-v2/servers/' . $id . '/recovery'));
    }

    public function apply(): never
    {
        Permissions::authorize(Permissions::RECONCILE);
        $id = (int)get_route_param('id');
        try {
            $server = (new ServerRepository())->find($id);
            if (!$this->inspected($id) || !$server || empty($server['is_enabled']) || !empty($server['maintenance_mode'])
                || empty($server['allow_new_connections'])) {
                throw new \Fireball\VpnManagerV2\Exceptions\ValidationException(
                    \FireballPluginVpnManagerV2::t('vpn_manager_v2_recovery_check_required'));
            }
            $repository = new ServerRecoveryRepository();
            $mapping = request()->post('mapping', []);
            if (!is_array($mapping)) { $mapping = []; }
            $repository->remap($id, $mapping);
            $queue = new OperationQueueRepository();
            $user = get_user();
            $operations = [];
            $signature = $this->signature($id);
            foreach ($repository->subscriptions($id) as $subscription) {
                $operation = $queue->enqueue('sync_subscription', 'manual_sync', $id,
                    (int)$subscription['id'], null,
                    ['repair_server_id' => $id, 'repair_server_signature' => $signature], (int)($user['id'] ?? 0));
                $operations[] = (string)$operation['operation_id'];
            }
            $this->json(['operation_ids' => $operations, 'message' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_recovery_started')]);
        } catch (\Throwable $exception) {
            $this->json(['error' => $this->safeError($exception)], 422);
        }
    }

    public function process(): never
    {
        Permissions::authorize(Permissions::RECONCILE);
        $id = (int)get_route_param('id');
        $uuid = trim((string)request()->post('operation_id', ''));
        $row = (new ServerRecoveryRepository())->operation($id, $uuid);
        if (!$row) { $this->json(['error' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_operation_not_found')], 404); }
        $queue = new OperationQueueRepository();
        if (!empty(request()->post('retry'))) { $queue->retryOperation($uuid); }
        (new RemoteOperationProcessor())->processOperation($uuid);
        $progress = $queue->progress($uuid) ?? $row;
        if ($progress['status'] !== 'completed') {
            $subscription = (new \Fireball\VpnManagerV2\Repositories\PlanReconciliationRepository())
                ->subscription((int)$row['subscription_id']);
            $progress['last_error'] = ($subscription['last_error'] ?? null) ?: ($progress['last_error'] ?? null);
        }
        $progress['status_label'] = LocalizedValue::operationStatus($progress['status']);
        $this->json($progress);
    }

    private function safeError(\Throwable $exception): string
    {
        return $exception instanceof VpnManagerV2Exception ? $exception->getMessage()
            : \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_sync_generic');
    }

    private function signature(int $id): string
    {
        $server = (new ServerRepository())->findWithSecrets($id) ?? [];
        return ServerConfigurationSignature::hash($server);
    }

    private function inspected(int $id): bool
    {
        $check = session()->get('vpn_recovery_' . $id, []);
        return is_array($check) && (int)($check['at'] ?? 0) > time() - 1800
            && hash_equals((string)($check['signature'] ?? ''), $this->signature($id));
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
