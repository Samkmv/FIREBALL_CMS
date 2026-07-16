<?php

namespace App\Search;

use App\Models\SiteSetting;

final class SearchConfig
{
    private array $settings;

    public function __construct(?array $settings = null)
    {
        $this->settings = $settings ?? (new SiteSetting())->all();
    }

    public function partialMatching(): bool
    {
        return $this->bool('search_partial_matching', true);
    }

    public function minimumQueryLength(): int
    {
        return $this->integer('search_min_query_length', 2, 1, 20);
    }

    public function minimumTokenLength(): int
    {
        return $this->integer('search_min_token_length', 3, 1, 20);
    }

    public function maximumResults(): int
    {
        return $this->integer('search_max_results', 100, 1, 500);
    }

    public function currentLocaleOnly(): bool
    {
        return $this->bool('search_current_locale_only', true);
    }

    /** @return list<string> */
    public function enabledTypes(): array
    {
        $decoded = json_decode((string)($this->settings['search_enabled_types'] ?? '[]'), true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn(mixed $type): string => trim((string)$type),
            $decoded
        ))));
    }

    public function weights(): array
    {
        return [
            'title' => $this->integer('search_weight_title', 40, 0, 500),
            'keywords' => $this->integer('search_weight_keywords', 25, 0, 500),
            'content' => $this->integer('search_weight_content', 10, 0, 500),
        ];
    }

    private function bool(string $key, bool $default): bool
    {
        if (!array_key_exists($key, $this->settings)) {
            return $default;
        }

        return in_array((string)$this->settings[$key], ['1', 'true', 'yes', 'on'], true);
    }

    private function integer(string $key, int $default, int $minimum, int $maximum): int
    {
        $value = filter_var($this->settings[$key] ?? $default, FILTER_VALIDATE_INT);
        $value = $value === false ? $default : $value;

        return max($minimum, min($maximum, $value));
    }
}
