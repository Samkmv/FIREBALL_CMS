<?php

namespace App\Services;

use App\Repositories\AnalyticsRepository;

final class AnalyticsService
{
    private AnalyticsRepository $repository;
    private int $cacheTtl = 120;

    public function __construct(?AnalyticsRepository $repository = null)
    {
        $this->repository = $repository ?: new AnalyticsRepository();
    }

    public function trackPublicRequest(): void
    {
        if (!$this->shouldTrackRequest()) {
            return;
        }

        $currentPage = $this->currentPage();
        if (!session()->get('analytics.landing_page')) {
            session()->set('analytics.landing_page', $currentPage);
        }

        $ip = $this->clientIp();
        $userAgent = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        $referer = trim((string)($_SERVER['HTTP_REFERER'] ?? ''));
        $geo = $this->resolveGeo($ip);

        $this->repository->insertVisit([
            'session_id' => session_id() ?: (string)session()->get('session_id', ''),
            'ip' => $ip,
            'country' => $geo['country'] ?? null,
            'country_code' => $geo['country_code'] ?? null,
            'city' => $geo['city'] ?? null,
            'device_type' => $this->detectDeviceType($userAgent),
            'os' => $this->detectOs($userAgent),
            'browser' => $this->detectBrowser($userAgent),
            'referer' => $referer,
            'source' => $this->detectSource($referer, request()->get('utm_source', '')),
            'landing_page' => (string)session()->get('analytics.landing_page', $currentPage),
            'current_page' => $currentPage,
            'utm_source' => request()->get('utm_source', ''),
            'utm_medium' => request()->get('utm_medium', ''),
            'utm_campaign' => request()->get('utm_campaign', ''),
            'utm_content' => request()->get('utm_content', ''),
            'utm_term' => request()->get('utm_term', ''),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        cache()->remove('analytics:dashboard');
    }

    public function dashboardData(): array
    {
        $cached = cache()->get('analytics:dashboard');
        if (is_array($cached)) {
            return $cached;
        }

        $today = date('Y-m-d 00:00:00');
        $last7 = date('Y-m-d 00:00:00', strtotime('-6 days'));
        $last30 = date('Y-m-d 00:00:00', strtotime('-29 days'));
        $last90 = date('Y-m-d 00:00:00', strtotime('-89 days'));
        $visits30 = $this->repository->countVisitsSince($last30);
        $mobile30 = $this->repository->countByDeviceSince('Mobile', $last30);
        $desktop30 = $this->repository->countByDeviceSince('Desktop', $last30);

        $data = [
            'cards' => [
                'today_visits' => $this->repository->countVisitsSince($today),
                'today_unique' => $this->repository->countUniqueSince($today),
                'visits_7' => $this->repository->countVisitsSince($last7),
                'visits_30' => $visits30,
                'mobile_percent' => $visits30 > 0 ? round($mobile30 / $visits30 * 100, 1) : 0,
                'desktop_percent' => $visits30 > 0 ? round($desktop30 / $visits30 * 100, 1) : 0,
            ],
            'traffic' => [
                '7' => $this->normalizeSeries($this->repository->visitsByDay(7), 7),
                '30' => $this->normalizeSeries($this->repository->visitsByDay(30), 30),
                '90' => $this->normalizeSeries($this->repository->visitsByDay(90), 90),
            ],
            'sources' => $this->compactSources($this->repository->topGrouped('source', $last30, 20)),
            'countries' => $this->normalizeCountryRows($this->repository->topGrouped('country', $last30, 10)),
            'devices' => $this->repository->topGrouped('os', $last30, 10),
            'pages' => $this->repository->popularPages($last30, 20),
            'latest' => $this->normalizeVisitRows($this->repository->latest(20)),
        ];

        cache()->set('analytics:dashboard', $data, $this->cacheTtl);

        return $data;
    }

    public function fullAnalyticsData(array $query = []): array
    {
        $period = (string)($query['period'] ?? '30');
        if (!in_array($period, ['7', '30', '90', 'all'], true)) {
            $period = '30';
        }

        $dateRange = $this->dateRangeForPeriod($period);
        $filters = [
            'period' => $period,
            'search' => trim((string)($query['search'] ?? '')),
            'country' => trim((string)($query['country'] ?? '')),
            'device_type' => trim((string)($query['device_type'] ?? '')),
            'browser' => trim((string)($query['browser'] ?? '')),
            'source' => trim((string)($query['source'] ?? '')),
            'from' => $dateRange['from'],
            'to' => $dateRange['to'],
        ];

        $pages = $this->repository->paginatedPopularPages(array_merge($filters, [
            'per_page' => 20,
            'sort' => (string)($query['pages_sort'] ?? 'views'),
            'direction' => (string)($query['pages_direction'] ?? 'desc'),
        ]));
        $visits = $this->repository->paginatedVisits(array_merge($filters, [
            'per_page' => 20,
            'sort' => (string)($query['visits_sort'] ?? 'created_at'),
            'direction' => (string)($query['visits_direction'] ?? 'desc'),
        ]));
        $visits['items'] = $this->normalizeVisitRows((array)($visits['items'] ?? []));

        return [
            'filters' => $filters,
            'filter_options' => $this->repository->filterOptions(),
            'pages' => $pages,
            'visits' => $visits,
        ];
    }

    public function legacyStats(): array
    {
        return [
            'site_visits' => $this->repository->countVisitsSince('1970-01-01 00:00:00'),
            'page_views' => $this->repository->countVisitsSince('1970-01-01 00:00:00'),
        ];
    }

    public function ensureSchema(): void
    {
        $this->repository->ensureSchema();
    }

    public function resetAll(): void
    {
        $this->repository->resetAll();
        cache()->remove('analytics:dashboard');
        session()->remove('analytics.landing_page');
        session()->remove('analytics.last_visit_at');
    }

    private function shouldTrackRequest(): bool
    {
        if (!request()->isGet() || request()->isAjax()) {
            return false;
        }

        $path = '/' . trim((string)request()->getPath(), '/');
        if (preg_match('/\.(css|js|map|png|jpe?g|webp|gif|svg|ico|woff2?|ttf|xml|txt|json|webmanifest)$/i', $path)) {
            return false;
        }

        $excludedPrefixes = [
            '/admin',
            '/api',
            '/assets',
            '/uploads',
            '/logout',
            '/notifications/feed',
            '/search/suggest',
            '/chat/messages',
            '/chat/unread-count',
            '/add-to-cart',
            '/remove-from-cart',
        ];

        foreach ($excludedPrefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return false;
            }
        }

        return true;
    }

    private function currentPage(): string
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? current_path());
        return $uri !== '' ? mb_substr($uri, 0, 2048) : '/';
    }

    private function clientIp(): string
    {
        $headers = [
            $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
            $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
            $_SERVER['HTTP_X_REAL_IP'] ?? '',
            $_SERVER['REMOTE_ADDR'] ?? '',
        ];
        $fallback = '';

        foreach ($headers as $header) {
            foreach (explode(',', (string)$header) as $candidate) {
                $ip = $this->normalizeIp($candidate);
                if ($ip === '') {
                    continue;
                }

                if ($fallback === '') {
                    $fallback = $ip;
                }

                if (!$this->isPrivateIp($ip)) {
                    return $ip;
                }
            }
        }

        return $fallback !== '' ? $fallback : '0.0.0.0';
    }

    private function resolveGeo(string $ip): array
    {
        $cloudflareCountry = $this->cloudflareCountry();
        if ($cloudflareCountry !== null) {
            return $cloudflareCountry;
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP) || $this->isPrivateIp($ip)) {
            return ['country' => null, 'country_code' => null, 'city' => null];
        }

        if (class_exists('\\GeoIp2\\Database\\Reader')) {
            $paths = [
                ROOT . '/storage/geoip/GeoLite2-City.mmdb',
                ROOT . '/var/geoip/GeoLite2-City.mmdb',
                CONFIG . '/GeoLite2-City.mmdb',
            ];

            foreach ($paths as $path) {
                if (!is_file($path)) {
                    continue;
                }

                try {
                    $reader = new \GeoIp2\Database\Reader($path);
                    $record = $reader->city($ip);

                    return [
                        'country' => $record->country->names['ru'] ?? $record->country->name ?? null,
                        'country_code' => $record->country->isoCode ?? null,
                        'city' => $record->city->names['ru'] ?? $record->city->name ?? null,
                    ];
                } catch (\Throwable) {
                    return ['country' => null, 'country_code' => null, 'city' => null];
                }
            }
        }

        return ['country' => null, 'country_code' => null, 'city' => null];
    }

    private function normalizeIp(string $candidate): string
    {
        $candidate = trim($candidate);
        if ($candidate === '') {
            return '';
        }

        $candidate = trim($candidate, " \t\n\r\0\x0B\"'");

        if (str_starts_with($candidate, '[') && str_contains($candidate, ']')) {
            $candidate = substr($candidate, 1, strpos($candidate, ']') - 1);
        } elseif (substr_count($candidate, ':') === 1 && preg_match('/^(.+):\d+$/', $candidate, $matches)) {
            $candidate = $matches[1];
        }

        return filter_var($candidate, FILTER_VALIDATE_IP) ? $candidate : '';
    }

    private function cloudflareCountry(): ?array
    {
        $code = strtoupper(trim((string)($_SERVER['HTTP_CF_IPCOUNTRY'] ?? '')));
        if ($code === '' || in_array($code, ['XX', 'T1'], true) || !preg_match('/^[A-Z]{2}$/', $code)) {
            return null;
        }

        return [
            'country' => $this->countryNameFromCode($code),
            'country_code' => $code,
            'city' => null,
        ];
    }

    private function isPrivateIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    private function detectDeviceType(string $ua): string
    {
        $ua = strtolower($ua);
        if ($this->isBot($ua)) {
            return 'Bot';
        }
        if (str_contains($ua, 'ipad') || str_contains($ua, 'tablet')) {
            return 'Tablet';
        }
        if (str_contains($ua, 'mobile') || str_contains($ua, 'iphone') || str_contains($ua, 'android')) {
            return 'Mobile';
        }

        return 'Desktop';
    }

    private function detectOs(string $ua): string
    {
        $uaLower = strtolower($ua);
        if (str_contains($uaLower, 'ipad') || (str_contains($uaLower, 'macintosh') && str_contains($uaLower, 'mobile'))) {
            return 'iPadOS';
        }
        if (str_contains($uaLower, 'iphone') || str_contains($uaLower, 'ios')) {
            return 'iOS';
        }
        if (str_contains($uaLower, 'android')) {
            return 'Android';
        }
        if (str_contains($uaLower, 'windows')) {
            return 'Windows';
        }
        if (str_contains($uaLower, 'mac os') || str_contains($uaLower, 'macintosh')) {
            return 'macOS';
        }
        if (str_contains($uaLower, 'linux')) {
            return 'Linux';
        }
        if ($this->isBot($uaLower)) {
            return 'Bot';
        }

        return 'Other';
    }

    private function detectBrowser(string $ua): string
    {
        $ua = strtolower($ua);
        if ($this->isBot($ua)) {
            return 'Bot';
        }
        if (str_contains($ua, 'samsungbrowser')) {
            return 'Samsung Browser';
        }
        if (str_contains($ua, 'opr/') || str_contains($ua, 'opera')) {
            return 'Opera';
        }
        if (str_contains($ua, 'edg/')) {
            return 'Edge';
        }
        if (str_contains($ua, 'firefox/')) {
            return 'Firefox';
        }
        if (str_contains($ua, 'chrome/') || str_contains($ua, 'crios/')) {
            return 'Chrome';
        }
        if (str_contains($ua, 'safari/')) {
            return 'Safari';
        }

        return 'Other';
    }

    private function detectSource(string $referer, string $utmSource = ''): string
    {
        $utmSource = strtolower(trim($utmSource));
        if ($utmSource !== '') {
            return $this->normalizeSource($utmSource);
        }

        if ($referer === '') {
            return 'Direct';
        }

        $host = strtolower((string)parse_url($referer, PHP_URL_HOST));
        $currentHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        if ($host === '' || $host === $currentHost) {
            return 'Direct';
        }

        return $this->normalizeSource($host);
    }

    private function normalizeSource(string $value): string
    {
        $value = strtolower($value);
        return match (true) {
            str_contains($value, 'google') => 'Google',
            str_contains($value, 'yandex') => 'Yandex',
            str_contains($value, 'bing') => 'Bing',
            str_contains($value, 'telegram') || str_contains($value, 't.me') => 'Telegram',
            str_contains($value, 'vk.com') || $value === 'vk' => 'VK',
            str_contains($value, 'facebook') || str_contains($value, 'fb.com') => 'Facebook',
            str_contains($value, 'instagram') => 'Instagram',
            str_contains($value, 'twitter') || str_contains($value, 'x.com') => 'Twitter/X',
            $value === 'direct' => 'Direct',
            default => 'External',
        };
    }

    private function isBot(string $ua): bool
    {
        return preg_match('/bot|crawler|spider|slurp|bingpreview|facebookexternalhit|telegrambot|yandex/i', $ua) === 1;
    }

    private function normalizeSeries(array $rows, int $days): array
    {
        $map = [];
        foreach ($rows as $row) {
            $map[(string)$row['label']] = (int)$row['total'];
        }

        $labels = [];
        $values = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime('-' . $i . ' days'));
            $labels[] = $date;
            $values[] = $map[$date] ?? 0;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    private function normalizeCountryRows(array $rows): array
    {
        foreach ($rows as &$row) {
            $label = trim((string)($row['label'] ?? ''));
            $row['label'] = $this->displayCountryName($label);
        }
        unset($row);

        return $rows;
    }

    private function normalizeVisitRows(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['country'] = $this->displayCountryName((string)($row['country'] ?? ''));
        }
        unset($row);

        return $rows;
    }

    private function displayCountryName(string $value): string
    {
        $value = trim($value);
        if ($value === '' || in_array(mb_strtolower($value), ['unknown', 'неизвестно'], true)) {
            return return_translation('admin_analytics_country_unknown');
        }

        if (preg_match('/^[A-Z]{2}$/', strtoupper($value))) {
            return $this->countryNameFromCode(strtoupper($value));
        }

        return $value;
    }

    private function countryNameFromCode(string $code): string
    {
        $code = strtoupper(trim($code));
        $locale = $this->analyticsLocale();

        if (class_exists('\\Locale')) {
            $name = trim((string)\Locale::getDisplayRegion('-' . $code, $locale));
            if ($name !== '' && strtoupper($name) !== $code) {
                return $name;
            }
        }

        $fallback = [
            'RU' => 'Россия',
            'US' => 'United States',
            'DE' => 'Germany',
            'CN' => 'China',
            'GB' => 'United Kingdom',
            'FR' => 'France',
            'KZ' => 'Kazakhstan',
            'BY' => 'Belarus',
            'UA' => 'Ukraine',
            'TR' => 'Turkey',
        ];

        return $fallback[$code] ?? return_translation('admin_analytics_country_unknown');
    }

    private function analyticsLocale(): string
    {
        $code = (string)(app()->get('lang')['code'] ?? 'ru');

        return match ($code) {
            'en' => 'en_US',
            'de' => 'de_DE',
            'zh-cn' => 'zh_CN',
            default => 'ru_RU',
        };
    }

    private function compactSources(array $rows): array
    {
        $allowed = ['Google', 'Telegram', 'Direct', 'Yandex'];
        $result = [];
        $other = 0;

        foreach ($rows as $row) {
            $label = (string)$row['label'];
            $total = (int)$row['total'];
            if (in_array($label, $allowed, true)) {
                $result[] = ['label' => $label, 'total' => $total];
            } else {
                $other += $total;
            }
        }

        if ($other > 0) {
            $result[] = ['label' => 'Other', 'total' => $other];
        }

        return $result;
    }

    private function dateRangeForPeriod(string $period): array
    {
        if ($period === 'all') {
            return ['from' => null, 'to' => null];
        }

        $days = max(1, (int)$period);

        return [
            'from' => date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days')),
            'to' => date('Y-m-d 23:59:59'),
        ];
    }
}
