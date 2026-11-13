#!/usr/bin/env python3
"""Native controller regressions, with optional real Xray API integration."""

import copy
import base64
import http.server
import importlib.util
import json
import socket
import subprocess
import sys
import tempfile
import threading
import time
import unittest
from unittest.mock import patch
import urllib.error
import urllib.request
from pathlib import Path

sys.dont_write_bytecode = True
ROOT = Path(__file__).resolve().parents[1]
SCRATCH = ROOT / "tests/.tmp"
SCRATCH.mkdir(exist_ok=True)
spec = importlib.util.spec_from_file_location("native_agent", ROOT / "plugins/vpn-manager-v2/agent/fireball_xray_agent.py")
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)

CLIENT = {"id": "cc8ddf18-1185-441e-a604-c88b9fb2c2ab", "email": "existing@example.test",
          "enable": True, "expiryTime": 0, "totalGB": 0, "limitHwid": 2, "limitIp": 0, "subId": "old-sub-id"}


def configuration():
    return {"log": {"loglevel": "debug"}, "api": {"tag": "api", "listen": "127.0.0.1:10085",
            "services": ["HandlerService", "StatsService"]}, "stats": {},
            "policy": {"levels": {"0": {"statsUserUplink": True, "statsUserDownlink": True, "statsUserOnline": True}}},
            "inbounds": [{"tag": "inbound-24", "listen": "127.0.0.1", "port": 19343,
                          "protocol": "vless", "settings": {"decryption": "none", "clients": [
                              {"id": CLIENT["id"], "email": CLIENT["email"], "level": 0}]}}],
            "outbounds": [{"protocol": "freedom", "tag": "direct"}]}


def manifest(client=None):
    return [{"inbound_id": 24, "protocol": "vless", "port": 19343, "clients": [copy.deepcopy(client or CLIENT)]}]


class FakeRuntime:
    def __init__(self):
        self.live = {"inbound-24": {CLIENT["email"]: {"email": CLIENT["email"], "account": {"id": CLIENT["id"]}}}}
        self.values = {}
        self.generation = "one"
        self.fail_add = False

    def users(self, tag):
        return copy.deepcopy(self.live.get(tag, {}))

    def stats(self):
        return dict(self.values)

    def online(self):
        return [CLIENT["email"]]

    def public_key(self, private_key):
        return "fixture-public-key"

    def epoch(self):
        return self.generation

    def validate(self, config):
        assert config["inbounds"][0]["protocol"] == "vless"

    def add(self, inbound, client):
        if self.fail_add:
            raise module.AgentError("xray_unavailable", 503)
        self.live.setdefault(inbound["tag"], {})[client["email"]] = {"email": client["email"], "account": dict(client)}

    def remove(self, tag, email):
        self.live.setdefault(tag, {}).pop(email, None)

    def replace_inbound(self, tag, inbound):
        self.live.pop(tag, None)
        if inbound:
            for client in inbound["settings"].get("clients", []):
                self.add(inbound, client)


class AgentTests(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory(prefix="native-agent-", dir=SCRATCH)
        self.path = Path(self.directory.name)
        self.config = self.path / "config.json"
        self.config.write_text(json.dumps(configuration()))
        self.runtime = FakeRuntime()
        self.agent = module.Agent(self.config, self.path / "state.sqlite", self.runtime)

    def tearDown(self):
        self.agent.connection.close()
        self.directory.cleanup()

    def adopt(self, client=None):
        args = {"manifest": manifest(client)}
        args["fingerprint"] = self.agent.dispatch("adopt_preview", args)["fingerprint"]
        return self.agent.dispatch("adopt", args)

    def test_adoption_preserves_keys_and_requires_preview(self):
        before = self.config.read_bytes()
        self.assertEqual(self.adopt()["matched"], 1)
        self.assertEqual(self.config.read_bytes(), before)
        self.assertEqual(self.agent.dispatch("inbound", {"inbound_id": 24})["settings"]["clients"][0]["id"], CLIENT["id"])
        with self.assertRaisesRegex(module.AgentError, "preview_expired"):
            self.agent.dispatch("adopt", {"manifest": manifest(), "fingerprint": "stale"})

    def test_unadopted_writes_and_wrong_keys_blocked(self):
        with self.assertRaisesRegex(module.AgentError, "adoption_required"):
            self.agent.dispatch("client_put", {"inbound_id": 24, "client": CLIENT})
        wrong = dict(CLIENT, id="wrong")
        with self.assertRaisesRegex(module.AgentError, "credential_mismatch"):
            self.adopt(wrong)
        with self.assertRaisesRegex(module.AgentError, "ip_limit_unsupported"):
            self.adopt(dict(CLIENT, limitIp=1))

    def test_counters_are_idempotent_and_survive_restart(self):
        self.adopt(dict(CLIENT, upload_bytes=10, download_bytes=20))
        self.runtime.values["user>>>" + CLIENT["email"] + ">>>traffic>>>uplink"] = 100
        first = self.agent.dispatch("traffic", {"email": CLIENT["email"]})
        second = self.agent.dispatch("traffic", {"email": CLIENT["email"]})
        self.assertEqual(first, second)
        self.assertEqual(first["up"], 110)
        self.runtime.generation = "two"
        self.runtime.values["user>>>" + CLIENT["email"] + ">>>traffic>>>uplink"] = 7
        self.assertEqual(self.agent.dispatch("traffic", {"email": CLIENT["email"]})["up"], 117)

    def test_expiration_quota_restore_and_hwid(self):
        self.adopt()
        self.assertEqual(self.agent.dispatch("hwid", {"sub_id": CLIENT["subId"], "hwid": "device-one"})["status"], 200)
        self.assertEqual(self.agent.dispatch("hwid", {"sub_id": CLIENT["subId"], "hwid": "device-two"})["status"], 200)
        self.assertEqual(self.agent.dispatch("hwid", {"sub_id": CLIENT["subId"], "hwid": "device-three"})["status"], 404)
        self.agent.dispatch("client_put", {"inbound_id": 24, "credential": CLIENT["id"],
                                          "client": dict(CLIENT, expiryTime=int(time.time() * 1000) - 1000)})
        self.assertNotIn(CLIENT["email"], self.runtime.users("inbound-24"))
        self.agent.dispatch("client_put", {"inbound_id": 24, "credential": CLIENT["id"], "client": CLIENT})
        self.assertIn(CLIENT["email"], self.runtime.users("inbound-24"))
        self.agent.dispatch("client_put", {"inbound_id": 24, "client": dict(CLIENT, totalGB=10)})
        self.runtime.values["user>>>" + CLIENT["email"] + ">>>traffic>>>uplink"] = 12
        self.agent.tick()
        self.assertNotIn(CLIENT["email"], self.runtime.users("inbound-24"))
        self.agent.dispatch("traffic_reset", {"inbound_id": 24, "email": CLIENT["email"]})
        self.assertIn(CLIENT["email"], self.runtime.users("inbound-24"))
        self.assertEqual(self.agent.dispatch("traffic", {"email": CLIENT["email"]})["up"], 0)

    def test_failed_add_is_durable_and_rename_preserves_usage(self):
        self.adopt()
        self.runtime.fail_add = True
        new = dict(CLIENT, id="b17af9cf-2b68-4a11-bcd7-586e761cd52f", email="new@example.test")
        with self.assertRaisesRegex(module.AgentError, "xray_unavailable"):
            self.agent.dispatch("client_put", {"inbound_id": 24, "client": new})
        self.assertIn(new["email"], self.agent.state()["clients"]["inbound-24"])
        self.runtime.fail_add = False
        self.agent.tick()
        self.assertIn(new["email"], self.runtime.users("inbound-24"))
        renamed = dict(new, email="renamed@example.test")
        self.agent.dispatch("client_put", {"inbound_id": 24, "credential": new["id"], "client": renamed})
        self.assertNotIn(new["email"], self.runtime.users("inbound-24"))
        self.assertIn(renamed["email"], self.runtime.users("inbound-24"))

    def test_delete_preserves_other_users_and_external_edits_block_writes(self):
        self.adopt()
        self.agent.dispatch("client_delete", {"inbound_id": 24, "credential": CLIENT["id"], "email": CLIENT["email"]})
        self.assertNotIn(CLIENT["email"], self.runtime.users("inbound-24"))
        self.assertEqual(json.loads(self.config.read_text())["inbounds"][0]["settings"]["clients"], [])
        config = json.loads(self.config.read_text())
        config["log"]["loglevel"] = "warning"
        self.config.write_text(json.dumps(config))
        with self.assertRaisesRegex(module.AgentError, "config_changed"):
            self.agent.dispatch("client_put", {"inbound_id": 24, "client": CLIENT})

    def test_pending_config_recovers_after_crash(self):
        self.adopt()
        state = self.agent.state()
        config = self.agent.config()
        changed = copy.deepcopy(config)
        changed["log"]["loglevel"] = "warning"
        state["baseline"] = module.digest(changed)
        state["pending"] = {"previous_hash": module.digest(config), "config": changed}
        self.agent.save(state)
        self.agent.recover()
        self.assertEqual(self.agent.config(), changed)
        self.assertNotIn("pending", self.agent.state())

    def test_inbound_edit_retains_clients_and_blocks_occupied_deletion(self):
        self.adopt()
        edited = {"tag": "inbound-24", "listen": "127.0.0.1", "port": 19443,
                  "protocol": "vless", "settings": {"decryption": "none"}}
        args = {"inbound": edited, "delete": False}
        args["fingerprint"] = self.agent.dispatch("inbound_preview", args)["fingerprint"]
        self.agent.dispatch("inbound_apply", args)
        self.assertIn(CLIENT["email"], self.runtime.users("inbound-24"))
        self.assertEqual(self.agent.config()["inbounds"][0]["port"], 19443)
        with self.assertRaisesRegex(module.AgentError, "inbound_has_clients"):
            self.agent.dispatch("inbound_preview", {"inbound": {"tag": "inbound-24"}, "delete": True})

    def test_foreign_users_and_existing_core_fields_are_retained(self):
        config = self.agent.config()
        config["inbounds"][0]["settings"]["clients"][0]["customField"] = "kept"
        foreign = {"email": "foreign@example.test", "id": "0bd0ca78-013a-4b5e-b540-252bdecd58a9"}
        config["inbounds"][0]["settings"]["clients"].append(foreign)
        self.config.write_text(json.dumps(config))
        self.runtime.live["inbound-24"][foreign["email"]] = {"email": foreign["email"], "account": foreign}
        self.adopt()
        self.agent.tick()
        rows = {row["email"]: row for row in self.agent.config()["inbounds"][0]["settings"]["clients"]}
        self.assertEqual(rows[CLIENT["email"]]["customField"], "kept")
        self.agent.dispatch("client_delete", {"inbound_id": 24, "credential": CLIENT["id"], "email": CLIENT["email"]})
        self.assertIn(foreign["email"], self.runtime.users("inbound-24"))
        self.assertEqual(self.agent.config()["inbounds"][0]["settings"]["clients"], [foreign])

    def test_transport_and_reality_key_mismatches_block_adoption(self):
        config = self.agent.config()
        config["inbounds"][0]["streamSettings"] = {"network": "tcp", "security": "reality", "realitySettings": {
            "privateKey": "fixture-private-key", "serverNames": ["example.test"], "shortIds": ["abcd"]}}
        self.config.write_text(json.dumps(config))
        plan = manifest()
        plan[0]["expected_stream"] = {"network": "tcp", "security": "reality", "realitySettings": {
            "settings": {"publicKey": "wrong"}, "serverNames": ["example.test"], "shortIds": ["abcd"]}}
        with self.assertRaisesRegex(module.AgentError, "reality_key_mismatch"):
            self.agent.dispatch("adopt_preview", {"manifest": plan})
        plan[0]["expected_stream"]["realitySettings"]["settings"]["publicKey"] = "fixture-public-key"
        self.assertEqual(self.agent.dispatch("adopt_preview", {"manifest": plan})["matched"], 1)
        projected = self.agent.dispatch("inbound", {"inbound_id": 24})
        self.assertNotIn("fixture-private-key", json.dumps(projected))
        self.assertIn("fixture-public-key", json.dumps(projected))

    def test_private_reality_key_never_enters_process_arguments(self):
        secret = base64.urlsafe_b64encode(bytes(range(32))).decode().rstrip("=")
        public_der = bytes.fromhex("302a300506032b656e032100") + bytes(range(32))
        response = subprocess.CompletedProcess([], 0, stdout=public_der, stderr=b"")
        runtime = module.XrayRuntime("/unused/xray", "127.0.0.1:1", "xray.service", self.path)
        with patch.object(module.subprocess, "run", return_value=response) as command:
            self.assertEqual(runtime.public_key(secret), secret)
            self.assertNotIn(secret, " ".join(command.call_args.args[0]))
            self.assertEqual(command.call_args.kwargs["input"][-32:], bytes(range(32)))


def unused_port():
    with socket.socket() as listener:
        listener.bind(("127.0.0.1", 0))
        return listener.getsockname()[1]


def real_traffic(binary, server_port, http_port, directory, log):
    class ResponseHandler(http.server.BaseHTTPRequestHandler):
        def do_GET(self):
            body = b"native-xray-test" * 8192
            self.send_response(200)
            self.send_header("Content-Length", str(len(body)))
            self.end_headers()
            self.wfile.write(body)

        def log_message(self, *arguments):
            pass

    http_server = http.server.HTTPServer(("127.0.0.1", http_port), ResponseHandler)
    thread = threading.Thread(target=http_server.serve_forever, daemon=True)
    thread.start()
    port = unused_port()
    path = directory / "client.json"
    path.write_text(json.dumps({"log": {"loglevel": "debug"}, "inbounds": [
        {"listen": "127.0.0.1", "port": port, "protocol": "socks", "settings": {"auth": "noauth"}}],
        "outbounds": [{"protocol": "vless", "settings": {"vnext": [{"address": "127.0.0.1", "port": server_port,
            "users": [{"id": CLIENT["id"], "encryption": "none"}]}]}}]}))
    process = subprocess.Popen([binary, "run", "-config", str(path)], stdout=log, stderr=log)
    try:
        for attempt in range(40):
            try:
                connection = socket.create_connection(("127.0.0.1", port), timeout=2)
                break
            except OSError:
                time.sleep(0.1)
        else:
            raise AssertionError("Client kernel failed to start")
        with connection:
            connection.settimeout(5)
            connection.sendall(b"\x05\x01\x00")
            assert connection.recv(2) == b"\x05\x00"
            connection.sendall(b"\x05\x01\x00\x01\x7f\x00\x00\x01" + http_server.server_port.to_bytes(2, "big"))
            assert connection.recv(10)[1] == 0
            connection.sendall(b"GET / HTTP/1.0\r\nHost: test.local\r\n\r\n")
            output = b""
            while True:
                block = connection.recv(65536)
                if not block:
                    break
                output += block
                if b"\r\n\r\n" in output:
                    headers, payload = output.split(b"\r\n\r\n", 1)
                    content_length = next((int(line.split(b":", 1)[1]) for line in headers.split(b"\r\n")
                                           if line.lower().startswith(b"content-length:")), 0)
                    if content_length > 0 and len(payload) >= content_length:
                        break
            assert len(output) > 100000
    except Exception:
        log.flush()
        print("Isolated test kernel diagnostics", Path(log.name).read_text()[-9000:])
        raise
    finally:
        process.terminate()
        process.wait(timeout=10)
        http_server.shutdown()
        http_server.server_close()
        thread.join(timeout=5)


def real_xray(binary):
    with tempfile.TemporaryDirectory(prefix="native-xray-live-", dir=SCRATCH) as temporary:
        directory = Path(temporary)
        config = configuration()
        http_port = unused_port()
        # Current Xray blocks private targets by default. Allow only this test endpoint.
        config["outbounds"][0]["settings"] = {"finalRules": [
            {"action": "allow", "network": "tcp", "ip": ["127.0.0.1/32"], "port": http_port}]}
        api_port = unused_port()
        config["api"]["listen"] = "127.0.0.1:" + str(api_port)
        config["inbounds"][0]["port"] = unused_port()
        path = directory / "config.json"
        path.write_text(json.dumps(config))
        runtime = module.XrayRuntime(binary, config["api"]["listen"], "xray.service", directory)
        runtime.epoch = lambda: "real-one"
        if sys.platform == "linux":
            assert len(runtime.public_key(base64.urlsafe_b64encode(bytes(range(32))).decode().rstrip("="))) == 43
        with (directory / "xray.log").open("w") as log:
            process = subprocess.Popen([binary, "run", "-config", str(path)], stdout=log, stderr=log)
            agent = None
            try:
                for attempt in range(40):
                    try:
                        live = runtime.users("inbound-24")
                        break
                    except module.AgentError:
                        time.sleep(0.1)
                else:
                    raise AssertionError("Real Xray did not start")
                assert module.Agent.live_matches(live[CLIENT["email"]], CLIENT), live
                agent = module.Agent(path, directory / "state.sqlite", runtime)
                assert agent.dispatch("health", {})["xray"]["state"] == "running"
                migration = manifest()
                migration[0]["port"] = config["inbounds"][0]["port"]
                args = {"manifest": migration}
                args["fingerprint"] = agent.dispatch("adopt_preview", args)["fingerprint"]
                assert agent.dispatch("adopt", args)["matched"] == 1
                new = dict(CLIENT, id="b17af9cf-2b68-4a11-bcd7-586e761cd52f", email="new@example.test")
                agent.dispatch("client_put", {"inbound_id": 24, "client": new})
                assert new["email"] in runtime.users("inbound-24")
                agent.dispatch("client_put", {"inbound_id": 24, "credential": new["id"], "client": dict(new, enable=False)})
                assert new["email"] not in runtime.users("inbound-24")
                agent.dispatch("client_put", {"inbound_id": 24, "credential": new["id"], "client": new})
                real_traffic(binary, config["inbounds"][0]["port"], http_port, directory, log)
                traffic = agent.dispatch("traffic", {"email": CLIENT["email"]})
                assert traffic["up"] > 0 and traffic["down"] > 100000, traffic
                assert agent.dispatch("traffic", {"email": CLIENT["email"]}) == traffic
                agent.dispatch("client_delete", {"inbound_id": 24, "credential": new["id"], "email": new["email"]})
                assert new["email"] not in runtime.users("inbound-24")
                assert CLIENT["email"] in runtime.users("inbound-24")
                assert isinstance(runtime.online(), list)
                extra = {"tag": "inbound-25", "listen": "127.0.0.1", "port": unused_port(),
                         "protocol": "vless", "settings": {"decryption": "none"}}
                args = {"inbound": extra, "delete": False}
                args["fingerprint"] = agent.dispatch("inbound_preview", args)["fingerprint"]
                agent.dispatch("inbound_apply", args)
                assert "inbound-25" in runtime.inbound_tags()
                args = {"inbound": {"tag": "inbound-25"}, "delete": True}
                args["fingerprint"] = agent.dispatch("inbound_preview", args)["fingerprint"]
                agent.dispatch("inbound_apply", args)
                assert "inbound-25" not in runtime.inbound_tags()
                runtime.validate(agent.config())
                process.terminate()
                process.wait(timeout=10)
                runtime.epoch = lambda: "real-two"
                process = subprocess.Popen([binary, "run", "-config", str(path)], stdout=log, stderr=log)
                for attempt in range(40):
                    try:
                        assert CLIENT["email"] in runtime.users("inbound-24")
                        break
                    except module.AgentError:
                        time.sleep(0.1)
                else:
                    raise AssertionError("Persistent configuration failed after restart")
                assert agent.dispatch("traffic", {"email": CLIENT["email"]}) == traffic
                token = "local-integration-test-token" * 2
                rpc_server = module.make_server(agent, token, 0)
                rpc_thread = threading.Thread(target=rpc_server.serve_forever, daemon=True)
                rpc_thread.start()
                try:
                    url = "http://127.0.0.1:" + str(rpc_server.server_port) + "/v1/rpc"
                    data = json.dumps({"action": "inbounds", "arguments": {}}).encode()
                    request = urllib.request.Request(url, data=data, headers={"Authorization": "Bearer " + token})
                    with urllib.request.urlopen(request, timeout=5) as response:
                        assert json.load(response)["protocol"] == "fireball-xray/1"
                    try:
                        urllib.request.urlopen(urllib.request.Request(url, data=data), timeout=5)
                        raise AssertionError("Unauthenticated RPC was accepted")
                    except urllib.error.HTTPError as error:
                        assert error.code == 404
                finally:
                    rpc_server.shutdown()
                    rpc_server.server_close()
                    rpc_thread.join(timeout=5)
                print("Real Xray passed adoption, client/inbound CRUD, live traffic, usage, restart persistence and online API.")
            finally:
                if agent:
                    agent.connection.close()
                process.terminate()
                process.wait(timeout=10)


if __name__ == "__main__":
    if len(sys.argv) == 3 and sys.argv[1] == "--xray":
        real_xray(str(Path(sys.argv[2]).resolve()))
    else:
        unittest.main()
