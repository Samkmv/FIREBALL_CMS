<?php

namespace FBL;

/** Request-scoped, opt-in aggregate metrics. Never records SQL text or parameters. */
final class PerformanceProfiler
{
    private static bool $enabled = false;
    private static float $started = 0;
    private static array $durations = [];
    private static array $counts = [];

    public static function start(?float $started = null): void
    {
        self::$enabled = (defined('DEBUG') && DEBUG)
            || (defined('PERFORMANCE_DEBUG') && PERFORMANCE_DEBUG)
            || getenv('FIREBALL_PERFORMANCE_DEBUG') === '1';
        if (!self::$enabled) {
            return;
        }
        self::$started = $started ?? microtime(true);
        self::$durations = [];
        self::$counts = [];
        header_register_callback([self::class, 'sendHeaders']);
    }

    public static function begin(): float
    {
        return self::$enabled ? microtime(true) : 0;
    }

    public static function end(string $name, float $started): void
    {
        if (self::$enabled && $started > 0) {
            self::$durations[$name] = (self::$durations[$name] ?? 0) + (microtime(true) - $started) * 1000;
        }
    }

    public static function count(string $name, int $amount = 1): void
    {
        if (self::$enabled) {
            self::$counts[$name] = (self::$counts[$name] ?? 0) + $amount;
        }
    }

    public static function sql(string $sql, float $started): void
    {
        if (!self::$enabled) {
            return;
        }
        self::end('db', $started);
        self::count('sql_queries');
        if (preg_match('/^\s*(?:CREATE|ALTER|DROP|TRUNCATE)\b/i', $sql)) {
            self::count('ddl_queries');
        }
        if (preg_match('/\binformation_schema\b|^\s*SHOW\s+(?:COLUMNS|INDEX|TABLES)/i', $sql)) {
            self::count('schema_queries');
        }
    }

    public static function snapshot(): array
    {
        if (!self::$enabled) {
            return [];
        }
        return [
            'durations_ms' => ['app' => (microtime(true) - self::$started) * 1000] + self::$durations,
            'counts' => self::$counts + ['pdo_connections' => 0, 'sql_queries' => 0, 'ddl_queries' => 0, 'schema_queries' => 0],
            'peak_memory_bytes' => memory_get_peak_usage(true),
        ];
    }

    public static function sendHeaders(): void
    {
        if (!self::$enabled || headers_sent()) {
            return;
        }
        $snapshot = self::snapshot();
        $metrics = [];
        foreach ($snapshot['durations_ms'] as $name => $duration) {
            $metrics[] = $name . ';dur=' . number_format($duration, 3, '.', '');
        }
        foreach ($snapshot['counts'] as $name => $count) {
            $metrics[] = $name . ';desc="' . $count . '"';
        }
        $metrics[] = 'peak_memory;desc="' . $snapshot['peak_memory_bytes'] . '"';
        header('Server-Timing: ' . implode(', ', $metrics));
    }
}
