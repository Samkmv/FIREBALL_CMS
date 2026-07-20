<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\DTO\PlanUpdateResult;
use Fireball\VpnManagerV2\DTO\ReconcileResult;
use Fireball\VpnManagerV2\Exceptions\ValidationException;
use Fireball\VpnManagerV2\Repositories\PlanReconciliationRepository;
use Fireball\VpnManagerV2\Repositories\PlanRepository;
use Fireball\VpnManagerV2\Repositories\SubscriptionRepository;
use Fireball\VpnManagerV2\Validators\PlanValidator;

final class PlanManagerService
{
    public function __construct(
        private readonly ?PlanRepository $repository = null,
        private readonly ?PlanValidator $validator = null,
        private readonly ?VpnPlanSubscriptionReconciler $reconciler = null,
        private readonly ?PlanReconciliationRepository $reconciliationRepository = null,
    ) {
    }

    public function create(array $input): int
    {
        $repository = $this->repository ?? new PlanRepository();
        $plan = ($this->validator ?? new PlanValidator())->validate(
            $input,
            $repository->topologyForInboundIds($this->inboundIds($input))
        );

        return $repository->create($plan);
    }

    public function update(int $id, array $input): PlanUpdateResult
    {
        $repository = $this->repository ?? new PlanRepository();
        if (!$repository->find($id)) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_plan_not_found'));
        }

        $before = $repository->nodes($id);
        $plan = ($this->validator ?? new PlanValidator())->validate(
            $input,
            $repository->topologyForInboundIds($this->inboundIds($input))
        );
        $repository->update($id, $plan);
        $after = $repository->nodes($id);
        $diff = $this->diffPlanNodes($before, $after);
        $adminId = $this->adminId();
        $events = new SubscriptionRepository();
        foreach ($diff['added'] as $node) {
            $events->logEvent('plan_node_added', null, null, (int)$node['server_id'], null, $adminId, [
                'plan_id' => $id,
                'plan_node_id' => (int)$node['id'],
                'inbound_id' => (int)$node['inbound_id'],
            ]);
        }
        foreach ($diff['removed'] as $node) {
            $events->logEvent('plan_node_removed', null, null, (int)$node['server_id'], null, $adminId, [
                'plan_id' => $id,
                'plan_node_id' => (int)$node['id'],
                'inbound_id' => (int)$node['inbound_id'],
            ]);
        }

        $reconciliationRepository = $this->reconciliationRepository ?? new PlanReconciliationRepository();
        foreach ($diff['removed'] as $removedNode) {
            $reconciliationRepository->markTargetObsolete(
                $id,
                (int)$removedNode['server_id'],
                (int)$removedNode['inbound_id']
            );
        }
        $affected = $reconciliationRepository->eligibleSubscriptionCount($id);
        $reconciliation = null;
        $reconciler = $this->reconciler ?? new VpnPlanSubscriptionReconciler();
        $propagate = !empty($input['reconcile_existing']);
        try {
            if ($affected > 0 && $propagate && ($diff['added'] !== [] || $diff['changed'] !== [])) {
                // A plan edit can fan out to many panels. Keep the admin request
                // local-only and let the registered worker perform remote I/O.
                $reconciliation = $reconciler->queuePlan($id, $adminId, ['batch_size' => 20]);
            } elseif ($affected > 0 && $diff['removed'] !== []) {
                // Removal only marks local connections obsolete. Remote clients keep working.
                $removalOptions = [
                    'initiated_by' => $adminId,
                    'provision_missing' => false,
                    'sync_flow' => false,
                    'batch_size' => 20,
                ];
                $reconciliation = $reconciler->queuePlan($id, $adminId, $removalOptions);
            }
        } catch (\Throwable $exception) {
            $events->logEvent('plan_reconcile_failed', null, null, null, null, $adminId, [
                'plan_id' => $id,
                'failure_count' => $affected,
                'safe_error_code' => $this->errorType($exception),
            ]);
            $reconciliation = new ReconcileResult($id, $affected, failed: max(1, $affected));
        }

        return new PlanUpdateResult(
            $id,
            $diff['added'],
            $diff['removed'],
            $diff['unchanged'],
            $diff['changed'],
            $affected,
            $reconciliation,
        );
    }

    public function diffPlanNodes(array $before, array $after): array
    {
        $old = $this->nodesByTarget($before);
        $new = $this->nodesByTarget($after);
        $added = $removed = $unchanged = $changed = [];
        foreach ($new as $key => $node) {
            if (!isset($old[$key])) {
                $added[] = $node;
                continue;
            }
            if ($this->nodeSignature($old[$key]) !== $this->nodeSignature($node)) {
                $changed[] = ['before' => $old[$key], 'after' => $node];
            } else {
                $unchanged[] = $node;
            }
        }
        foreach ($old as $key => $node) {
            if (!isset($new[$key])) {
                $removed[] = $node;
            }
        }

        return compact('added', 'removed', 'unchanged', 'changed');
    }

    public function toggle(int $id): bool
    {
        $repository = $this->repository ?? new PlanRepository();
        $plan = $repository->find($id);
        if (!$plan) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_plan_not_found'));
        }

        if (empty($plan['is_active'])) {
            $nodes = $repository->nodes($id);
            $nodeInput = array_map(static function (array $node): array {
                $storedFlow = $node['flow_override'] ?? null;

                return [
                    'server_id' => (int)$node['server_id'],
                    'inbound_id' => (int)$node['inbound_id'],
                    'flow_override' => $storedFlow === null
                        ? '__auto__'
                        : ((string)$storedFlow === '' ? '__none__' : (string)$storedFlow),
                    'sort_order' => (int)$node['sort_order'],
                ];
            }, $nodes);
            ($this->validator ?? new PlanValidator())->validateNodes(
                $nodeInput,
                $repository->topologyForInboundIds(array_column($nodeInput, 'inbound_id'))
            );
        }

        return $repository->toggle($id);
    }

    public function delete(int $id): bool
    {
        $repository = $this->repository ?? new PlanRepository();
        if (!$repository->find($id)) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_plan_not_found'));
        }

        return $repository->archive($id);
    }

    private function inboundIds(array $input): array
    {
        $ids = [];
        foreach ((array)($input['nodes'] ?? []) as $node) {
            if (is_array($node) && is_scalar($node['inbound_id'] ?? null)) {
                $ids[] = (int)$node['inbound_id'];
            }
        }

        return $ids;
    }

    private function nodesByTarget(array $nodes): array
    {
        $map = [];
        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }
            $map[(int)($node['server_id'] ?? 0) . ':' . (int)($node['inbound_id'] ?? 0)] = $node;
        }

        return $map;
    }

    private function nodeSignature(array $node): string
    {
        $flow = array_key_exists('flow_override', $node) && $node['flow_override'] !== null
            ? trim((string)$node['flow_override'])
            : '__auto__';

        return (int)($node['server_id'] ?? 0) . ':' . (int)($node['inbound_id'] ?? 0)
            . ':' . $flow . ':' . (!empty($node['is_enabled']) ? '1' : '0');
    }

    private function adminId(): int
    {
        $user = get_user();

        return is_array($user) ? (int)($user['id'] ?? 0) : 0;
    }

    private function errorType(\Throwable $exception): string
    {
        $class = get_class($exception);
        $position = strrpos($class, '\\');

        return mb_substr($position === false ? $class : substr($class, $position + 1), 0, 120);
    }
}
