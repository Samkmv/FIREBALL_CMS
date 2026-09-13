<?php

namespace FBL;

/** Request memory → optional APCu → atomic file cache. */
class Cache
{
    private const DEFAULT_TTL = 3600;
    private static array $memory = [];
    private static ?string $generation = null;

    private function namespace(): string
    {
        self::$generation ??= trim((string)@file_get_contents(CACHE . '/.generation')) ?: 'initial';
        return 'fireball:' . hash('sha256', CACHE) . ':' . self::$generation . ':';
    }

    private function apcu(): bool
    {
        return function_exists('apcu_enabled') && apcu_enabled();
    }

    private function rotateGeneration(): void
    {
        // Also invalidates other SAPIs (CLI/FPM) and hosts sharing the file cache.
        $generation = bin2hex(random_bytes(12));
        $this->writeFile(CACHE . '/.generation', $generation);
        self::$generation = $generation;
    }

    public function set($key, $data, $seconds = 3600): void
    {
        $started = PerformanceProfiler::begin();
        try {
            $seconds = (int)$seconds > 0 ? (int)$seconds : self::DEFAULT_TTL;
            $id = md5((string)$key);
            $payload = serialize(['data' => $data, 'end_time' => time() + $seconds]);
            self::$memory[$id] = $payload;
            $this->writeFile(CACHE . '/' . $id . '.txt', $payload);
            $this->rotateGeneration();
            // Use the same safe serialization contract on all three layers.
            self::$memory[$id] = $payload;
            if ($this->apcu()) {
                apcu_store($this->namespace() . $id, $payload, min(600, $seconds + 1));
            }
        } catch (\RuntimeException $exception) {
            // Unwritable optional cache must not turn a public request into a 500.
            log_error_details('Cache write error', [], $exception);
        } finally {
            PerformanceProfiler::end('cache', $started);
        }
    }

    public function get($key, $default = null)
    {
        $started = PerformanceProfiler::begin();
        try {
            $id = md5((string)$key);
            if (array_key_exists($id, self::$memory)) {
                PerformanceProfiler::count('cache_l1_hits');
                $payload = self::$memory[$id];
            } else {
                $payload = false;
                if ($this->apcu()) {
                    $payload = apcu_fetch($this->namespace() . $id);
                }
                if (!is_string($payload)) {
                    PerformanceProfiler::count('cache_file_reads');
                    $payload = @file_get_contents(CACHE . '/' . $id . '.txt');
                }
                self::$memory[$id] = $payload;
            }
            if (!is_string($payload)) {
                return $default;
            }
            $entry = @unserialize($payload, ['allowed_classes' => false]);
            if (!is_array($entry) || !array_key_exists('data', $entry)
                || (int)($entry['end_time'] ?? 0) < time()) {
                self::$memory[$id] = false;
                return $default;
            }
            if ($this->apcu() && !apcu_exists($this->namespace() . $id)) {
                apcu_store($this->namespace() . $id, $payload, min(600, max(1, (int)$entry['end_time'] - time() + 1)));
            }
            return $entry['data'];
        } finally {
            PerformanceProfiler::end('cache', $started);
        }
    }

    public function remove($key): void
    {
        $id = md5((string)$key);
        unset(self::$memory[$id]);
        if ($this->apcu()) {
            apcu_delete($this->namespace() . $id);
        }
        $path = CACHE . '/' . $id . '.txt';
        if (is_file($path) && !@unlink($path)) {
            throw new \RuntimeException('Unable to invalidate file cache.');
        }
        $this->rotateGeneration();
    }

    public function clear(): int
    {
        self::$memory = [];
        $deleted = 0;
        if (is_dir(CACHE)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(CACHE, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($iterator as $item) {
                if ($item->isDir()) {
                    @rmdir($item->getPathname());
                } elseif (!in_array($item->getFilename(), ['.gitkeep', '.htaccess', '.generation'], true)) {
                    if (@unlink($item->getPathname())) {
                        $deleted++;
                    }
                }
            }
        }
        $this->rotateGeneration();
        return $deleted;
    }

    private function writeFile(string $path, string $content): void
    {
        if (!is_dir(CACHE) && !@mkdir(CACHE, 0755, true) && !is_dir(CACHE)) {
            throw new \RuntimeException('Unable to create cache directory.');
        }
        $temporary = tempnam(CACHE, '.cache-');
        if ($temporary === false) {
            throw new \RuntimeException('Unable to create cache file.');
        }
        try {
            if (file_put_contents($temporary, $content, LOCK_EX) === false || !@rename($temporary, $path)) {
                throw new \RuntimeException('Unable to write cache file.');
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }
}
