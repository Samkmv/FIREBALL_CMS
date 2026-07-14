<?php

namespace Fireball\VpnManagerV2\DTO;

final readonly class ParsedInbound
{
    public function __construct(
        public string $remoteInboundId,
        public string $name,
        public ?string $remark,
        public string $protocol,
        public int $port,
        public ?string $network,
        public string $security,
        public ?string $defaultFlow,
        public string $settingsJson,
        public string $streamSettingsJson,
        public string $status,
        public bool $isEnabled,
        public ParsedStreamSettings $stream,
    ) {
    }

    public function toRepositoryArray(): array
    {
        return [
            'remote_inbound_id' => $this->remoteInboundId,
            'name' => $this->name,
            'remark' => $this->remark,
            'protocol' => $this->protocol,
            'port' => $this->port,
            'network' => $this->network,
            'security' => $this->security,
            'default_flow' => $this->defaultFlow,
            'settings_json' => $this->settingsJson,
            'stream_settings_json' => $this->streamSettingsJson,
            'status' => $this->status,
            'is_enabled' => $this->isEnabled ? 1 : 0,
        ];
    }
}
