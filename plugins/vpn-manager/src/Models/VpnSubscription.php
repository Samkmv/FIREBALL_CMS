<?php

namespace Fireball\VpnManager\Models;

final class VpnSubscription
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

    public function userId(): int
    {
        return (int)($this->attributes['user_id'] ?? 0);
    }

    public function status(): string
    {
        return (string)($this->attributes['status'] ?? '');
    }

    public function isActive(): bool
    {
        return $this->status() === 'active';
    }

    public function toArray(): array
    {
        return $this->attributes;
    }
}
