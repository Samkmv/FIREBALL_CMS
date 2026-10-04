<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$release = require $root . '/config/version.php';
$manifest = json_decode(file_get_contents($root . '/update.json'), true, 512, JSON_THROW_ON_ERROR);
$version = (string)$release['version'];
if ($manifest['version'] !== $version || !preg_match('/^\d+\.\d+\.\d+$/D', $version)) throw new RuntimeException('Stable version metadata must agree.');
if (!is_file($root . '/vendor/autoload.php')) throw new RuntimeException('Install locked Composer dependencies before packaging.');
$directories = ['app', 'core', 'helpers', 'vendor', 'config', 'database', 'plugins', 'themes', 'public', 'bin'];
$files = [];
foreach ($directories as $directory) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $item) {
        $path = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));
        if ($item->isLink()) throw new RuntimeException('Release cannot contain symbolic links: ' . $path);
        if (!$item->isFile() || str_contains($path, '.bak') || preg_match('~(?:^|/)(?:tests|\.git|node_modules|\.DS_Store)(?:/|$)~', $path)
            || str_starts_with($path, 'public/uploads/') || str_starts_with($path, 'themes/custom/')
            || str_starts_with($path, 'public/assets/cartzilla-html/') || str_starts_with($path, 'config/config.local.php')
            || $path === 'bin/check-release.php') continue;
        $files[$path] = $item->getPathname();
    }
}
foreach (['.htaccess', '.gitattributes', '.gitignore', 'composer.json', 'composer.lock', 'update.json', 'README.md', 'LICENSE',
    'public/uploads/.htaccess', 'public/uploads/.gitkeep', 'tmp/.htaccess', 'tmp/cache/.gitkeep',
    'storage/backups/.htaccess', 'storage/backups/.gitkeep', 'storage/backups/.gitignore',
    'storage/logs/.htaccess', 'storage/logs/.gitkeep'] as $path) if (is_file($root . '/' . $path)) $files[$path] = $root . '/' . $path;
foreach (glob($root . '/docs/deployment/*') ?: [] as $path) if (is_file($path)) $files['docs/deployment/' . basename($path)] = $path;
ksort($files);
$plugins = [];
foreach (glob($root . '/plugins/*/plugin.json') ?: [] as $path) {
    $metadata = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    $slug = basename(dirname($path)); $hashes = [];
    foreach ($files as $relative => $source) if (str_starts_with($relative, 'plugins/' . $slug . '/')) $hashes[$relative] = hash_file('sha256', $source);
    $plugins[$slug] = ['version' => $metadata['version'], 'source_sha256' => hash('sha256', json_encode($hashes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES))];
}
ksort($plugins);
if (!is_dir($root . '/dist') && !mkdir($root . '/dist', 0755)) throw new RuntimeException('Cannot create dist.');
$target = $root . '/dist/fireball-cms-' . $version . '.zip';
$temporary = $target . '.tmp';
$zip = new ZipArchive();
if ($zip->open($temporary, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Cannot create release archive.');
try {
    foreach ($files as $relative => $source) {
        if (!$zip->addFile($source, $relative)) throw new RuntimeException('Cannot add release file.');
        // Normalize timestamps and permissions so unchanged sources produce the same artifact.
        $zip->setMtimeName($relative, strtotime($release['released_at'] . ' 00:00:00 UTC'));
        $zip->setExternalAttributesName($relative, ZipArchive::OPSYS_UNIX, 0100644 << 16);
    }
    $zip->addFromString('release-plugins.json', json_encode(['engine_version' => $version, 'plugins' => $plugins], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    $zip->setMtimeName('release-plugins.json', strtotime($release['released_at'] . ' 00:00:00 UTC'));
    $zip->setExternalAttributesName('release-plugins.json', ZipArchive::OPSYS_UNIX, 0100644 << 16);
} finally { if (!$zip->close()) throw new RuntimeException('Cannot finalize archive.'); }
if (!rename($temporary, $target)) throw new RuntimeException('Cannot publish local artifact.');
$verify = new ZipArchive();
if ($verify->open($target) !== true || $verify->numFiles !== count($files) + 1) throw new RuntimeException('Archive inventory differs.');
try {
    foreach ($files as $relative => $source) {
        $contents = $verify->getFromName($relative);
        if ($contents === false || !hash_equals(hash_file('sha256', $source), hash('sha256', $contents))) throw new RuntimeException('Archive checksum differs: ' . $relative);
    }
} finally { $verify->close(); }
$digest = hash_file('sha256', $target);
file_put_contents($target . '.sha256', $digest . '  ' . basename($target) . "\n");
echo basename($target) . ': ' . (count($files) + 1) . ' files, SHA256 ' . $digest . "\n";
