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

        $ready = $this->waitForHlsReady(
            $hlsUrl,
            (int)$config['ready_timeout_seconds'],
            (int)$config['ready_interval_ms'],
            (int)$config['http_timeout_seconds']
        );

        response()->json([
            'success' => true,
            'stream_id' => $streamId,
            'woke' => true,
            'ready' => $ready['ready'],
            'message' => $ready['message'],
        ]);
    }

    private function waitForHlsReady(string $manifestUrl, int $timeoutSeconds, int $intervalMs, int $httpTimeoutSeconds): array
    {
        $deadline = microtime(true) + $timeoutSeconds;
        $lastMessage = 'HLS manifest exists but segments are not ready';

        do {
            $check = $this->checkHlsReady($manifestUrl, $httpTimeoutSeconds);
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

    private function checkHlsReady(string $manifestUrl, int $httpTimeoutSeconds): array
    {
        $manifest = $this->httpRequest($manifestUrl, 'HEAD', $httpTimeoutSeconds);
        if ($manifest['status'] !== 200 || $manifest['body'] === '') {
            $manifest = $this->httpRequest($manifestUrl, 'GET', $httpTimeoutSeconds);
        }

        if ($manifest['status'] !== 200) {
            return [
                'ready' => false,
                'retry' => in_array($manifest['status'], [0, 404], true),
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

        $segmentUrl = $this->firstMediaSegmentUrl($body, $manifestUrl);
        if ($segmentUrl === '') {
            return [
                'ready' => false,
                'retry' => true,
                'message' => 'HLS manifest exists but segments are not ready',
            ];
        }

        $segment = $this->httpRequest($segmentUrl, 'HEAD', $httpTimeoutSeconds);
        if (!in_array($segment['status'], [200, 206], true)) {
            $segment = $this->httpRequest($segmentUrl, 'GET', $httpTimeoutSeconds, [0, 0]);
        }

        return [
            'ready' => in_array($segment['status'], [200, 206], true),
            'retry' => in_array($segment['status'], [0, 404], true),
            'message' => in_array($segment['status'], [200, 206], true)
                ? 'HLS is ready'
                : 'HLS manifest exists but segments are not ready',
        ];
    }

    private function firstMediaSegmentUrl(string $manifest, string $manifestUrl): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $manifest) ?: [];
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

            if (!$expectSegment || str_starts_with($line, '#')) {
                continue;
            }

            return $this->resolveUrl($manifestUrl, $line);
        }

        return '';
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
