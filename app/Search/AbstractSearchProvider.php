<?php

namespace App\Search;

use App\Search\Contracts\SearchProviderInterface;

abstract class AbstractSearchProvider implements SearchProviderInterface
{
    public function canAccess(SearchDocument $document, array $context = []): bool
    {
        return $document->status === 'published';
    }
}
