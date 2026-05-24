<?php

namespace FBL;

/**
 * Простой файловый кэш для временного хранения данных по ключу.
 */
class Cache
{

    /**
     * Сохраняет данные в кэш на указанное количество секунд.
     */
    public function set($key, $data, $seconds = 3600): void
    {
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

        if (file_put_contents($cache_file, serialize($content)) === false) {
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
                return $default;
            }

            if (time() <= $content['end_time']) {
                return $content['data'];
            }

            if (!@unlink($cache_file)) {
                log_error_details('Cache delete error', [
                    'Key' => $key,
                    'Cache File' => $cache_file,
                ]);
            }
        }

        return $default;
    }

    /**
     * Удаляет запись из кэша по ключу.
     */
    public function remove($key): void
    {
        $cache_file = CACHE . '/' . md5($key) . '.txt';

        if (file_exists($cache_file) && !@unlink($cache_file)) {
            log_error_details('Cache delete error', [
                'Key' => $key,
                'Cache File' => $cache_file,
            ]);
        }
    }

}
