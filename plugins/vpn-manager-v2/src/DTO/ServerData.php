<?php

namespace Fireball\VpnManagerV2\DTO;

final readonly class ServerData
{
    public function __construct(
        public string $name,
        public string $code,
        public string $panelUrl,
        public string $panelPath,
        public string $authType,
        public ?string $countryCode,
        public ?string $countryName,
        public ?string $city,
        public bool $showFlag,
        public bool $isEnabled,
    ) {
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'code' => $this->code,
            'panel_url' => $this->panelUrl,
            'panel_path' => $this->panelPath !== '' ? $this->panelPath : null,
            'auth_type' => $this->authType,
            'country_code' => $this->countryCode,
            'country_name' => $this->countryName,
            'city' => $this->city,
            'show_flag' => $this->showFlag ? 1 : 0,
            'is_enabled' => $this->isEnabled ? 1 : 0,
        ];
    }
}
