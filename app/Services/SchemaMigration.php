<?php

namespace App\Services;

/** Explicit install/update scope; never inferred from request URLs or user input. */
final class SchemaMigration
{
    private static int $depth = 0;

    public static function isRunning(): bool
    {
        return self::$depth > 0;
    }

    public static function run(callable $operation): mixed
    {
        self::$depth++;
        try {
            return $operation();
        } finally {
            self::$depth--;
        }
    }
}
