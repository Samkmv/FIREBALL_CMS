<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\DTO\ParsedStreamSettings;

final class StreamSettingsParser
{
    private const SENSITIVE_KEYS = [
        'privatekey',
        'private_key',
        'password',
        'passwd',
        'token',
        'secret',
        'mldsa65seed',
        'mldsa65_seed',
    ];

    public function parse(mixed $value, bool $provided = true): ParsedStreamSettings
    {
        [$stream, $valid] = $this->decode($value, $provided);
        $stream = $this->sanitize($stream);

        if (!$valid) {
            return new ParsedStreamSettings(null, 'none', [], [], [], [], [], [], [], false);
        }

        $network = strtolower(trim((string)($stream['network'] ?? 'tcp')));
        $network = $network !== '' ? mb_substr($network, 0, 40) : 'tcp';
        $security = strtolower(trim((string)($stream['security'] ?? 'none')));
        $security = in_array($security, ['reality', 'tls'], true) ? $security : 'none';

        $reality = $this->section($stream, 'realitySettings', $valid);
        $tls = $this->section($stream, 'tlsSettings', $valid);
        $tcpRaw = $network === 'raw'
            ? $this->firstSection($stream, ['rawSettings', 'tcpSettings'], $valid)
            : $this->firstSection($stream, ['tcpSettings', 'rawSettings'], $valid);
        $xhttp = $this->firstSection($stream, ['xhttpSettings', 'splithttpSettings'], $valid);
        $websocket = $this->section($stream, 'wsSettings', $valid);
        $grpc = $this->section($stream, 'grpcSettings', $valid);

        if (!$valid) {
            return new ParsedStreamSettings(null, 'none', [], [], [], [], [], [], [], false);
        }

        return new ParsedStreamSettings(
            $network,
            $security,
            $reality,
            $tls,
            $tcpRaw,
            $xhttp,
            $websocket,
            $grpc,
            $stream,
            true,
        );
    }

    private function decode(mixed $value, bool $provided): array
    {
        if (is_array($value)) {
            return [$value, true];
        }
        if (!$provided || $value === null) {
            return [[], true];
        }
        if (!is_string($value) || trim($value) === '') {
            return [[], false];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? [$decoded, true] : [[], false];
    }

    private function section(array $stream, string $key, bool &$valid): array
    {
        if (!array_key_exists($key, $stream)) {
            return [];
        }
        if (!is_array($stream[$key])) {
            $valid = false;
            return [];
        }

        return $stream[$key];
    }

    private function firstSection(array $stream, array $keys, bool &$valid): array
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $stream)) {
                return $this->section($stream, $key, $valid);
            }
        }

        return [];
    }

    private function sanitize(array $value): array
    {
        $sanitized = [];
        foreach ($value as $key => $item) {
            $normalizedKey = strtolower((string)$key);
            if (in_array($normalizedKey, self::SENSITIVE_KEYS, true)) {
                $sanitized[$key] = '[redacted]';
                continue;
            }

            $sanitized[$key] = is_array($item) ? $this->sanitize($item) : $item;
        }

        return $sanitized;
    }
}
