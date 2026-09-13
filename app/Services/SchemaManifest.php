<?php

namespace App\Services;

/** Persistent schema metadata, refreshed by migrations; independent of disposable cache. */
final class SchemaManifest
{
    private static ?array $tables = null;
    private static array $presence = [];

    public static function columns(string $table): array
    {
        if (self::$tables === null) {
            $data = json_decode((string)@file_get_contents(STORAGE . '/schema-manifest.json'), true);
            self::$tables = is_array($data) ? ($data['tables'] ?? []) : [];
        }
        return self::$tables[$table] ?? [];
    }

    public static function hasTable(string $table): bool
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
            throw new \InvalidArgumentException('Invalid database identifier.');
        }
        if (self::columns($table) !== []) return true;
        if (array_key_exists($table, self::$presence)) return self::$presence[$table];
        // A missing/outdated manifest is not evidence that application data is absent.
        // Probe without reading rows, changing schema or consulting information_schema.
        try {
            db()->query("SELECT 1 FROM `{$table}` LIMIT 0");
            return self::$presence[$table] = true;
        } catch (\PDOException $exception) {
            if ((string)$exception->getCode() !== '42S02') throw $exception;
            return self::$presence[$table] = false;
        }
    }

    public static function rebuild(): void
    {
        $tables = [];
        foreach (db()->query('SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME, ORDINAL_POSITION')->get() ?: [] as $row) {
            $tables[$row['TABLE_NAME']][] = $row['COLUMN_NAME'];
        }
        $path = STORAGE . '/schema-manifest.json';
        $temporary = tempnam(STORAGE, '.schema-');
        try {
            if ($temporary === false || file_put_contents($temporary, json_encode(['version' => 1, 'tables' => $tables], JSON_THROW_ON_ERROR), LOCK_EX) === false || !rename($temporary, $path)) {
                throw new \RuntimeException('Unable to write schema manifest.');
            }
            self::$tables = $tables;
            self::$presence = [];
        } finally {
            if (is_string($temporary) && is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }
}
