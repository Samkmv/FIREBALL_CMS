<?php
declare(strict_types=1);
namespace Fireball\ReelPlayer\Services;

/** Bounded, private scratch data; never store tokens here. */
final class DriveCache
{
    public const PREFIX_BYTES = 2 * 1048576;
    public const TTL = 900;
    public const METADATA_TTL = 300;
    public const OWNER_BYTES = 32 * 1048576;
    public const TOTAL_BYTES = 128 * 1048576;
    private string $directory;

    public function __construct(private string $root, int $owner, private string $account)
    {
        if ($owner < 1 || !preg_match('/^[a-f0-9]{64}$/D', $account)) throw new \InvalidArgumentException('Invalid cache owner.');
        $this->directory = $root . '/' . $owner . '/drive-cache';
    }

    private function directory(): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0700, true) && !is_dir($this->directory)) throw new \RuntimeException('Не удалось подготовить буфер Google Drive.');
    }

    private function key(string $id): string { return hash('sha256', $this->account . ':' . $id); }
    private function path(string $id, string $extension): string { return $this->directory . '/' . $this->key($id) . '.' . $extension; }
    private static function version(array $file): string
    {
        return hash('sha256', json_encode([$file['id'], $file['size'], $file['headRevisionId'] ?? '', $file['md5Checksum'] ?? '', $file['modifiedTime'] ?? ''], JSON_THROW_ON_ERROR));
    }

    public function metadata(string $id): ?array
    {
        $path = $this->path($id, 'meta');
        if (!is_file($path) || filemtime($path) < time() - self::METADATA_TTL) return null;
        $file = json_decode((string)file_get_contents($path), true);
        return is_array($file) && ($file['id'] ?? '') === $id ? $file : null;
    }

    public function saveMetadata(array $file): void
    {
        $this->directory(); $this->cleanup();
        $allowed = array_intersect_key($file, array_flip(['id','name','mimeType','size','headRevisionId','md5Checksum','modifiedTime','capabilities']));
        $this->atomic($this->path($file['id'], 'meta'), json_encode($allowed, JSON_THROW_ON_ERROR));
    }

    public function prefix(array $file): ?string
    {
        $path = $this->path($file['id'], 'bin'); $stamp = $this->path($file['id'], 'info');
        if (!is_file($path) || !is_file($stamp) || filemtime($stamp) < time() - self::TTL) return null;
        $info = json_decode((string)file_get_contents($stamp), true);
        if (!is_array($info) || ($info['version'] ?? '') !== self::version($file) || filesize($path) !== min(self::PREFIX_BYTES, (int)$file['size'])) return null;
        return $path;
    }

    public function savePrefix(array $file, string $bytes): void
    {
        if (strlen($bytes) !== min(self::PREFIX_BYTES, (int)$file['size'])) throw new \RuntimeException('Неполный буфер Google Drive.');
        $this->directory();
        // Reserve the incoming allocation before publishing it. A short global
        // lock protects accounting, never a network transfer or an audio stream.
        $budget = fopen($this->root . '/drive-cache-budget.lock', 'c');
        if (!$budget || !flock($budget, LOCK_EX)) throw new \RuntimeException('Не удалось сохранить буфер.');
        @chmod($this->root . '/drive-cache-budget.lock', 0600);
        try {
            $this->invalidate($file['id'], false);
            $this->cleanup(strlen($bytes));
            $this->atomic($this->path($file['id'], 'bin'), $bytes);
            $this->atomic($this->path($file['id'], 'info'), json_encode(['version'=>self::version($file)], JSON_THROW_ON_ERROR));
        } finally { flock($budget, LOCK_UN); fclose($budget); }
    }

    private function atomic(string $path, string $bytes): void
    {
        $tmp = $this->directory . '/' . bin2hex(random_bytes(16)) . '.tmp';
        if (file_put_contents($tmp, $bytes, LOCK_EX) === false) throw new \RuntimeException('Не удалось сохранить буфер.');
        chmod($tmp, 0600);
        if (!rename($tmp, $path)) { @unlink($tmp); throw new \RuntimeException('Не удалось сохранить буфер.'); }
    }

    public function invalidate(string $id, bool $metadata = true): void
    {
        foreach ($metadata ? ['bin','info','meta'] : ['bin','info'] as $ext) @unlink($this->path($id, $ext));
    }

    /** Non-blocking single-flight locks. Playback never waits for warming. */
    public function lock(string $id, bool $warming = false): mixed
    {
        $this->directory();
        $path = $warming ? $this->directory . '/warming.lock' : $this->path($id, 'lock');
        $handle = fopen($path, 'c'); @chmod($path, 0600);
        if (!$handle || !flock($handle, LOCK_EX | LOCK_NB)) { if ($handle) fclose($handle); return null; }
        return $handle;
    }

    public static function unlock(mixed $handle): void { if (is_resource($handle)) { flock($handle, LOCK_UN); fclose($handle); } }

    public static function clearOwner(string $root, int $owner): void
    {
        foreach (glob($root . '/' . $owner . '/drive-cache/*') ?: [] as $path) {
            // Leave lock inodes intact: a concurrent transfer must keep its lock.
            if (!str_ends_with($path, '.lock') && is_file($path)) @unlink($path);
        }
    }

    public function cleanup(int $reserve = 0): void
    {
        $ownerEntries = []; $globalEntries = []; $ownerBytes = 0; $totalBytes = 0;
        foreach (glob($this->root . '/*/drive-cache/*') ?: [] as $path) {
            if (!is_file($path) || is_link($path)) continue;
            $ext = pathinfo($path, PATHINFO_EXTENSION); $mtime = filemtime($path);
            if (in_array($ext, ['bin','info','meta','tmp'], true) && $mtime < time() - ($ext === 'meta' ? self::METADATA_TTL : self::TTL)) { @unlink($path); continue; }
            if ($ext !== 'bin') continue;
            $entry = ['path'=>$path, 'time'=>$mtime, 'size'=>(int)filesize($path)];
            $globalEntries[] = $entry; $totalBytes += $entry['size'];
            if (dirname($path) === $this->directory) { $ownerEntries[] = $entry; $ownerBytes += $entry['size']; }
        }
        $oldest = static fn(array $a, array $b): int => $a['time'] <=> $b['time'];
        usort($ownerEntries, $oldest); usort($globalEntries, $oldest);
        $removed = [];
        foreach ($ownerEntries as $entry) {
            if ($ownerBytes + $reserve <= self::OWNER_BYTES) break;
            $this->evict($entry['path']); $removed[$entry['path']] = true;
            $ownerBytes -= $entry['size']; $totalBytes -= $entry['size'];
        }
        foreach ($globalEntries as $entry) {
            if ($totalBytes + $reserve <= self::TOTAL_BYTES) break;
            if (isset($removed[$entry['path']])) continue;
            $this->evict($entry['path']); $totalBytes -= $entry['size'];
        }
        // Small metadata and idle file locks also have a bounded count per owner.
        $metadata = glob($this->directory . '/*.meta') ?: [];
        usort($metadata, static fn(string $a, string $b): int => filemtime($a) <=> filemtime($b));
        foreach (array_slice($metadata, 0, max(0, count($metadata) - 127)) as $path) @unlink($path);
        foreach (glob($this->directory . '/*.lock') ?: [] as $path) {
            if (filemtime($path) >= time() - self::TTL || basename($path) === 'warming.lock') continue;
            $lock = fopen($path, 'c');
            if ($lock && flock($lock, LOCK_EX | LOCK_NB)) { @unlink($path); flock($lock, LOCK_UN); }
            if ($lock) fclose($lock);
        }
    }

    private function evict(string $path): void { @unlink($path); @unlink(substr($path, 0, -4) . '.info'); }
}
