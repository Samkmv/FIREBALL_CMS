<?php

declare(strict_types=1);

define('STORAGE', sys_get_temp_dir() . '/fireball-search-schema-' . bin2hex(random_bytes(6)));
mkdir(STORAGE, 0700);
require dirname(__DIR__) . '/app/Services/SchemaManifest.php';
require dirname(__DIR__) . '/app/Search/SearchNormalizer.php';
final class SearchProbeDatabase
{
    public array $queries = [];
    public function query(string $sql): self
    {
        $this->queries[] = $sql;
        if (str_contains($sql, '`absent`')) {
            $exception = new PDOException('Table missing');
            (new ReflectionProperty(Exception::class, 'code'))->setValue($exception, '42S02');
            throw $exception;
        }
        if (str_contains($sql, '`unavailable`')) throw new PDOException('Connection failed');
        return $this;
    }
}
$database = new SearchProbeDatabase();
function db(): SearchProbeDatabase { return $GLOBALS['database']; }
$checks = 0;
function checkSearch(bool $condition, string $message): void {
    $GLOBALS['checks']++;
    if (!$condition) throw new RuntimeException($message);
}
try {
    checkSearch(App\Services\SchemaManifest::hasTable('posts'), 'Missing manifest must not hide existing posts');
    checkSearch(App\Services\SchemaManifest::hasTable('posts') && count($database->queries) === 1, 'Presence probe is request-memoized');
    checkSearch(!App\Services\SchemaManifest::hasTable('absent'), 'Missing optional table is handled');
    try {
        App\Services\SchemaManifest::hasTable('unavailable');
        checkSearch(false, 'Connection failures must not masquerade as empty documents');
    } catch (PDOException) { checkSearch(true, 'Connection failure propagated'); }
    try {
        App\Services\SchemaManifest::hasTable('posts;DROP');
        checkSearch(false, 'Unsafe identifier accepted');
    } catch (InvalidArgumentException) { checkSearch(true, 'Unsafe identifier rejected'); }
    checkSearch(array_reduce($database->queries, static fn(bool $ok, string $sql): bool => $ok && str_starts_with($sql, 'SELECT 1 FROM `') && str_ends_with($sql, ' LIMIT 0'), true), 'Probes do not inspect or change schema');
    checkSearch(App\Search\SearchNormalizer::tokens('1234 1234') === ['1234'], 'Numeric queries preserve string token contract');
    checkSearch(App\Search\SearchNormalizer::tokens('Техническая запись') === ['техническая', 'запись'], 'Cyrillic queries normalize');
    checkSearch(App\Search\SearchNormalizer::tokens('0 0012') === ['0', '0012'], 'Numeric zero and leading zeros are preserved');
    echo "Search regression: {$checks} checks passed.\n";
} finally { rmdir(STORAGE); }
