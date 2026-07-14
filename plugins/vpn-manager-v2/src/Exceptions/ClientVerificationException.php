<?php

namespace Fireball\VpnManagerV2\Exceptions;

final class ClientVerificationException extends VpnManagerV2Exception
{
    public function __construct(string $message, private readonly bool $flowMismatch = false)
    {
        parent::__construct($message);
    }

    public function isFlowMismatch(): bool
    {
        return $this->flowMismatch;
    }
}
