<?php

namespace Fireball\CameraManager;

use RuntimeException;

final class StreamKeyGenerator
{
    public function generate(string $siteCode, int $channel): string
    {
        $siteCode = trim($siteCode);
        if (preg_match('/^[A-Za-z0-9_-]{1,32}$/', $siteCode) !== 1 || $channel < 1 || $channel > 4096) {
            throw new RuntimeException('Невозможно создать ключ потока из кода объекта и номера канала.');
        }

        return strtolower($siteCode) . '-' . str_pad((string)$channel, 2, '0', STR_PAD_LEFT);
    }

    public function validate(string $streamKey): string
    {
        $streamKey = trim($streamKey);
        if (preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $streamKey) !== 1) {
            throw new RuntimeException('Некорректный ключ потока. Пример: 33-01.');
        }

        return $streamKey;
    }
}
