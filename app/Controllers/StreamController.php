<?php

namespace App\Controllers;

final class StreamController extends BaseController
{
    public function wake(): void
    {
        $rawStreamId = request()->post('stream_id', '');
        $streamId = is_scalar($rawStreamId) ? trim((string)$rawStreamId) : '';

        if ($streamId === '' || !preg_match('/^[a-zA-Z0-9_-]+$/', $streamId)) {
            response()->json([
                'success' => false,
                'stream_id' => $streamId,
                'woke' => false,
                'ready' => false,
                'message' => 'Invalid stream_id',
            ]);
        }

        $config = stream_config();
        $rawHlsUrl = request()->post('hls_url', '');
        $hlsUrl = $this->normalizeClientHlsUrl(is_scalar($rawHlsUrl) ? trim((string)$rawHlsUrl) : '');

        if (!$this->isAllowedHlsUrl($hlsUrl, $streamId)) {
            response()->json([
                'success' => false,
                'stream_id' => $streamId,
                'woke' => false,
                'ready' => false,
                'message' => 'Invalid HLS URL',
            ]);
        }

        $readyCacheSeconds = (int)($config['ready_cache_seconds'] ?? 0);
        if ($this->hasReadyCache($streamId, $hlsUrl, $readyCacheSeconds)) {
            response()->json([
                'success' => true,
                'stream_id' => $streamId,
                'woke' => true,
                'ready' => true,
                'message' => 'HLS is ready (cached)',
            ]);
            return;
        }

        $ready = $this->waitForHlsReady(
            $hlsUrl,
            (int)$config['ready_timeout_seconds'],
            (int)$config['ready_interval_ms'],
            (int)$config['http_timeout_seconds'],
            (int)($config['ready_segment_probe_count'] ?? 3)
        );

        if ($ready['ready']) {
            $this->markReadyCache($streamId, $hlsUrl);
        }

        response()->json([
            'success' => true,
            'stream_id' => $streamId,
            'woke' => true,
            'ready' => $ready['ready'],
            'message' => $ready['message'],
        ]);
    }

    private function waitForHlsReady(
        string $manifestUrl,
        int $timeoutSeconds,
        int $intervalMs,
        int $httpTimeoutSeconds,
        int $segmentProbeCount
    ): array {
        $deadline = microtime(true) + $timeoutSeconds;
        $lastMessage = 'HLS manifest exists but segments are not ready';

        do {
            $check = $this->checkHlsReady($manifestUrl, $httpTimeoutSeconds, $segmentProbeCount);
            if ($check['ready']) {
                return [
                    'ready' => true,
                    'message' => 'HLS is ready',
                ];
            }

            $lastMessage = $check['message'];
            if (!($check['retry'] ?? true)) {
                break;
            }

            if (microtime(true) < $deadline) {
                usleep($intervalMs * 1000);
            }
        } while (microtime(true) < $deadline);

        return [
            'ready' => false,
            'message' => $lastMessage,
        ];
    }

    private function checkHlsReady(
        string $manifestUrl,
        int $httpTimeoutSeconds,
        int $segmentProbeCount
    ): array {
        // We need the manifest body, so HEAD only adds a redundant round trip.
        $manifest = $this->httpRequest($manifestUrl, 'GET', $httpTimeoutSeconds);

        if ($manifest['status'] !== 200) {
            return [
                'ready' => false,
                'retry' => $this->isRetryableHlsStatus((int)$manifest['status']),
                'message' => 'HLS manifest is not available',
            ];
        }

        $body = trim((string)$manifest['body']);
        if ($body === '' || !str_contains($body, '#EXTM3U')) {
            return [
                'ready' => false,
                'retry' => true,
                'message' => 'HLS manifest is empty or invalid',
            ];
        }

        if (!str_contains($body, '#EXTINF')) {
            return [
                'ready' => false,
                'retry' => true,
                'message' => 'HLS manifest exists but segments are not ready',
            ];
        }

        $segmentUrls = $this->mediaSegmentUrls(
            $body,
            $manifestUrl,
            max(1, $segmentProbeCount)
        );

        if ($segmentUrls === []) {
            return [
                'ready' => false,
                'retry' => true,
                'message' => 'HLS manifest exists but segments are not ready',
            ];
        }

        $retry = false;

        // Newest -> oldest. In a rolling live playlist an older segment can be
        // deleted between the manifest fetch and the probe, so one 404 must not
        // make a healthy stream look cold.
        foreach ($segmentUrls as $segmentUrl) {
            $segment = $this->httpRequest($segmentUrl, 'HEAD', $httpTimeoutSeconds);
            if (!in_array($segment['status'], [200, 206], true)) {
                $segment = $this->httpRequest($segmentUrl, 'GET', $httpTimeoutSeconds, [0, 0]);
            }

            $status = (int)$segment['status'];
            if (in_array($status, [200, 206], true)) {
                return [
                    'ready' => true,
                    'retry' => false,
                    'message' => 'HLS is ready',
                ];
            }

            if ($this->isRetryableHlsStatus($status)) {
                $retry = true;
            }
        }

        return [
            'ready' => false,
            'retry' => $retry,
            'message' => 'HLS manifest exists but recent segments are not ready',
        ];
    }

    private function mediaSegmentUrls(
        string $manifest,
        string $manifestUrl,
        int $limit = 3
    ): array {
        $lines = preg_split('/\r\n|\r|\n/', $manifest) ?: [];
        $segments = [];
        $expectSegment = false;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (str_starts_with($line, '#EXTINF')) {
                $expectSegment = true;
                continue;
            }

            // Tags such as EXT-X-BYTERANGE may appear between EXTINF and URI.
            if (str_starts_with($line, '#')) {
                continue;
            }

            if (!$expectSegment) {
                continue;
            }

            $segmentUrl = $this->resolveUrl($manifestUrl, $line);
            if ($segmentUrl !== '') {
                $segments[] = $segmentUrl;
            }

            $expectSegment = false;
        }

        if ($segments === []) {
            return [];
        }

        return array_slice(array_reverse($segments), 0, max(1, $limit));
    }

    private function isRetryableHlsStatus(int $status): bool
    {
        return $status === 0
            || in_array($status, [404, 408, 425, 429], true)
            || ($status >= 500 && $status <= 599);
    }

    private function hasReadyCache(
        string $streamId,
        string $manifestUrl,
        int $ttlSeconds
    ): bool {
        if ($ttlSeconds <= 0) {
            return false;
        }

        $path = $this->readyCachePath($streamId, $manifestUrl);
        if ($path === '' || !is_file($path)) {
            return false;
        }

        $modifiedAt = @filemtime($path);
        if ($modifiedAt === false) {
            return false;
        }

        if ((time() - $modifiedAt) <= $ttlSeconds) {
            return true;
        }

        @unlink($path);
        return false;
    }

    private function markReadyCache(string $streamId, string $manifestUrl): void
    {
        $path = $this->readyCachePath($streamId, $manifestUrl, true);
        if ($path === '') {
            return;
        }

        try {
            $suffix = bin2hex(random_bytes(4));
        } catch (\Throwable) {
            $suffix = str_replace('.', '', uniqid('', true));
        }

        $temporaryPath = $path . '.tmp-' . $suffix;
        $payload = json_encode([
            'stream_id' => $streamId,
            'ready_at' => microtime(true),
        ], JSON_UNESCAPED_SLASHES);

        if (!is_string($payload) || @file_put_contents($temporaryPath, $payload, LOCK_EX) === false) {
            @unlink($temporaryPath);
            return;
        }

        if (!@rename($temporaryPath, $path)) {
            @unlink($temporaryPath);
        }
    }

    private function readyCachePath(
        string $streamId,
        string $manifestUrl,
        bool $createDirectory = false
    ): string {
        $base = defined('CACHE')
            ? rtrim((string)CACHE, '/\\')
            : rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'fireball-cache';

        $directory = $base . DIRECTORY_SEPARATOR . 'streams';

        if ($createDirectory && !is_dir($directory)) {
            if (!@mkdir($directory, 0755, true) && !is_dir($directory)) {
                return '';
            }
        }

        if (!is_dir($directory)) {
            return '';
        }

        $key = hash('sha256', $streamId . '|' . $manifestUrl);

        return $directory . DIRECTORY_SEPARATOR . 'ready-' . $key . '.json';
    }

    private function resolveUrl(string $baseUrl, string $path): string
    {
        if ($this->isHttpUrl($path)) {
            return $path;
        }

        $base = parse_url($baseUrl);
        if (!is_array($base) || empty($base['scheme']) || empty($base['host'])) {
            return '';
        }

        $scheme = (string)$base['scheme'];
        if (str_starts_with($path, '//')) {
            return $scheme . ':' . $path;
        }

        $host = (string)$base['host'];
        $port = isset($base['port']) ? ':' . (int)$base['port'] : '';
        $basePath = (string)($base['path'] ?? '/');
        $directory = preg_replace('~/[^/]*$~', '/', $basePath) ?: '/';

        if (str_starts_with($path, '/')) {
            $resolvedPath = $path;
        } else {
            $resolvedPath = $directory . $path;
        }

        return $scheme . '://' . $host . $port . $this->normalizeUrlPath($resolvedPath);
    }

    private function normalizeUrlPath(string $path): string
    {
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);
                continue;
            }

            $segments[] = $segment;
        }

        return '/' . implode('/', $segments);
    }

    private function httpRequest(string $url, string $method, int $timeoutSeconds, ?array $range = null): array
    {
        $method = strtoupper($method);
        if (!in_array($method, ['GET', 'HEAD', 'POST'], true) || !$this->isHttpUrl($url)) {
            return ['status' => 0, 'body' => ''];
        }

        if (function_exists('curl_init')) {
            return $this->curlRequest($url, $method, $timeoutSeconds, $range);
        }

        return $this->streamRequest($url, $method, $timeoutSeconds, $range);
    }

    private function curlRequest(string $url, string $method, int $timeoutSeconds, ?array $range): array
    {
        $handle = curl_init($url);
        if (!$handle) {
            return ['status' => 0, 'body' => ''];
        }

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => $timeoutSeconds,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_USERAGENT => 'FIREBALL-CMS/stream-wake',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
            curl_setopt($handle, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        }

        if (defined('CURLOPT_REDIR_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
            curl_setopt($handle, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        }

        if ($method === 'HEAD') {
            curl_setopt($handle, CURLOPT_NOBODY, true);
        }

        if ($range !== null) {
            curl_setopt($handle, CURLOPT_RANGE, (int)$range[0] . '-' . (int)$range[1]);
        }

        $body = curl_exec($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        return [
            'status' => $status,
            'body' => is_string($body) ? $body : '',
        ];
    }

    private function streamRequest(string $url, string $method, int $timeoutSeconds, ?array $range): array
    {
        $headers = ["User-Agent: FIREBALL-CMS/stream-wake"];
        if ($range !== null) {
            $headers[] = 'Range: bytes=' . (int)$range[0] . '-' . (int)$range[1];
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'timeout' => $timeoutSeconds,
                'ignore_errors' => true,
                'header' => implode("\r\n", $headers),
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        $status = 0;
        foreach (($http_response_header ?? []) as $header) {
            if (preg_match('~^HTTP/\S+\s+(\d{3})~', $header, $match)) {
                $status = (int)$match[1];
                break;
            }
        }

        return [
            'status' => $status,
            'body' => is_string($body) ? $body : '',
        ];
    }

    private function isHttpUrl(string $url): bool
    {
        $parts = parse_url($url);

        return is_array($parts)
            && in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true)
            && !empty($parts['host']);
    }

    private function normalizeClientHlsUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if ($this->isHttpUrl($url)) {
            return $url;
        }

        if (str_starts_with($url, '//') || preg_match('~^[a-z][a-z0-9+.-]*:~i', $url)) {
            return '';
        }

        return base_url('/' . ltrim($url, '/'));
    }

    private function isAllowedHlsUrl(string $url, string $streamId): bool
    {
        if (!$this->isHttpUrl($url)) {
            return false;
        }

        $path = (string)(parse_url($url, PHP_URL_PATH) ?? '');
        $match = [];
        if (!preg_match('~/stream-([^/]+)/index\.m3u8$~i', $path, $match)) {
            return false;
        }

        return hash_equals($streamId, (string)$match[1]);
    }

}
