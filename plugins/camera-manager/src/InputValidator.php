<?php

namespace Fireball\CameraManager;

use RuntimeException;

final class InputValidator
{
    public const RTSP_PROFILES = [
        'auto',
        'dahua',
        'dahua_legacy',
        'hikvision_isapi',
        'hikvision_streaming',
        'generic_channel_stream',
        'custom',
    ];

    public const STREAM_MODES = ['camera', 'main', 'sub'];
    public const NETWORK_STATUSES = ['not_configured', 'instructions_ready', 'configured', 'verified'];

    public static function nullableIp(mixed $value, string $label): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }
        if (filter_var($value, FILTER_VALIDATE_IP) === false) {
            throw new RuntimeException($label . ' указан некорректно.');
        }

        return $value;
    }

    public static function requiredIp(mixed $value, string $label): string
    {
        $ip = self::nullableIp($value, $label);
        if ($ip === null) {
            throw new RuntimeException($label . ' обязателен.');
        }

        return $ip;
    }

    public static function nullableIpv4(mixed $value, string $label): ?string
    {
        $ip = self::nullableIp($value, $label);
        if ($ip !== null && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            throw new RuntimeException($label . ' должен быть IPv4-адресом для генератора iptables.');
        }

        return $ip;
    }

    public static function requiredIpv4(mixed $value, string $label): string
    {
        $ip = self::nullableIpv4($value, $label);
        if ($ip === null) {
            throw new RuntimeException($label . ' обязателен.');
        }

        return $ip;
    }

    public static function nullableIpv4Cidr(mixed $value, string $label = 'LAN CIDR'): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }
        if (preg_match('~^([^/]+)/([0-9]{1,2})$~', $value, $matches) !== 1
            || filter_var($matches[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            throw new RuntimeException($label . ' должен иметь вид 192.168.34.0/24.');
        }
        $prefix = (int)$matches[2];
        if ($prefix < 0 || $prefix > 32) {
            throw new RuntimeException($label . ': длина префикса должна быть от 0 до 32.');
        }
        $ip = ip2long($matches[1]);
        if ($ip === false) {
            throw new RuntimeException($label . ' указан некорректно.');
        }
        $mask = $prefix === 0 ? 0 : ((0xFFFFFFFF << (32 - $prefix)) & 0xFFFFFFFF);
        $network = long2ip($ip & $mask);
        if ($network !== $matches[1]) {
            throw new RuntimeException($label . ' должен указывать адрес сети: ' . $network . '/' . $prefix . '.');
        }

        return $network . '/' . $prefix;
    }

    public static function nullablePort(mixed $value, string $label): ?int
    {
        if ($value === null || trim((string)$value) === '') {
            return null;
        }
        if (filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]) === false) {
            throw new RuntimeException($label . ' должен быть в диапазоне 1–65535.');
        }

        return (int)$value;
    }

    public static function requiredPort(mixed $value, string $label, int $default): int
    {
        $port = self::nullablePort($value === null || $value === '' ? $default : $value, $label);

        return $port ?? $default;
    }

    public static function wireGuardPublicKey(mixed $value, string $label = 'PublicKey WireGuard'): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }
        $decoded = base64_decode($value, true);
        if (strlen($value) !== 44 || $decoded === false || strlen($decoded) !== 32) {
            throw new RuntimeException($label . ' должен быть корректным 32-байтовым ключом Base64.');
        }

        return $value;
    }

    public static function linuxInterface(mixed $value, string $label): string
    {
        $value = trim((string)$value);
        if (preg_match('/^[a-zA-Z0-9_.-]{1,15}$/', $value) !== 1) {
            throw new RuntimeException($label . ' указан некорректно.');
        }

        return $value;
    }

    public static function wireGuardEndpoint(mixed $value): string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^(?:[a-zA-Z0-9](?:[a-zA-Z0-9.-]{0,251}[a-zA-Z0-9])?|\[[0-9a-fA-F:]+\]):([0-9]{1,5})$/', $value, $matches) !== 1
            || (int)$matches[1] < 1 || (int)$matches[1] > 65535) {
            throw new RuntimeException('Endpoint WireGuard должен иметь вид vpn.example.com:51820.');
        }

        return $value;
    }

    public static function rtspProfile(mixed $value): string
    {
        $profile = trim((string)$value);
        if ($profile === '') {
            return 'custom';
        }
        if (!in_array($profile, self::RTSP_PROFILES, true)) {
            throw new RuntimeException('Неизвестный RTSP-профиль.');
        }

        return $profile;
    }

    public static function streamMode(mixed $value): string
    {
        $mode = trim((string)$value);
        if ($mode === '') {
            return 'camera';
        }
        if (!in_array($mode, self::STREAM_MODES, true)) {
            throw new RuntimeException('Неизвестный режим RTSP-потока.');
        }

        return $mode;
    }

    public static function networkStatus(mixed $value): string
    {
        $status = trim((string)$value);
        if ($status === '') {
            return 'not_configured';
        }
        if (!in_array($status, self::NETWORK_STATUSES, true)) {
            throw new RuntimeException('Неизвестный статус сетевой настройки.');
        }

        return $status;
    }
}
