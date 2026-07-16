<?php

namespace App\Search;

use App\Search\Contracts\SearchProviderInterface;

final class SearchRegistry
{
    private array $definitions = [];
    private array $providers = [];
    private array $owners = [];

    public function registerProvider(
        string $name,
        SearchProviderInterface|string|callable $provider,
        ?string $owner = null
    ): void {
        $name = trim($name);
        if (!preg_match('/^[a-z0-9][a-z0-9._-]*$/', $name)) {
            throw new \InvalidArgumentException('Search provider name is invalid.');
        }

        $this->definitions[$name] = $provider;
        unset($this->providers[$name]);
        $this->owners[$name] = trim((string)$owner);
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->definitions);
    }

    public function provider(string $name): SearchProviderInterface
    {
        if (isset($this->providers[$name])) {
            return $this->providers[$name];
        }
        if (!$this->has($name)) {
            throw new \InvalidArgumentException('Unknown search provider: ' . $name);
        }

        $definition = $this->definitions[$name];
        $provider = is_string($definition) ? new $definition() : $definition;
        if (is_callable($provider) && !$provider instanceof SearchProviderInterface) {
            $provider = $provider();
        }
        if (!$provider instanceof SearchProviderInterface) {
            throw new \RuntimeException('Search provider must implement SearchProviderInterface: ' . $name);
        }

        return $this->providers[$name] = $provider;
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->definitions);
    }

    public function owner(string $name): ?string
    {
        $owner = trim((string)($this->owners[$name] ?? ''));

        return $owner !== '' ? $owner : null;
    }

    /** @return list<string> */
    public function namesByOwner(string $owner): array
    {
        return array_keys(array_filter(
            $this->owners,
            static fn(string $registeredOwner): bool => $registeredOwner === $owner
        ));
    }
}
