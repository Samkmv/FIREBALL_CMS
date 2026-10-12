"""Build and verify an installable Tape Room release without tests or user data."""
from pathlib import Path
import hashlib
import json
import zipfile

project = Path(__file__).resolve().parent.parent
plugin = project / "plugins/reel-player"
version = json.loads((plugin / "plugin.json").read_text())["version"]
# The prepared favicon is reserved for a later task; it is not wired or released.
excluded_assets = {"assets/favicon-tape-room-plugin.png"}
files = sorted(path for path in plugin.rglob("*") if path.is_file()
               and path.relative_to(plugin).as_posix() not in excluded_assets
               and "tests" not in path.relative_to(plugin).parts
               and not any(part.startswith(".") for part in path.relative_to(plugin).parts))
archive = project / "dist" / f"reel-player-{version}.zip"
archive.parent.mkdir(exist_ok=True)
with zipfile.ZipFile(archive, "w", zipfile.ZIP_DEFLATED) as bundle:
    for path in files:
        bundle.write(path, Path("reel-player") / path.relative_to(plugin))
with zipfile.ZipFile(archive) as bundle:
    assert bundle.testzip() is None
    expected = {str(Path("reel-player") / path.relative_to(plugin)): path for path in files}
    assert set(bundle.namelist()) == set(expected)
    for name, path in expected.items():
        assert bundle.read(name) == path.read_bytes(), name
    assert json.loads(bundle.read("reel-player/plugin.json"))["version"] == version
checksum = hashlib.sha256(archive.read_bytes()).hexdigest()
archive.with_suffix(".zip.sha256").write_text(f"{checksum}  {archive.name}\n")
print(f"Verified {archive.relative_to(project)}: {len(files)} files, {archive.stat().st_size} bytes, SHA-256 {checksum}")
