<?php
declare(strict_types=1);
require dirname(__DIR__) . '/plugins/reel-player/src/Services/MediaStorage.php';

use Fireball\ReelPlayer\Services\MediaStorage;

$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (!$condition) throw new RuntimeException($message);
};
$temporary = __DIR__ . '/.tmp';
if (!is_dir($temporary)) mkdir($temporary, 0700, true);
$root = $temporary . '/tape-room-images-' . bin2hex(random_bytes(8));
mkdir($root . '/7', 0700, true);
$storage = new MediaStorage($root);
$relative = '7/' . str_repeat('a', 40) . '.png';
$path = $storage->path($relative);
copy(dirname(__DIR__) . '/plugins/reel-player/assets/cover-light.png', $path);
$originalHash = hash_file('sha256', $path);
$cache = $root . '/7/.covers/' . str_repeat('a', 40);
try {
    $previousBytes = 0;
    foreach (MediaStorage::COVER_SIZES as $size) {
        $variant = $storage->coverVariant($relative, 'image/png', $size);
        $check($variant['mime'] === 'image/webp' && is_file($variant['path']), 'A WebP derivative exists for size ' . $size);
        $dimensions = getimagesize($variant['path']);
        $check($dimensions[0] === $size && $dimensions[1] === $size, 'Display dimensions match the requested bounded size');
        $check(filesize($variant['path']) < filesize($path) / 10, 'Transferred image payload is substantially smaller than the original');
        $check(filesize($variant['path']) > $previousBytes, 'Larger variants retain more image detail');
        $previousBytes = filesize($variant['path']);
        $hash = hash_file('sha256', $variant['path']);
        $modified = filemtime($variant['path']);
        $again = $storage->coverVariant($relative, 'image/png', $size);
        $check($again === $variant && filemtime($again['path']) === $modified && hash_file('sha256', $again['path']) === $hash, 'Repeated requests reuse the same derivative instead of re-encoding');
        $check((fileperms($variant['path']) & 0777) === 0600, 'Uploaded image derivatives remain private on disk');
    }
    $check(count(glob($cache . '/*.webp')) === 3, 'At most the three permitted sizes are generated');
    foreach ([0, -1, 1, 95, 1000, PHP_INT_MAX] as $size) {
        $check($storage->coverVariant($relative, 'image/png', $size) === ['path' => $path, 'mime' => 'image/png'], 'An unbounded size does not generate an arbitrary derivative');
    }
    $check(hash_file('sha256', $path) === $originalHash, 'Full-resolution original remains byte-for-byte intact');
    $check($storage->coverVariant($relative, 'application/octet-stream', 320)['path'] === $path, 'Unsupported image MIME never enters the decoder');
    $limit = ini_get('memory_limit');
    ini_set('memory_limit', '16M');
    $check($storage->coverVariant($relative, 'image/png', 320)['path'] === $path, 'Memory-constrained systems fall back safely to the original');
    ini_set('memory_limit', $limit);
    $timestamp = filemtime($path);
    file_put_contents($path, "same-second-new-source", FILE_APPEND);
    touch($path, $timestamp);
    $updated = $storage->coverVariant($relative, 'image/png', 320);
    $check(!str_contains($updated['path'], $originalHash) && $updated['mime'] === 'image/webp', 'A same-timestamp replacement receives a fresh fingerprint and derivative');
    $check(count(glob($cache . '/*.webp')) === 1, 'Replacement removes all obsolete source derivatives');
    $portrait = '7/' . str_repeat('b', 40) . '.png';
    $source = imagecreatefrompng($path);
    $cropped = imagecrop($source, ['x' => 0, 'y' => 0, 'width' => 627, 'height' => 1254]);
    imagepng($cropped, $storage->path($portrait));
    imagedestroy($cropped); imagedestroy($source);
    $portraitVariant = $storage->coverVariant($portrait, 'image/png', 320);
    $dimensions = getimagesize($portraitVariant['path']);
    $check($dimensions[0] === 160 && $dimensions[1] === 320, 'Portrait aspect ratio is preserved without cropping or stretching');
    $camera = imagecreatetruecolor(400, 800);
    $colors = [0xff0000, 0x00ff00, 0x0000ff, 0xffff00];
    foreach ($colors as $index => $color) imagefilledrectangle($camera, ($index % 2) * 200, intdiv($index, 2) * 400, ($index % 2 + 1) * 200 - 1, (intdiv($index, 2) + 1) * 400 - 1, $color);
    $cameraFile = $root . '/camera-fixture.jpg';
    imagejpeg($camera, $cameraFile, 92); imagedestroy($camera);
    $cameraBytes = file_get_contents($cameraFile);
    $positions = [1 => [0,1,2,3], 2 => [1,0,3,2], 3 => [3,2,1,0], 4 => [2,3,0,1], 5 => [0,2,1,3], 6 => [2,0,3,1], 7 => [3,1,2,0], 8 => [1,3,0,2]];
    foreach ($positions as $orientation => $expected) {
        $exif = "Exif\0\0II" . pack('vVv', 42, 8, 1) . pack('vvVv', 0x0112, 3, 1, $orientation) . "\0\0" . pack('V', 0);
        $oriented = '7/' . str_repeat((string)$orientation, 40) . '.jpg';
        $orientedPath = $storage->path($oriented);
        file_put_contents($orientedPath, substr($cameraBytes, 0, 2) . "\xff\xe1" . pack('n', strlen($exif) + 2) . $exif . substr($cameraBytes, 2));
        $cameraHash = hash_file('sha256', $orientedPath);
        $variant = $storage->coverVariant($oriented, 'image/jpeg', 320);
        $decoded = imagecreatefromwebp($variant['path']);
        $actualWidth = imagesx($decoded); $actualHeight = imagesy($decoded);
        $check($actualWidth === ($orientation >= 5 ? 320 : 160) && $actualHeight === ($orientation >= 5 ? 160 : 320), 'Phone JPEG orientation ' . $orientation . ' keeps the correct display dimensions');
        $matches = true;
        foreach ($expected as $index => $colorIndex) {
            $actual = imagecolorat($decoded, (int)(($index % 2 + .5) * $actualWidth / 2), (int)((intdiv($index, 2) + .5) * $actualHeight / 2));
            foreach ([0,8,16] as $shift) $matches = $matches && abs(($actual >> $shift & 255) - ($colors[$colorIndex] >> $shift & 255)) < 12;
        }
        imagedestroy($decoded);
        $check($matches && hash_file('sha256', $orientedPath) === $cameraHash, 'Phone JPEG orientation ' . $orientation . ' preserves corner placement and untouched original bytes');
    }
    $small = '7/' . str_repeat('c', 40) . '.png';
    file_put_contents($storage->path($small), base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/G94AAAAASUVORK5CYII='));
    $check($storage->coverVariant($small, 'image/png', 640)['path'] === $storage->path($small), 'Small images are never enlarged');
    $broken = '7/' . str_repeat('d', 40) . '.png';
    file_put_contents($storage->path($broken), 'not an image');
    $check($storage->coverVariant($broken, 'image/png', 320)['path'] === $storage->path($broken), 'A malformed legacy image falls back without a PHP error');
    $missing = '7/' . str_repeat('e', 40) . '.png';
    $check($storage->coverVariant($missing, 'image/png', 320)['path'] === $storage->path($missing), 'Missing source remains missing and cannot resurrect stale derivatives');
    try { $storage->coverVariant('../7/source.png', 'image/png', 320); $check(false, 'Traversal must fail'); }
    catch (InvalidArgumentException) { $check(true, 'Thumbnail source paths keep strict traversal protection'); }
    $workerCode = 'require $argv[1]; echo json_encode((new \\Fireball\\ReelPlayer\\Services\\MediaStorage($argv[2]))->coverVariant($argv[3],"image/png",320));';
    $classFile = dirname(__DIR__) . '/plugins/reel-player/src/Services/MediaStorage.php';
    // This PHP build statically bundles GD; disabling its WebP entry point
    // exercises the same function_exists fallback as a server without GD.
    $withoutGd = proc_open([PHP_BINARY, '-d', 'disable_functions=imagewebp', '-r', $workerCode, $classFile, $root, $relative],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', $root . '/without-gd.log', 'w']], $pipes);
    if (!is_resource($withoutGd)) throw new RuntimeException('Unable to start no-GD fixture.');
    fclose($pipes[0]);
    $withoutGdResult = json_decode(stream_get_contents($pipes[1]), true); fclose($pipes[1]);
    $check(proc_close($withoutGd) === 0 && $withoutGdResult === ['path' => $path, 'mime' => 'image/png'], 'Servers without the GD WebP encoder retain a working original image response');
    $check(file_get_contents($root . '/without-gd.log') === '', 'The no-GD fallback emits no PHP warnings');
    $concurrent = '7/' . str_repeat('f', 40) . '.png';
    copy($path, $storage->path($concurrent));
    $workers = [];
    for ($index = 0; $index < 4; $index++) {
        $process = proc_open([PHP_BINARY, '-r', $workerCode, $classFile, $root, $concurrent],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', $root . '/worker-' . $index . '.log', 'w']], $pipes);
        if (!is_resource($process)) throw new RuntimeException('Unable to start concurrent fixture.');
        fclose($pipes[0]); $workers[] = [$process, $pipes[1], $index];
    }
    $concurrentResult = null;
    foreach ($workers as [$process, $output, $index]) {
        $value = json_decode(stream_get_contents($output), true); fclose($output);
        $check(proc_close($process) === 0 && $value['mime'] === 'image/webp' && ($concurrentResult === null || $value === $concurrentResult), 'Concurrent requests agree on one valid atomic derivative');
        $check(file_get_contents($root . '/worker-' . $index . '.log') === '', 'Concurrent decoding leaves no warnings or partial-file errors');
        $concurrentResult = $value;
    }
    $concurrentCache = $root . '/7/.covers/' . str_repeat('f', 40);
    $check(count(glob($concurrentCache . '/*.webp')) === 1 && count(glob($concurrentCache . '/.variant-*')) === 0, 'Concurrent first load produces one complete cached derivative and no temporary files');
    $storage->delete($relative);
    $check(!is_file($path) && !is_dir($cache), 'Deleting or replacing an uploaded cover cleans its cached variants');
    $check($storage->coverVariant($relative, 'image/png', 320)['path'] === $path, 'Deleted cover never returns a cached derivative');
    $check(is_file($storage->path($portrait)), 'Removing one image preserves all other uploads');
    echo "Tape Room images: {$checks} checks passed.\n";
} finally {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    rmdir($root);
}
