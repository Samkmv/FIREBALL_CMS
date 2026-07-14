<?php

namespace Fireball\VpnManagerV2\DTO;

final readonly class ConnectionTestResult
{
    public function __construct(
        public bool $success,
        public string $message,
        public int $inboundCount = 0,
        public string $status = 'online',
    ) {
    }
}
