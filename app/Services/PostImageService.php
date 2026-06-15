<?php

namespace App\Services;

final class PostImageService
{
    private const REMOTE_TTL = 604800;
    private const REMOTE_FAILURE_TTL = 3600;
    private const REMOTE_MAX_BYTES = 10485760;
    private const REMOTE_MAX_PIXELS = 40000000;
    private const REMOTE_MAX_REDIRECTS = 3;

    private const WIDTHS = [
        'mobile' => 480,
        'thumb' => 768,
        'desktop' => 1024,
    ];

    private const CACHE_DIRECTORY = 'uploads/cache/images/posts';
    private const REMOTE_CACHE_DIRECTORY = 'uploads/cache/images/remote';

    public function prepare(string $image, string $version = '1'): array
    {
        $image = trim($image);
        if ($image === '') {
            $image = 'assets/img/no-image.png';
        }

        if ($this->isRemote($image)) {
            $remote = $this->cacheRemoteImage($image);
            if ($remote === null) {
                return $this->prepare('assets/img/no-image.png', $version);
            }

            return $this->prepareLocal($remote['relative_path'], $remote['absolute_path']);
        }

        $relativePath = ltrim((string)(parse_url($image, PHP_URL_PATH) ?: $image), '/');
        $sourcePath = $this->resolvePublicPath($relativePath);
        if ($sourcePath === null || !is_file($sourcePath)) {
            if ($relativePath !== 'assets/img/no-image.png') {
                return $this->prepare('assets/img/no-image.png', $version);
            }

            $fallbackUrl = get_image('assets/img/no-image.png');
            return $this->result($fallbackUrl, $fallbackUrl, $fallbackUrl, $fallbackUrl, '', 0, 0);
        }

        return $this->prepareLocal($relativePath, $sourcePath);
    }

    private function prepareLocal(string $relativePath, string $sourcePath): array
    {
        $mtime = (int)(filemtime($sourcePath) ?: time());
        $originalUrl = $this->appendVersion(get_image($relativePath), (string)$mtime);
        $dimensions = @getimagesize($sourcePath);
        $originalWidth = is_array($dimensions) ? (int)($dimensions[0] ?? 0) : 0;
        $originalHeight = is_array($dimensions) ? (int)($dimensions[1] ?? 0) : 0;

        if (!extension_loaded('gd') || !is_array($dimensions)) {
            return $this->result(
                $originalUrl,
                $originalUrl,
                $originalUrl,
                $originalUrl,
                '',
                $originalWidth,
                $originalHeight
            );
        }

        $variants = [];
        $variantsByWidth = [];
        foreach (self::WIDTHS as $name => $width) {
            $effectiveWidth = min($width, $originalWidth);
            if (!isset($variantsByWidth[$effectiveWidth])) {
                $variantsByWidth[$effectiveWidth] = $this->generateVariant(
                    $sourcePath,
                    $relativePath,
                    $mtime,
                    $effectiveWidth,
                    $dimensions
                ) ?: ['url' => $originalUrl, 'width' => $originalWidth];
            }
            $variants[$name] = $variantsByWidth[$effectiveWidth];
        }

        $srcsetItems = [];
        foreach ($variants as $variant) {
            if ($variant['width'] > 0) {
                $srcsetItems[$variant['width']] = $variant['url'] . ' ' . $variant['width'] . 'w';
            }
        }
        ksort($srcsetItems);
        $srcset = implode(', ', $srcsetItems);

        return $this->result(
            $originalUrl,
            $variants['thumb']['url'],
            $variants['mobile']['url'],
            $variants['desktop']['url'],
            $srcset,
            $originalWidth,
            $originalHeight
        );
    }

    public static function clearGeneratedCache(): int
    {
        $deleted = 0;
        foreach ([self::CACHE_DIRECTORY, self::REMOTE_CACHE_DIRECTORY] as $directory) {
            $deleted += self::clearDirectory(WWW . '/' . $directory);
        }

        return $deleted;
    }

    private static function clearDirectory(string $directory): int
    {
        if (!is_dir($directory)) {
            return 0;
        }

        $deleted = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                if (@rmdir($item->getPathname())) {
                    $deleted++;
                }
                continue;
            }

            if (@unlink($item->getPathname())) {
                $deleted++;
            }
        }

        return $deleted;
    }

    private function cacheRemoteImage(string $url): ?array
    {
        $url = str_starts_with($url, '//') ? 'https:' . $url : $url;
        if (!$this->isSafeRemoteUrl($url)) {
            return null;
        }

        $hash = hash('sha256', $url);
        $directory = WWW . '/' . self::REMOTE_CACHE_DIRECTORY . '/' . substr($hash, 0, 2);
        $existingPath = $this->findRemoteCacheFile($directory, $hash);
        if ($existingPath !== null && (int)filemtime($existingPath) + self::REMOTE_TTL >= time()) {
            return $this->remoteCacheResult($existingPath);
        }

        if (cache()->get('post_images:remote_failure:' . $hash) === true) {
            return $existingPath !== null ? $this->remoteCacheResult($existingPath) : null;
        }

        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            return $existingPath !== null ? $this->remoteCacheResult($existingPath) : null;
        }

        $lock = @fopen($directory . '/' . $hash . '.lock', 'c');
        if ($lock === false) {
            return $existingPath !== null ? $this->remoteCacheResult($existingPath) : null;
        }

        try {
            if (!flock($lock, LOCK_EX)) {
                return $existingPath !== null ? $this->remoteCacheResult($existingPath) : null;
            }

            clearstatcache();
            $existingPath = $this->findRemoteCacheFile($directory, $hash);
            if ($existingPath !== null && (int)filemtime($existingPath) + self::REMOTE_TTL >= time()) {
                return $this->remoteCacheResult($existingPath);
            }

            $download = $this->downloadRemoteImage($url, self::REMOTE_MAX_REDIRECTS);
            if ($download === null) {
                cache()->set('post_images:remote_failure:' . $hash, true, self::REMOTE_FAILURE_TTL);
                return $existingPath !== null ? $this->remoteCacheResult($existingPath) : null;
            }

            $destinationPath = $directory . '/' . $hash . '.' . $download['extension'];
            if (!@rename($download['temporary_path'], $destinationPath)) {
                @unlink($download['temporary_path']);
                return $existingPath !== null ? $this->remoteCacheResult($existingPath) : null;
            }

            @chmod($destinationPath, 0644);
            if ($existingPath !== null && $existingPath !== $destinationPath) {
                @unlink($existingPath);
            }
            cache()->remove('post_images:remote_failure:' . $hash);

            return $this->remoteCacheResult($destinationPath);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
            @unlink($directory . '/' . $hash . '.lock');
        }
    }

    private function downloadRemoteImage(string $url, int $redirectsRemaining): ?array
    {
        $target = $this->resolveRemoteTarget($url);
        if ($target === null || !function_exists('curl_init')) {
            return null;
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'fireball-image-');
        if ($temporaryPath === false) {
            return null;
        }

        $handle = @fopen($temporaryPath, 'wb');
        if ($handle === false) {
            @unlink($temporaryPath);
            return null;
        }

        $bytes = 0;
        $headers = [];
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_USERAGENT => 'FIREBALL-CMS-Image-Cache/1.0',
            CURLOPT_HTTPHEADER => ['Accept: image/avif,image/webp,image/*;q=0.8'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_NOSIGNAL => true,
            CURLOPT_RESOLVE => [$target['resolve']],
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int {
                $length = strlen($line);
                $line = trim($line);
                if ($line === '' || !str_contains($line, ':')) {
                    return $length;
                }

                [$name, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($name))] = trim($value);

                return $length;
            },
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use ($handle, &$bytes): int {
                $length = strlen($chunk);
                $bytes += $length;
                if ($bytes > self::REMOTE_MAX_BYTES) {
                    return 0;
                }

                $written = fwrite($handle, $chunk);

                return $written === false ? 0 : $written;
            },
        ]);

        $success = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        fclose($handle);

        if ($status >= 300 && $status < 400 && $redirectsRemaining > 0 && !empty($headers['location'])) {
            @unlink($temporaryPath);
            $redirectUrl = $this->resolveRedirectUrl($url, $headers['location']);

            return $redirectUrl !== null
                ? $this->downloadRemoteImage($redirectUrl, $redirectsRemaining - 1)
                : null;
        }

        if ($success !== true || $status < 200 || $status >= 300 || $bytes <= 0) {
            @unlink($temporaryPath);
            return null;
        }

        $contentLength = (int)($headers['content-length'] ?? 0);
        if ($contentLength > self::REMOTE_MAX_BYTES) {
            @unlink($temporaryPath);
            return null;
        }

        $dimensions = @getimagesize($temporaryPath);
        if (!is_array($dimensions)) {
            @unlink($temporaryPath);
            return null;
        }

        $width = (int)($dimensions[0] ?? 0);
        $height = (int)($dimensions[1] ?? 0);
        $type = (int)($dimensions[2] ?? 0);
        $extension = $this->imageExtension($type);
        if ($extension === null || $width <= 0 || $height <= 0 || $width * $height > self::REMOTE_MAX_PIXELS) {
            @unlink($temporaryPath);
            return null;
        }

        return [
            'temporary_path' => $temporaryPath,
            'extension' => $extension,
        ];
    }

    private function resolveRemoteTarget(string $url): ?array
    {
        if (!$this->isSafeRemoteUrl($url)) {
            return null;
        }

        $host = (string)parse_url($url, PHP_URL_HOST);
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        $port = (int)(parse_url($url, PHP_URL_PORT) ?: ($scheme === 'https' ? 443 : 80));
        $ips = filter_var($host, FILTER_VALIDATE_IP) !== false
            ? [$host]
            : (@gethostbynamel($host) ?: []);
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if (is_array($records)) {
            foreach ($records as $record) {
                $ip = (string)($record['ip'] ?? $record['ipv6'] ?? '');
                if ($ip !== '') {
                    $ips[] = $ip;
                }
            }
        }

        foreach (array_unique($ips) as $ip) {
            if (!$this->isPublicIp($ip)) {
                continue;
            }

            $resolvedIp = str_contains($ip, ':') ? '[' . $ip . ']' : $ip;

            return [
                'resolve' => $host . ':' . $port . ':' . $resolvedIp,
            ];
        }

        return null;
    }

    private function isSafeRemoteUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        $host = strtolower(rtrim((string)parse_url($url, PHP_URL_HOST), '.'));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return false;
        }
        if (parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null) {
            return false;
        }

        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) {
            return false;
        }

        return filter_var($host, FILTER_VALIDATE_IP) === false || $this->isPublicIp($host);
    }

    private function isPublicIp(string $ip): bool
    {
        return $ip !== '' && filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    private function resolveRedirectUrl(string $baseUrl, string $location): ?string
    {
        $location = trim($location);
        if ($location === '') {
            return null;
        }
        if (filter_var($location, FILTER_VALIDATE_URL)) {
            return $location;
        }
        if (str_starts_with($location, '//')) {
            return (string)parse_url($baseUrl, PHP_URL_SCHEME) . ':' . $location;
        }

        $scheme = (string)parse_url($baseUrl, PHP_URL_SCHEME);
        $host = (string)parse_url($baseUrl, PHP_URL_HOST);
        $port = parse_url($baseUrl, PHP_URL_PORT);
        if ($scheme === '' || $host === '') {
            return null;
        }

        $origin = $scheme . '://' . $host . ($port ? ':' . $port : '');
        if (str_starts_with($location, '/')) {
            return $origin . $location;
        }

        $path = (string)parse_url($baseUrl, PHP_URL_PATH);
        $directory = rtrim(str_replace('\\', '/', dirname($path)), '/');

        return $origin . ($directory !== '' ? $directory : '') . '/' . $location;
    }

    private function findRemoteCacheFile(string $directory, string $hash): ?string
    {
        foreach (['jpg', 'png', 'gif', 'webp'] as $extension) {
            $path = $directory . '/' . $hash . '.' . $extension;
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function remoteCacheResult(string $absolutePath): array
    {
        return [
            'absolute_path' => $absolutePath,
            'relative_path' => ltrim(str_replace(WWW, '', $absolutePath), '/'),
        ];
    }

    private function imageExtension(int $type): ?string
    {
        return match ($type) {
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_GIF => 'gif',
            IMAGETYPE_WEBP => 'webp',
            default => null,
        };
    }

    private function generateVariant(
        string $sourcePath,
        string $relativePath,
        int $mtime,
        int $targetWidth,
        array $dimensions
    ): ?array {
        $sourceWidth = (int)($dimensions[0] ?? 0);
        $sourceHeight = (int)($dimensions[1] ?? 0);
        $imageType = (int)($dimensions[2] ?? 0);
        if ($sourceWidth <= 0 || $sourceHeight <= 0) {
            return null;
        }

        $width = min($targetWidth, $sourceWidth);
        $height = max(1, (int)round($sourceHeight * ($width / $sourceWidth)));
        $extension = function_exists('imagewebp') ? 'webp' : 'jpg';
        $fileName = hash('sha256', $relativePath . '|' . $mtime) . "-{$targetWidth}.{$extension}";
        $relativeCachePath = self::CACHE_DIRECTORY . '/' . substr($fileName, 0, 2) . '/' . $fileName;
        $destinationPath = WWW . '/' . $relativeCachePath;

        if (is_file($destinationPath)) {
            return ['url' => get_image($relativeCachePath), 'width' => $width];
        }

        $directory = dirname($destinationPath);
        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            return null;
        }

        $source = $this->createSourceImage($sourcePath, $imageType);
        if (!$source) {
            return null;
        }

        $target = imagecreatetruecolor($width, $height);
        if (!$target) {
            imagedestroy($source);
            return null;
        }

        imagealphablending($target, false);
        imagesavealpha($target, true);
        $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
        imagefilledrectangle($target, 0, 0, $width, $height, $transparent);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);

        $temporaryPath = $destinationPath . '.tmp-' . bin2hex(random_bytes(4));
        $saved = $extension === 'webp'
            ? imagewebp($target, $temporaryPath, 82)
            : imagejpeg($target, $temporaryPath, 84);

        imagedestroy($target);
        imagedestroy($source);

        if (!$saved || !@rename($temporaryPath, $destinationPath)) {
            @unlink($temporaryPath);
            return null;
        }

        @chmod($destinationPath, 0644);

        return ['url' => get_image($relativeCachePath), 'width' => $width];
    }

    private function createSourceImage(string $path, int $type): \GdImage|false
    {
        return match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_GIF => @imagecreatefromgif($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };
    }

    private function resolvePublicPath(string $relativePath): ?string
    {
        if ($relativePath === '' || str_contains($relativePath, "\0") || preg_match('~(^|/)\.\.(/|$)~', $relativePath)) {
            return null;
        }

        $candidate = WWW . '/' . $relativePath;
        $realPath = realpath($candidate);
        $publicRoot = realpath(WWW);

        if ($realPath === false || $publicRoot === false || !str_starts_with($realPath, $publicRoot . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $realPath;
    }

    private function isRemote(string $image): bool
    {
        return filter_var($image, FILTER_VALIDATE_URL) !== false || str_starts_with($image, '//');
    }

    private function appendVersion(string $url, string $version): string
    {
        $fragment = '';
        if (str_contains($url, '#')) {
            [$url, $fragment] = explode('#', $url, 2);
            $fragment = '#' . $fragment;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . 'v=' . rawurlencode($version) . $fragment;
    }

    private function result(
        string $original,
        string $thumb,
        string $mobile,
        string $webp,
        string $srcset,
        int $width,
        int $height
    ): array {
        return [
            'image_original' => $original,
            'image_thumb' => $thumb,
            'image_mobile' => $mobile,
            'image_webp' => $webp,
            'image_srcset' => $srcset,
            'image_width' => $width,
            'image_height' => $height,
        ];
    }
}
