<?php

namespace App\Services;

final class PostPublicCache
{
    private const VERSION_KEY = 'posts:public_version';
    private const DEFAULT_VERSION = '1';

    public function key(string $name): string
    {
        return \FBL\Localization::localeCacheKey('posts', 'v' . $this->version() . ':' . $name);
    }

    public function version(): string
    {
        return (string)cache()->get(self::VERSION_KEY, self::DEFAULT_VERSION);
    }

    public function clear(): void
    {
        // Bump the namespace version so old public post cache entries become unreachable immediately.
        cache()->set(self::VERSION_KEY, (string)microtime(true), 31536000);

        foreach ([
            'posts:navigation_categories',
            'posts:home_featured:8',
            'posts:sidebar_categories',
        ] as $key) {
            cache()->remove($key);
        }
    }
}
