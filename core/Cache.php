<?php

namespace FBL;

/**
 * Простой файловый кэш для временного хранения данных по ключу.
 */
class Cache
{
    private const DEFAULT_TTL = 3600;

    /**
     * Сохраняет данные в кэш на указанное количество секунд.
     */
    public function set($key, $data, $seconds = 3600): void
    {
        $seconds = (int)$seconds;
        if ($seconds <= 0) {
            // Safety fallback: invalid TTL values should not create immediately stale cache entries.
            $seconds = self::DEFAULT_TTL;
        }

        $content['data'] = $data;
        $content['end_time'] = time() + $seconds;

        $cache_file = CACHE . '/' . md5($key) . '.txt';
        if (!is_dir(CACHE) && !@mkdir(CACHE, 0755, true) && !is_dir(CACHE)) {
            log_error_details('Cache write error', [
                'Key' => $key,
                'Cache Directory' => CACHE,
                'Reason' => 'Unable to create cache directory',
            ]);
            return;
        }

        if (file_put_contents($cache_file, serialize($content), LOCK_EX) === false) {
            log_error_details('Cache write error', [
                'Key' => $key,
                'Cache File' => $cache_file,
                'Cache Directory Writable' => is_writable(CACHE) ? 'yes' : 'no',
            ]);
        }
    }

    /**
     * Возвращает данные из кэша, если запись существует и ещё не истекла.
     */
    public function get($key, $default = null)
    {
        $cache_file = CACHE . '/' . md5($key) . '.txt';

        if (file_exists($cache_file)) {
            $rawContent = file_get_contents($cache_file);
            if ($rawContent === false) {
                log_error_details('Cache read error', [
                    'Key' => $key,
                    'Cache File' => $cache_file,
                ]);
                return $default;
            }

            $content = @unserialize($rawContent, ['allowed_classes' => false]);
            if (!is_array($content) || !array_key_exists('end_time', $content)) {
                log_error_details('Cache payload error', [
                    'Key' => $key,
                    'Cache File' => $cache_file,
                ]);
                $this->deleteFile($cache_file, $key);
                return $default;
            }

            $endTime = (int)$content['end_time'];
            if ($endTime > 0 && time() <= $endTime) {
                return $content['data'];
            }

            $this->deleteFile($cache_file, $key);
        }

        return $default;
    }

    /**
     * Удаляет запись из кэша по ключу.
     */
    public function remove($key): void
    {
        $cache_file = CACHE . '/' . md5($key) . '.txt';

        if (file_exists($cache_file)) {
            $this->deleteFile($cache_file, $key);
        }
    }

    /**
     * Полностью очищает файловый кэш и возвращает количество удалённых cache-файлов.
     */
    public function clear(): int
    {
        if (!is_dir(CACHE)) {
            return 0;
        }

        $deleted = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(CACHE, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $path = $item->getPathname();
            if ($item->isDir()) {
                @rmdir($path);
                continue;
            }

            // Keep repository sentinels and local access rules intact while clearing runtime cache payloads.
            if (in_array($item->getFilename(), ['.gitkeep', '.htaccess'], true)) {
                continue;
            }

            if (@unlink($path)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    private function deleteFile(string $path, string $key = ''): void
    {
        if (!@unlink($path)) {
            log_error_details('Cache delete error', [
                'Key' => $key,
                'Cache File' => $path,
            ]);
        }
    }
}
