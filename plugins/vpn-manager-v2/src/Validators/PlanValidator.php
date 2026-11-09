<?php

namespace Fireball\VpnManagerV2\Validators;

use Fireball\VpnManagerV2\DTO\PlanData;
use Fireball\VpnManagerV2\DTO\PlanNodeData;
use Fireball\VpnManagerV2\Exceptions\ValidationException;
use Fireball\VpnManagerV2\Services\VpnFlowResolver;

final class PlanValidator
{
    public function __construct(private readonly ?VpnFlowResolver $flowResolver = null)
    {
    }

    public function validate(array $input, array $topology, array $existingNodes = [], bool $allowEmpty = false): PlanData
    {
        $name = mb_substr(trim((string)($input['name'] ?? '')), 0, 255);
        if ($name === '') {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_plan_name_required'));
        }

        $durationDays = $this->integer($input['duration_days'] ?? 30, 1, 36500, 'vpn_manager_v2_error_plan_duration');
        $deviceLimit = $this->integer($input['device_limit'] ?? 1, 1, 100000, 'vpn_manager_v2_error_plan_device_limit');
        $nodeInput = $input['nodes'] ?? [];
        $nodes = $allowEmpty && $nodeInput === [] ? [] : $this->validateNodes($nodeInput, $topology, $existingNodes);

        return new PlanData(
            $name,
            $this->nullableString($input['description'] ?? null, 12000),
            $durationDays,
            $this->trafficBytes($input['traffic_limit_value'] ?? 0, (string)($input['traffic_unit'] ?? 'gb')),
            $deviceLimit,
            !empty($input['is_active']),
            $nodes,
            $this->integer($input['ip_limit'] ?? 0, 0, 100000, 'vpn_manager_v2_error_plan_ip_limit'),
        );
    }

    public function validateNodes(mixed $nodesInput, array $topology, array $existingNodes = []): array
    {
        if (!is_array($nodesInput) || $nodesInput === []) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_plan_nodes_required'));
        }

        $retained = [];
        foreach ($existingNodes as $node) {
            $retained[$node['server_id'] . ':' . $node['inbound_id']] = $node;
        }
        $resolver = $this->flowResolver ?? new VpnFlowResolver();
        $nodes = [];
        $seen = [];
        foreach ($nodesInput as $nodeInput) {
            if (!is_array($nodeInput)) {
                throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_plan_node_invalid'));
            }

            $serverId = (int)($nodeInput['server_id'] ?? 0);
            $inboundId = (int)($nodeInput['inbound_id'] ?? 0);
            if ($serverId <= 0 || $inboundId <= 0 || !isset($topology[$inboundId])) {
                throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_plan_inbound_unavailable'));
            }

            $inbound = $topology[$inboundId];
            if ((int)($inbound['server_id'] ?? 0) !== $serverId) {
                throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_plan_node_mismatch'));
            }
            $key = $serverId . ':' . $inboundId;
            $previous = $retained[$key] ?? null;
            if (!$previous && empty($inbound['server_is_enabled'])) {
                throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_plan_server_disabled'));
            }
            $available = !empty($inbound['server_is_enabled']) && !empty($inbound['is_enabled'])
                && (string)($inbound['status'] ?? '') === 'active';
            if (!$previous && !$available) {
                throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_plan_inbound_disabled'));
            }

            if (isset($seen[$key])) {
                throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_plan_node_duplicate'));
            }
            $seen[$key] = true;

            $flowOverride = $available
                ? $this->flowOverride($nodeInput['flow_override'] ?? '__auto__', $inbound, $resolver)
                : $previous['flow_override'];
            $sortOrder = $this->integer($nodeInput['sort_order'] ?? 0, 0, 1000000, 'vpn_manager_v2_error_plan_sort_order');
            $nodes[] = new PlanNodeData($serverId, $inboundId, $flowOverride, $sortOrder);
        }

        return $nodes;
    }

    private function flowOverride(mixed $value, array $inbound, VpnFlowResolver $resolver): ?string
    {
        if (!is_scalar($value) && $value !== null) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_plan_flow_incompatible'));
        }

        $value = trim((string)$value);
        if ($value === '' || $value === '__auto__') {
            return null;
        }
        if ($value === '__none__') {
            return '';
        }

        $flow = $resolver->normalizeFlow($value);
        if ($flow === null || !$resolver->isFlowCompatible($flow, $inbound)) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_plan_flow_incompatible'));
        }

        return $flow;
    }

    private function trafficBytes(mixed $value, string $unit): ?int
    {
        $normalized = str_replace(',', '.', trim((string)$value));
        if ($normalized === '' || !is_numeric($normalized) || (float)$normalized < 0) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_plan_traffic_limit'));
        }

        $number = (float)$normalized;
        if ($number == 0.0) {
            return null;
        }

        $multiplier = match (strtolower($unit)) {
            'mb' => 1024 ** 2,
            'gb' => 1024 ** 3,
            'tb' => 1024 ** 4,
            default => throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_plan_traffic_limit')),
        };
        $bytes = $number * $multiplier;
        if (!is_finite($bytes) || $bytes > PHP_INT_MAX) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_plan_traffic_limit'));
        }

        return (int)round($bytes);
    }

    private function integer(mixed $value, int $minimum, int $maximum, string $errorKey): int
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t($errorKey));
        }

        $value = (int)$value;
        if ($value < $minimum || $value > $maximum) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t($errorKey));
        }

        return $value;
    }

    private function nullableString(mixed $value, int $length): ?string
    {
        $value = mb_substr(trim((string)$value), 0, $length);

        return $value !== '' ? $value : null;
    }
}
