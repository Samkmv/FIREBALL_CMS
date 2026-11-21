<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\Exceptions\VpnConfigValidationException;

final class XrayAutoConfigService
{
    private const NODE_PREFIX = 'vpn-node-';
    private const BLOCK = 'vpn-block';
    private const BALANCER = 'vpn-auto';
    private const DNS = 'vpn-dns';

    /** Each array entry is a complete independently runnable Xray profile. */
    public function buildProfiles(array $uris, array $settings, string $name, array $manualUris = []): array
    {
        $result = $this->build($uris, $settings, $name);
        $result['profiles'] = [$result['profile']];
        $result['manual_count'] = 0;
        $result['manual_skipped'] = 0;
        $names = [$name => true];
        if (empty($settings['manual_servers'])) return $result;
        foreach (array_values(array_unique($manualUris)) as $index => $uri) {
            $label = rawurldecode((string)(parse_url($uri, PHP_URL_FRAGMENT) ?? ''));
            $label = trim(preg_replace('/[\x00-\x1f\x7f]/u', '', $label) ?? '');
            if ($label === '') $label = 'VPN #' . ($index + 1);
            $single = $this->build([$uri], $settings, mb_substr($label, 0, 160));
            if ($single['count'] !== 1) {
                $result['manual_skipped']++;
                continue;
            }
            $profile = $single['profile'];
            if (isset($names[$profile['remarks']])) {
                $address = (string)parse_url($uri, PHP_URL_HOST) . ':' . (int)parse_url($uri, PHP_URL_PORT);
                $profile['remarks'] = mb_substr($profile['remarks'], 0, 100) . ' · ' . $address;
                $base = $profile['remarks'];
                for ($suffix = 2; isset($names[$profile['remarks']]); $suffix++) {
                    $profile['remarks'] = $base . ' #' . $suffix;
                }
            }
            $names[$profile['remarks']] = true;
            // The primary outbound is the selected server, not a placeholder.
            // One-node selector/observatory can never switch to another server.
            $primary = array_values(array_filter($profile['outbounds'], static fn(array $o): bool => $o['protocol'] === 'vless'))[0];
            $profile['outbounds'] = array_merge([$primary], array_values(array_filter(
                $profile['outbounds'], static fn(array $o): bool => $o['tag'] !== $primary['tag']
            )));
            $result['profiles'][] = $profile;
            $result['manual_count']++;
        }
        return $result;
    }

    public function subscriptionUris(array $subscription, array $nodes, array $settings, VpnSubscriptionBuilder $builder, bool $allowExternal): array
    {
        $nodes = array_filter($nodes, static fn(array $node): bool => empty($node['maintenance_mode'])
            && ($settings['servers'] === [] || in_array((int)$node['server_id'], $settings['servers'], true)));
        return $builder->build($subscription, $nodes, includeExternal: $allowExternal && $settings['include_external']);
    }

    /** @param list<string> $uris Already authorized and filtered subscription entries. */
    public function build(array $uris, array $settings, string $name): array
    {
        $outbounds = [['tag' => self::BLOCK, 'protocol' => 'blackhole']];
        $count = 0;
        foreach ($uris as $uri) {
            $outbound = $this->outbound($uri, self::NODE_PREFIX . ($count + 1));
            if ($outbound !== null && ($settings['routing'] ?? 'none') === 'roscomvpn'
                && filter_var($outbound['settings']['vnext'][0]['address'], FILTER_VALIDATE_IP) === false) {
                $outbound = null; // A domain endpoint would require DNS before VPN bootstrap.
            }
            if ($outbound !== null) {
                $outbounds[] = $outbound;
                $count++;
            }
        }
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
            'outbounds' => $outbounds,
            'routing' => ['domainStrategy' => 'AsIs', 'rules' => []],
        ];
        if ($count === 0) {
            return ['profile' => $profile, 'count' => 0, 'skipped' => count($uris)];
        }

        $selector = [self::NODE_PREFIX];
        $leastLoad = $settings['strategy'] === 'leastLoad';
        $profile['dns'] = ['tag' => self::DNS, 'servers' => ['https://1.1.1.1/dns-query']];
        $profile['outbounds'][] = ['tag' => 'vpn-dns-out', 'protocol' => 'dns'];
        $profile['routing']['rules'] = [
            ['type' => 'field', 'inboundTag' => [self::DNS], 'balancerTag' => self::BALANCER],
            ['type' => 'field', 'inboundTag' => ['vpn-socks'], 'network' => 'tcp,udp', 'port' => '53', 'outboundTag' => 'vpn-dns-out'],
            ['type' => 'field', 'inboundTag' => ['vpn-socks'], 'network' => 'tcp,udp', 'balancerTag' => self::BALANCER],
        ];
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
        if (($settings['routing'] ?? 'none') === 'roscomvpn') {
            $profile = (new RoscomVpnRouting())->apply($profile, $settings);
        }
        return ['profile' => $profile, 'count' => $count, 'skipped' => count($uris) - $count];
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
