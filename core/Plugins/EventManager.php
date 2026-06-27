<?php

namespace FBL\Plugins;

final class EventManager
{
    private array $listeners = [];

    public function listen(string $eventName, callable $callback): void
    {
        $this->listeners[$eventName][] = $callback;
    }

    public function fire(string $eventName, mixed $payload = null): void
    {
        foreach ($this->listeners[$eventName] ?? [] as $callback) {
            $callback($payload, $eventName);
        }
    }
}
