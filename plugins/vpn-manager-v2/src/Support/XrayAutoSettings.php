<?php

namespace Fireball\VpnManagerV2\Support;

use Fireball\VpnManagerV2\Exceptions\ValidationException;

final class XrayAutoSettings
{
    public static function defaults(): array
    {
        return [
            'enabled' => false,
            'export' => true,
            'visible' => false,
            'include_external' => true,
            'strategy' => 'leastPing',
            'interval' => 30,
            'probe_url' => 'https://www.google.com/generate_204',
            'timeout' => 5,
            'fallback' => 'block',
            'servers' => [],
        ];
    }

    public static function normalize(mixed $input, bool $strict = false): array
    {
        $defaults = self::defaults();
        $invalid = static function (string $key) use ($defaults, $strict): mixed {
            if ($strict) {
                throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_xray_invalid'));
            }
            return $defaults[$key];
        };
        if (!is_array($input)) {
            $invalid('enabled');
            return $defaults;
        }
        $data = array_replace($defaults, array_intersect_key($input, $defaults));
        foreach (['enabled', 'export', 'visible', 'include_external'] as $key) {
            $data[$key] = filter_var($data[$key], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $invalid($key);
        }
        foreach (['strategy' => ['leastPing', 'leastLoad'], 'fallback' => ['block', 'first']] as $key => $options) {
            if (!in_array($data[$key], $options, true)) {
                $data[$key] = $invalid($key);
            }
        }
        foreach (['interval' => [10, 300], 'timeout' => [1, 15]] as $key => [$min, $max]) {
            $number = filter_var($data[$key], FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
            $data[$key] = $number === false ? $invalid($key) : $number;
        }
        $url = is_string($data['probe_url']) ? trim($data['probe_url']) : '';
        $parts = parse_url($url);
        if (strlen($url) > 512 || !filter_var($url, FILTER_VALIDATE_URL)
            || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            $url = $invalid('probe_url');
        }
        $data['probe_url'] = $url;

        $ids = $data['servers'];
        if (!is_array($ids) || count($ids) > 1000) {
            $ids = $invalid('servers');
            $data['enabled'] = false;
        }
        $data['servers'] = [];
        foreach ($ids as $id) {
            $id = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false) {
                // A malformed allowlist must never widen access to every server.
                $invalid('servers');
                $data['enabled'] = false;
                break;
            }
            $data['servers'][$id] = $id;
        }
        $data['servers'] = array_values($data['servers']);
        return $data;
    }
}
