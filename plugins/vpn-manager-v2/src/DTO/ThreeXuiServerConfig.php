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
        public ?string $apiUrl = null,
        public bool $verifySsl = true,
        public bool $allowPrivateNetwork = false,
    ) {
    }

    public function endpoint(string $path): string
    {
        $useApiUrl = str_starts_with('/' . ltrim($path, '/'), '/panel/api/')
            && trim((string)$this->apiUrl) !== '';
        $baseUrl = $useApiUrl ? (string)$this->apiUrl : $this->panelUrl;

        return rtrim($baseUrl, '/')
            . (!$useApiUrl && $this->panelPath !== '' ? '/' . trim($this->panelPath, '/') : '')
            . '/' . ltrim($path, '/');
    }
}
