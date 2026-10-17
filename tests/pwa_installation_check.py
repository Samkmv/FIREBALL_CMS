"""Exercise real CMS routes, helpers and image responses in an isolated installation."""
import hashlib
from html.parser import HTMLParser
import json
import os
from pathlib import Path
import shutil
import struct
import subprocess
import tempfile
import time
import urllib.error
import urllib.request
import urllib.parse

project = Path(__file__).resolve().parents[1]
scratch = Path(tempfile.mkdtemp(prefix="pwa-installation-", dir=project / "tests/.tmp"))
server = None
checks = []
php = os.environ.get("FBL_TEST_PHP", "/Applications/MAMP/bin/php/php8.2.0/bin/php")
base = "http://127.0.0.1:8796"

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
        server = subprocess.Popen([php, "-S", "127.0.0.1:8796", str(project / "tests/pwa_installation_fixture.php")],
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
        check("Default theme branding is served by the root Apple icon", status == 200 and image == theme_icon)
        check("Root icon is a correctly typed 180px PNG", headers.get_content_type() == "image/png" and dimensions(image) == (180, 180))
        _, _, html = request("/")
        links = Links()
        links.feed(html.decode())
        default_apple = [link for link in links.links if link.get("rel") == "apple-touch-icon"]
        check("Default theme declares its own versioned static Apple PNG", len(default_apple) == 1 and urllib.parse.urlparse(default_apple[0]["href"]).path == "/themes/tape-room/assets/images/apple-touch-icon.png" and "v=" in default_apple[0]["href"])
        status, _, static_default = request(default_apple[0]["href"].removeprefix(base))
        check("Default static Apple icon matches root discovery", status == 200 and static_default == theme_icon)
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
        check("HTML declares one versioned static PNG from the selected icon set", len(apple) == 1 and urllib.parse.urlparse(apple[0]["href"]).path.startswith("/uploads/pwa/generated-") and urllib.parse.urlparse(apple[0]["href"]).path.endswith("/apple-touch-icon.png") and "v=" in apple[0]["href"])
        apple_url_a = apple[0]["href"]
        status, static_headers, static_image = request(apple_url_a.removeprefix(base))
        check("The declared static PNG is public and matches the root icon", status == 200 and static_headers.get_content_type() == "image/png" and static_image == image_a)
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
        check("Changing the admin icon changes the URL advertised to Safari", apple_url_b != apple_url_a)
        status, _, static_image_b = request(apple_url_b.removeprefix(base))
        check("Safari's new URL serves the newly selected PNG", status == 200 and static_image_b == image_b)
        status, _, body = request("/apple-touch-icon.png", {"If-None-Match": '"other", W/' + updated_headers["ETag"]})
        check("Weak and multiple validators use standard conditional semantics", status == 304 and not body)
        _, _, manifest = request("/manifest.webmanifest")
        manifest = json.loads(manifest)
        pngs = [icon for icon in manifest["icons"] if icon["sizes"] in ("192x192", "512x512")]
        for icon in pngs:
            status, image_headers, data = request(icon["src"].removeprefix(base))
            check("Manifest icon remains available: " + icon["sizes"] + " " + icon["purpose"], status == 200 and image_headers.get_content_type() == "image/png" and dimensions(data) == tuple(map(int, icon["sizes"].split("x"))))
        select("svg")
        status, _, data = request("/apple-touch-icon.png")
        check("SVG favicon retains a real raster Apple icon", status == 200 and dimensions(data) == (180, 180))
        check("Icon requests and generation never rewrite shipped icons", initial == {p.name: hashlib.sha256(p.read_bytes()).hexdigest() for p in icons.iterdir() if p.is_file()})
        select("unavailable")
        status, _, body = request("/apple-touch-icon.png")
        check("Missing icon is an empty 404, never a 200 HTML fallback", status == 404 and not body)
        out = project / "tests/artifacts/pwa-installation"
        out.mkdir(exist_ok=True)
        (out / "checks.json").write_text(json.dumps({"passed": len(checks), "checks": checks}, ensure_ascii=False, indent=2) + "\n")
        (out / "selected-apple-icon.png").write_bytes(image_b)
        print(json.dumps({"passed": len(checks), "checks": checks}, ensure_ascii=False, indent=2))
finally:
    if server is not None:
        server.terminate()
        server.wait(timeout=5)
    shutil.rmtree(scratch)
