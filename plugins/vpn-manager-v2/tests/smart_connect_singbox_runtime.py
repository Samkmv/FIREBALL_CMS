"""Real sing-box urltest with local VLESS nodes; no customer servers or TUN permissions.
Run: VPN_TEST_SINGBOX=/path/to/sing-box python3 tests/smart_connect_singbox_runtime.py
Only the test harness replaces TUN with loopback mixed input and HTTPS probe with local HTTP.
"""
import http.client
import http.server
import json
import os
import pathlib
import socket
import subprocess
import tempfile
import threading
import time
import urllib.request

binary = os.environ['VPN_TEST_SINGBOX']
php = os.environ.get('VPN_TEST_PHP', '/Applications/MAMP/bin/php/php8.2.0/bin/php')
root = pathlib.Path(__file__).resolve().parent

def free_port():
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        return sock.getsockname()[1]

class Origin(http.server.BaseHTTPRequestHandler):
    def do_GET(self):
        self.send_response(204)
        self.end_headers()
    def do_HEAD(self):
        self.do_GET()
    def log_message(self, *args):
        pass

ports = []
while len(ports) < 5:
    port = free_port()
    if port not in ports:
        ports.append(port)
a_port, b_port, proxy_port, api_port, origin_port = ports
origin = http.server.ThreadingHTTPServer(('127.0.0.1', origin_port), Origin)
threading.Thread(target=origin.serve_forever, daemon=True).start()
processes = {}
cases = []
with tempfile.TemporaryDirectory(prefix='vpn-smart-engine-') as tmp:
    work = pathlib.Path(tmp)
    logs = {}

    def start(name, config):
        path = work / (name + '.json')
        path.write_text(json.dumps(config))
        checked = subprocess.run([binary, 'check', '-c', str(path)], capture_output=True, text=True)
        assert checked.returncode == 0, checked.stderr
        logs[name] = open(work / (name + '.log'), 'w')
        processes[name] = subprocess.Popen([binary, 'run', '-c', str(path)], stdout=logs[name], stderr=logs[name])

    def stop(name):
        process = processes.pop(name, None)
        if process:
            process.terminate()
            try:
                process.wait(timeout=5)
            except subprocess.TimeoutExpired:
                process.kill()
                process.wait(timeout=5)
        if name in logs:
            logs.pop(name).close()

    def server(port):
        return {'log': {'disabled': True}, 'inbounds': [{'type': 'vless', 'listen': '127.0.0.1',
            'listen_port': port, 'users': [{'uuid': '81111111-1111-4111-8111-111111111111'}]}],
            'outbounds': [{'type': 'direct', 'tag': 'direct'}], 'route': {'final': 'direct'}}

    def request():
        connection = http.client.HTTPConnection('127.0.0.1', proxy_port, timeout=2)
        try:
            connection.request('GET', 'http://127.0.0.1:' + str(origin_port) + '/test')
            response = connection.getresponse()
            response.read()
            return response.status == 204
        except (OSError, http.client.HTTPException):
            return False
        finally:
            connection.close()

    def selected():
        try:
            with urllib.request.urlopen('http://127.0.0.1:' + str(api_port) + '/proxies/auto', timeout=2) as response:
                return json.load(response)['now']
        except (OSError, ValueError, KeyError):
            return ''

    def wait_for(predicate, timeout=45):
        deadline = time.monotonic() + timeout
        while time.monotonic() < deadline:
            if predicate():
                return
            # Keep URLTest active (its ticker is started by use).
            request()
            time.sleep(0.5)
        raise AssertionError('Timed out waiting for engine selection/recovery')

    try:
        profile = json.loads(subprocess.check_output([php, str(root / 'smart_connect_singbox_fixture.php'), str(a_port), str(b_port)], text=True))
        tags = profile['outbounds'][0]['outbounds']
        profile['inbounds'] = [{'type': 'mixed', 'listen': '127.0.0.1', 'listen_port': proxy_port}]
        profile['dns'] = {'servers': [{'type': 'local', 'tag': 'bootstrap'}]}
        profile['outbounds'][0]['url'] = 'http://127.0.0.1:' + str(origin_port) + '/probe'
        profile['experimental'] = {'clash_api': {'external_controller': '127.0.0.1:' + str(api_port)}}
        start('a', server(a_port))
        start('client', profile)
        wait_for(lambda: selected() == tags[0] and request(), 15)
        cases.append('unavailable_node_not_selected')
        print('selected_first_healthy', flush=True)
        start('b', server(b_port))
        # Allow the documented 30s interval to discover B; 100ms tolerance keeps A.
        deadline = time.monotonic() + 34
        while time.monotonic() < deadline:
            assert request(), 'Healthy selected tunnel unexpectedly failed'
            assert selected() == tags[0], 'Flapped between similarly fast nodes'
            time.sleep(0.5)
        cases.append('tolerance_prevents_flapping')
        stop('a')
        wait_for(lambda: selected() == tags[1] and request())
        cases.append('live_failure_switches_to_reserve')
        print('switched_to_reserve', flush=True)
        stop('b')
        assert not request(), 'All unavailable nodes must not fall back to direct'
        cases.append('all_nodes_down_no_direct_leak')
        start('a', server(a_port))
        wait_for(lambda: selected() == tags[0] and request())
        cases.append('recovered_node_selected_again')
        print(json.dumps({'status': 'ok', 'cases': cases, 'engine': 'sing-box', 'mobile_verified': False}), flush=True)
    finally:
        for name in list(processes):
            stop(name)
        origin.shutdown()
