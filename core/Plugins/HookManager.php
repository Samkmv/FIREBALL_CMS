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

    public function applyFiltersSafely(string $hook, mixed $value, mixed ...$args): mixed
    {
        foreach ($this->callbacks($this->filters, $hook) as $callback) {
            try {
                $value = $callback($value, ...$args);
            } catch (\Throwable $exception) {
                if (function_exists('log_error_details')) {
                    \log_error_details('Plugin hook failed without interrupting the request', [
                        'Hook' => $hook,
                        'Callback' => $this->callbackName($callback),
                    ], $exception);
                }
            }
        }

        return $value;
    }

    private function callbackName(callable $callback): string
    {
        if (is_string($callback)) {
            return $callback;
        }
        if (is_array($callback)) {
            $owner = is_object($callback[0] ?? null) ? get_class($callback[0]) : (string)($callback[0] ?? '');
            return $owner . '::' . (string)($callback[1] ?? '');
        }

        return $callback instanceof \Closure ? 'Closure' : get_debug_type($callback);
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
