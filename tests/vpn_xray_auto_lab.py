"""Test generated Xray profiles with three disposable loopback REALITY peers."""

import http.server
import json
import os
from pathlib import Path
import socket
import subprocess
import sys
import tempfile
import threading
import time
from contextlib import ExitStack


def free_port():
    with socket.socket() as sock:
        sock.bind(("127.0.0.1", 0))
        return sock.getsockname()[1]


class Handler(http.server.BaseHTTPRequestHandler):
    def do_GET(self):
        if self.path == "/probe":
            time.sleep(self.server.delay)
            self.send_response(204)
            self.end_headers()
        else:
            body = self.server.label.encode()
            self.send_response(200)
            self.send_header("Content-Length", str(len(body)))
            self.end_headers()
            self.wfile.write(body)

    def log_message(self, *_):
        pass


def serve(stack, label, delay):
    server = http.server.ThreadingHTTPServer(("127.0.0.1", 0), Handler)
    server.delay, server.label = delay, label
    thread = threading.Thread(target=server.serve_forever, daemon=True)
    thread.start()
    stack.callback(server.server_close)
    stack.callback(thread.join, 2)
    stack.callback(server.shutdown)
    return server


def stop(process):
    if process.poll() is None:
        process.terminate()
        try:
            process.wait(4)
        except subprocess.TimeoutExpired:
            process.kill()
            process.wait()


def main(xray, profile_path):
    root = Path(__file__).resolve().parent / ".tmp"
    root.mkdir(exist_ok=True)
    xray, profile_path = Path(xray).resolve(), Path(profile_path).resolve()
    with tempfile.TemporaryDirectory(prefix="xray-reality-lab-", dir=root) as folder, ExitStack() as stack:
        work = Path(folder)
        env = {**os.environ, "TMPDIR": folder, "TMP": folder, "TEMP": folder}

        def run(args, **options):
            return subprocess.run(args, cwd=work, env=env, text=True, **options)

        logs = []

        def start(name, config):
            path = work / (name + ".json")
            path.write_text(json.dumps(config), encoding="utf-8")
            run([xray, "run", "-test", "-config", path], check=True, capture_output=True)
            log = work / (name + ".log")
            logs.append(log)
            stream = stack.enter_context(log.open("w", encoding="utf-8"))
            process = subprocess.Popen([xray, "run", "-config", path], cwd=work, env=env,
                                       stdout=stream, stderr=subprocess.STDOUT)
            stack.callback(stop, process)
            return process

        run(["openssl", "req", "-x509", "-newkey", "rsa:2048", "-nodes", "-days", "1",
             "-subj", "/CN=localhost", "-keyout", "key.pem", "-out", "cert.pem"], check=True, capture_output=True)
        cover_port = free_port()
        cover = subprocess.Popen(["node", "-e", """
            const https = require('node:https');
            const fs = require('node:fs');
            https.createServer({key: fs.readFileSync('key.pem'), cert: fs.readFileSync('cert.pem'),
                minVersion: 'TLSv1.3'}, (_, response) => response.end('cover'))
                .listen(Number(process.argv[1]), '127.0.0.1');
        """, str(cover_port)], cwd=work, env=env)
        stack.callback(stop, cover)

        config = json.loads(profile_path.read_text(encoding="utf-8"))
        servers, origins = [], []
        for i, node in enumerate(config["outbounds"][1:4]):
            label = f"node-{i + 1}"
            origin = serve(stack, label, (0.02, 0.14, 0.3)[i])
            origins.append(origin)
            lines = run([xray, "x25519"], check=True, capture_output=True).stdout.splitlines()
            keys = dict(line.split(": ", 1) for line in lines if ": " in line)
            port = free_port()
            peer = node["settings"]["vnext"][0]
            peer.update(address="127.0.0.1", port=port)
            reality = node["streamSettings"]["realitySettings"]
            reality.update(serverName="localhost", publicKey=keys["Password (PublicKey)"])
            user = peer["users"][0]
            servers.append(start(label, {
                "log": {"loglevel": "warning"},
                "inbounds": [{
                    "listen": "127.0.0.1", "port": port, "protocol": "vless",
                    "settings": {"decryption": "none", "clients": [{"id": user["id"], "flow": user["flow"]}]},
                    "streamSettings": {"network": "tcp", "security": "reality", "realitySettings": {
                        "target": f"127.0.0.1:{cover_port}", "serverNames": ["localhost"],
                        "privateKey": keys["PrivateKey"], "shortIds": [reality["shortId"]],
                    }},
                }],
                "outbounds": [{"protocol": "freedom", "settings": {
                    "redirect": f"127.0.0.1:{origin.server_port}",
                    "finalRules": [{"action": "allow", "network": "tcp", "ip": ["127.0.0.1/32"], "port": origin.server_port}],
                }}],
            }))

        local_socks = free_port()
        config["inbounds"][0]["port"] = local_socks
        config["observatory"].update(probeUrl="http://probe.example/probe", probeInterval="2s")
        client = start("client", config)

        def request(url="http://target.example/which"):
            return run(["curl", "--noproxy", "", "--silent", "--max-time", "2", "--socks5-hostname",
                        f"127.0.0.1:{local_socks}", url], capture_output=True)

        def expect(expected, timeout=15):
            deadline = time.monotonic() + timeout
            while time.monotonic() < deadline:
                assert client.poll() is None, "Xray client stopped"
                result = request()
                if result.returncode == 0 and result.stdout == expected:
                    return
                time.sleep(0.25)
            raise AssertionError(f"Expected {expected}, got {result.stdout!r}")

        try:
            expect("node-1")
            stop(servers[0])
            expect("node-2")
            origins[1].delay, origins[2].delay = 0.4, 0.02
            expect("node-3")
            stop(servers[1])
            stop(servers[2])
            time.sleep(3)
            url = f"http://127.0.0.1:{origins[2].server_port}/which"
            direct = run(["curl", "--noproxy", "*", "--silent", "--max-time", "2", url], capture_output=True)
            assert direct.returncode == 0 and direct.stdout == "node-3", "Direct test target is unavailable"
            assert request(url).returncode != 0, "Traffic escaped the fail-closed profile"
        except (AssertionError, subprocess.CalledProcessError):
            for path in logs:
                print(path.name, path.read_text(encoding="utf-8")[-4000:], file=sys.stderr)
            raise
        print("PASS REALITY traffic, leastPing, node loss, latency change and all-down blocking")


if __name__ == "__main__":
    main(*sys.argv[1:])
