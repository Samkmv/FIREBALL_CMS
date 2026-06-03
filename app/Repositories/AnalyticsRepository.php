<?php

namespace App\Repositories;

use FBL\Pagination;

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
            "SELECT current_page AS label, COUNT(*) AS views, COUNT(*) AS total
             FROM {$this->table}
             WHERE created_at >= ?
             GROUP BY current_page
             ORDER BY views DESC
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

    public function paginatedPopularPages(array $params = []): array
    {
        $this->ensureSchema();

        $perPage = max(1, min(100, (int)($params['per_page'] ?? 20)));
        $sort = (string)($params['sort'] ?? 'views');
        $direction = strtoupper((string)($params['direction'] ?? 'desc')) === 'ASC' ? 'ASC' : 'DESC';
        $sortMap = [
            'page' => 'label',
            'views' => 'views',
        ];
        $orderBy = $sortMap[$sort] ?? 'views';
        $filter = $this->buildFilterWhere($params, ['search', 'country', 'device_type', 'browser', 'source']);
        $where = $filter['where'] !== '' ? 'WHERE ' . $filter['where'] : '';

        $total = (int)db()->query(
            "SELECT COUNT(*) FROM (
                SELECT current_page
                FROM {$this->table}
                {$where}
                GROUP BY current_page
             ) grouped_pages",
            $filter['params']
        )->getColumn();

        $pagination = new Pagination($total, $perPage, PAGINATION_SETTINGS['midSize'], PAGINATION_SETTINGS['maxPages'], PAGINATION_SETTINGS['tpl'], 'pages_page');
        $offset = $pagination->getOffset();

        $items = db()->query(
            "SELECT current_page AS label, COUNT(*) AS views, COUNT(*) AS total
             FROM {$this->table}
             {$where}
             GROUP BY current_page
             ORDER BY {$orderBy} {$direction}
             LIMIT {$offset}, {$perPage}",
            $filter['params']
        )->get() ?: [];

        return [
            'items' => $items,
            'total' => $total,
            'pagination' => $pagination,
            'sort' => $sort,
            'direction' => strtolower($direction),
            'per_page' => $perPage,
        ];
    }

    public function paginatedVisits(array $params = []): array
    {
        $this->ensureSchema();

        $perPage = max(1, min(100, (int)($params['per_page'] ?? 20)));
        $sort = (string)($params['sort'] ?? 'created_at');
        $direction = strtoupper((string)($params['direction'] ?? 'desc')) === 'ASC' ? 'ASC' : 'DESC';
        $sortMap = [
            'created_at' => 'created_at',
            'country' => 'country',
            'device' => 'device_type',
            'browser' => 'browser',
            'source' => 'source',
            'page' => 'current_page',
        ];
        $orderBy = $sortMap[$sort] ?? 'created_at';
        $filter = $this->buildFilterWhere($params, ['search', 'country', 'device_type', 'browser', 'source']);
        $where = $filter['where'] !== '' ? 'WHERE ' . $filter['where'] : '';

        $total = (int)db()->query(
            "SELECT COUNT(*) FROM {$this->table} {$where}",
            $filter['params']
        )->getColumn();

        $pagination = new Pagination($total, $perPage, PAGINATION_SETTINGS['midSize'], PAGINATION_SETTINGS['maxPages'], PAGINATION_SETTINGS['tpl'], 'visits_page');
        $offset = $pagination->getOffset();

        $items = db()->query(
            "SELECT id, created_at, ip, country, country_code, city, device_type, os, browser, source, landing_page, current_page
             FROM {$this->table}
             {$where}
             ORDER BY {$orderBy} {$direction}, id DESC
             LIMIT {$offset}, {$perPage}",
            $filter['params']
        )->get() ?: [];

        return [
            'items' => $items,
            'total' => $total,
            'pagination' => $pagination,
            'sort' => $sort,
            'direction' => strtolower($direction),
            'per_page' => $perPage,
        ];
    }

    public function filterOptions(): array
    {
        $this->ensureSchema();

        return [
            'countries' => $this->distinctValues('country'),
            'devices' => $this->distinctValues('device_type'),
            'browsers' => $this->distinctValues('browser'),
            'sources' => $this->distinctValues('source'),
        ];
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

    private function buildFilterWhere(array $params, array $enabledFilters): array
    {
        $where = [];
        $queryParams = [];

        if (!empty($params['from'])) {
            $where[] = 'created_at >= ?';
            $queryParams[] = (string)$params['from'];
        }

        if (!empty($params['to'])) {
            $where[] = 'created_at <= ?';
            $queryParams[] = (string)$params['to'];
        }

        if (in_array('search', $enabledFilters, true) && trim((string)($params['search'] ?? '')) !== '') {
            $search = '%' . trim((string)$params['search']) . '%';
            $where[] = '(current_page LIKE ? OR landing_page LIKE ? OR ip LIKE ? OR country LIKE ? OR city LIKE ?)';
            array_push($queryParams, $search, $search, $search, $search, $search);
        }

        $exactFilters = [
            'country' => 'country',
            'device_type' => 'device_type',
            'browser' => 'browser',
            'source' => 'source',
        ];

        foreach ($exactFilters as $key => $column) {
            if (!in_array($key, $enabledFilters, true)) {
                continue;
            }

            $value = trim((string)($params[$key] ?? ''));
            if ($value === '') {
                continue;
            }

            $where[] = "{$column} = ?";
            $queryParams[] = $value;
        }

        return [
            'where' => implode(' AND ', $where),
            'params' => $queryParams,
        ];
    }

    private function distinctValues(string $column): array
    {
        $this->ensureAllowedColumn($column);

        return db()->query(
            "SELECT DISTINCT {$column} AS value
             FROM {$this->table}
             WHERE {$column} IS NOT NULL AND {$column} <> ''
             ORDER BY {$column} ASC
             LIMIT 200"
        )->get() ?: [];
    }
}
