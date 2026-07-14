<?php

namespace Fireball\VpnManagerV2\Exceptions;

final class ThreeXuiHttpException extends ThreeXuiException
{
    public function __construct(string $message, private readonly int $httpStatus)
    {
        parent::__construct($message, $httpStatus);
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}
