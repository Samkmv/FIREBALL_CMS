<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\DTO\PlanNodeData;
use Fireball\VpnManagerV2\Exceptions\ValidationException;
use Fireball\VpnManagerV2\Repositories\PlanReconciliationRepository;
use Fireball\VpnManagerV2\Repositories\PlanRepository;
use Fireball\VpnManagerV2\Repositories\ServerPresetRepository;
use Fireball\VpnManagerV2\Repositories\SubscriptionRepository;
use Fireball\VpnManagerV2\Validators\PlanValidator;

final class ServerPresetService
{
    public function save(int $id, array $input, int $adminId): array
    {
        $name = is_string($input['name'] ?? null) ? mb_substr(trim($input['name']), 0, 255) : '';
        if ($name === '') {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_preset_name_required'));
        }
        $presets = new ServerPresetRepository();
        $plans = new PlanRepository();
        $database = db();
        $database->beginTransaction();
        try {
            $before = [];
            $new = $id === 0;
            if (!$new) {
                $presets->requirePreset($id);
                $before = $presets->nodes($id, forUpdate: true);
            }
            $nodeInput = $input['nodes'] ?? [];
            $inboundIds = [];
            foreach (is_array($nodeInput) ? $nodeInput : [] as $node) {
                if (is_array($node) && is_scalar($node['inbound_id'] ?? null)) {
                    $inboundIds[] = (int)$node['inbound_id'];
                }
            }
            $topology = $plans->topologyForInboundIds($inboundIds, forUpdate: true);
            $nodes = (new PlanValidator())->validateNodes($nodeInput, $topology, $before);
            usort($nodes, static fn(PlanNodeData $left, PlanNodeData $right): int => $left->sortOrder <=> $right->sortOrder);

            $now = date('Y-m-d H:i:s');
            if ($new) {
                $id = $presets->create($name, $now);
            } else {
                $presets->rename($id, $name, $now);
            }

            $updated = $queued = 0;
            if ($nodes != array_map(PlanNodeData::fromRow(...), $before)) {
                $presets->replaceNodes($id, $nodes);
                $reconciliation = new PlanReconciliationRepository();
                $reconciler = new VpnPlanSubscriptionReconciler();
                $revisions = new VpnSubscriptionRevisionService();
                foreach ($presets->plans($id, forUpdate: true) as $plan) {
                    $planId = (int)$plan['id'];
                    $plans->replaceNodes($planId, $nodes, $now);
                    $revisions->touchByPlan($planId);
                    if ($reconciliation->eligibleSubscriptionCount($planId) > 0) {
                        $reconciler->queuePlan($planId, $adminId, ['batch_size' => 20]);
                        $queued++;
                    }
                    cache()->remove('vpn-v2:plan-preview:' . $planId);
                    $updated++;
                }
            }

            (new SubscriptionRepository())->logEvent($new ? 'server_preset.created' : 'server_preset.updated',
                null, null, null, null, $adminId ?: null, [
                    'preset_id' => $id, 'node_count' => count($nodes), 'updated_plans' => $updated, 'queued_plans' => $queued,
                ]);
            $database->commit();
            return ['plans' => $updated, 'queued' => $queued];
        } catch (\Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    public function delete(int $id, int $adminId): void
    {
        $presets = new ServerPresetRepository();
        $database = db();
        $database->beginTransaction();
        try {
            $presets->requirePreset($id);
            if ($presets->plans($id, forUpdate: true) !== []) {
                throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_preset_in_use'));
            }
            $presets->delete($id);
            (new SubscriptionRepository())->logEvent(
                'server_preset.deleted', null, null, null, null, $adminId ?: null, ['preset_id' => $id]
            );
            $database->commit();
        } catch (\Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }
}
