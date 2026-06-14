<?php

namespace App\Services;

use App\Repositories\AnalyticsRepository;

final class AnalyticsService
{
    private AnalyticsRepository $repository;
    private int $cacheTtl = 120;
    private const GEOIP_DOWNLOAD_MAX_BYTES = 33554432;
    private const GEOIP_DATABASE_MAX_BYTES = 134217728;
    private string $geoIpInstallError = '';

    public function __construct(?AnalyticsRepository $repository = null)
    {
        $this->loadGeoIpReader();
        $this->repository = $repository ?: new AnalyticsRepository();
    }

    private function loadGeoIpReader(): void
    {
        if (class_exists('\\MaxMind\\Db\\Reader')) {
            return;
        }

        $autoloadPath = ROOT . '/vendor/maxmind-db/reader/autoload.php';
        if (is_file($autoloadPath)) {
            require_once $autoloadPath;
        }
    }

    public function trackPublicRequest(): void
    {
        if (!$this->shouldTrackRequest()) {
            return;
        }

        $this->track([]);
    }

    public function track(array $payload): void
    {
        $payload = array_intersect_key($payload, array_flip([
            'page', 'landing_page', 'referer', 'utm_source', 'utm_medium',
            'utm_campaign', 'utm_content', 'utm_term',
        ]));
        $currentPage = $this->sanitizePage((string)($payload['page'] ?? '/'));
        if (!session()->get('analytics.landing_page')) {
            session()->set(
                'analytics.landing_page',
                $this->sanitizePage((string)($payload['landing_page'] ?? $currentPage))
            );
        }

        $ip = $this->clientIp();
        if ($this->isPrivateIp($ip)) {
            return;
        }

        $userAgent = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        $referer = $this->limitString((string)($payload['referer'] ?? ($_SERVER['HTTP_REFERER'] ?? '')), 2048);
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
            'source' => $this->detectSource($referer, $this->payloadValue($payload, 'utm_source')),
            'landing_page' => (string)session()->get('analytics.landing_page', $currentPage),
            'current_page' => $currentPage,
            'utm_source' => $this->payloadValue($payload, 'utm_source'),
            'utm_medium' => $this->payloadValue($payload, 'utm_medium'),
            'utm_campaign' => $this->payloadValue($payload, 'utm_campaign'),
            'utm_content' => $this->payloadValue($payload, 'utm_content'),
            'utm_term' => $this->payloadValue($payload, 'utm_term'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
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

    public function clearDashboardCache(): void
    {
        cache()->remove('analytics:dashboard');
    }

    public function refreshGeoData(int $limit = 1000): int
    {
        $databasePath = $this->geoIpDatabasePath();
        if ($databasePath === null || !class_exists('\\MaxMind\\Db\\Reader')) {
            return 0;
        }

        $updated = 0;
        $reader = new \MaxMind\Db\Reader($databasePath);

        try {
            foreach ($this->repository->visitsWithoutGeo($limit) as $visit) {
                $id = (int)($visit['id'] ?? 0);
                $ip = $this->normalizeIp((string)($visit['ip'] ?? ''));
                if ($id <= 0 || $ip === '' || $this->isPrivateIp($ip)) {
                    continue;
                }

                try {
                    $geo = $this->geoFromReader($reader, $ip);
                    if (empty($geo['country_code'])) {
                        continue;
                    }

                    $this->repository->updateVisitGeo($id, $geo);
                    $updated++;
                } catch (\Throwable $exception) {
                    log_error_details('Analytics GeoIP refresh failed', ['Visit ID' => $id, 'IP' => $ip], $exception);
                }
            }
        } finally {
            $reader->close();
        }

        if ($updated > 0) {
            $this->clearDashboardCache();
        }

        return $updated;
    }

    public function installGeoIpDatabase(): bool
    {
        $this->geoIpInstallError = '';

        if ($this->geoIpDatabasePath() !== null && class_exists('\\MaxMind\\Db\\Reader')) {
            return true;
        }

        if (!class_exists('\\MaxMind\\Db\\Reader')) {
            $this->geoIpInstallError = 'MMDB reader is unavailable. Run composer install.';
            return false;
        }

        $directory = $this->geoIpWritableDirectory();
        if ($directory === null) {
            $this->geoIpInstallError = 'No writable GeoIP directory: storage/geoip, var/geoip or tmp/cache/geoip.';
            return false;
        }

        $targetPath = $directory . '/GeoLite2-City.mmdb';
        $token = bin2hex(random_bytes(6));
        $archivePath = $directory . '/geoip-' . $token . '.mmdb.gz';
        $databasePath = $directory . '/geoip-' . $token . '.mmdb';
        $errors = [];

        try {
            foreach ($this->geoIpDownloadUrls() as $url) {
                try {
                    $this->downloadGeoIpArchive($url, $archivePath);
                    $this->extractGeoIpArchive($archivePath, $databasePath);
                    $this->validateGeoIpDatabase($databasePath);

                    if (!@rename($databasePath, $targetPath)) {
                        throw new \RuntimeException('Unable to install GeoIP database.');
                    }

                    @chmod($targetPath, 0644);
                    @file_put_contents(
                        $directory . '/database.json',
                        json_encode([
                            'provider' => 'DB-IP',
                            'source' => $url,
                            'installed_at' => date('c'),
                        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                        LOCK_EX
                    );

                    return true;
                } catch (\Throwable $exception) {
                    @unlink($archivePath);
                    @unlink($databasePath);
                    $errors[] = basename($url) . ': ' . $exception->getMessage();
                    log_error_details('GeoIP database download failed', ['URL' => $url], $exception);
                }
            }
        } finally {
            @unlink($archivePath);
            @unlink($databasePath);
        }

        $this->geoIpInstallError = implode(' | ', array_slice(array_unique($errors), 0, 3));
        return false;
    }

    public function geoIpInstallError(): string
    {
        return $this->geoIpInstallError;
    }

    public function geoIpStatus(): array
    {
        $databasePath = $this->geoIpDatabasePath();

        return [
            'connected' => $databasePath !== null && class_exists('\\MaxMind\\Db\\Reader'),
            'database_found' => $databasePath !== null,
            'reader_available' => class_exists('\\MaxMind\\Db\\Reader'),
            'path' => $databasePath,
        ];
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

    private function clientIp(): string
    {
        $ip = $this->normalizeIp(client_ip());
        if ($ip !== '' && !$this->isPrivateIp($ip)) {
            return $ip;
        }

        $remoteAddress = $this->normalizeIp((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        if ($remoteAddress === '' || !$this->isPrivateIp($remoteAddress)) {
            return $ip !== '' ? $ip : '0.0.0.0';
        }

        foreach ([
            $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
            $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
            $_SERVER['HTTP_X_REAL_IP'] ?? '',
        ] as $header) {
            foreach (explode(',', (string)$header) as $candidate) {
                $candidate = $this->normalizeIp($candidate);
                if ($candidate !== '' && !$this->isPrivateIp($candidate)) {
                    return $candidate;
                }
            }
        }

        return $ip !== '' ? $ip : '0.0.0.0';
    }

    private function resolveGeo(string $ip): array
    {
        $cloudflareCountry = $this->cloudflareCountry();
        if ($cloudflareCountry !== null) {
            return $cloudflareCountry;
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP) || $this->isPrivateIp($ip)) {
            return $this->unknownGeo();
        }

        $databasePath = $this->geoIpDatabasePath();
        if ($databasePath !== null && class_exists('\\MaxMind\\Db\\Reader')) {
            try {
                $reader = new \MaxMind\Db\Reader($databasePath);
                try {
                    return $this->geoFromReader($reader, $ip);
                } finally {
                    $reader->close();
                }
            } catch (\Throwable $exception) {
                log_error_details('GeoIP lookup failed', ['Database' => $databasePath, 'IP' => $ip], $exception);
            }
        }

        return $this->unknownGeo();
    }

    private function geoIpDatabasePath(): ?string
    {
        foreach ([
            ROOT . '/storage/geoip/GeoLite2-City.mmdb',
            ROOT . '/var/geoip/GeoLite2-City.mmdb',
            CACHE . '/geoip/GeoLite2-City.mmdb',
            CONFIG . '/GeoLite2-City.mmdb',
        ] as $path) {
            if (is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    private function geoIpWritableDirectory(): ?string
    {
        foreach ([
            ROOT . '/storage/geoip',
            ROOT . '/var/geoip',
            CACHE . '/geoip',
        ] as $directory) {
            if (!is_dir($directory) && !@mkdir($directory, 0755, true)) {
                continue;
            }
            if (is_writable($directory)) {
                return $directory;
            }
        }

        return null;
    }

    private function geoFromReader(\MaxMind\Db\Reader $reader, string $ip): array
    {
        $record = $reader->get($ip);
        if (!is_array($record)) {
            return $this->unknownGeo();
        }

        $country = is_array($record['country'] ?? null) ? $record['country'] : [];
        $city = is_array($record['city'] ?? null) ? $record['city'] : [];
        $countryNames = is_array($country['names'] ?? null) ? $country['names'] : [];
        $cityNames = is_array($city['names'] ?? null) ? $city['names'] : [];

        return [
            'country' => $countryNames['ru'] ?? $countryNames['en'] ?? 'Unknown',
            'country_code' => $country['iso_code'] ?? null,
            'city' => $cityNames['ru'] ?? $cityNames['en'] ?? null,
        ];
    }

    private function geoIpDownloadUrls(): array
    {
        $urls = [];
        for ($offset = 0; $offset < 3; $offset++) {
            $month = date('Y-m', strtotime('-' . $offset . ' month'));
            $urls[] = 'https://download.db-ip.com/free/dbip-country-lite-' . $month . '.mmdb.gz';
        }

        return $urls;
    }

    private function downloadGeoIpArchive(string $url, string $targetPath): void
    {
        $errors = [];

        if (function_exists('curl_init')) {
            try {
                $this->downloadGeoIpWithCurl($url, $targetPath);
                $this->validateGeoIpArchiveSize($targetPath);
                return;
            } catch (\Throwable $exception) {
                $errors[] = 'PHP cURL: ' . $exception->getMessage();
                @unlink($targetPath);
            }
        }

        if (filter_var((string)ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
            try {
                $this->downloadGeoIpWithStream($url, $targetPath);
                $this->validateGeoIpArchiveSize($targetPath);
                return;
            } catch (\Throwable $exception) {
                $errors[] = 'PHP stream: ' . $exception->getMessage();
                @unlink($targetPath);
            }
        }

        if ($this->canRunSystemProcess()) {
            try {
                $this->downloadGeoIpWithSystemCurl($url, $targetPath);
                $this->validateGeoIpArchiveSize($targetPath);
                return;
            } catch (\Throwable $exception) {
                $errors[] = 'system curl: ' . $exception->getMessage();
                @unlink($targetPath);
            }
        }

        throw new \RuntimeException($errors !== []
            ? implode('; ', $errors)
            : 'No HTTPS download transport is available.');
    }

    private function downloadGeoIpWithCurl(string $url, string $targetPath): void
    {
        $handle = @fopen($targetPath, 'wb');
        if (!is_resource($handle)) {
            throw new \RuntimeException('Unable to create download file.');
        }

        $curl = null;
        try {
            $curl = curl_init($url);
            if ($curl === false) {
                throw new \RuntimeException('Unable to initialize cURL.');
            }
            if (!curl_setopt_array($curl, [
                CURLOPT_FILE => $handle,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 90,
                CURLOPT_FAILONERROR => true,
                CURLOPT_USERAGENT => 'FIREBALL-CMS GeoIP Updater',
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            ])) {
                throw new \RuntimeException('Unable to configure cURL.');
            }

            $success = curl_exec($curl);
            $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $error = curl_error($curl);
            if ($success !== true || $status < 200 || $status >= 300) {
                throw new \RuntimeException($error !== '' ? $error : 'HTTP ' . $status);
            }
        } finally {
            if ($curl instanceof \CurlHandle) {
                curl_close($curl);
            }
            fclose($handle);
        }
    }

    private function downloadGeoIpWithStream(string $url, string $targetPath): void
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 90,
                'follow_location' => 1,
                'max_redirects' => 3,
                'user_agent' => 'FIREBALL-CMS GeoIP Updater',
            ],
        ]);
        $source = @fopen($url, 'rb', false, $context);
        $target = @fopen($targetPath, 'wb');
        if (!is_resource($source) || !is_resource($target)) {
            if (is_resource($source)) {
                fclose($source);
            }
            if (is_resource($target)) {
                fclose($target);
            }
            throw new \RuntimeException('Unable to open HTTPS stream.');
        }

        try {
            $copied = stream_copy_to_stream($source, $target, self::GEOIP_DOWNLOAD_MAX_BYTES + 1);
            if ($copied === false) {
                throw new \RuntimeException('Unable to copy response.');
            }
        } finally {
            fclose($source);
            fclose($target);
        }
    }

    private function downloadGeoIpWithSystemCurl(string $url, string $targetPath): void
    {
        $process = proc_open([
            'curl',
            '--fail',
            '--location',
            '--silent',
            '--show-error',
            '--connect-timeout',
            '10',
            '--max-time',
            '90',
            '--output',
            $targetPath,
            $url,
        ], [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);
        if (!is_resource($process)) {
            throw new \RuntimeException('Unable to start curl.');
        }

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            throw new \RuntimeException(trim((string)$stderr) ?: trim((string)$stdout) ?: 'curl exit ' . $exitCode);
        }
    }

    private function validateGeoIpArchiveSize(string $targetPath): void
    {
        clearstatcache(true, $targetPath);
        $size = @filesize($targetPath);
        if (!is_int($size) || $size <= 0 || $size > self::GEOIP_DOWNLOAD_MAX_BYTES) {
            throw new \RuntimeException('GeoIP archive has an invalid size.');
        }
    }

    private function canRunSystemProcess(): bool
    {
        if (!function_exists('proc_open')) {
            return false;
        }

        $disabled = array_filter(array_map('trim', explode(',', (string)ini_get('disable_functions'))));
        return !in_array('proc_open', $disabled, true);
    }

    private function extractGeoIpArchive(string $archivePath, string $targetPath): void
    {
        if (!function_exists('gzopen')) {
            $this->extractGeoIpWithSystemGzip($archivePath, $targetPath);
            return;
        }

        $source = @gzopen($archivePath, 'rb');
        $target = @fopen($targetPath, 'wb');
        if (!is_resource($source) || !is_resource($target)) {
            if (is_resource($source)) {
                gzclose($source);
            }
            if (is_resource($target)) {
                fclose($target);
            }
            throw new \RuntimeException('Unable to extract GeoIP archive.');
        }

        $written = 0;
        try {
            while (!gzeof($source)) {
                $chunk = gzread($source, 1048576);
                if ($chunk === false) {
                    throw new \RuntimeException('Unable to read GeoIP archive.');
                }
                $written += strlen($chunk);
                if ($written > self::GEOIP_DATABASE_MAX_BYTES || fwrite($target, $chunk) === false) {
                    throw new \RuntimeException('GeoIP database has an invalid size.');
                }
            }
        } finally {
            gzclose($source);
            fclose($target);
        }
    }

    private function extractGeoIpWithSystemGzip(string $archivePath, string $targetPath): void
    {
        if (!$this->canRunSystemProcess()) {
            throw new \RuntimeException('Neither PHP zlib nor system gzip is available.');
        }

        $target = @fopen($targetPath, 'wb');
        if (!is_resource($target)) {
            throw new \RuntimeException('Unable to create GeoIP database file.');
        }

        $process = proc_open(['gzip', '--decompress', '--stdout', $archivePath], [
            1 => $target,
            2 => ['pipe', 'w'],
        ], $pipes);
        if (!is_resource($process)) {
            fclose($target);
            throw new \RuntimeException('Unable to start gzip.');
        }

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        fclose($target);

        if ($exitCode !== 0) {
            throw new \RuntimeException(trim((string)$stderr) ?: 'gzip exit ' . $exitCode);
        }

        clearstatcache(true, $targetPath);
        $size = @filesize($targetPath);
        if (!is_int($size) || $size <= 0 || $size > self::GEOIP_DATABASE_MAX_BYTES) {
            throw new \RuntimeException('GeoIP database has an invalid size.');
        }
    }

    private function validateGeoIpDatabase(string $path): void
    {
        if (!class_exists('\\MaxMind\\Db\\Reader')) {
            throw new \RuntimeException('MMDB reader is unavailable.');
        }

        $reader = new \MaxMind\Db\Reader($path);
        try {
            $record = $reader->get('8.8.8.8');
            if (!is_array($record) || strtoupper((string)($record['country']['iso_code'] ?? '')) !== 'US') {
                throw new \RuntimeException('Downloaded GeoIP database failed validation.');
            }
        } finally {
            $reader->close();
        }
    }

    private function unknownGeo(): array
    {
        return ['country' => 'Unknown', 'country_code' => null, 'city' => null];
    }

    private function sanitizePage(string $page): string
    {
        $page = trim($page);
        if ($page === '' || preg_match('/[\r\n\0]/', $page)) {
            return '/';
        }

        $parts = parse_url($page);
        if ($parts === false || isset($parts['scheme']) || isset($parts['host'])) {
            return '/';
        }

        $path = '/' . ltrim((string)($parts['path'] ?? '/'), '/');
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';

        return $this->limitString($path . $query, 2048);
    }

    private function payloadValue(array $payload, string $key): string
    {
        return $this->limitString((string)($payload[$key] ?? ''), 255);
    }

    private function limitString(string $value, int $length): string
    {
        return mb_substr(trim($value), 0, $length);
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
        if (!is_trusted_proxy()) {
            return null;
        }

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
            $country = trim((string)($row['country'] ?? ''));
            $countryCode = strtoupper(trim((string)($row['country_code'] ?? '')));
            if (($country === '' || in_array(mb_strtolower($country), ['unknown', 'неизвестно'], true))
                && preg_match('/^[A-Z]{2}$/', $countryCode)
            ) {
                $country = $countryCode;
            }

            $row['country'] = $this->displayCountryName($country);
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
