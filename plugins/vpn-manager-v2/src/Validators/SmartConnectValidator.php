<?php

namespace Fireball\VpnManagerV2\Validators;

use Fireball\VpnManagerV2\DTO\SmartConnectData;
use Fireball\VpnManagerV2\Exceptions\ValidationException;

final class SmartConnectValidator
{
    public function validate(array $input, bool $strict = true): array
    {
        $defaults = SmartConnectData::defaults();
        $result = $defaults;
        foreach ($defaults as $key => $fallback) {
            $value = $input[$key] ?? $fallback;
            if (is_bool($fallback)) {
                $result[$key] = is_scalar($value) && in_array(strtolower((string)$value), ['1', 'true', 'yes', 'on'], true);
            } elseif (is_int($fallback)) {
                [$min, $max] = $key === 'smart_connect_interval_seconds' ? [30, 1800] : [1, 5000];
                $number = filter_var($value, FILTER_VALIDATE_INT);
                $result[$key] = $number !== false && $number >= $min && $number <= $max
                    ? $number : $this->invalid($strict, $fallback);
            } elseif ($key === 'smart_connect_server_priorities') {
                if (!is_array($value) || count($value) > 1000) {
                    $result[$key] = $this->invalid($strict, []);
                    continue;
                }
                $priorities = [];
                foreach ($value as $id => $priority) {
                    $serverId = filter_var($id, FILTER_VALIDATE_INT);
                    $number = filter_var($priority, FILTER_VALIDATE_INT);
                    if ($serverId === false || $serverId <= 0 || $number === false || $number < 0 || $number > 1000) {
                        $this->invalid($strict, null);
                        continue;
                    }
                    $priorities[$serverId] = $number;
                }
                ksort($priorities, SORT_NUMERIC);
                $result[$key] = $priorities;
            } else {
                $value = is_string($value) ? trim($value) : '';
                $valid = match ($key) {
                    'smart_connect_mode' => in_array($value, ['manual', 'smart', 'failover'], true),
                    'smart_connect_happ_ping_type' => in_array($value, ['proxy', 'proxy-head'], true),
                    'smart_connect_happ_provider_id' => $value === '' || preg_match('/\A[A-Za-z0-9._:-]{1,128}\z/D', $value) === 1,
                    'smart_connect_test_url' => $this->validTestUrl($value),
                    default => false,
                };
                $result[$key] = $valid ? $value : $this->invalid($strict, $fallback);
            }
        }
        if (SmartConnectData::automatic($result) && $result['smart_connect_happ_enabled']) {
            if ($result['smart_connect_happ_provider_id'] === '') {
                if ($strict) {
                    throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_smart_provider_required'));
                }
                $result['smart_connect_happ_enabled'] = false;
            }
            if (($result['smart_connect_happ_autoconnect'] || $result['smart_connect_happ_sort_ping'])
                && !$result['smart_connect_happ_ping_on_open']) {
                if ($strict) {
                    throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_smart_ping_required'));
                }
                $result['smart_connect_happ_autoconnect'] = false;
                $result['smart_connect_happ_sort_ping'] = false;
            }
        }
        return $result;
    }

    private function validTestUrl(string $url): bool
    {
        if (strlen($url) > 512 || preg_match('/[\x00-\x20\x7f]/', $url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $parts = parse_url($url);
        // Probes run on users' devices. Do not distribute credential-bearing or local URLs.
        if (($parts['scheme'] ?? '') !== 'https' || isset($parts['user'], $parts['pass'])
            || isset($parts['user']) || isset($parts['fragment'])) {
            return false;
        }
        try {
            (new \Fireball\VpnManagerV2\Support\NetworkTargetGuard())->assertConfigurationHost((string)($parts['host'] ?? ''), false);
        } catch (\Throwable) {
            return false;
        }
        return true;
    }

    private function invalid(bool $strict, mixed $fallback): mixed
    {
        if ($strict) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_smart_invalid'));
        }
        return $fallback;
    }
}
