<?php

namespace App\Search\Contracts;

use App\Search\SearchDocument;

/**
 * Supplies searchable documents and enforces runtime access to them.
 */
interface SearchProviderInterface
{
    /** @return iterable<SearchDocument> */
    public function getDocuments(): iterable;

    public function getDocument(int|string $entityId): ?SearchDocument;

    public function canAccess(SearchDocument $document, array $context = []): bool;
}
