<?php

namespace App\Repositories;

final class AnalyticsRepository
{
    private string $table = 'analytics_visits';
    private static bool $schemaReady = false;

    public function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }

        db()->query(
            "CREATE TABLE IF NOT EXISTS {$this->table} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                session_id VARCHAR(128) NOT NULL,
                ip VARCHAR(45) NOT NULL,
                country VARCHAR(120) NULL,
                country_code VARCHAR(8) NULL,
                city VARCHAR(120) NULL,
                device_type VARCHAR(20) NOT NULL DEFAULT 'Desktop',
                os VARCHAR(40) NOT NULL DEFAULT 'Other',
                browser VARCHAR(40) NOT NULL DEFAULT 'Other',
                referer TEXT NULL,
                source VARCHAR(80) NOT NULL DEFAULT 'Direct',
                landing_page VARCHAR(2048) NOT NULL,
                current_page VARCHAR(2048) NOT NULL,
                utm_source VARCHAR(255) NULL,
                utm_medium VARCHAR(255) NULL,
                utm_campaign VARCHAR(255) NULL,
                utm_content VARCHAR(255) NULL,
                utm_term VARCHAR(255) NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_created_at (created_at),
                KEY idx_session_created (session_id, created_at),
                KEY idx_source_created (source, created_at),
                KEY idx_country_created (country_code, created_at),
                KEY idx_device_created (device_type, created_at),
                KEY idx_os_created (os, created_at),
                KEY idx_browser_created (browser, created_at),
                KEY idx_page_created (current_page(191), created_at),
                KEY idx_utm_source_created (utm_source(120), created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        self::$schemaReady = true;
    }

    public function insertVisit(array $visit): void
    {
        $this->ensureSchema();

        db()->query(
            "INSERT INTO {$this->table}
                (session_id, ip, country, country_code, city, device_type, os, browser, referer, source,
                 landing_page, current_page, utm_source, utm_medium, utm_campaign, utm_content, utm_term, created_at)
             VALUES
                (:session_id, :ip, :country, :country_code, :city, :device_type, :os, :browser, :referer, :source,
                 :landing_page, :current_page, :utm_source, :utm_medium, :utm_campaign, :utm_content, :utm_term, :created_at)",
            [
                'session_id' => (string)($visit['session_id'] ?? ''),
                'ip' => (string)($visit['ip'] ?? ''),
                'country' => $this->nullableString($visit['country'] ?? null),
                'country_code' => $this->nullableString($visit['country_code'] ?? null),
                'city' => $this->nullableString($visit['city'] ?? null),
                'device_type' => (string)($visit['device_type'] ?? 'Desktop'),
                'os' => (string)($visit['os'] ?? 'Other'),
                'browser' => (string)($visit['browser'] ?? 'Other'),
                'referer' => $this->nullableString($visit['referer'] ?? null),
                'source' => (string)($visit['source'] ?? 'Direct'),
                'landing_page' => (string)($visit['landing_page'] ?? '/'),
                'current_page' => (string)($visit['current_page'] ?? '/'),
                'utm_source' => $this->nullableString($visit['utm_source'] ?? null),
                'utm_medium' => $this->nullableString($visit['utm_medium'] ?? null),
                'utm_campaign' => $this->nullableString($visit['utm_campaign'] ?? null),
                'utm_content' => $this->nullableString($visit['utm_content'] ?? null),
                'utm_term' => $this->nullableString($visit['utm_term'] ?? null),
                'created_at' => (string)($visit['created_at'] ?? date('Y-m-d H:i:s')),
            ]
        );
    }

    public function countVisitsSince(string $from): int
    {
        $this->ensureSchema();

        return (int)db()->query(
            "SELECT COUNT(*) FROM {$this->table} WHERE created_at >= ?",
            [$from]
        )->getColumn();
    }

    public function countUniqueSince(string $from): int
    {
        $this->ensureSchema();

        return (int)db()->query(
            "SELECT COUNT(DISTINCT session_id) FROM {$this->table} WHERE created_at >= ?",
            [$from]
        )->getColumn();
    }

    public function countByDeviceSince(string $deviceType, string $from): int
    {
        $this->ensureSchema();

        return (int)db()->query(
            "SELECT COUNT(*) FROM {$this->table} WHERE device_type = ? AND created_at >= ?",
            [$deviceType, $from]
        )->getColumn();
    }

    public function visitsByDay(int $days): array
    {
        $this->ensureSchema();
        $from = date('Y-m-d 00:00:00', strtotime('-' . max(0, $days - 1) . ' days'));

        return db()->query(
            "SELECT DATE(created_at) AS label, COUNT(*) AS total
             FROM {$this->table}
             WHERE created_at >= ?
             GROUP BY DATE(created_at)
             ORDER BY label ASC",
            [$from]
        )->get() ?: [];
    }

    public function topGrouped(string $column, string $from, int $limit = 10): array
    {
        $this->ensureAllowedColumn($column);
        $this->ensureSchema();

        return db()->query(
            "SELECT COALESCE(NULLIF({$column}, ''), 'Unknown') AS label, COUNT(*) AS total
             FROM {$this->table}
             WHERE created_at >= ?
             GROUP BY label
             ORDER BY total DESC
             LIMIT " . max(1, $limit),
            [$from]
        )->get() ?: [];
    }

    public function popularPages(string $from, int $limit = 20): array
    {
        $this->ensureSchema();

        return db()->query(
            "SELECT current_page AS label, COUNT(*) AS total
             FROM {$this->table}
             WHERE created_at >= ?
             GROUP BY current_page
             ORDER BY total DESC
             LIMIT " . max(1, $limit),
            [$from]
        )->get() ?: [];
    }

    public function latest(int $limit = 20): array
    {
        $this->ensureSchema();

        return db()->query(
            "SELECT created_at, country, country_code, city, device_type, os, browser, source, current_page
             FROM {$this->table}
             ORDER BY created_at DESC
             LIMIT " . max(1, $limit)
        )->get() ?: [];
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }

    private function ensureAllowedColumn(string $column): void
    {
        $allowed = ['source', 'country', 'country_code', 'device_type', 'os', 'browser'];
        if (!in_array($column, $allowed, true)) {
            throw new \InvalidArgumentException('Unsupported analytics grouping column.');
        }
    }
}
