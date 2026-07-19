<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\Exceptions\VpnConfigValidationException;

final class VpnConfigValidator
{
    private const NETWORKS = ['tcp', 'raw', 'xhttp', 'splithttp', 'ws', 'grpc', 'httpupgrade'];
    private const REALITY_KEYS = ['pbk', 'sid', 'spx', 'pqv'];

    public function __construct(private readonly ?VpnFlowResolver $flowResolver = null)
    {
    }

    public function validate(array $node, array $stream, array $params): void
    {
        $protocol = strtolower(trim((string)($node['protocol'] ?? '')));
        $network = strtolower(trim((string)($node['network'] ?? '')));
        $security = strtolower(trim((string)($node['security'] ?? 'none')));
        $host = trim((string)($node['config_host'] ?? ''));
        $uuid = trim((string)($node['client_uuid'] ?? ''));
        $streamNetwork = strtolower(trim((string)($stream['network'] ?? '')));
        $streamSecurity = strtolower(trim((string)($stream['security'] ?? 'none')));

        $uuidCredential = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid) === 1;
        if (!in_array($protocol, ['vless', 'vmess', 'trojan'], true)
            || (($protocol === 'vless' || $protocol === 'vmess') && !$uuidCredential)
            || ($protocol === 'trojan' && $uuid === '')
            || !$this->validHost($host)
            || (int)($node['port'] ?? 0) < 1
            || (int)($node['port'] ?? 0) > 65535
            || !in_array($network, self::NETWORKS, true)
            || !in_array($security, ['reality', 'tls', 'none'], true)
            || $streamNetwork !== $network
            || $streamSecurity !== $security) {
            $this->invalid();
        }
        if ($security === 'reality' && $protocol !== 'vless') {
            $this->invalid();
        }

        $flow = ($this->flowResolver ?? new VpnFlowResolver())->normalizeFlow($node['flow'] ?? null);
        if (!($this->flowResolver ?? new VpnFlowResolver())->isFlowCompatible($flow, [
            'protocol' => $protocol,
            'network' => $network,
            'security' => $security,
        ])) {
            $this->invalid();
        }
        if (array_key_exists('flow', $params) && trim((string)$params['flow']) === '') {
            $this->invalid();
        }
        if ($protocol === 'vless' && ($params['encryption'] ?? null) !== 'none') {
            $this->invalid();
        }

        if ($security === 'reality') {
            foreach (['pbk', 'sid', 'sni', 'fp'] as $key) {
                if (!$this->usable($params[$key] ?? null)) {
                    $this->invalid();
                }
            }
        } elseif ($security === 'tls') {
            if (!$this->usable($params['sni'] ?? null)) {
                $this->invalid();
            }
            foreach (self::REALITY_KEYS as $key) {
                if (array_key_exists($key, $params)) {
                    $this->invalid();
                }
            }
        } else {
            foreach (array_merge(self::REALITY_KEYS, ['sni', 'fp', 'alpn', 'allowInsecure']) as $key) {
                if (array_key_exists($key, $params)) {
                    $this->invalid();
                }
            }
        }

        if (in_array($network, ['xhttp', 'splithttp'], true)) {
            $settings = $stream['xhttpSettings'] ?? $stream['splithttpSettings'] ?? null;
            if (!is_array($settings) || !$this->usable($params['path'] ?? null) || !$this->usable($params['mode'] ?? null)) {
                $this->invalid();
            }
        }
    }

    public function validateUri(string $uri): void
    {
        if ($uri === '' || str_contains($uri, "\n") || str_contains($uri, "\r")) {
            $this->invalid();
        }

        if (str_starts_with($uri, 'vmess://')) {
            $encoded = substr($uri, strlen('vmess://'));
            $json = base64_decode($encoded, true);
            $payload = is_string($json) ? json_decode($json, true) : null;
            if (!is_array($payload)
                || !in_array((string)($payload['v'] ?? ''), ['2', '3'], true)
                || preg_match('/^[0-9a-f-]{36}$/i', (string)($payload['id'] ?? '')) !== 1
                || !$this->validHost((string)($payload['add'] ?? ''))
                || (int)($payload['port'] ?? 0) < 1
                || trim((string)($payload['ps'] ?? '')) === '') {
                $this->invalid();
            }

            return;
        }

        if (!str_starts_with($uri, 'vless://') && !str_starts_with($uri, 'trojan://')) {
            $this->invalid();
        }

        $parts = parse_url($uri);
        if (!is_array($parts)
            || empty($parts['user'])
            || empty($parts['host'])
            || (int)($parts['port'] ?? 0) < 1
            || empty($parts['query'])
            || empty($parts['fragment'])) {
            $this->invalid();
        }

        parse_str((string)$parts['query'], $query);
        $vless = str_starts_with($uri, 'vless://');
        if (($vless && ($query['encryption'] ?? null) !== 'none')
            || !in_array((string)($query['security'] ?? ''), ['reality', 'tls', 'none'], true)
            || (!$vless && (string)($query['security'] ?? '') === 'reality')
            || empty($query['type'])
            || (array_key_exists('flow', $query) && trim((string)$query['flow']) === '')) {
            $this->invalid();
        }
    }

    private function validHost(string $host): bool
    {
        if ($host === '' || str_contains($host, '/') || str_contains($host, '@')) {
            return false;
        }

        return filter_var($host, FILTER_VALIDATE_IP) !== false
            || preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i', $host) === 1;
    }

    private function usable(mixed $value): bool
    {
        $value = trim((string)$value);

        return $value !== '' && $value !== '[redacted]';
    }

    private function invalid(): never
    {
        throw new VpnConfigValidationException(
            \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_config_invalid')
        );
    }
}
