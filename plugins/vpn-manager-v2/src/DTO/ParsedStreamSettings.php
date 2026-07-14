<?php

namespace Fireball\VpnManagerV2\DTO;

final readonly class ParsedStreamSettings
{
    public function __construct(
        public ?string $network,
        public string $security,
        public array $reality,
        public array $tls,
        public array $tcpRaw,
        public array $xhttp,
        public array $websocket,
        public array $grpc,
        public array $normalized,
        public bool $valid,
    ) {
    }
}
