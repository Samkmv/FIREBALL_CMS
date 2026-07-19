<?php

namespace Fireball\VpnManagerV2\DTO;

final readonly class ServerData
{
    public function __construct(
        public string $name,
        public string $code,
        public string $panelUrl,
        public string $panelPath,
        public ?string $apiUrl,
        public string $authType,
        public ?string $countryCode,
        public ?string $countryName,
        public ?string $city,
        public bool $showFlag,
        public bool $isEnabled,
        public bool $maintenanceMode,
        public bool $allowNewConnections,
        public bool $verifySsl,
        public bool $allowPrivateNetwork,
        public int $connectTimeout,
        public int $readTimeout,
    ) {
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'code' => $this->code,
            'panel_url' => $this->panelUrl,
            'panel_path' => $this->panelPath !== '' ? $this->panelPath : null,
            'api_url' => $this->apiUrl,
            'auth_type' => $this->authType,
            'country_code' => $this->countryCode,
            'country_name' => $this->countryName,
            'city' => $this->city,
            'show_flag' => $this->showFlag ? 1 : 0,
            'is_enabled' => $this->isEnabled ? 1 : 0,
            'maintenance_mode' => $this->maintenanceMode ? 1 : 0,
            'allow_new_connections' => $this->allowNewConnections ? 1 : 0,
            'verify_ssl' => $this->verifySsl ? 1 : 0,
            'allow_private_network' => $this->allowPrivateNetwork ? 1 : 0,
            'connect_timeout' => $this->connectTimeout,
            'read_timeout' => $this->readTimeout,
        ];
    }
}
