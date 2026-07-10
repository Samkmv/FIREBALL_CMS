<?php

namespace Fireball\VpnManager\Models;

final class VpnServer
{
    public function __construct(private array $attributes)
    {
    }

    public static function fromArray(array $attributes): self
    {
        return new self($attributes);
    }

    public function id(): int
    {
        return (int)($this->attributes['id'] ?? 0);
    }

    public function name(): string
    {
        return (string)($this->attributes['name'] ?? '');
    }

    public function code(): string
    {
        return (string)($this->attributes['code'] ?? '');
    }

    public function isEnabled(): bool
    {
        return !empty($this->attributes['is_enabled']);
    }

    public function toArray(): array
    {
        return $this->attributes;
    }
}
