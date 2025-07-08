<?php

namespace FBL;

class Cache
{

    public function set($key, $data, $seconds = 3600): void
    {
        $content['data'] = $data;
        $content['end_time'] = time() + $seconds;

        if (!is_dir(CACHE)) {
            mkdir(CACHE, 0755, true);
        }

        $cache_file = CACHE . '/' . md5($key) . '.txt';
        file_put_contents($cache_file, serialize($content));
    }

    public function get($key, $default = null)
    {
        $cache_file = CACHE . '/' . md5($key) . '.txt';

        if (file_exists($cache_file)) {

            $content = unserialize(file_get_contents($cache_file));

            if (time() <= $content['end_time']) {
                return $content['data'];
            }

            unlink($cache_file);
        }

        return $default;
    }

    public function remove($key): void
    {
        $cache_file = CACHE . '/' . md5($key) . '.txt';

        if (file_exists($cache_file)) {
            unlink($cache_file);
        }
    }

}