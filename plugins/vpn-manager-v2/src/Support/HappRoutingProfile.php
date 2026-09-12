<?php

namespace Fireball\VpnManagerV2\Support;

final class HappRoutingProfile
{
    // Keep the routing header within common web-server header buffer limits.
    public const MAX_LINK_BYTES = 4096;
    public const MAX_INPUT_BYTES = 65536;

    public function normalize(mixed $input): string
    {
        if (!is_string($input) || strlen($input) > self::MAX_INPUT_BYTES) {
            throw new \InvalidArgumentException('Invalid Happ routing input.');
        }
        $input = trim($input);
        if ($input === '') {
            return '';
        }

        if (str_starts_with($input, '{')) {
            $json = $input;
        } elseif (preg_match('~\Ahapp://routing/(?:onadd|add)/([A-Za-z0-9+/]+={0,2})\z~D', $input, $matches) === 1) {
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

        $link = 'happ://routing/onadd/' . base64_encode(json_encode(
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

    private function storedLink(array $settings): string
    {
        try {
            return $this->normalize($settings['happ_routing_link'] ?? '');
        } catch (\InvalidArgumentException) {
            return '';
        }
    }
}
