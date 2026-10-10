<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\Exceptions\VpnConfigValidationException;
use Fireball\VpnManagerV2\Validators\SmartConnectValidator;

/** Explicit sing-box 1.12+ profile; never substituted for a URI subscription. */
final class SingBoxSubscriptionBuilder
{
    public function build(array $uris, array $settings, array $firstPartyPriorities = []): string
    {
        $settings = (new SmartConnectValidator())->validate($settings, false);
        if ($uris === [] || count($uris) > 500) {
            $this->unsupported();
        }
        $outbounds = [];
        foreach (array_values($uris) as $index => $uri) {
            $outbound = $this->outbound((string)$uri);
            // Stable tags without credentials, even when display names are equal.
            $outbound['tag'] = 'node-' . substr(hash('sha256', explode('#', (string)$uri, 2)[0]), 0, 16);
            $outbounds[] = ['outbound' => $outbound, 'order' => $index, 'priority' => $firstPartyPriorities[$uri] ?? 1000];
        }
        // Explicit priorities affect only this profile. URI/manual order is untouched.
        usort($outbounds, static fn(array $a, array $b): int => [$a['priority'], $a['order']] <=> [$b['priority'], $b['order']]);
        $outbounds = array_column($outbounds, 'outbound');
        $tags = array_column($outbounds, 'tag');
        if (count(array_unique($tags)) !== count($tags)) {
            $this->unsupported();
        }
        $group = $settings['smart_connect_mode'] === 'manual'
            ? ['type' => 'selector', 'tag' => 'auto', 'outbounds' => $tags, 'default' => $tags[0]]
            : [
                'type' => 'urltest', 'tag' => 'auto', 'outbounds' => $tags,
                'url' => $settings['smart_connect_test_url'],
                'interval' => $settings['smart_connect_interval_seconds'] . 's',
                'tolerance' => $settings['smart_connect_tolerance_ms'],
                'idle_timeout' => '30m',
                'interrupt_exist_connections' => $settings['smart_connect_mode'] === 'failover',
            ];
        $profile = $this->baseProfile();
        $profile['outbounds'] = array_merge([$group], $outbounds);
        $profile['dns']['servers'][] = ['type' => 'https', 'tag' => 'remote', 'server' => '1.1.1.1',
            'tls' => ['enabled' => true, 'server_name' => 'cloudflare-dns.com'], 'detour' => 'auto'];
        $profile['dns']['final'] = 'remote';
        $profile['route']['rules'] = [['protocol' => 'dns', 'action' => 'hijack-dns']];
        $profile['route']['final'] = 'auto';
        return $this->encode($profile);
    }

    public function inactive(): string
    {
        $profile = $this->baseProfile();
        $profile['outbounds'] = [];
        $profile['route']['rules'] = [['action' => 'reject']];
        return $this->encode($profile);
    }

    private function baseProfile(): array
    {
        return [
            'log' => ['disabled' => true],
            'dns' => ['servers' => [['type' => 'local', 'tag' => 'bootstrap']], 'final' => 'bootstrap'],
            'inbounds' => [['type' => 'tun', 'tag' => 'tun-in', 'address' => ['172.19.0.1/30', 'fdfe:dcba:9876::1/126'],
                'auto_route' => true, 'strict_route' => true, 'stack' => 'mixed']],
            'route' => ['auto_detect_interface' => true, 'default_domain_resolver' => 'bootstrap'],
        ];
    }

    public function outbound(string $uri): array
    {
        $scheme = strtolower((string)parse_url($uri, PHP_URL_SCHEME));
        if ($scheme === 'vmess') {
            $json = $this->base64(explode('#', substr($uri, 8), 2)[0]);
            $v = json_decode($json, true);
            if (!is_array($v) || !isset($v['id'], $v['add'], $v['port'])
                || count(array_filter($v, static fn($value): bool => !is_scalar($value))) > 0
                || array_diff(array_keys($v), ['v', 'ps', 'add', 'port', 'id', 'aid', 'scy', 'net', 'type', 'host', 'path', 'tls', 'sni', 'alpn', 'fp', 'allowInsecure']) !== []) {
                $this->unsupported();
            }
            $port = $this->integer($v['port'], 1, 65535);
            $alterId = $this->integer($v['aid'] ?? 0, 0, 65535);
            $p = ['type' => $v['net'] ?? 'tcp', 'headerType' => $v['type'] ?? 'none',
                'host' => $v['host'] ?? '', 'path' => $v['path'] ?? '',
                'serviceName' => $v['path'] ?? '', 'security' => $v['tls'] ?? 'none',
                'sni' => $v['sni'] ?? '', 'alpn' => $v['alpn'] ?? '', 'fp' => $v['fp'] ?? '', 'allowInsecure' => $v['allowInsecure'] ?? ''];
            $out = ['type' => 'vmess', 'server' => $v['add'], 'server_port' => $port,
                'uuid' => $v['id'], 'security' => $v['scy'] ?? 'auto', 'alter_id' => $alterId];
            if (!in_array($out['security'], ['auto', 'none', 'zero', 'aes-128-gcm', 'chacha20-poly1305'], true)) {
                $this->unsupported();
            }
        } elseif (in_array($scheme, ['vless', 'trojan', 'ss'], true)) {
            $parts = parse_url($uri);
            if (!is_array($parts) || !isset($parts['host'], $parts['port'], $parts['user'])) {
                $this->unsupported();
            }
            parse_str($parts['query'] ?? '', $p);
            // Never silently strip an unknown transport/security extension.
            $allowed = ['encryption', 'security', 'type', 'flow', 'sni', 'peer', 'fp', 'pbk', 'sid', 'spx',
                'alpn', 'allowInsecure', 'host', 'path', 'serviceName', 'headerType'];
            if (array_diff(array_keys($p), $allowed) !== [] || count(array_filter($p, 'is_array')) > 0) {
                $this->unsupported();
            }
            if ($scheme === 'ss' && $p !== []) {
                $this->unsupported();
            }
            $credential = rawurldecode($parts['user']);
            if (isset($parts['pass'])) {
                $credential .= ':' . rawurldecode($parts['pass']);
            }
            $out = ['type' => $scheme === 'ss' ? 'shadowsocks' : $scheme,
                'server' => trim($parts['host'], '[]'), 'server_port' => (int)$parts['port']];
            if ($scheme === 'ss') {
                if (!str_contains($credential, ':')) {
                    $credential = $this->base64($credential);
                }
                [$method, $password] = array_pad(explode(':', $credential, 2), 2, '');
                if (!in_array($method, ['aes-128-gcm', 'aes-256-gcm', 'chacha20-ietf-poly1305'], true) || $password === '') {
                    $this->unsupported();
                }
                $out['method'] = $method;
                $out['password'] = $password;
            } else {
                $out[$scheme === 'vless' ? 'uuid' : 'password'] = $credential;
            }
            if ($scheme === 'vless') {
                if (($p['encryption'] ?? 'none') !== 'none' || !in_array($p['flow'] ?? '', ['', 'xtls-rprx-vision'], true)) {
                    $this->unsupported();
                }
                if (!empty($p['flow'])) {
                    $out['flow'] = $p['flow'];
                }
            }
        } else {
            $this->unsupported();
        }
        if (!is_string($out['server']) || $out['server'] === '' || $out['server_port'] < 1 || $out['server_port'] > 65535
            || preg_match('/[\x00-\x20\x7f]/', $out['server'])) {
            $this->unsupported();
        }
        if (isset($out['uuid']) && preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/iD', (string)$out['uuid']) !== 1) {
            $this->unsupported();
        }
        if (isset($out['password']) && $out['password'] === '') {
            $this->unsupported();
        }
        $this->security($out, $p, $scheme);
        $this->transport($out, $p);
        return $out;
    }

    private function security(array &$out, array $p, string $scheme): void
    {
        $security = $p['security'] ?? ($scheme === 'trojan' ? 'tls' : 'none');
        if ($security === '') {
            $security = 'none';
        }
        if (!in_array($security, ['none', 'tls', 'reality'], true)) {
            $this->unsupported();
        }
        if ($security === 'none') {
            if (!empty($out['flow'])) {
                $this->unsupported();
            }
            return;
        }
        if (!empty($out['flow']) && !in_array(strtolower((string)($p['type'] ?? 'tcp')), ['tcp', 'raw'], true)) {
            $this->unsupported();
        }
        $tls = ['enabled' => true, 'server_name' => ($p['sni'] ?? '') ?: ($p['peer'] ?? $out['server'])];
        if (preg_match('/[\x00-\x20\x7f]/', (string)$tls['server_name'])) {
            $this->unsupported();
        }
        if (isset($p['allowInsecure']) && !in_array((string)$p['allowInsecure'], ['', '0', '1', 'false', 'true'], true)) {
            $this->unsupported();
        }
        if (!empty($p['allowInsecure']) && in_array((string)$p['allowInsecure'], ['1', 'true'], true)) {
            $tls['insecure'] = true;
        }
        if (!empty($p['alpn'])) {
            $tls['alpn'] = explode(',', (string)$p['alpn']);
        }
        if (!empty($p['fp'])) {
            if (!in_array($p['fp'], ['chrome', 'firefox', 'edge', 'safari', '360', 'qq', 'ios', 'android',
                'random', 'randomized', 'chrome_psk', 'chrome_psk_shuffle', 'chrome_padding_psk_shuffle', 'chrome_pq', 'chrome_pq_psk'], true)) {
                $this->unsupported();
            }
            $tls['utls'] = ['enabled' => true, 'fingerprint' => $p['fp']];
        }
        if ($security === 'reality') {
            if (empty($p['pbk']) || strlen($this->base64((string)$p['pbk'])) !== 32
                || ($p['sid'] ?? '') !== '' && preg_match('/\A(?:[0-9a-fA-F]{2}){1,8}\z/', $p['sid']) !== 1) {
                $this->unsupported();
            }
            $tls['reality'] = ['enabled' => true, 'public_key' => $p['pbk'], 'short_id' => $p['sid'] ?? ''];
            $tls['utls'] ??= ['enabled' => true, 'fingerprint' => 'chrome'];
        }
        $out['tls'] = $tls;
    }

    private function transport(array &$out, array $p): void
    {
        $network = strtolower((string)($p['type'] ?? 'tcp'));
        if (in_array($network, ['tcp', 'raw'], true) && in_array($p['headerType'] ?? 'none', ['', 'none'], true)) {
            return;
        }
        $out['transport'] = match ($network) {
            'ws' => ['type' => 'ws', 'path' => $p['path'] ?? '/',
                'headers' => !empty($p['host']) ? ['Host' => $p['host']] : new \stdClass()],
            'grpc' => ['type' => 'grpc', 'service_name' => $p['serviceName'] ?? ''],
            'httpupgrade' => ['type' => 'httpupgrade', 'host' => $p['host'] ?? '', 'path' => $p['path'] ?? '/'],
            default => $this->unsupported(),
        };
    }

    private function base64(string $value): string
    {
        $value = strtr($value, '-_', '+/');
        $decoded = base64_decode($value . str_repeat('=', (4 - strlen($value) % 4) % 4), true);
        return $decoded !== false ? $decoded : $this->unsupported();
    }

    private function integer(mixed $value, int $min, int $max): int
    {
        if ((!is_int($value) && !is_string($value)) || preg_match('/\A[0-9]+\z/D', (string)$value) !== 1
            || (float)$value < $min || (float)$value > $max) {
            $this->unsupported();
        }
        return (int)$value;
    }

    private function encode(array $profile): string
    {
        try {
            return json_encode($profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
        } catch (\JsonException) {
            $this->unsupported();
        }
    }

    private function unsupported(): never
    {
        // Do not put the rejected URI (credentials) into errors or logs.
        throw new VpnConfigValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_smart_export_unsupported'));
    }
}
