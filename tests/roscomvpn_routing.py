#!/usr/bin/env python3
"""Experimental RoscomVPN compiler. Never imported by CMS/public subscription code.

Inputs are local, explicitly supplied files. This tool does not fetch URLs, apply
configs to servers, or read CMS credentials. Geo entries are copied byte-for-byte.
"""
import argparse
import base64
import copy
import hashlib
import ipaddress
import json
import os
from pathlib import Path
import re


def decode_profile(text):
    text = text.strip()
    if not text.startswith('{'):
        match = re.fullmatch(r'(?:happ|incy)://routing/(?:onadd|add)/([A-Za-z0-9+/]+={0,2})', text)
        if not match:
            raise ValueError('Expected JSON or a Happ/Incy routing link')
        text = base64.b64decode(match[1], validate=True).decode('utf-8')
    profile = json.loads(text)
    if not isinstance(profile, dict) or not isinstance(profile.get('Name'), str):
        raise ValueError('Missing profile Name')
    if profile.get('RouteOrder') != 'block-proxy-direct' or profile.get('GlobalProxy') not in [True, 'true']:
        raise ValueError('This experiment requires block-proxy-direct and GlobalProxy=true')
    if profile.get('FakeDNS') not in [False, 'false'] or profile.get('DomainStrategy') != 'IPIfNonMatch':
        raise ValueError('This experiment requires FakeDNS=false and IPIfNonMatch')
    for group in ('Block', 'Proxy', 'Direct'):
        for suffix in ('Sites', 'Ip'):
            rules = profile.get(group + suffix, [])
            if not isinstance(rules, list) or any(not isinstance(r, str) or not r or '\n' in r or '\r' in r for r in rules):
                raise ValueError('Invalid routing rules')
    for key in ('Geositeurl', 'Geoipurl'):
        if not isinstance(profile.get(key), str) or not profile[key].startswith('https://'):
            raise ValueError('Original HTTPS Geo URLs are required')
    return profile


def incy_profile(profile):
    result = copy.deepcopy(profile)
    if 'UseChunkFiles' in result:
        result['useChunkFiles'] = result.pop('UseChunkFiles') in (True, 'true')
    return result


def routing_link(profile, client):
    payload = incy_profile(profile) if client == 'incy' else profile
    return client + '://routing/onadd/' + base64.b64encode(
        json.dumps(payload, ensure_ascii=False, separators=(',', ':')).encode()).decode()


def varint(data, offset):
    value = 0
    for shift in range(0, 70, 7):
        if offset >= len(data):
            raise ValueError('Truncated protobuf')
        byte = data[offset]
        offset += 1
        value |= (byte & 127) << shift
        if byte < 128:
            return value, offset
    raise ValueError('Oversized protobuf varint')


def fields(data):
    offset = 0
    while offset < len(data):
        start = offset
        key, offset = varint(data, offset)
        number, wire = key >> 3, key & 7
        if number == 0:
            raise ValueError('Invalid protobuf field')
        if wire == 0:
            value, offset = varint(data, offset)
        elif wire == 2:
            size, offset = varint(data, offset)
            end = offset + size
            value = data[offset:end]
            offset = end
        elif wire in (1, 5):
            size = 8 if wire == 1 else 4
            value = data[offset:offset + size]
            offset += size
        else:
            raise ValueError('Unsupported protobuf wire type')
        if offset > len(data):
            raise ValueError('Truncated protobuf field')
        yield number, wire, value, data[start:offset]


def geo_categories(data):
    categories = {}
    for number, wire, value, raw in fields(data):
        if number != 1 or wire != 2:
            raise ValueError('Unexpected Geo list layout; refusing to trim')
        names = [v.decode('utf-8').lower() for n, w, v, _ in fields(value) if n == 1 and w == 2]
        if len(names) != 1 or names[0] in categories:
            raise ValueError('Invalid or duplicated Geo category')
        categories[names[0]] = raw
    return categories


def required_categories(profile, kind):
    suffix = 'Sites' if kind == 'geosite' else 'Ip'
    result = set()
    for group in ('Block', 'Proxy', 'Direct'):
        for rule in profile.get(group + suffix, []):
            if rule.startswith(kind + ':'):
                category = rule.split(':', 1)[1].lower()
                if '@' in category:
                    raise ValueError('Attribute-filtered Geo categories need a separate identity check')
                result.add(category)
            elif rule.startswith('ext:'):
                raise ValueError('External Geo paths need explicitly supplied files')
    return result


def trim_geo(profile, kind, source):
    categories = geo_categories(source)
    needed = required_categories(profile, kind)
    missing = needed - categories.keys()
    if missing:
        raise ValueError('Missing ' + kind + ' categories: ' + ', '.join(sorted(missing)))
    trimmed = b''.join(categories[name] for name in sorted(needed))
    return trimmed, {
        'source_url': profile['Geositeurl' if kind == 'geosite' else 'Geoipurl'],
        'original_bytes': len(source), 'trimmed_bytes': len(trimmed),
        'original_sha256': hashlib.sha256(source).hexdigest(),
        'trimmed_sha256': hashlib.sha256(trimmed).hexdigest(),
        'categories': sorted(needed),
        'byte_identical_entries': True,
    }


def build_config(profile, nodes, socks_port=10808, probe_url='https://www.google.com/generate_204'):
    if not isinstance(nodes, list) or len(nodes) != 3:
        raise ValueError('Exactly three authorized VPN outbounds are required')
    nodes = copy.deepcopy(nodes)
    for i, node in enumerate(nodes, 1):
        if node.get('protocol') != 'vless':
            raise ValueError('This experiment accepts explicit VLESS outbounds only')
        # Resolve/bootstrap the VPN endpoint outside this resolver graph. IP
        # endpoints cannot recurse through the VPN DNS they are establishing.
        endpoints = node.get('settings', {}).get('vnext', [])
        if len(endpoints) != 1:
            raise ValueError('Exactly one VLESS endpoint is required per outbound')
        ipaddress.ip_address(endpoints[0]['address'])
        node['tag'] = 'vpn-node-' + str(i)
    for prefix in ('Remote', 'Domestic'):
        if profile.get(prefix + 'DNSType') != 'DoH':
            raise ValueError('Only the supplied DoH profile is supported')
        ipaddress.ip_address(profile[prefix + 'DNSIP'])
        if profile[prefix + 'DNSDomain'] != 'https://' + profile[prefix + 'DNSIP'] + '/dns-query':
            raise ValueError('DoH endpoint must use the supplied IP to avoid bootstrap recursion')

    def rule(target, **conditions):
        return {'type': 'field', **conditions, **target}
    auto = {'balancerTag': 'auto'}
    direct = {'outboundTag': 'direct'}
    blocked = {'outboundTag': 'block'}
    rules = [
        rule(direct, inboundTag=['dns-domestic'], ip=[profile['DomesticDNSIP']], port='443', network='tcp'),
        rule(auto, inboundTag=['dns-remote'], ip=[profile['RemoteDNSIP']], port='443', network='tcp'),
        # Unrecognized internally generated DNS destinations must never escape.
        rule(blocked, inboundTag=['dns-domestic', 'dns-remote']),
        rule({'outboundTag': 'dns-out'}, inboundTag=['vpn-socks'], network='tcp,udp', port='53'),
    ]
    for group, target in [('Block', blocked), ('Proxy', auto), ('Direct', direct)]:
        for suffix, field in [('Sites', 'domain'), ('Ip', 'ip')]:
            if profile.get(group + suffix):
                rules.append(rule(target, **{field: profile[group + suffix]}))
    # A network/domain catch-all would match on the first routing pass, making
    # IPIfNonMatch skip domain resolution and ignore DirectIp for domain traffic.
    # An IP catch-all triggers the second pass; failed resolution hits block,
    # the first outbound. Unmatched resolved traffic always uses the balancer.
    rules.append(rule(auto, ip=['0.0.0.0/0', '::/0']))

    proxy_domains = list(dict.fromkeys(profile.get('BlockSites', []) + profile.get('ProxySites', [])))
    return {
        'remarks': profile['Name'] + ' - Auto Select (LAB)',
        'log': {'loglevel': 'warning'},
        'inbounds': [{'tag': 'vpn-socks', 'listen': '127.0.0.1', 'port': socks_port,
                      'protocol': 'socks', 'settings': {'auth': 'noauth', 'udp': True}}],
        'outbounds': [{'tag': 'block', 'protocol': 'blackhole'}, *nodes,
                      {'tag': 'direct', 'protocol': 'freedom', 'settings': {'domainStrategy': 'UseIP'}},
                      {'tag': 'dns-out', 'protocol': 'dns', 'settings': {'nonIPQuery': 'drop'}}],
        'dns': {'hosts': copy.deepcopy(profile.get('DnsHosts', {})), 'queryStrategy': 'UseIP',
                'disableFallbackIfMatch': True,
                'servers': [
                    {'address': profile['RemoteDNSDomain'], 'tag': 'dns-remote', 'domains': proxy_domains, 'finalQuery': True},
                    {'address': profile['DomesticDNSDomain'], 'tag': 'dns-domestic',
                     'domains': profile.get('DirectSites', []), 'skipFallback': True},
                ]},
        'routing': {'domainStrategy': profile['DomainStrategy'], 'rules': rules,
                    'balancers': [{'tag': 'auto', 'selector': ['vpn-node-'], 'fallbackTag': 'block',
                                   'strategy': {'type': 'leastPing'}}]},
        'observatory': {'subjectSelector': ['vpn-node-'], 'probeUrl': probe_url,
                        'probeInterval': '10s', 'enableConcurrency': True},
    }


def extract_nodes(data):
    """Accept an outbound list or one existing, authorized CMS Xray export."""
    if isinstance(data, list) and len(data) == 1 and isinstance(data[0], dict) and 'outbounds' in data[0]:
        data = data[0]
    if isinstance(data, dict) and 'outbounds' in data:
        data = [node for node in data['outbounds'] if node.get('protocol') == 'vless'
                and node.get('tag', '').startswith('vpn-node-')]
    return data


def write_json(path, data):
    path.write_text(json.dumps(data, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--profile', type=Path, required=True)
    parser.add_argument('--nodes', type=Path, required=True, help='Local JSON list of three authorized VLESS outbounds')
    parser.add_argument('--geosite', type=Path, required=True)
    parser.add_argument('--geoip', type=Path, required=True)
    parser.add_argument('--output', type=Path, required=True, help='Private directory under tests/.tmp or tests/artifacts')
    args = parser.parse_args()
    root = Path(__file__).resolve().parent
    output = args.output.resolve()
    if not any(parent in output.parents for parent in (root / '.tmp', root / 'artifacts')):
        parser.error('Output must be a task subdirectory of tests/.tmp or tests/artifacts')
    profile = decode_profile(args.profile.read_text(encoding='utf-8'))
    config = build_config(profile, extract_nodes(json.loads(args.nodes.read_text(encoding='utf-8'))))
    geodata = {}
    trimmed_files = {}
    for kind in ('geosite', 'geoip'):
        trimmed_files[kind], geodata[kind] = trim_geo(profile, kind, getattr(args, kind).read_bytes())
    output.mkdir(parents=True, exist_ok=True, mode=0o700)
    output.chmod(0o700)
    for kind, data in trimmed_files.items():
        (output / (kind + '.dat')).write_bytes(data)
    write_json(output / 'routing.original.json', profile)
    write_json(output / 'routing.incy.json', incy_profile(profile))
    write_json(output / 'xray.json', config)
    os.chmod(output / 'xray.json', 0o600)
    write_json(output / 'geo-verification.json', geodata)
    for client in ('happ', 'incy'):
        (output / (client + '-routing.txt')).write_text(routing_link(profile, client) + '\n', encoding='utf-8')
    print(json.dumps({'status': 'experimental', 'output': str(output), 'geodata': geodata}))


if __name__ == '__main__':
    main()
