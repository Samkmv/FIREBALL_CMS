#!/usr/bin/env python3
"""FIREBALL's loopback-only Xray controller. No third-party Python packages."""

import argparse
import base64
import copy
import fcntl
import hashlib
import hmac
import json
import logging
import os
import re
import shutil
import sqlite3
import stat
import subprocess
import tempfile
import threading
import time
from http.server import BaseHTTPRequestHandler, HTTPServer
from pathlib import Path


class AgentError(Exception):
    def __init__(self, code, status=409):
        super().__init__(code)
        self.code = code
        self.status = status


def encoded(value):
    return json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":")).encode()


def digest(value):
    return hashlib.sha256(encoded(value)).hexdigest()


def atomic_write(path, data):
    metadata = path.stat() if path.exists() else None
    fd, temporary = tempfile.mkstemp(prefix=".fireball-", dir=path.parent)
    try:
        os.fchmod(fd, stat.S_IMODE(metadata.st_mode) if metadata else 0o600)
        if metadata:
            os.fchown(fd, metadata.st_uid, metadata.st_gid)
        with os.fdopen(fd, "wb") as stream:
            stream.write(data)
            stream.flush()
            os.fsync(stream.fileno())
        os.replace(temporary, path)
        directory = os.open(path.parent, os.O_RDONLY)
        try:
            os.fsync(directory)
        finally:
            os.close(directory)
    finally:
        if os.path.exists(temporary):
            os.unlink(temporary)


class XrayRuntime:
    def __init__(self, binary, api, service, scratch):
        self.binary = binary
        self.api = api
        self.service = service
        self.scratch = scratch
        self.cpu_sample = None

    def run(self, arguments):
        try:
            result = subprocess.run(arguments, capture_output=True, timeout=15, check=False)
        except (OSError, subprocess.TimeoutExpired) as error:
            raise AgentError("xray_unavailable", 503) from error
        if result.returncode != 0:
            # Xray's stderr may contain credentials. Only fixed error codes leave the agent.
            raise AgentError("xray_command_failed", 503)
        if len(result.stdout) > 8 * 1024 * 1024:
            raise AgentError("response_too_large", 503)
        return result.stdout.decode("utf-8")

    def api_call(self, command, *arguments):
        return self.run([self.binary, "api", command, "--server=" + self.api, "-timeout=10", *arguments])

    def users(self, tag):
        result = json.loads(self.api_call("inbounduser", "-tag=" + tag))
        return {item["email"]: item for item in result.get("users", [])}

    def stats(self):
        result = json.loads(self.api_call("statsquery", "-pattern=user>>>"))
        return {item["name"]: max(0, int(item.get("value", 0))) for item in result.get("stat", [])}

    def online(self):
        result = json.loads(self.api_call("statsgetallonlineusers"))
        return result.get("users", [])

    def public_key(self, private_key):
        try:
            raw = base64.urlsafe_b64decode(private_key + "=" * (-len(private_key) % 4))
            if len(raw) != 32:
                raise ValueError("invalid key length")
            # RFC 8410 PKCS#8 X25519. The secret is sent over stdin, never in process arguments.
            private_der = bytes.fromhex("302e020100300506032b656e04220420") + raw
            result = subprocess.run(["/usr/bin/openssl", "pkey", "-inform", "DER", "-pubout", "-outform", "DER"],
                                    input=private_der, capture_output=True, timeout=10, check=False)
        except (ValueError, OSError, subprocess.TimeoutExpired) as error:
            raise AgentError("public_key_unavailable") from error
        if result.returncode != 0 or len(result.stdout) != 44 or not result.stdout.startswith(bytes.fromhex("302a300506032b656e032100")):
            raise AgentError("public_key_unavailable")
        return base64.urlsafe_b64encode(result.stdout[-32:]).decode().rstrip("=")

    def health(self):
        system = json.loads(self.api_call("statssys"))
        result = {"xray": {"state": "running", "version": self.run([self.binary, "version"]).splitlines()[0]},
                  "uptime": system.get("uptime", system.get("Uptime", 0)), "cpuCores": os.cpu_count(),
                  "loads": list(os.getloadavg())}
        disk = shutil.disk_usage(self.scratch)
        result["disk"] = {"current": disk.used, "total": disk.total}
        memory_file = Path("/proc/meminfo")
        if memory_file.exists():
            memory = {line.split(":", 1)[0]: int(line.split()[1]) * 1024 for line in memory_file.read_text().splitlines()}
            result["mem"] = {"current": memory["MemTotal"] - memory["MemAvailable"], "total": memory["MemTotal"]}
            result["swap"] = {"current": memory["SwapTotal"] - memory["SwapFree"], "total": memory["SwapTotal"]}
        cpu_file = Path("/proc/stat")
        if cpu_file.exists():
            values = [int(value) for value in cpu_file.read_text().splitlines()[0].split()[1:9]]
            sample = (sum(values), values[3] + values[4])
            if self.cpu_sample and sample[0] > self.cpu_sample[0]:
                result["cpu"] = 100 * (1 - (sample[1] - self.cpu_sample[1]) / (sample[0] - self.cpu_sample[0]))
            self.cpu_sample = sample
        network_file = Path("/proc/net/dev")
        if network_file.exists():
            rows = [line.split(":", 1) for line in network_file.read_text().splitlines()[2:]]
            result["netTraffic"] = {"recv": sum(int(row[1].split()[0]) for row in rows if row[0].strip() != "lo"),
                                    "sent": sum(int(row[1].split()[8]) for row in rows if row[0].strip() != "lo")}
        return result

    def epoch(self):
        pid = self.run(["/usr/bin/systemctl", "show", self.service, "--property=MainPID", "--value"]).strip()
        if not pid.isdigit() or int(pid) <= 0:
            raise AgentError("xray_unavailable", 503)
        # Process start time distinguishes restarts even if the PID is reused.
        stat = Path("/proc", pid, "stat").read_text().rsplit(")", 1)[1].split()
        return Path("/proc/sys/kernel/random/boot_id").read_text().strip() + ":" + pid + ":" + stat[19]

    def validate(self, config):
        self.with_config(config, lambda path: self.run([self.binary, "run", "-test", "-config", path]))

    def add(self, inbound, client):
        content = copy.deepcopy(inbound)
        content["settings"]["clients"] = [client]
        # adu can exit 0 even if an individual addition failed. Read-back below is mandatory.
        self.with_config({"inbounds": [content]}, lambda path: self.api_call("adu", path))

    def remove(self, tag, email):
        self.api_call("rmu", "-tag=" + tag, "--", email)

    def inbound_tags(self):
        result = json.loads(self.api_call("lsi", "-isOnlyTags=true"))
        return {row["tag"] for row in result.get("inbounds", [])}

    def replace_inbound(self, tag, inbound):
        if tag in self.inbound_tags():
            self.api_call("rmi", "--", tag)
            if tag in self.inbound_tags():
                raise AgentError("inbound_remove_not_confirmed", 503)
        if inbound is not None:
            self.with_config({"inbounds": [inbound]}, lambda path: self.api_call("adi", path))
            if tag not in self.inbound_tags():
                raise AgentError("inbound_add_not_confirmed", 503)

    def with_config(self, config, callback):
        fd, path = tempfile.mkstemp(prefix="xray-", suffix=".json", dir=self.scratch)
        try:
            with os.fdopen(fd, "wb") as stream:
                stream.write(encoded(config))
            return callback(path)
        finally:
            os.unlink(path)


class Agent:
    SUPPORTED = {"vless", "vmess", "trojan", "shadowsocks"}

    def __init__(self, config_path, state_path, runtime):
        self.config_path = Path(config_path)
        self.runtime = runtime
        self.lock = threading.RLock()
        self.connection = sqlite3.connect(state_path, check_same_thread=False)
        self.connection.execute("PRAGMA journal_mode=WAL")
        self.connection.execute("PRAGMA synchronous=FULL")
        self.connection.execute("CREATE TABLE IF NOT EXISTS state (id INTEGER PRIMARY KEY CHECK(id=1), data TEXT NOT NULL)")
        if self.connection.execute("SELECT data FROM state WHERE id=1").fetchone() is None:
            self.save({"adopted": False, "clients": {}, "counters": {}, "devices": {}, "ids": {}, "baseline": ""})
        self.recover()

    def state(self):
        return json.loads(self.connection.execute("SELECT data FROM state WHERE id=1").fetchone()[0])

    def save(self, state):
        with self.connection:
            self.connection.execute("INSERT OR REPLACE INTO state(id,data) VALUES(1,?)", (encoded(state).decode(),))

    def config(self):
        if self.config_path.is_symlink():
            raise AgentError("config_symlink")
        value = json.loads(self.config_path.read_bytes())
        if not isinstance(value, dict) or not isinstance(value.get("inbounds"), list):
            raise AgentError("invalid_config")
        return value

    def recover(self):
        state = self.state()
        pending = state.get("pending")
        if pending:
            actual = digest(self.config())
            if actual not in (pending["previous_hash"], digest(pending["config"])):
                raise AgentError("config_changed")
            atomic_write(self.config_path, (json.dumps(pending["config"], ensure_ascii=False, indent=2) + "\n").encode())
            del state["pending"]
            self.save(state)

    def publish(self, config, state):
        previous = self.config()
        if state["baseline"] and digest(previous) != state["baseline"]:
            raise AgentError("config_changed")
        self.runtime.validate(config)
        state["baseline"] = digest(config)
        state["pending"] = {"previous_hash": digest(previous), "config": config}
        self.save(state)
        self.recover()

    def inventory(self, config, state):
        result = {}
        seen = set()
        for inbound in config["inbounds"]:
            if inbound.get("protocol") not in self.SUPPORTED:
                continue
            tag = inbound.get("tag", "")
            if not tag or tag in seen:
                raise AgentError("inbound_tag_required")
            seen.add(tag)
            number = state["ids"].get(tag)
            if number is None:
                match = re.fullmatch(r"inbound-(\d+)", tag)
                number = int(match[1]) if match else max([0, *state["ids"].values()]) + 1
                if number <= 0 or number in state["ids"].values():
                    raise AgentError("inbound_id_conflict")
                state["ids"][tag] = number
            result[number] = inbound
        return result

    def selected(self, arguments, config, state):
        inbound = self.inventory(config, state).get(int(arguments.get("inbound_id", 0)))
        if inbound is None:
            raise AgentError("inbound_missing", 404)
        return inbound

    @staticmethod
    def credential(client):
        return str(client.get("password", client.get("id", "")))

    @staticmethod
    def live_matches(live, client):
        if live is None:
            return False
        account = live.get("account", {})
        return Agent.credential(account) == Agent.credential(client) and account.get("flow", "") == client.get("flow", "")

    @staticmethod
    def core_client(protocol, client):
        result = copy.deepcopy(client.get("_core", {}))
        result["email"] = client["email"]
        result.setdefault("level", 0)
        if protocol in ("vless", "vmess"):
            result["id"] = client["id"]
        else:
            result["password"] = client["password"]
        if protocol == "vless":
            if client.get("flow"):
                result["flow"] = client["flow"]
            else:
                result.pop("flow", None)
        if protocol == "vmess":
            result.setdefault("alterId", 0)
        return result

    @staticmethod
    def validate_client(protocol, client):
        email = client.get("email", "")
        if not isinstance(email, str) or not email or len(email) > 190 or ">>>" in email or any(ord(c) < 32 for c in email):
            raise AgentError("invalid_client", 400)
        if not Agent.credential(client):
            raise AgentError("invalid_client", 400)
        if protocol not in Agent.SUPPORTED:
            raise AgentError("unsupported_protocol")
        if int(client.get("limitIp", 0)) > 0:
            raise AgentError("ip_limit_unsupported")
        if int(client.get("limitHwid", 0)) > 0 and not client.get("subId"):
            raise AgentError("device_identity_missing")
        for key in ("limitHwid", "limitIp", "totalGB", "expiryTime"):
            if not isinstance(client.get(key, 0), int) or client.get(key, 0) < 0:
                raise AgentError("invalid_client", 400)
        if not isinstance(client.get("enable", True), bool):
            raise AgentError("invalid_client", 400)

    def collect(self, state):
        epoch = self.runtime.epoch()
        stats = self.runtime.stats()
        now = int(time.time())
        for rows in state["clients"].values():
            for email in rows:
                counter = state["counters"].setdefault(email, {"up": 0, "down": 0})
                delta = 0
                for direction, suffix in (("up", "uplink"), ("down", "downlink")):
                    value = stats.get("user>>>" + email + ">>>traffic>>>" + suffix, 0)
                    previous = counter.get("last_" + direction, 0) if counter.get("epoch") == epoch else 0
                    increment = value - previous if value >= previous else value
                    counter[direction] += increment
                    counter["last_" + direction] = value
                    delta += increment
                if delta:
                    counter["last_activity"] = now
                counter["epoch"] = epoch
        self.save(state)

    @staticmethod
    def enabled(client, counter):
        expired = client.get("expiryTime", 0) > 0 and client["expiryTime"] <= time.time() * 1000
        exceeded = client.get("totalGB", 0) > 0 and counter.get("up", 0) + counter.get("down", 0) >= client["totalGB"]
        return bool(client.get("enable", True)) and not expired and not exceeded

    def reconcile(self, state):
        config = self.config()
        if state["baseline"] != digest(config):
            raise AgentError("config_changed")
        dirty = False
        for inbound in self.inventory(config, state).values():
            tag = inbound["tag"]
            owned = state["clients"].get(tag, {})
            settings = inbound["settings"]
            preserved = [row for row in settings.get("clients", []) if row.get("email") not in owned]
            desired = [self.core_client(inbound["protocol"], row) for email, row in owned.items()
                       if self.enabled(row, state["counters"].get(email, {}))]
            if settings.get("clients", []) != preserved + desired:
                settings["clients"] = preserved + desired
                dirty = True
        if dirty:
            self.publish(config, state)
            state = self.state()
        for tag in state.get("replacements", []):
            inbound = next((row for row in config["inbounds"] if row.get("tag") == tag), None)
            self.runtime.replace_inbound(tag, inbound)
        state.pop("replacements", None)
        # Process deletions before additions so a rename never creates a duplicate UUID.
        for tag, emails in list(state.get("deletions", {}).items()):
            live = self.runtime.users(tag)
            for email in emails:
                if email in live:
                    self.runtime.remove(tag, email)
                    if email in self.runtime.users(tag):
                        raise AgentError("remove_not_confirmed", 503)
        for inbound in self.inventory(config, state).values():
            owned = state["clients"].get(inbound["tag"], {})
            live = self.runtime.users(inbound["tag"])
            for email, row in owned.items():
                should_enable = self.enabled(row, state["counters"].get(email, {}))
                expected = self.core_client(inbound["protocol"], row)
                matches = self.live_matches(live.get(email), expected)
                if email in live and (not should_enable or not matches):
                    self.runtime.remove(inbound["tag"], email)
                    if email in self.runtime.users(inbound["tag"]):
                        raise AgentError("remove_not_confirmed", 503)
                if should_enable and not matches:
                    self.runtime.add(inbound, expected)
                    if not self.live_matches(self.runtime.users(inbound["tag"]).get(email), expected):
                        raise AgentError("add_not_confirmed", 503)
        # Tombstones remain durable until runtime absence is confirmed, then are removed.
        state.pop("deletions", None)
        self.save(state)

    def adopt(self, arguments, state, config, apply):
        manifest = arguments.get("manifest", [])
        if not isinstance(manifest, list):
            raise AgentError("invalid_manifest", 400)
        draft = copy.deepcopy(state)
        existing = self.inventory(config, draft)
        count = missing = 0
        seen_emails = set()
        for item in manifest:
            inbound = existing.get(int(item["inbound_id"]))
            if inbound is None:
                missing += len(item["clients"])
                continue
            if inbound["protocol"] != item["protocol"] or inbound["port"] != item["port"]:
                raise AgentError("inbound_mismatch")
            draft.setdefault("names", {})[inbound["tag"]] = str(item.get("name", inbound["tag"]))[:255]
            self.verify_transport(item.get("expected_stream", {}), inbound.get("streamSettings", {}))
            if inbound["protocol"] == "shadowsocks" and inbound["settings"].get("method", "").startswith("2022-"):
                raise AgentError("unsupported_protocol")
            live = self.runtime.users(inbound["tag"])
            raw = {row.get("email"): row for row in inbound["settings"].get("clients", [])}
            for client in item["clients"]:
                self.validate_client(inbound["protocol"], client)
                email = client["email"]
                if email in seen_emails:
                    raise AgentError("duplicate_email")
                seen_emails.add(email)
                expected = self.core_client(inbound["protocol"], client)
                if email in raw and self.credential(raw[email]) != self.credential(expected):
                    raise AgentError("credential_mismatch")
                previous = draft["clients"].get(inbound["tag"], {}).get(email)
                if previous is not None and self.credential(previous) != self.credential(expected):
                    raise AgentError("credential_mismatch")
                if email in live and not self.live_matches(live[email], expected):
                    raise AgentError("credential_mismatch")
                count += int(email in live)
                missing += int(email not in live)
                client = copy.deepcopy(client)
                client["_core"] = copy.deepcopy(previous.get("_core", {}) if previous else raw.get(email, {}))
                level = str(client["_core"].get("level", 0))
                policy = config.get("policy", {}).get("levels", {}).get(level, {})
                if not policy.get("statsUserUplink") or not policy.get("statsUserDownlink"):
                    raise AgentError("statistics_not_configured")
                draft["clients"].setdefault(inbound["tag"], {})[email] = client
                if email not in draft["counters"]:
                    draft["counters"][email] = {"up": max(0, int(client.get("upload_bytes", 0))),
                                                "down": max(0, int(client.get("download_bytes", 0)))}
        # Unknown users are retained. A shared email would make usage and deletion ambiguous.
        for inbound in existing.values():
            for row in inbound["settings"].get("clients", []):
                email = row.get("email", "")
                if email in seen_emails and email not in draft["clients"].get(inbound["tag"], {}):
                    raise AgentError("duplicate_email")
        levels = config.get("policy", {}).get("levels", {}).get("0", {})
        if "stats" not in config or not levels.get("statsUserUplink") or not levels.get("statsUserDownlink"):
            raise AgentError("statistics_not_configured")
        fingerprint = digest({"config": config, "manifest": manifest,
                              "clients": state["clients"], "adopted": state["adopted"], "ids": state["ids"]})
        if apply:
            if not hmac.compare_digest(str(arguments.get("fingerprint", "")), fingerprint):
                raise AgentError("preview_expired")
            # Take current raw counters as a baseline, preserving the CMS's cumulative usage.
            stats = self.runtime.stats()
            epoch = self.runtime.epoch()
            for email, counter in draft["counters"].items():
                if not counter.get("epoch"):
                    counter.update({"last_up": stats.get("user>>>" + email + ">>>traffic>>>uplink", 0),
                                    "last_down": stats.get("user>>>" + email + ">>>traffic>>>downlink", 0),
                                    "epoch": epoch})
            draft["adopted"] = True
            draft["baseline"] = digest(config)
            self.save(draft)
        return {"matched": count, "missing": missing, "fingerprint": fingerprint, "adopted": apply}

    def verify_transport(self, expected, actual):
        normalize_network = lambda value: "tcp" if value in ("tcp", "raw", "") else value
        if normalize_network(expected.get("network", "tcp")) != normalize_network(actual.get("network", "tcp")) or expected.get("security", "none") != actual.get("security", "none"):
            raise AgentError("transport_mismatch")
        for section, fields in (("wsSettings", ("path", "headers")), ("grpcSettings", ("serviceName", "multiMode")),
                                ("xhttpSettings", ("path", "host", "mode")), ("httpupgradeSettings", ("path", "host"))):
            old = expected.get(section, {})
            new = actual.get(section, {})
            if any(field in old and old[field] != new.get(field) for field in fields):
                raise AgentError("transport_mismatch")
        if expected.get("security") == "reality":
            old = expected.get("realitySettings", {})
            new = actual.get("realitySettings", {})
            expected_key = old.get("settings", {}).get("publicKey", old.get("publicKey", ""))
            if expected_key and (not new.get("privateKey") or self.runtime.public_key(new["privateKey"]) != expected_key):
                raise AgentError("reality_key_mismatch")
            if not set(old.get("serverNames", [])).issubset(new.get("serverNames", [])) or not set(old.get("shortIds", [])).issubset(new.get("shortIds", [])):
                raise AgentError("transport_mismatch")

    def projected(self, inbound, number, state):
        live = self.runtime.users(inbound["tag"])
        rows = {row["email"]: row for row in inbound["settings"].get("clients", []) if row.get("email")}
        owned = state["clients"].get(inbound["tag"], {})
        rows.update(owned)
        clients = []
        for email, row in rows.items():
            projected = dict(row)
            projected.pop("_core", None)
            projected["enable"] = self.live_matches(live.get(email), self.core_client(inbound["protocol"], row))
            clients.append(projected)
        settings = copy.deepcopy(inbound["settings"])
        settings["clients"] = clients
        stream = copy.deepcopy(inbound.get("streamSettings", {}))
        # Private REALITY keys never leave the server, including through debug views.
        reality = stream.get("realitySettings", {})
        private_key = reality.pop("privateKey", None)
        reality.pop("mldsa65Seed", None)
        if private_key:
            reality.setdefault("settings", {})["publicKey"] = self.runtime.public_key(private_key)
        stream.get("tlsSettings", {}).pop("certificates", None)
        return {"id": number, "tag": inbound["tag"], "remark": state.get("names", {}).get(inbound["tag"], inbound["tag"]), "enable": True,
                "protocol": inbound["protocol"], "port": inbound["port"], "settings": settings,
                "streamSettings": stream, "clientStats": [
                    {"email": email, "up": state["counters"].get(email, {}).get("up", 0),
                     "down": state["counters"].get(email, {}).get("down", 0)} for email in owned]}

    def change_inbound(self, arguments, state, config, apply):
        proposal = arguments.get("inbound")
        if not isinstance(proposal, dict) or not re.fullmatch(r"inbound-[1-9][0-9]{0,8}", proposal.get("tag", "")):
            raise AgentError("invalid_inbound", 400)
        tag = proposal["tag"]
        previous = next((row for row in config["inbounds"] if row.get("tag") == tag), None)
        deletion = arguments.get("delete") is True
        if deletion:
            if previous and (previous.get("settings", {}).get("clients") or state["clients"].get(tag) or state.get("deletions", {}).get(tag)):
                raise AgentError("inbound_has_clients")
            config["inbounds"] = [row for row in config["inbounds"] if row.get("tag") != tag]
        else:
            if proposal.get("protocol") not in self.SUPPORTED or not isinstance(proposal.get("port"), int) or not 1 <= proposal["port"] <= 65535:
                raise AgentError("invalid_inbound", 400)
            if "clients" in proposal.get("settings", {}):
                raise AgentError("clients_managed_separately")
            if previous and proposal["protocol"] != previous["protocol"]:
                raise AgentError("protocol_change_blocked")
            merged = copy.deepcopy(proposal)
            merged.setdefault("settings", {})["clients"] = copy.deepcopy(previous.get("settings", {}).get("clients", [])) if previous else []
            if previous:
                # A public projection deliberately omits these server-only secrets.
                old_stream = previous.get("streamSettings", {})
                stream = merged.setdefault("streamSettings", copy.deepcopy(old_stream))
                for section, field in (("realitySettings", "privateKey"), ("realitySettings", "mldsa65Seed"), ("tlsSettings", "certificates")):
                    if section in stream and field not in stream[section] and field in old_stream.get(section, {}):
                        stream[section][field] = old_stream[section][field]
            config["inbounds"] = [row for row in config["inbounds"] if row.get("tag") != tag] + [merged]
        self.inventory(config, state)
        self.runtime.validate(config)
        fingerprint = digest({"current": self.config(), "proposal": proposal, "delete": deletion, "clients": state["clients"]})
        if apply:
            if not hmac.compare_digest(str(arguments.get("fingerprint", "")), fingerprint):
                raise AgentError("preview_expired")
            state.setdefault("replacements", []).append(tag)
            self.publish(config, state)
            self.reconcile(self.state())
        return {"fingerprint": fingerprint, "applied": apply, "delete": deletion, "tag": tag,
                "clients_preserved": len(previous.get("settings", {}).get("clients", [])) if previous else 0}

    def dispatch(self, action, arguments):
        with self.lock:
            self.recover()
            state = self.state()
            config = self.config()
            if action == "health":
                return dict(self.runtime.health(), adopted=state["adopted"])
            if action in ("adopt_preview", "adopt"):
                return self.adopt(arguments, state, config, action == "adopt")
            if action in ("inbounds", "inbound"):
                inventory = self.inventory(config, state)
                self.save(state)
                if action == "inbound":
                    inbound = self.selected(arguments, config, state)
                    return self.projected(inbound, int(arguments["inbound_id"]), state)
                return [self.projected(row, number, state) for number, row in inventory.items()]
            if not state["adopted"]:
                raise AgentError("adoption_required")
            if digest(config) != state["baseline"]:
                raise AgentError("config_changed")
            if action in ("inbound_preview", "inbound_apply"):
                return self.change_inbound(arguments, state, config, action == "inbound_apply")
            self.collect(state)
            if action == "traffic":
                counter = state["counters"].get(arguments.get("email"))
                if counter is None:
                    raise AgentError("client_missing", 404)
                return {"email": arguments["email"], "up": counter["up"], "down": counter["down"]}
            if action == "online":
                # Current sessions, not a guessed online status from traffic changes.
                for rows in state["clients"].values():
                    for client in rows.values():
                        level = str(client.get("_core", {}).get("level", 0))
                        if not config.get("policy", {}).get("levels", {}).get(level, {}).get("statsUserOnline"):
                            raise AgentError("online_not_configured", 503)
                return [email for email in self.runtime.online() if email in state["counters"]]
            if action in ("hwid", "devices", "device_delete"):
                return self.devices(action, arguments, state)
            inbound = self.selected(arguments, config, state)
            tag = inbound["tag"]
            owned = state["clients"].setdefault(tag, {})
            if action == "traffic_reset":
                email = arguments.get("email")
                if email not in owned:
                    raise AgentError("client_missing", 404)
                state["counters"][email]["up"] = state["counters"][email]["down"] = 0
                self.save(state)
                self.reconcile(state)
                return {"success": True}
            if action == "client_put":
                client = copy.deepcopy(arguments["client"])
                self.validate_client(inbound["protocol"], client)
                if inbound["protocol"] == "shadowsocks" and inbound["settings"].get("method", "").startswith("2022-"):
                    raise AgentError("unsupported_protocol")
                email = client["email"]
                credential = arguments.get("credential", self.credential(client))
                if credential != self.credential(client):
                    raise AgentError("credential_mismatch")
                old_email = next((key for key, value in owned.items() if self.credential(value) == credential), email)
                raw = {row.get("email"): row for row in inbound["settings"].get("clients", [])}
                before = owned.get(old_email, raw.get(old_email))
                if before and self.credential(before) != self.credential(client):
                    raise AgentError("credential_mismatch")
                client["_core"] = copy.deepcopy(before.get("_core", raw.get(old_email, {})) if before else {})
                if email != old_email:
                    if "credential" not in arguments:
                        raise AgentError("duplicate_credential")
                    if email in raw or email in owned:
                        raise AgentError("duplicate_email")
                    inbound["settings"]["clients"] = [row for row in inbound["settings"].get("clients", []) if row.get("email") != old_email]
                    owned.pop(old_email, None)
                    state["counters"][email] = state["counters"].pop(old_email, {"up": 0, "down": 0})
                    raw_stats = self.runtime.stats()
                    state["counters"][email].update({"epoch": self.runtime.epoch(),
                        "last_up": raw_stats.get("user>>>" + email + ">>>traffic>>>uplink", 0),
                        "last_down": raw_stats.get("user>>>" + email + ">>>traffic>>>downlink", 0)})
                    state.setdefault("deletions", {}).setdefault(tag, []).append(old_email)
                for other in self.inventory(config, state).values():
                    if other["tag"] != tag and (email in state["clients"].get(other["tag"], {})
                        or any(row.get("email") == email for row in other["settings"].get("clients", []))):
                        raise AgentError("duplicate_email")
                if before is None and email in state["counters"]:
                    raw_stats = self.runtime.stats()
                    state["counters"][email] = {"up": 0, "down": 0, "epoch": self.runtime.epoch(),
                        "last_up": raw_stats.get("user>>>" + email + ">>>traffic>>>uplink", 0),
                        "last_down": raw_stats.get("user>>>" + email + ">>>traffic>>>downlink", 0)}
                owned[email] = client
            elif action == "client_delete":
                email = arguments.get("email")
                before = owned.get(email)
                if before is None:
                    raise AgentError("client_not_owned")
                if self.credential(before) != arguments.get("credential"):
                    raise AgentError("credential_mismatch")
                del owned[email]
                inbound["settings"]["clients"] = [row for row in inbound["settings"].get("clients", []) if row.get("email") != email]
                state.setdefault("deletions", {}).setdefault(tag, []).append(email)
            else:
                raise AgentError("unknown_action", 400)
            self.publish(config, state)
            self.reconcile(self.state())
            return {"success": True}

    def devices(self, action, arguments, state):
        sub_id = arguments.get("sub_id")
        if action != "hwid":
            matches = [row for rows in state["clients"].values() for row in rows.values() if row["email"] == arguments.get("email")]
            if len(matches) != 1:
                raise AgentError("client_missing", 404)
            sub_id = matches[0].get("subId", "")
        clients = [row for rows in state["clients"].values() for row in rows.values() if sub_id and row.get("subId") == sub_id]
        if not clients:
            raise AgentError("client_missing", 404)
        rows = state["devices"].setdefault(sub_id, [])
        if action == "devices":
            return [{key: value for key, value in row.items() if key != "hash"} for row in rows]
        if action == "device_delete":
            state["devices"][sub_id] = [row for row in rows if row["id"] != int(arguments["device_id"])] if arguments.get("device_id") else []
        else:
            hwid = arguments.get("hwid", "")
            if not isinstance(hwid, str) or not 6 <= len(hwid) <= 512:
                return {"status": 404}
            fingerprint = hashlib.sha256(hwid.encode()).hexdigest()
            row = next((row for row in rows if hmac.compare_digest(row["hash"], fingerprint)), None)
            limit = min(row.get("limitHwid", 0) for row in clients)
            if row is None:
                if limit > 0 and len(rows) >= limit:
                    return {"status": 404}
                row = {"id": max([0, *[item["id"] for item in rows]]) + 1, "hash": fingerprint,
                       "fingerprint": fingerprint[:12], "firstSeen": int(time.time() * 1000)}
                rows.append(row)
            row["lastSeen"] = int(time.time() * 1000)
        self.save(state)
        return {"status": 200}

    def tick(self):
        with self.lock:
            self.recover()
            state = self.state()
            if state["adopted"]:
                self.collect(state)
                self.reconcile(state)


def make_server(agent, token, port):
    class Handler(BaseHTTPRequestHandler):
        server_version = "FIREBALL"
        sys_version = ""

        def do_POST(self):
            if self.path != "/v1/rpc" or not hmac.compare_digest(self.headers.get("Authorization", ""), "Bearer " + token):
                self.send_error(404)
                return
            action = "invalid"
            try:
                length = int(self.headers.get("Content-Length", "0"))
                if not 0 < length <= 2 * 1024 * 1024:
                    raise AgentError("request_too_large", 413)
                self.connection.settimeout(10)
                data = json.loads(self.rfile.read(length))
                action = data.get("action", "")
                if not isinstance(action, str) or not re.fullmatch(r"[a-z_]{1,30}", action):
                    raise AgentError("unknown_action", 400)
                arguments = data.get("arguments", {})
                if not isinstance(arguments, dict):
                    raise AgentError("invalid_arguments", 400)
                result = agent.dispatch(action, arguments)
                body = encoded({"protocol": "fireball-xray/1", "result": result})
                status = 200
                logging.info("operation=%s status=ok", action)
            except AgentError as error:
                status = error.status
                body = encoded({"protocol": "fireball-xray/1", "error": error.code})
                logging.warning("operation=%s error=%s", action, error.code)
            except Exception:
                status = 500
                body = encoded({"protocol": "fireball-xray/1", "error": "internal_error"})
                logging.error("operation=%s error=internal_error", action)
            self.send_response(status)
            self.send_header("Content-Type", "application/json")
            self.send_header("Cache-Control", "no-store")
            self.send_header("Content-Length", str(len(body)))
            self.end_headers()
            self.wfile.write(body)

        def log_message(self, *arguments):
            pass

    class Server(HTTPServer):
        def get_request(self):
            connection, address = super().get_request()
            connection.settimeout(10)
            return connection, address

    return Server(("127.0.0.1", port), Handler)


def serve(agent, token, port):
    server = make_server(agent, token, port)
    stop = threading.Event()

    def worker():
        while not stop.wait(5):
            try:
                agent.tick()
            except AgentError as error:
                logging.warning("operation=enforce error=%s", error.code)
            except Exception:
                logging.error("operation=enforce error=internal_error")

    threading.Thread(target=worker, daemon=True).start()
    try:
        server.serve_forever()
    finally:
        stop.set()
        server.server_close()


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--config", default="/usr/local/etc/xray/config.json")
    parser.add_argument("--state", default="/var/lib/fireball-xray")
    parser.add_argument("--token-file", default="/etc/fireball-xray/token")
    parser.add_argument("--xray", default="/usr/local/bin/xray")
    parser.add_argument("--api", default="127.0.0.1:10085")
    parser.add_argument("--service", default="xray.service")
    parser.add_argument("--port", type=int, default=9085)
    args = parser.parse_args()
    os.umask(0o077)
    if not re.fullmatch(r"127\.0\.0\.1:\d{1,5}", args.api) or not re.fullmatch(r"[a-zA-Z0-9_.@-]+\.service", args.service):
        parser.error("Xray API must listen on loopback and the service name must be valid")
    state_path = Path(args.state)
    state_path.mkdir(mode=0o700, parents=True, exist_ok=True)
    process_lock = (state_path / "agent.lock").open("a")
    try:
        fcntl.flock(process_lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
    except BlockingIOError:
        parser.error("Another agent already owns this state directory")
    token = Path(args.token_file).read_text().strip()
    if len(token) < 32 or not token.isascii() or any(c.isspace() for c in token):
        parser.error("Token must contain at least 32 ASCII characters without whitespace")
    logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(message)s")
    runtime = XrayRuntime(args.xray, args.api, args.service, state_path)
    agent = Agent(args.config, state_path / "state.sqlite", runtime)
    serve(agent, token, args.port)


if __name__ == "__main__":
    main()
