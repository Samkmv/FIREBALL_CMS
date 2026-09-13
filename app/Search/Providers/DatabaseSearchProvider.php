<?php

namespace App\Search\Providers;

use App\Search\AbstractSearchProvider;

abstract class DatabaseSearchProvider extends AbstractSearchProvider
{
    protected function tableExists(string $table): bool
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
            return false;
        }

        return \App\Services\SchemaManifest::hasTable($table);
    }
}
