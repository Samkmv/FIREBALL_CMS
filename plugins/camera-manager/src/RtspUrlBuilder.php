<?php

namespace Fireball\CameraManager;

use RuntimeException;

final class RtspUrlBuilder
{
    public const PROFILE_LABELS = [
        'auto' => 'Auto / Custom',
        'dahua' => 'Dahua',
        'dahua_legacy' => 'Dahua legacy underscore',
        'hikvision_isapi' => 'Hikvision / HiWatch ISAPI',
        'hikvision_streaming' => 'Hikvision Streaming',
        'generic_channel_stream' => 'Generic channel/stream',
        'custom' => 'Custom',
    ];

    public function path(
        string $profile,
        int $channel,
        int $subtype,
        string $customTemplate,
        string $streamMode = 'camera'
    ): string {
        if ($channel < 1 || $channel > 4096 || $subtype < 0 || $subtype > 99) {
            throw new RuntimeException('Некорректный номер канала или субпотока.');
        }
        $profile = InputValidator::rtspProfile($profile);
        $streamMode = InputValidator::streamMode($streamMode);
        $effectiveSubtype = $streamMode === 'main' ? 0 : ($streamMode === 'sub' ? 1 : $subtype);

        if ($profile === 'dahua') {
            return '/cam/realmonitor?channel=' . $channel . '&subtype=' . $effectiveSubtype;
        }
        if ($profile === 'dahua_legacy') {
            return '/cam/realmonitor?channel=' . $channel . '_subtype=' . $effectiveSubtype;
        }
        if ($profile === 'generic_channel_stream') {
            return '/?channel=' . $channel . '_stream=' . $effectiveSubtype;
        }
        if ($profile === 'hikvision_isapi' || $profile === 'hikvision_streaming') {
            $hikvisionId = ($channel * 100) + ($effectiveSubtype === 0 ? 1 : 2);
            $prefix = $profile === 'hikvision_isapi' ? '/ISAPI' : '';

            return $prefix . '/Streaming/Channels/' . $hikvisionId;
        }

        return $this->customPath($customTemplate, $channel, $effectiveSubtype);
    }

    public function customPath(string $template, int $channel, int $subtype): string
    {
        $template = trim($template);
        if ($template === '' || !str_starts_with($template, '/') || str_contains($template, "\n") || str_contains($template, "\r")) {
            throw new RuntimeException('RTSP-шаблон должен начинаться с /.');
        }
        return str_replace(
            ['{channel}', '{subtype}', '{stream}'],
            [(string)$channel, (string)$subtype, (string)$subtype],
            $template
        );
    }

    public function url(string $host, int $port, string $username, string $password, string $path): string
    {
        InputValidator::requiredIp($host, 'IP регистратора');
        InputValidator::requiredPort($port, 'RTSP-порт', 554);
        if ($username === '') {
            throw new RuntimeException('RTSP-логин обязателен.');
        }

        return 'rtsp://' . rawurlencode($username) . ':' . rawurlencode($password) . '@' . $host . ':' . $port . $path;
    }

    public function maskedUrl(string $host, int $port, string $username, string $path): string
    {
        return 'rtsp://' . rawurlencode($username) . ':••••••@' . $host . ':' . $port . $path;
    }

    /** @return list<array{profile:string,label:string,path:string}> */
    public function presets(int $channel, int $subtype, string $customTemplate = ''): array
    {
        $presets = [];
        foreach (['dahua', 'dahua_legacy', 'hikvision_isapi', 'hikvision_streaming', 'generic_channel_stream'] as $profile) {
            $presets[] = [
                'profile' => $profile,
                'label' => self::PROFILE_LABELS[$profile],
                'path' => $this->path($profile, $channel, $subtype, '/unused/{channel}'),
            ];
        }
        if ($customTemplate !== '') {
            $presets[] = [
                'profile' => 'custom',
                'label' => self::PROFILE_LABELS['custom'],
                'path' => $this->customPath($customTemplate, $channel, $subtype),
            ];
        }

        return $presets;
    }
}
