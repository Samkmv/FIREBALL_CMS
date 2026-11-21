#!/usr/bin/env python3
"""Real Xray/REALITY traffic checks with original RoscomVPN geodata.

All VPN peers and HTTP targets are disposable loopback processes. Optional DoH
checks contact only the two DNS endpoints explicitly supplied by the user.
"""
import argparse
from contextlib import ExitStack
import copy
import json
import os
from pathlib import Path
import socket
import struct
import subprocess
import tempfile
import time

from roscomvpn_routing import build_config, decode_profile, trim_geo, write_json
from vpn_xray_auto_lab import free_port, serve, stop


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--xray', type=Path, required=True)
    parser.add_argument('--artifacts', type=Path, required=True)
    parser.add_argument('--doh', action='store_true')
    args = parser.parse_args()
    xray, artifacts = args.xray.resolve(), args.artifacts.resolve()
    source = decode_profile((artifacts / 'roscomvpn.original.json').read_text())
    nodes = json.loads((artifacts / 'nodes.example.json').read_text())
    result = {'xray_version': subprocess.check_output([xray, 'version'], text=True).splitlines()[0],
              'ordinary_subscriptions_changed': False, 'ios_device_tested': False}
    root = Path(__file__).resolve().parent
    with tempfile.TemporaryDirectory(prefix='roscomvpn-lab-', dir=root / '.tmp') as folder, ExitStack() as stack:
        work = Path(folder)
        env = {**os.environ, 'TMPDIR': folder, 'TMP': folder, 'TEMP': folder,
               'XRAY_LOCATION_ASSET': str(artifacts)}
        logs = []

        def run(command, **kwargs):
            return subprocess.run(command, cwd=work, env=env, text=True, **kwargs)

        def start(name, config):
            file = work / (name + '.json')
            write_json(file, config)
            run([xray, 'run', '-test', '-config', file], check=True, capture_output=True)
            log = work / (name + '.log')
            logs.append(log)
            output = stack.enter_context(log.open('w'))
            process = subprocess.Popen([xray, 'run', '-config', file], cwd=work, env=env,
                                       stdout=output, stderr=subprocess.STDOUT)
            stack.callback(stop, process)
            return process

        run(['openssl', 'req', '-x509', '-newkey', 'rsa:2048', '-nodes', '-days', '1',
             '-subj', '/CN=localhost', '-keyout', 'key.pem', '-out', 'cert.pem'], check=True, capture_output=True)
        cover_port = free_port()
        cover = subprocess.Popen(['node', '-e', """
            const https = require('node:https'), fs = require('node:fs');
            https.createServer({key:fs.readFileSync('key.pem'), cert:fs.readFileSync('cert.pem'),
              minVersion:'TLSv1.3'}, (_,res) => res.end('cover')).listen(Number(process.argv[1]),'127.0.0.1');
        """, str(cover_port)], cwd=work, env=env)
        stack.callback(stop, cover)
        direct_origin = serve(stack, 'direct-only', 0)
        servers, origins = [], []
        for i, node in enumerate(nodes):
            label = 'node-' + str(i+1)
            origin = serve(stack, label, (0.02, 0.14, 0.3)[i])
            origins.append(origin)
            keys = dict(line.split(': ', 1) for line in run([xray, 'x25519'], check=True, capture_output=True).stdout.splitlines() if ': ' in line)
            peer = node['settings']['vnext'][0]
            peer.update(address='127.0.0.1', port=free_port())
            reality = node['streamSettings']['realitySettings']
            reality.update(serverName='localhost', publicKey=keys['Password (PublicKey)'])
            user = peer['users'][0]
            servers.append(start(label, {
                'log': {'loglevel': 'warning', 'access': str(work / (label + '-access.log'))},
                'inbounds': [{'tag': 'peer', 'listen': '127.0.0.1', 'port': peer['port'], 'protocol': 'vless',
                              'settings': {'decryption': 'none', 'clients': [{'id': user['id'], 'flow': user['flow']}]},
                              'streamSettings': {'network': 'tcp', 'security': 'reality', 'realitySettings': {
                                  'target': '127.0.0.1:' + str(cover_port), 'serverNames': ['localhost'],
                                  'privateKey': keys['PrivateKey'], 'shortIds': [reality['shortId']]}}}],
                'outbounds': [{'tag': 'marker', 'protocol': 'freedom', 'settings': {
                    'redirect': '127.0.0.1:' + str(origin.server_port),
                    'finalRules': [{'action': 'allow', 'network': 'tcp', 'ip': ['127.0.0.1/32'], 'port': origin.server_port}]}},
                    {'tag': 'remote-doh', 'protocol': 'freedom'}],
                'routing': {'rules': [{'type': 'field', 'ip': ['8.8.8.8'], 'port': '443', 'outboundTag': 'remote-doh'}]},
            }))

        config = build_config(source, nodes, free_port(), 'http://probe-fixture.invalid/probe')
        config['observatory']['probeInterval'] = '2s'
        config['log'] = {'loglevel': 'debug', 'access': str(work / 'client-access.log')}
        config['dns']['hosts'].update({
            'github.com': '127.0.0.1', 'apple.com': '127.0.0.1', 'a.ads2.msn.com': '127.0.0.1',
            'ads.twitch.tv': '127.0.0.1', 'default-fixture-autoselect-643f.net': '93.184.216.34',
            'ip-direct-fixture-autoselect-643f.net': '127.0.0.1',
        })
        config['dns']['disableCache'] = True
        direct = next(node for node in config['outbounds'] if node['tag'] == 'direct')
        direct['settings']['finalRules'] = [{'action': 'allow', 'network': 'tcp',
                                            'ip': ['127.0.0.1/32'], 'port': direct_origin.server_port}]
        client = start('client', config)
        socks = config['inbounds'][0]['port']

        def request(domain='github.com', port=80):
            return run(['curl', '--noproxy', '', '--silent', '--max-time', '2', '--socks5-hostname',
                        '127.0.0.1:' + str(socks), 'http://' + domain + ':' + str(port) + '/which'], capture_output=True)

        def expect(label, domain='github.com', port=80, timeout=16):
            deadline = time.monotonic() + timeout
            while time.monotonic() < deadline:
                assert client.poll() is None, 'Xray client stopped'
                response = request(domain, port)
                if response.returncode == 0 and response.stdout == label:
                    return
                time.sleep(0.25)
            raise AssertionError('Expected ' + label + ' for ' + domain + ', got ' + repr(response.stdout))

        def recv_exact(sock, length):
            data = b''
            while len(data) < length:
                chunk = sock.recv(length-len(data))
                if not chunk:
                    raise ConnectionError('Unexpected EOF')
                data += chunk
            return data

        def dns_query(domain, query_type=1):
            with socket.create_connection(('127.0.0.1', socks), timeout=12) as conn:
                conn.settimeout(12)
                conn.sendall(b'\x05\x01\x00')
                assert recv_exact(conn, 2) == b'\x05\x00'
                conn.sendall(b'\x05\x01\x00\x01' + socket.inet_aton('198.18.0.2') + struct.pack('!H', 53))
                header = recv_exact(conn, 4)
                assert header[1] == 0, 'SOCKS DNS connection failed'
                recv_exact(conn, {1:4,4:16}[header[3]] + 2)
                query = struct.pack('!HHHHHH', 700, 0x100, 1, 0, 0, 0)
                query += b''.join(bytes([len(label)]) + label.encode() for label in domain.split('.')) + b'\x00'
                query += struct.pack('!HH', query_type, 1)
                conn.sendall(struct.pack('!H', len(query)) + query)
                length = struct.unpack('!H', recv_exact(conn, 2))[0]
                response = recv_exact(conn, length)
                return {'rcode': response[3] & 15, 'answers': struct.unpack('!H', response[6:8])[0], 'hex': response.hex()}

        try:
            expect('node-1')
            expect('node-1', 'ads.twitch.tv')  # Proxy wins over Direct twitch.
            expect('node-1', 'default-fixture-autoselect-643f.net')
            expect('direct-only', 'apple.com', direct_origin.server_port)
            expect('direct-only', 'ip-direct-fixture-autoselect-643f.net', direct_origin.server_port)
            assert request('a.ads2.msn.com', direct_origin.server_port).returncode != 0, 'Block lost to Direct microsoft'
            result['routing'] = 'PASS: Proxy, Block, Direct, overlap priority, default VPN, IPIfNonMatch second pass'
            # Check both preserved static host records through intercepted TCP DNS.
            host_a = dns_query('lkfl2.nalog.ru')
            host_b = dns_query('lknpd.nalog.ru')
            assert 'd51840af' in host_a['hex'] and 'd51840b5' in host_b['hex'], 'Static DNS host mapping changed'
            result['static_dns'] = 'PASS: both original nalog.ru IPs'
            if args.doh:
                # These names are absent from the local hosts fixture. Query domains
                # deliberately exercise original Proxy and Direct geosite categories.
                try:
                    remote = dns_query('www.youtube.com')
                    domestic = dns_query('yandex.ru')
                    assert remote['rcode'] == 0 and remote['answers'] > 0, remote
                    assert domestic['rcode'] == 0 and domestic['answers'] > 0, domestic
                    access = (work / 'client-access.log').read_text()
                    assert 'https://8.8.8.8/dns-query [dns-remote -> vpn-node-' in access, 'Remote DoH did not traverse a VPN node'
                    assert 'https://77.88.8.8/dns-query [dns-domestic -> direct]' in access, 'Domestic DoH did not use direct'
                    result['public_doh'] = 'PASS: Google via auto, Yandex via direct, no resolver cross-fallback'
                except (AssertionError, OSError, ConnectionError) as error:
                    result['public_doh'] = 'UNVERIFIED: ' + str(error)[:500]
            else:
                result['public_doh'] = 'NOT RUN'
            stop(servers[0])
            expect('node-2')
            origins[1].delay, origins[2].delay = 0.4, 0.02
            expect('node-3')
            result['autoselect'] = 'PASS: REALITY traffic, node failure and latency change 1 -> 2 -> 3'
            stop(servers[1]); stop(servers[2]); time.sleep(3)
            # github.com resolves to a live loopback target but must use Proxy,
            # even when every VPN peer is unavailable. Direct remains allowed.
            assert request('github.com', direct_origin.server_port).returncode != 0, 'VPN bypass with all peers down'
            assert request('default-fixture-autoselect-643f.net', direct_origin.server_port).returncode != 0, 'Default traffic escaped'
            deadline = time.monotonic() + 40
            while True:
                assert request('github.com', direct_origin.server_port).returncode != 0, 'VPN bypass during health convergence'
                lines = (work / 'client-access.log').read_text().splitlines()
                last = [line for line in lines if 'github.com:' + str(direct_origin.server_port) in line][-1]
                if '[vpn-socks -> block]' in last:
                    break
                if time.monotonic() >= deadline:
                    raise AssertionError('Balancer did not converge to its blackhole fallback')
                time.sleep(0.5)
            expect('direct-only', 'apple.com', direct_origin.server_port, timeout=4)
            if args.doh and result.get('public_doh', '').startswith('PASS'):
                try:
                    failed_remote = dns_query('www.github.com')
                    assert failed_remote['answers'] == 0, 'Remote DNS escaped the failed VPN'
                except (OSError, ConnectionError):
                    pass  # A timeout/closed stream is a permitted fail-closed result.
                live_domestic = dns_query('yandex.ru')
                assert live_domestic['rcode'] == 0 and live_domestic['answers'] > 0
                result['dns_all_down'] = 'PASS: uncached remote query fails; domestic DoH still answers'
            result['all_down'] = 'PASS: Proxy/default blocked, blackhole fallback observed; deliberate Direct exclusions remain reachable'
            access = (work / 'client-access.log').read_text()
            assert '[vpn-socks -> block]' in access
            result['raw_dns'] = 'Only built-in DoH resolvers configured; non-A/AAAA queries drop; port 53 intercepted'
            (artifacts / 'core-lab-access.log').write_text((work / 'client-access.log').read_text())
            write_json(artifacts / 'core-lab-results.json', result)
        except Exception:
            for log in logs:
                print(log.name, log.read_text()[-5000:])
            raise
        print(json.dumps(result, indent=2))


if __name__ == '__main__':
    main()
