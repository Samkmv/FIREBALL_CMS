<?php

namespace FBL\Plugins;

final class HookManager
{
    private array $actions = [];
    private array $filters = [];

    public function addAction(string $hook, callable $callback, int $priority = 10): void
    {
        $this->actions[$hook][$priority][] = $callback;
    }

    public function doAction(string $hook, mixed ...$args): void
    {
        foreach ($this->callbacks($this->actions, $hook) as $callback) {
            $callback(...$args);
        }
    }

    public function addFilter(string $hook, callable $callback, int $priority = 10): void
    {
        $this->filters[$hook][$priority][] = $callback;
    }

    public function applyFilters(string $hook, mixed $value, mixed ...$args): mixed
    {
        foreach ($this->callbacks($this->filters, $hook) as $callback) {
            $value = $callback($value, ...$args);
        }

        return $value;
    }

    private function callbacks(array $registry, string $hook): array
    {
        if (empty($registry[$hook])) {
            return [];
        }

        ksort($registry[$hook]);

        $callbacks = [];
        foreach ($registry[$hook] as $priorityCallbacks) {
            foreach ($priorityCallbacks as $callback) {
                $callbacks[] = $callback;
            }
        }

        return $callbacks;
    }
}
