<?php

namespace Fireball\VpnManagerV2\Services;

final class VpnServerNameRenderer
{
    public function __construct(private readonly ?CountryFlagService $flags = null)
    {
    }

    public function render(array $node, array $settings): string
    {
        $flags = $this->flags ?? new CountryFlagService();
        $countryCode = $flags->normalize((string)($node['country_code'] ?? ''));
        $values = [
            '{flag}' => $flags->forServer(
                $countryCode,
                !empty($node['show_flag']),
                !empty($settings['global_show_flags'])
            ),
            '{service}' => trim((string)($settings['service_name'] ?? 'VPN V2')),
            '{country}' => trim((string)($node['country_name'] ?? '')),
            '{country_code}' => $countryCode,
            '{city}' => trim((string)($node['city'] ?? '')),
            '{server}' => trim((string)($node['server_name'] ?? $node['server_code'] ?? '')),
            '{protocol}' => strtoupper(trim((string)($node['protocol'] ?? ''))),
        ];
        $template = trim((string)($settings['server_name_template'] ?? ''));
        if ($template === '') {
            $template = '{flag} {service} · {country} {city} · {server} · {protocol}';
        }
        $name = strtr($template, $values);
        $name = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $name);
        $name = preg_replace('/[ \t]+/u', ' ', (string)$name);
        $name = preg_replace('/(?:\s*[·|]\s*){2,}/u', ' · ', (string)$name);
        $name = trim((string)$name, " \t\n\r\0\x0B·|,-/");
        if ($name === '') {
            $name = $values['{service}'] ?: ($values['{server}'] ?: 'VPN V2');
        }

        return mb_substr($name, 0, 180);
    }
}
