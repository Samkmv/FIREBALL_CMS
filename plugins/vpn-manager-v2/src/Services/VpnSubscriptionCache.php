<?php

namespace Fireball\VpnManagerV2\Services;

final class VpnSubscriptionCache
{
    private const DEFAULT_TTL = 300;
    private const FORMATS = ['base64', 'plain'];

    public function get(string $token, int $revision, string $format): ?array
    {
        $cached = cache()->get($this->key($token, $revision, $format));

        return is_array($cached)
            && isset($cached['body'], $cached['config_count'])
            && is_string($cached['body'])
            ? $cached
            : null;
    }

    public function set(string $token, int $revision, string $format, string $body, int $configCount): void
    {
        cache()->set($this->key($token, $revision, $format), [
            'body' => $body,
            'config_count' => $configCount,
        ], $this->ttl());
    }

    public function invalidate(string $token, int $revision): void
    {
        foreach (self::FORMATS as $format) {
            cache()->remove($this->key($token, $revision, $format));
        }
    }

    public function key(string $token, int $revision, string $format): string
    {
        $format = in_array($format, self::FORMATS, true) ? $format : 'base64';

        return 'vpn-v2:subscription:' . hash('sha256', $token)
            . ':revision:' . max(1, $revision)
            . ':format:' . $format;
    }

    public function ttl(): int
    {
        $settings = (new SettingsService())->current();

        return max(30, min(3600, (int)($settings['subscription_cache_ttl_seconds'] ?? self::DEFAULT_TTL)));
    }
}
