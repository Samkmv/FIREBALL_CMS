<?php

namespace App\Search\Contracts;

/**
 * Allows a CMS entity to describe itself without exposing its storage schema.
 */
interface SearchableEntityInterface
{
    public function getSearchType(): string;

    public function getSearchEntityId(): int|string;

    public function getSearchTitle(): string;

    public function getSearchContent(): string;

    public function getSearchKeywords(): array;

    public function getSearchUrl(): string;

    public function getSearchLocale(): ?string;
}
