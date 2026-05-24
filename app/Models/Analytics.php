<?php

namespace App\Models;

/**
 * Собирает базовую аналитику публичной части сайта: просмотры страниц и визиты.
 */
class Analytics
{

    protected string $table = 'site_metrics';
    protected int $visitLifetime = 1800;
    protected static bool $schemaReady = false;

    /**
     * Учитывает текущий публичный запрос в метриках сайта.
     */
    public function trackPublicRequest(): void
    {
        if (!$this->shouldTrackRequest()) {
            return;
        }

        $this->ensureSchema();
        $this->incrementMetric('page_views');

        if ($this->shouldCountVisit()) {
            $this->incrementMetric('site_visits');
            session()->set('analytics.last_visit_at', time());
        }
    }

    /**
     * Возвращает текущие агрегированные значения основных метрик.
     */
    public function getStats(): array
    {
        $this->ensureSchema();

        return [
            'site_visits' => $this->getMetricValue('site_visits'),
            'page_views' => $this->getMetricValue('page_views'),
        ];
    }

    /**
     * Определяет, нужно ли учитывать текущий запрос в аналитике.
     */
    protected function shouldTrackRequest(): bool
    {
        if (!request()->isGet() || request()->isAjax()) {
            return false;
        }

        $path = '/' . trim((string)request()->getPath(), '/');
        $excludedPaths = [
            '/admin',
            '/logout',
            '/notifications/feed',
            '/search/suggest',
            '/chat/messages',
            '/chat/unread-count',
            '/add-to-cart',
            '/remove-from-cart',
        ];

        foreach ($excludedPaths as $excludedPath) {
            if ($path === $excludedPath || str_starts_with($path, $excludedPath . '/')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Проверяет, следует ли считать текущий просмотр новым визитом.
     */
    protected function shouldCountVisit(): bool
    {
        $lastVisitAt = (int)session()->get('analytics.last_visit_at', 0);
        return $lastVisitAt <= 0 || (time() - $lastVisitAt) >= $this->visitLifetime;
    }

    /**
     * Увеличивает значение указанной метрики на заданный шаг.
     */
    protected function incrementMetric(string $key, int $step = 1): void
    {
        db()->query(
            "INSERT INTO {$this->table} (metric_key, metric_value, updated_at)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE
                metric_value = metric_value + VALUES(metric_value),
                updated_at = VALUES(updated_at)",
            [$key, max(1, $step), date('Y-m-d H:i:s')]
        );
    }

    /**
     * Возвращает числовое значение метрики по её ключу.
     */
    protected function getMetricValue(string $key): int
    {
        return (int)db()->query(
            "SELECT metric_value FROM {$this->table} WHERE metric_key = ? LIMIT 1",
            [$key]
        )->getColumn();
    }

    /**
     * Создаёт таблицу метрик при первом обращении, если она ещё не существует.
     */
    protected function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }

        db()->query(
            "CREATE TABLE IF NOT EXISTS {$this->table} (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                metric_key VARCHAR(100) NOT NULL,
                metric_value BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY metric_key (metric_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        self::$schemaReady = true;
    }

}
