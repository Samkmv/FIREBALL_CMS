<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\Exceptions\VpnConfigValidationException;

final class XrayAutoConfigService
{
    private const NODE_PREFIX = 'vpn-node-';
    private const BLOCK = 'vpn-block';
    private const BALANCER = 'vpn-auto';
    private const DNS = 'vpn-dns';
    private const DNS_OUTBOUND = 'vpn-dns-out';

    public function subscriptionUris(array $subscription, array $nodes, array $settings, VpnSubscriptionBuilder $builder, bool $allowExternal): array
    {
        $nodes = array_filter($nodes, static fn(array $node): bool => empty($node['maintenance_mode'])
            && ($settings['servers'] === [] || in_array((int)$node['server_id'], $settings['servers'], true)));
        return $builder->build($subscription, $nodes, includeExternal: $allowExternal && $settings['include_external']);
    }

    /** @param list<string> $uris Already authorized and filtered subscription entries. */
    public function build(array $uris, array $settings, string $name): array
    {
        $peers = [];
        $names = [];
        foreach ($uris as $uri) {
            $outbound = $this->outbound($uri, self::NODE_PREFIX . (count($peers) + 1));
            if ($outbound !== null) {
                $peers[] = $outbound;
                $names[] = $this->peerName($uri, $outbound);
            }
        }
        $count = count($peers);
        $profile = [
            'remarks' => $name,
            'log' => ['access' => 'none', 'loglevel' => 'warning'],
            'inbounds' => [[
                'tag' => 'vpn-socks',
                'listen' => '127.0.0.1',
                'port' => 10808,
                'protocol' => 'socks',
                'settings' => ['auth' => 'noauth', 'udp' => true],
            ]],
            'outbounds' => [['tag' => self::BLOCK, 'protocol' => 'blackhole']],
            'routing' => ['domainStrategy' => 'AsIs', 'rules' => []],
        ];
        if ($count === 0) {
            return ['profile' => $profile, 'manual' => [], 'count' => 0, 'skipped' => count($uris)];
        }

        $selector = [self::NODE_PREFIX];
        $leastLoad = $settings['strategy'] === 'leastLoad';
        $profile['dns'] = ['tag' => self::DNS, 'servers' => ['https://1.1.1.1/dns-query']];
        $block = $profile['outbounds'][0];
        $dns = ['tag' => self::DNS_OUTBOUND, 'protocol' => 'dns'];
        $manual = [];
        $nameCounts = array_count_values($names);
        foreach ($peers as $index => $peer) {
            $entry = $profile;
            $entry['remarks'] = $nameCounts[$names[$index]] > 1 || $names[$index] === $name
                ? $names[$index] . ' · #' . ($index + 1) : $names[$index];
            $entry['outbounds'] = [$block, $peer, $dns];
            $entry['routing']['rules'] = $this->routingRules(['outboundTag' => $peer['tag']]);
            $manual[] = $entry;
        }
        $profile['outbounds'] = [$block, ...$peers, $dns];
        $profile['routing']['rules'] = $this->routingRules(['balancerTag' => self::BALANCER]);
        $profile['routing']['balancers'] = [[
            'tag' => self::BALANCER,
            'selector' => $selector,
            'fallbackTag' => $settings['fallback'] === 'first' ? self::NODE_PREFIX . '1' : self::BLOCK,
            'strategy' => $leastLoad
                ? ['type' => 'leastLoad', 'settings' => ['expected' => 1, 'tolerance' => 0.34]]
                : ['type' => 'leastPing'],
        ]];
        if ($leastLoad) {
            $profile['burstObservatory'] = [
                'subjectSelector' => $selector,
                'pingConfig' => [
                    'destination' => $settings['probe_url'],
                    'connectivity' => '',
                    'interval' => $settings['interval'] . 's',
                    'timeout' => $settings['timeout'] . 's',
                    'sampling' => 3,
                    'httpMethod' => 'GET',
                ],
            ];
        } else {
            $profile['observatory'] = [
                'subjectSelector' => $selector,
                'probeUrl' => $settings['probe_url'],
                'probeInterval' => $settings['interval'] . 's',
                'enableConcurrency' => true,
            ];
        }
        return ['profile' => $profile, 'manual' => $manual, 'count' => $count, 'skipped' => count($uris) - $count];
    }

    private function routingRules(array $target): array
    {
        return [
            ['type' => 'field', 'inboundTag' => [self::DNS]] + $target,
            ['type' => 'field', 'inboundTag' => ['vpn-socks'], 'network' => 'tcp,udp', 'port' => '53', 'outboundTag' => self::DNS_OUTBOUND],
            ['type' => 'field', 'inboundTag' => ['vpn-socks'], 'network' => 'tcp,udp'] + $target,
        ];
    }

    private function peerName(string $uri, array $outbound): string
    {
        $fragment = (string)parse_url($uri, PHP_URL_FRAGMENT);
        $label = rawurldecode(explode('?serverDescription=', $fragment, 2)[0]);
        $label = trim(preg_replace('/[[:cntrl:]]/u', '', $label) ?? '');
        $peer = $outbound['settings']['vnext'][0];

        return $label !== '' ? mb_substr($label, 0, 120) : $peer['address'] . ':' . $peer['port'];
    }

    private function outbound(string $uri, string $tag): ?array
    {
        if (!str_starts_with(strtolower($uri), 'vless://')) {
            return null;
        }
        $parts = parse_url($uri);
        if ($parts === false || isset($parts['pass'])) {
            return null;
        }
        $query = (string)($parts['query'] ?? '');
        if (substr_count($query, '&') > 20) {
            return null;
        }
        parse_str($query, $params);
        $supported = ['security', 'type', 'encryption', 'flow', 'sni', 'fp', 'pbk', 'sid', 'spx', 'pqv', 'headerType'];
        if (array_diff(array_keys($params), $supported) !== [] || array_filter($params, 'is_array') !== []
            || ($params['security'] ?? '') !== 'reality'
            || !in_array($params['type'] ?? 'tcp', ['tcp', 'raw'], true)
            || ($params['encryption'] ?? 'none') !== 'none'
            || ($params['headerType'] ?? 'none') !== 'none'
            || !in_array($params['flow'] ?? '', ['', 'xtls-rprx-vision'], true)
            || preg_match('/\A[A-Za-z0-9_-]{43}\z/D', $params['pbk'] ?? '') !== 1
            || preg_match('/\A(?:[0-9a-fA-F]{2}){0,8}\z/D', $params['sid'] ?? '') !== 1
            || (($params['pqv'] ?? '') !== '' && preg_match('/\A[A-Za-z0-9_-]{2603}\z/D', $params['pqv']) !== 1)
            || (($params['spx'] ?? '') !== '' && (preg_match('~\A/(?!/)[^\x00-\x20\x7f]*\z~D', $params['spx']) !== 1
                || preg_match('/%(?![0-9a-f]{2})/i', $params['spx']) === 1))
            || filter_var($params['sni'] ?? '', FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false
            || !in_array($params['fp'] ?? 'chrome', ['chrome', 'firefox', 'safari', 'ios', 'android',
                'edge', '360', 'qq', 'random', 'randomized'], true)) {
            return null;
        }
        try {
            (new VpnConfigValidator())->validateUri($uri);
        } catch (VpnConfigValidationException) {
            return null;
        }
        $user = ['id' => rawurldecode($parts['user']), 'encryption' => 'none'];
        if (!empty($params['flow'])) {
            $user['flow'] = $params['flow'];
        }
        $reality = [
            'serverName' => $params['sni'],
            'fingerprint' => $params['fp'] ?? 'chrome',
            'publicKey' => $params['pbk'], // Xray also accepts this alias on current cores.
            'shortId' => $params['sid'] ?? '',
        ];
        foreach (['spx' => 'spiderX', 'pqv' => 'mldsa65Verify'] as $uriKey => $configKey) {
            if (isset($params[$uriKey])) {
                $reality[$configKey] = $params[$uriKey];
            }
        }
        return [
            'tag' => $tag,
            'protocol' => 'vless',
            'settings' => ['vnext' => [[
                'address' => trim($parts['host'], '[]'),
                'port' => $parts['port'],
                'users' => [$user],
            ]]],
            'streamSettings' => ['network' => 'tcp', 'security' => 'reality', 'realitySettings' => $reality],
        ];
    }
}
