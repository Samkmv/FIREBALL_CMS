"""Exercise real CMS routes, helpers and image responses in an isolated installation."""
import base64
import hashlib
from html.parser import HTMLParser
import json
import os
from pathlib import Path
import shutil
import socket
import struct
import subprocess
import tempfile
import time
import urllib.error
import urllib.request
import urllib.parse
import zlib

project = Path(__file__).resolve().parents[1]
(project / "tests/.tmp").mkdir(parents=True, exist_ok=True)

server = None
checks = []
php = os.environ.get("FBL_TEST_PHP", "/Applications/MAMP/bin/php/php8.2.0/bin/php")
with socket.socket() as port_probe:
    port_probe.bind(("127.0.0.1", 0))
    port = port_probe.getsockname()[1]
base = "http://127.0.0.1:" + str(port)

def check(label, condition):
    if not condition:
        raise AssertionError(label)
    checks.append(label)

def request(path, headers=None, method="GET"):
    try:
        response = urllib.request.urlopen(urllib.request.Request(base + path, headers=headers or {}, method=method), timeout=10)
    except urllib.error.HTTPError as response:
        return response.code, response.headers, response.read()
    with response:
        return response.status, response.headers, response.read()

def select(mode):
    status, _, body = request("/test/select?mode=" + mode)
    if status != 200:
        raise AssertionError(body.decode(errors="replace"))

def dimensions(data):
    return struct.unpack(">II", data[16:24]) if data[:8] == b"\x89PNG\r\n\x1a\n" else None

def inline_png(link):
    prefix = "data:image/png;base64,"
    href = link["href"]
    if not href.startswith(prefix):
        raise AssertionError("Installer must receive its image without fetching a URL")
    return base64.b64decode(href[len(prefix):], validate=True)

def rgb_pixels(data, background=(255, 255, 255)):
    """Independent 8-bit PNG decoder for RGB/RGBA fixtures; compare branding, not compression."""
    width, height = dimensions(data)
    if data[24] != 8 or data[25] not in (2, 6):
        raise AssertionError("Unexpected PNG fixture format")
    channels = 3 if data[25] == 2 else 4
    offset, compressed = 8, b""
    while offset < len(data):
        length = struct.unpack(">I", data[offset:offset + 4])[0]
        if data[offset + 4:offset + 8] == b"IDAT":
            compressed += data[offset + 8:offset + 8 + length]
        offset += 12 + length
    raw, cursor, previous, pixels = zlib.decompress(compressed), 0, bytearray(width * channels), []
    def paeth(a, b, c):
        prediction = a + b - c
        distances = (abs(prediction - a), abs(prediction - b), abs(prediction - c))
        return (a, b, c)[distances.index(min(distances))]
    for _ in range(height):
        kind = raw[cursor]
        row = bytearray(raw[cursor + 1:cursor + 1 + width * channels])
        cursor += 1 + width * channels
        for i in range(len(row)):
            a, b, c = row[i - channels] if i >= channels else 0, previous[i], previous[i - channels] if i >= channels else 0
            predictors = (0, a, b, (a + b) // 2, paeth(a, b, c))
            row[i] = (row[i] + predictors[kind]) & 255
        for i in range(0, len(row), channels):
            alpha = row[i + 3] / 255 if channels == 4 else 1
            pixels.extend(round(row[i + channel] * alpha + background[channel] * (1 - alpha)) for channel in range(3))
        previous = row
    return pixels

class Links(HTMLParser):
    def __init__(self):
        super().__init__()
        self.links = []
    def handle_starttag(self, tag, attrs):
        if tag == "link":
            self.links.append(dict(attrs))

try:
    public = scratch / "installation/public"
    icons = public / "assets/default/pwa"
    shutil.copytree(project / "public/assets/default/pwa", icons)
    initial = {p.name: hashlib.sha256(p.read_bytes()).hexdigest() for p in icons.iterdir() if p.is_file()}
    (public / "uploads").mkdir()
    shutil.copyfile(icons / "icon-512.png", public / "uploads/source-a.png")
    shutil.copyfile(project / "themes/tape-room/assets/images/app-icon-192.png", public / "uploads/source-b.png")
    (public / "uploads/source.svg").write_text('<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"><rect width="100" height="100" fill="red"/></svg>')
    (public / "assets/img").mkdir()
    shutil.copyfile(icons / "icon-512.png", public / "assets/img/fbl_logo.png")
    theme = scratch / "installation/themes/tape-room"
    (theme / "assets/images").mkdir(parents=True)
    shutil.copyfile(project / "themes/tape-room/theme.json", theme / "theme.json")
    theme_icon = (project / "themes/tape-room/assets/images/apple-touch-icon.png").read_bytes()
    (theme / "assets/images/apple-touch-icon.png").write_bytes(theme_icon)
    with (scratch / "server.log").open("wb") as log:
        server = subprocess.Popen([php, "-S", "127.0.0.1:" + str(port), str(project / "tests/pwa_installation_fixture.php")],
                                  env={**os.environ, "FBL_PWA_TEST_ROOT": str(scratch)}, stdout=log, stderr=log, cwd=project)
        for attempt in range(30):
            try:
                select("default")
                break
            except (OSError, AssertionError):
                if server.poll() is not None:
                    raise RuntimeError((scratch / "server.log").read_text())
                time.sleep(0.1)
        else:
            raise RuntimeError((scratch / "server.log").read_text())
        status, headers, image = request("/apple-touch-icon.png")
        check("Default theme branding is served by the root Apple icon", status == 200 and max(abs(a-b) for a,b in zip(rgb_pixels(image), rgb_pixels(theme_icon))) <= 2)
        check("Root icon is a correctly typed 180px PNG", headers.get_content_type() == "image/png" and dimensions(image) == (180, 180))
        _, _, html = request("/")
        links = Links()
        links.feed(html.decode())
        default_apple = [link for link in links.links if link.get("rel") == "apple-touch-icon"]
        check("Default theme declares exactly one inline Apple PNG", len(default_apple) == 1 and inline_png(default_apple[0]) == image)
        check("Apple PNG is opaque RGB and remains independently decodable", image[25] == 2 and len(rgb_pixels(image)) == 180 * 180 * 3)
        select("a")
        status, headers, image_a = request("/apple-touch-icon.png")
        etag_a = headers.get("ETag")
        check("An uploaded icon works without a login cookie", status == 200 and dimensions(image_a) == (180, 180))
        check("ETag fingerprints the actual response", etag_a == '"' + hashlib.sha256(image_a).hexdigest() + '"')
        check("Root icon revalidates instead of caching an old choice indefinitely", "must-revalidate" in headers.get("Cache-Control", "") and "immutable" not in headers.get("Cache-Control", ""))
        check("PNG Content-Length matches its body", int(headers["Content-Length"]) == len(image_a))
        status, _, html = request("/")
        links = Links()
        links.feed(html.decode())
        apple = [link for link in links.links if link.get("rel") == "apple-touch-icon"]
        check("HTML declares one inline PNG from the selected icon set", len(apple) == 1 and inline_png(apple[0]) == image_a)
        apple_url_a = apple[0]["href"]
        check("The install image needs no cookie, JavaScript, service worker or image HTTP request", dimensions(inline_png(apple[0])) == (180, 180))
        check("Apple installation icon does not trigger an unused image preload", not any(link.get("rel") == "preload" and link.get("href") == apple[0]["href"] for link in links.links))
        status, head_headers, body = request("/apple-touch-icon.png", method="HEAD")
        check("HEAD returns image headers without HTML or a body", status == 200 and not body and head_headers.get_content_type() == "image/png" and head_headers.get("Content-Length") == str(len(image_a)))
        status, _, body = request("/apple-touch-icon.png", {"If-None-Match": etag_a})
        check("Unchanged icon supports a conditional 304 response", status == 304 and not body)
        status, _, alias = request("/apple-touch-icon-precomposed.png")
        check("Legacy Apple root discovery resolves to the same selected PNG", status == 200 and alias == image_a)
        status, _, injected = request("/apple-touch-icon.png?path=/etc/passwd")
        check("The image endpoint ignores caller-provided filesystem paths", status == 200 and injected == image_a)
        status, _, _ = request("/apple-touch-iconXpng")
        check("The image route matches a literal PNG extension", status == 404)
        select("b")
        status, updated_headers, image_b = request("/apple-touch-icon.png", {"If-None-Match": etag_a})
        check("Changing the admin icon invalidates the previous ETag", status == 200 and image_b != image_a and updated_headers.get("ETag") != etag_a)
        _, _, html = request("/")
        links_b = Links()
        links_b.feed(html.decode())
        apple_url_b = next(link["href"] for link in links_b.links if link.get("rel") == "apple-touch-icon")
        check("Changing the admin icon changes the embedded image without clearing any cache", apple_url_b != apple_url_a)
        check("Safari's new inline PNG matches public root discovery", inline_png(next(link for link in links_b.links if link.get("rel") == "apple-touch-icon")) == image_b)
        _, _, theme_head = request("/test/theme-head")
        theme_links = Links(); theme_links.feed(theme_head.decode())
        theme_apples = [link for link in theme_links.links if link.get("rel") == "apple-touch-icon"]
        check("Tape Room's head overrides do not replace or duplicate the shared inline Apple icon", len(theme_apples) == 1 and inline_png(theme_apples[0]) == image_b)
        status, _, body = request("/apple-touch-icon.png", {"If-None-Match": '"other", W/' + updated_headers["ETag"]})
        check("Weak and multiple validators use standard conditional semantics", status == 304 and not body)
        _, _, manifest = request("/manifest.webmanifest")
        manifest = json.loads(manifest)
        apple_manifest = next(icon for icon in manifest["icons"] if icon["sizes"] == "180x180")
        status, _, apple_from_manifest = request(apple_manifest["src"].removeprefix(base))
        check("Manifest consumers retain a public HTTP 180px fallback with the same bytes", status == 200 and apple_from_manifest == image_b and apple_manifest["purpose"] == "any")
        check("Manifest URLs remain HTTP resources, not Base64 data", all(icon["src"].startswith(base + "/") for icon in manifest["icons"]))
        pngs = [icon for icon in manifest["icons"] if icon["sizes"] in ("192x192", "512x512")]
        for icon in pngs:
            status, image_headers, data = request(icon["src"].removeprefix(base))
            check("Manifest icon remains available: " + icon["sizes"] + " " + icon["purpose"], status == 200 and image_headers.get_content_type() == "image/png" and dimensions(data) == tuple(map(int, icon["sizes"].split("x"))))
        for policy, embedded in [("blocked", False), ("default-blocked", False), ("allowed", True), ("report", True)]:
            _, _, page = request("/?csp=" + policy)
            csp_links = Links(); csp_links.feed(page.decode())
            href = next(link["href"] for link in csp_links.links if link.get("rel") == "apple-touch-icon")
            check("CSP policy is respected: " + policy, href.startswith("data:") == embedded and (embedded or href.startswith(base + "/apple-touch-icon.png?v=")))
        select("flat")
        _, _, flat_html = request("/")
        flat_links = Links(); flat_links.feed(flat_html.decode())
        _, _, flat_png = request("/apple-touch-icon.png")
        check("Old mutable uploads/pwa sets work without resaving settings or regenerating files on page requests", inline_png(next(link for link in flat_links.links if link.get("rel") == "apple-touch-icon")) == flat_png and flat_png == image_a)
        select("default")
        _, white_headers, white = request("/apple-touch-icon.png")
        select("dark")
        _, dark_headers, dark = request("/apple-touch-icon.png")
        check("Changing the app background changes opaque PNG bytes and ETag", white != dark and white_headers["ETag"] != dark_headers["ETag"])
        _, _, limit = request("/test/inline-limit")
        check("Oversized inline payloads fall back instead of bloating HTML", json.loads(limit)["inline"] == "")
        select("svg")
        status, _, data = request("/apple-touch-icon.png")
        check("SVG favicon retains a real raster Apple icon", status == 200 and dimensions(data) == (180, 180))
        check("Icon requests and generation never rewrite shipped icons", initial == {p.name: hashlib.sha256(p.read_bytes()).hexdigest() for p in icons.iterdir() if p.is_file()})
        select("corrupt")
        status, _, body = request("/apple-touch-icon.png")
        check("A truncated PNG header is not emitted as a usable install image", status == 404 and not body)
        select("unavailable")
        status, _, body = request("/apple-touch-icon.png")
        check("Missing icon is an empty 404, never a 200 HTML fallback", status == 404 and not body)
        out = project / "tests/artifacts/pwa-installation"
        out.mkdir(parents=True, exist_ok=True)
        (out / "checks.json").write_text(json.dumps({"passed": len(checks), "checks": checks}, ensure_ascii=False, indent=2) + "\n")
        (out / "selected-apple-icon.png").write_bytes(image_b)
        print(json.dumps({"passed": len(checks), "checks": checks}, ensure_ascii=False, indent=2))
finally:
    if server is not None:
        server.terminate()
        server.wait(timeout=5)
    shutil.rmtree(scratch)
