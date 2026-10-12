<?php
declare(strict_types=1);

// Deterministic display-size WebP derivatives; the owner's original artwork is
// retained byte-for-byte. All generation scratch space belongs to this test.
require dirname(__DIR__) . '/plugins/reel-player/src/Services/MediaStorage.php';

use Fireball\ReelPlayer\Services\MediaStorage;

$write = in_array('--write', $argv, true);
$assets = dirname(__DIR__) . '/plugins/reel-player/assets';
$temporary = __DIR__ . '/.tmp';
if (!is_dir($temporary)) mkdir($temporary, 0700, true);
$root = $temporary . '/tape-room-image-assets-' . bin2hex(random_bytes(8));
mkdir($root . '/1', 0700, true);
$storage = new MediaStorage($root);
$result = [];
try {
    foreach (['cover-light', 'cover-dark', 'artist-light', 'artist-dark'] as $index => $name) {
        $original = $assets . '/' . $name . '.png';
        $hash = hash_file('sha256', $original);
        $relative = '1/' . str_repeat((string)($index + 1), 40) . '.png';
        copy($original, $storage->path($relative));
        foreach (MediaStorage::COVER_SIZES as $size) {
            $variant = $storage->coverVariant($relative, 'image/png', $size);
            if ($variant['mime'] !== 'image/webp') throw new RuntimeException('GD WebP support and sufficient memory are required to generate display assets.');
            $destination = $assets . '/' . $name . '-' . $size . '.webp';
            if ($write) {
                copy($variant['path'], $destination);
                chmod($destination, 0644);
            }
            if (!is_file($destination)) throw new RuntimeException('Missing ' . basename($destination) . '; run this helper with --write.');
            $info = getimagesize($destination);
            if ($info[0] !== $size || $info[1] !== $size || $info['mime'] !== 'image/webp') throw new RuntimeException('Invalid ' . basename($destination));
            if (hash_file('sha256', $destination) !== hash_file('sha256', $variant['path'])) throw new RuntimeException('Stale display asset ' . basename($destination));
            $result[basename($destination)] = ['bytes' => filesize($destination), 'originalBytes' => filesize($original), 'width' => $size];
        }
        if (hash_file('sha256', $original) !== $hash) throw new RuntimeException('Original artwork changed.');
    }
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
} finally {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    rmdir($root);
}
