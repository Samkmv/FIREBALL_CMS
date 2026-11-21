<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\Support\HappRoutingProfile;

final class RoscomVpnRouting
{
    public function source(): array
    {
        return json_decode(file_get_contents(dirname(__DIR__, 2) . '/resources/roscomvpn-routing.json'), true, 32, JSON_THROW_ON_ERROR);
    }

    public function headers(bool $incy): array
    {
        $source = $this->source();
        // Full JSON loads original assets. Keep profile rules for Geo discovery.
        $source['UseChunkFiles'] = 'false';
        $link = (new HappRoutingProfile())->normalize(json_encode($source, JSON_THROW_ON_ERROR), $incy);
        return $incy ? ['routing' => $link]
            : ['routing' => $link, 'routing-enable' => 'true', 'dns-from-json-enable' => 'true'];
    }

    public function apply(array $profile, array $settings): array
    {
        $source = $this->source();
        $profile['outbounds'][] = ['tag' => 'direct', 'protocol' => 'freedom',
            'settings' => ['domainStrategy' => 'UseIP', 'finalRules' => [[
                'action' => 'block', 'ip' => [$source['RemoteDNSIP']], 'port' => '443',
            ]]]];
        // Even a client that injects a Direct rule for Google DNS cannot leak
        // it through freedom. The supported path remains the auto balancer.
        $auto = ['balancerTag' => 'auto'];
        $direct = ['outboundTag' => 'direct'];
        $block = ['outboundTag' => 'vpn-block'];
        $rule = static fn(array $target, array $conditions): array => ['type' => 'field'] + $conditions + $target;
        $rules = [
            $rule($direct, ['inboundTag' => ['dns-domestic'], 'ip' => [$source['DomesticDNSIP']], 'port' => '443', 'network' => 'tcp']),
            $rule($auto, ['inboundTag' => ['dns-remote'], 'ip' => [$source['RemoteDNSIP']], 'port' => '443', 'network' => 'tcp']),
            $rule($block, ['inboundTag' => ['dns-domestic', 'dns-remote']]),
            $rule(['outboundTag' => 'vpn-dns-out'], ['inboundTag' => ['vpn-socks'], 'network' => 'tcp,udp', 'port' => '53']),
        ];
        foreach (['Block' => $block, 'Proxy' => $auto, 'Direct' => $direct] as $group => $target) {
            foreach (['Sites' => 'domain', 'Ip' => 'ip'] as $suffix => $field) {
                if ($source[$group . $suffix] !== []) $rules[] = $rule($target, [$field => $source[$group . $suffix]]);
            }
        }
        $rules[] = $rule($auto, ['ip' => ['0.0.0.0/0', '::/0']]);
        $profile['routing'] = ['domainStrategy' => 'IPIfNonMatch', 'rules' => $rules, 'balancers' => [[
            'tag' => 'auto', 'selector' => ['vpn-node-'], 'fallbackTag' => 'vpn-block', 'strategy' => ['type' => 'leastPing'],
        ]]];
        $profile['dns'] = ['hosts' => $source['DnsHosts'], 'queryStrategy' => 'UseIP', 'disableFallbackIfMatch' => true, 'servers' => [
            ['address' => $source['RemoteDNSDomain'], 'tag' => 'dns-remote',
                'domains' => array_values(array_unique(array_merge($source['BlockSites'], $source['ProxySites']))), 'finalQuery' => true],
            ['address' => $source['DomesticDNSDomain'], 'tag' => 'dns-domestic', 'domains' => $source['DirectSites'], 'skipFallback' => true],
        ]];
        foreach ($profile['outbounds'] as &$outbound) {
            if ($outbound['protocol'] === 'dns') $outbound['settings'] = ['nonIPQuery' => 'drop'];
        }
        unset($outbound, $profile['burstObservatory']);
        $profile['observatory'] = ['subjectSelector' => ['vpn-node-'], 'probeUrl' => $settings['probe_url'],
            'probeInterval' => $settings['interval'] . 's', 'enableConcurrency' => true];
        return $profile;
    }
}
