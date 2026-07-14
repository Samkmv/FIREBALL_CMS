<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\Exceptions\ValidationException;
use Fireball\VpnManagerV2\Repositories\PlanRepository;
use Fireball\VpnManagerV2\Validators\PlanValidator;

final class PlanManagerService
{
    public function __construct(
        private readonly ?PlanRepository $repository = null,
        private readonly ?PlanValidator $validator = null,
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

    public function update(int $id, array $input): void
    {
        $repository = $this->repository ?? new PlanRepository();
        if (!$repository->find($id)) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_plan_not_found'));
        }

        $plan = ($this->validator ?? new PlanValidator())->validate(
            $input,
            $repository->topologyForInboundIds($this->inboundIds($input))
        );
        $repository->update($id, $plan);
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
}
