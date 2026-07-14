<?php

namespace Fireball\VpnManagerV2\DTO;

final readonly class ThreeXuiServerConfig
{
    public function __construct(
        public string $panelUrl,
        public string $panelPath,
        public string $authType,
        public string $username,
        public string $password,
        public string $token,
        public int $connectTimeout = 5,
        public int $readTimeout = 15,
    ) {
    }

    public function endpoint(string $path): string
    {
        return rtrim($this->panelUrl, '/')
            . ($this->panelPath !== '' ? '/' . trim($this->panelPath, '/') : '')
            . '/' . ltrim($path, '/');
    }
}
