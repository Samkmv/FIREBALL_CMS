<?php

namespace Fireball\VpnManagerV2\DTO;

final readonly class ThreeXuiHttpResponse
{
    public function __construct(
        public int $status,
        public string $contentType,
        public string $body,
    ) {
    }
}
