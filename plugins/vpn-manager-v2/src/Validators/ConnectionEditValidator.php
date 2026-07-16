<?php

namespace Fireball\VpnManagerV2\Validators;

use Fireball\VpnManagerV2\DTO\ConnectionEditData;
use Fireball\VpnManagerV2\Exceptions\ValidationException;
use Fireball\VpnManagerV2\Services\VpnFlowResolver;

final class ConnectionEditValidator
{
    public function __construct(private readonly ?VpnFlowResolver $flowResolver = null)
    {
    }

    public function validate(array $input, array $connection): ConnectionEditData
    {
        $resolver = $this->flowResolver ?? new VpnFlowResolver();
        $rawFlow = trim((string)($input['flow'] ?? ''));
        $flow = in_array($rawFlow, ['', '__none__'], true) ? null : $resolver->normalizeFlow($rawFlow);
        if (!$resolver->isFlowCompatible($flow, $connection)) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_plan_flow_incompatible'));
        }

        return new ConnectionEditData(
            $flow,
            $this->trafficBytes($input['traffic_limit_value'] ?? 0, (string)($input['traffic_unit'] ?? 'gb')),
        );
    }

    private function trafficBytes(mixed $value, string $unit): ?int
    {
        $normalized = str_replace(',', '.', trim((string)$value));
        if ($normalized === '' || !is_numeric($normalized) || (float)$normalized < 0) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_subscription_edit_limit'));
        }
        if ((float)$normalized === 0.0) {
            return null;
        }
        $multiplier = match (strtolower(trim($unit))) {
            'mb' => 1024 ** 2,
            'gb' => 1024 ** 3,
            'tb' => 1024 ** 4,
            default => throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_subscription_edit_limit')),
        };
        $bytes = (float)$normalized * $multiplier;
        if (!is_finite($bytes) || $bytes > PHP_INT_MAX) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_subscription_edit_limit'));
        }

        return (int)round($bytes);
    }
}
