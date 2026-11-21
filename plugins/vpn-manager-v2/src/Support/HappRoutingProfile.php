<?php

namespace Fireball\VpnManagerV2\Support;

final class HappRoutingProfile
{
    // Keep the routing header within common web-server header buffer limits.
    public const MAX_LINK_BYTES = 4096;
    public const MAX_INPUT_BYTES = 65536;

    public function normalize(mixed $input, bool $incy = false): string
    {
        if (!is_string($input) || strlen($input) > self::MAX_INPUT_BYTES) {
            throw new \InvalidArgumentException('Invalid Happ routing input.');
        }
        $input = trim($input);
        if ($input === '') {
            return '';
        }

        $scheme = $incy ? 'incy' : 'happ';
        $inputScheme = $incy ? '(?:incy|happ)?' : 'happ';
        if (str_starts_with($input, '{')) {
            $json = $input;
        } elseif (preg_match('~\A' . $inputScheme . '://routing/(?:onadd|add)/([A-Za-z0-9+/]+={0,2})\z~D', $input, $matches) === 1) {
            $json = base64_decode($matches[1], true);
            if ($json === false) {
                throw new \InvalidArgumentException('Invalid Happ routing encoding.');
            }
        } else {
            throw new \InvalidArgumentException('Expected a Happ routing link or JSON profile.');
        }

        try {
            $profile = json_decode($json, false, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException('Invalid Happ routing JSON.', 0, $exception);
        }
        if (!$profile instanceof \stdClass || !isset($profile->Name) || !is_string($profile->Name)
            || trim($profile->Name) === '' || mb_strlen($profile->Name) > 120
            || preg_match('/[\x00-\x1F\x7F]/u', $profile->Name) !== 0) {
            throw new \InvalidArgumentException('A Happ routing profile requires a valid Name.');
        }
        foreach (['DirectSites', 'DirectIp', 'ProxySites', 'ProxyIp', 'BlockSites', 'BlockIp'] as $key) {
            if (!property_exists($profile, $key)) {
                continue;
            }
            if (!is_array($profile->$key)) {
                throw new \InvalidArgumentException('Happ routing rules must be lists.');
            }
            foreach ($profile->$key as $rule) {
                if (!is_string($rule) || trim($rule) === '' || preg_match('/[\x00-\x1F\x7F]/u', $rule) !== 0) {
                    throw new \InvalidArgumentException('Invalid Happ routing rule.');
                }
            }
        }
        foreach (['GlobalProxy', 'FakeDNS'] as $key) {
            if (!property_exists($profile, $key)) {
                continue;
            }
            if (!in_array($profile->$key, [true, false, 'true', 'false'], true)) {
                throw new \InvalidArgumentException('Invalid Happ routing boolean.');
            }
            $profile->$key = in_array($profile->$key, [true, 'true'], true) ? 'true' : 'false';
        }

        if ($incy && property_exists($profile, 'UseChunkFiles') && !property_exists($profile, 'useChunkFiles')) {
            if (!in_array($profile->UseChunkFiles, [true, false, 'true', 'false'], true)) {
                throw new \InvalidArgumentException('Invalid geo trimming setting.');
            }
            $profile->useChunkFiles = in_array($profile->UseChunkFiles, [true, 'true'], true);
            unset($profile->UseChunkFiles);
        }

        $link = $scheme . '://routing/onadd/' . base64_encode(json_encode(
            $profile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ));
        if (strlen($link) > self::MAX_LINK_BYTES) {
            throw new \InvalidArgumentException('Happ routing profile exceeds the header size limit.');
        }

        return $link;
    }

    public function activeLink(array $settings): string
    {
        return !empty($settings['happ_routing_enabled']) ? $this->storedLink($settings) : '';
    }

    public function headers(array $settings): array
    {
        $link = $this->storedLink($settings);
        if ($link === '') {
            return [];
        }

        return !empty($settings['happ_routing_enabled'])
            ? ['routing' => $link, 'routing-enable' => 'true']
            : ['routing' => 'happ://routing/off', 'routing-enable' => '0'];
    }

    /** An empty Incy override deliberately follows the current Happ profile. */
    public function incyHeaders(array $settings): array
    {
        try {
            $input = $settings['incy_routing_link'] ?? '';
            if ($input === '') {
                if (empty($settings['incy_routing_enabled']) && empty($settings['incy_routing_managed'])) {
                    return [];
                }
                $input = $settings['happ_routing_link'] ?? '';
            }
            $link = $this->normalize($input, incy: true);
        } catch (\InvalidArgumentException) {
            return [];
        }
        if ($link === '') {
            return [];
        }
        return ['routing' => !empty($settings['incy_routing_enabled']) ? $link : 'off'];
    }

    public function markUpdated(string $link, string $previous, bool $incy = false): string
    {
        $normalized = $this->normalize($link, $incy);
        if ($normalized === '' || $normalized === $previous) {
            return $normalized;
        }
        $profile = json_decode(base64_decode(substr($normalized, strpos($normalized, '/onadd/') + 7)), false, 32, JSON_THROW_ON_ERROR);
        $oldTime = 0;
        if ($previous !== '') {
            try {
                $old = $this->normalize($previous, $incy);
                $oldProfile = json_decode(base64_decode(substr($old, strpos($old, '/onadd/') + 7)), false, 32, JSON_THROW_ON_ERROR);
                $oldTime = (int)($oldProfile->LastUpdated ?? 0);
            } catch (\InvalidArgumentException) {
                // Invalid old settings must not prevent saving a valid profile.
            }
        }
        $profile->LastUpdated = (string)max(time(), min($oldTime, PHP_INT_MAX - 1) + 1);
        return $this->normalize(json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $incy);
    }

    private function storedLink(array $settings): string
    {
        try {
            return $this->normalize($settings['happ_routing_link'] ?? '');
        } catch (\InvalidArgumentException) {
            return '';
        }
    }
}
