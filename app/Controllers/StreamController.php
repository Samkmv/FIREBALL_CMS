<?php

namespace App\Controllers;

final class StreamController extends BaseController
{
    public function wake(): void
    {
        $rawStreamId = request()->post('stream_id', '');
        $streamId = is_scalar($rawStreamId) ? trim((string)$rawStreamId) : '';

        if ($streamId === '' || !preg_match('/^[a-zA-Z0-9_-]{1,100}$/', $streamId)) {
            response()->json([
                'success' => false,
                'stream_id' => $streamId,
                'woke' => false,
                'ready' => false,
                'message' => 'Invalid stream_id',
                'state' => 'failed', 'retryable' => false, 'code' => 'INVALID_STREAM',
            ]);
            return;
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
                'state' => 'failed', 'retryable' => false, 'code' => 'INVALID_HLS_URL',
            ]);
            return;
        }

        $result = $this->coordinateReadiness($streamId, $hlsUrl, $config);
        response()->json(array_merge([
            'success' => true,
            'stream_id' => $streamId,
            'woke' => true,
        ], $result));
    }

    private function coordinateReadiness(string $streamId, string $url, array $config): array
    {
        // Bounded lock slots, kept under the cache's persistent .locks directory.
        // Never unlink a lock inode: other workers may already hold its descriptor.
        $key = hash('sha256', $streamId . '|' . $url);
        $directory = rtrim(CACHE, '/\\') . '/.locks';
        if (!is_dir($directory)) { @mkdir($directory, 0755, true); }
        $lock = @fopen($directory . '/stream-' . substr($key, 0, 2), 'c+');
        if (!$lock) {
            return ['ready' => false, 'state' => 'failed', 'retryable' => true,
                'code' => 'COORDINATION_UNAVAILABLE', 'message' => 'Camera readiness is temporarily unavailable'];
        }
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            return ['ready' => false, 'state' => 'starting', 'retryable' => true,
                'code' => 'STARTING', 'message' => 'Camera readiness check is in progress'];
        }
        try {
            $cached = json_decode((string)stream_get_contents($lock, 4096), true);
            if (is_array($cached) && ($cached['key'] ?? '') === $key
                && ($cached['expires'] ?? 0) > microtime(true)) {
                return ['ready' => true, 'state' => 'ready', 'retryable' => false,
                    'code' => 'READY_CACHED', 'message' => 'HLS is ready'];
            }
            $ready = $this->waitForHlsReady($url, (int)$config['ready_timeout_seconds'],
                (int)$config['ready_interval_ms'], (int)$config['http_timeout_seconds'],
                (int)($config['ready_segment_probe_count'] ?? 3));
            rewind($lock);
            ftruncate($lock, 0);
            if ($ready['ready']) {
                fwrite($lock, json_encode(['key' => $key,
                    'expires' => microtime(true) + (int)($config['ready_cache_seconds'] ?? 5)]));
                fflush($lock);
            }
            return $ready + ['state' => $ready['ready'] ? 'ready' : 'failed',
                'retryable' => !$ready['ready'], 'code' => $ready['ready'] ? 'READY' : 'SEGMENTS_NOT_READY'];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
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
        $lastCode = 'SEGMENTS_NOT_READY';

        do {
            $check = $this->checkHlsReady($manifestUrl, $httpTimeoutSeconds, $segmentProbeCount);
            if ($check['ready']) {
                return [
                    'ready' => true,
                    'message' => 'HLS is ready',
                    'code' => 'READY',
                ];
            }

            $lastMessage = $check['message'];
            $lastCode = $check['code'] ?? 'SEGMENTS_NOT_READY';
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
            'code' => $lastCode,
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
                'code' => $manifest['status'] === 0 ? 'UPSTREAM_TIMEOUT' : 'MANIFEST_UNAVAILABLE',
            ];
        }

        $body = trim((string)$manifest['body']);
        if ($body === '' || !str_starts_with($body, '#EXTM3U')) {
            return [
                'ready' => false,
                'retry' => true,
                'message' => 'HLS manifest is empty or invalid',
                'code' => 'MANIFEST_INVALID',
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
            // A playlist cannot turn this endpoint into a cross-host HTTP proxy.
            if (!$this->isTrustedHlsUrl($segmentUrl)) { continue; }
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

        $suffixAt = strcspn($resolvedPath, '?#');
        return $scheme . '://' . $host . $port . $this->normalizeUrlPath(substr($resolvedPath, 0, $suffixAt)) . substr($resolvedPath, $suffixAt);
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
            CURLOPT_FOLLOWLOCATION => false,
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

        $body = '';
        $limit = $range === null ? 131072 : 1;
        curl_setopt($handle, CURLOPT_WRITEFUNCTION, static function ($handle, string $chunk) use (&$body, $limit): int {
            $remaining = $limit - strlen($body);
            $body .= substr($chunk, 0, max(0, $remaining));
            return strlen($chunk) > $remaining ? 0 : strlen($chunk);
        });
        curl_exec($handle);
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
                'follow_location' => 0,
                'header' => implode("\r\n", $headers),
            ],
        ]);

        $body = @file_get_contents($url, false, $context, 0, $range === null ? 131072 : 1);
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
        if (!$this->isTrustedHlsUrl($url)) {
            return false;
        }

        $path = (string)(parse_url($url, PHP_URL_PATH) ?? '');
        $match = [];
        if (!preg_match('~/stream-([^/]+)/index\.m3u8$~i', $path, $match)) {
            return false;
        }

        return hash_equals($streamId, (string)$match[1]);
    }

    private function isTrustedHlsUrl(string $url): bool
    {
        if (!$this->isHttpUrl($url) || preg_match('/[\x00-\x20\\\\]/', $url)) { return false; }
        $parts = parse_url($url);
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) { return false; }
        $bases = stream_config()['allowed_hls_bases'] ?? [];
        if (class_exists('FireballPluginCameraManager')) {
            $bases[] = \FireballPluginCameraManager::settings()['hls_base_url'] ?? '';
        }
        foreach ($bases as $base) {
            $trusted = parse_url((string)$base);
            if (!is_array($trusted) || empty($trusted['host'])) { continue; }
            $port = static fn(array $p): int => (int)($p['port'] ?? (($p['scheme'] ?? '') === 'https' ? 443 : 80));
            $path = rawurldecode((string)($parts['path'] ?? '/'));
            if (preg_match('~(?:^|/)\.\.?(/|$)|[\\\\\x00-\x20]~', $path)) { return false; }
            if (strtolower($parts['host']) === strtolower($trusted['host'])
                && ($parts['scheme'] ?? '') === ($trusted['scheme'] ?? '') && $port($parts) === $port($trusted)
                && str_starts_with($path, rtrim((string)($trusted['path'] ?? ''), '/') . '/')) { return true; }
        }
        return false;
    }

}
