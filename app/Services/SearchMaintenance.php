<?php

namespace App\Services;

use App\Search\SearchIndexer;
use App\Search\SearchRegistry;
use App\Search\Providers\PageSearchProvider;
use App\Search\Providers\PostSearchProvider;
use App\Search\Providers\ProductSearchProvider;
use FBL\Database;

/** Full index builds belong to installation, updates and explicit CLI maintenance. */
final class SearchMaintenance
{
    public static function rebuild(?Database $database = null, ?SearchRegistry $registry = null): array
    {
        $previous = app()->db;
        app()->db = $database ?? $previous;
        $registry ??= new SearchRegistry();
        foreach (['pages'=>PageSearchProvider::class, 'posts'=>PostSearchProvider::class, 'products'=>ProductSearchProvider::class] as $name=>$provider) {
            if (!$registry->has($name)) $registry->registerProvider($name, $provider);
        }
        try {
            return (new SearchIndexer($registry))->reindexAll();
        } finally {
            app()->db = $previous;
        }
    }
}
