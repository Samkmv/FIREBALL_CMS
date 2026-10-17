<?php
declare(strict_types=1);
namespace Fireball\ReelPlayer\Services;

use App\Services\UploadPolicy;

final class MediaStorage
{
    public const COVER_SIZES = [96, 320, 640];
    private string $root;

    public function __construct(?string $root = null)
    {
        $this->root = $root ?? STORAGE . '/reel-player';
    }

    public function upload(array $file, int $owner, bool $cover = false): array
    {
        UploadPolicy::assertUpload((int)($file['error'] ?? UPLOAD_ERR_NO_FILE), (int)($file['size'] ?? 0), $cover ? 8 * 1048576 : null);
        $tmp = (string)($file['tmp_name'] ?? '');
        if (!is_uploaded_file($tmp)) throw new \InvalidArgumentException('Не удалось прочитать загруженный файл.');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        if ($cover) {
            $info = @getimagesize($tmp);
            $ext = match ($info['mime'] ?? '') { 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', default => '' };
            if ($ext === '' || $info[0] * $info[1] > 20000000) throw new \InvalidArgumentException('Нужна обложка JPG, PNG или WebP, до 20 мегапикселей.');
            $mime = $info['mime'];
        } else {
            $formats = [
                'mp3' => ['audio/mpeg', 'audio/mp3'], 'wav' => ['audio/x-wav', 'audio/wav', 'audio/vnd.wave'],
                'flac' => ['audio/flac', 'audio/x-flac'], 'ogg' => ['audio/ogg', 'application/ogg'],
                'opus' => ['audio/ogg', 'application/ogg'], 'm4a' => ['audio/mp4', 'video/mp4', 'audio/x-m4a'],
                'aac' => ['audio/aac', 'audio/x-hx-aac', 'audio/x-aac'], 'webm' => ['audio/webm', 'video/webm'],
            ];
            if (!in_array($mime, $formats[$ext] ?? [], true)) throw new \InvalidArgumentException('Формат не распознан. Поддерживаются MP3, WAV, FLAC, OGG, Opus, M4A, AAC и WebM.');
            $mime = match ($ext) { 'm4a' => 'audio/mp4', 'webm' => 'audio/webm', 'ogg', 'opus' => 'audio/ogg', default => $mime };
        }
        $directory = $this->root . '/' . $owner;
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) throw new \RuntimeException('Не удалось создать музыкальную библиотеку.');
        $relative = $owner . '/' . bin2hex(random_bytes(20)) . '.' . $ext;
        if (!move_uploaded_file($tmp, $this->root . '/' . $relative)) throw new \RuntimeException('Не удалось сохранить файл.');
        chmod($this->root . '/' . $relative, 0600);
        return ['path' => $relative, 'mime' => $mime, 'size' => (int)filesize($this->root . '/' . $relative)];
    }

    public function storeArtwork(string $bytes, int $owner): array
    {
        if ($owner<1 || $bytes==='' || strlen($bytes)>8*1048576) throw new \InvalidArgumentException('Обложка должна быть до 8 МБ.');
        $info=@getimagesizefromstring($bytes);
        $extension=match($info['mime'] ?? '') { 'image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp',default=>'' };
        if (!$info || $extension==='' || $info[0]*$info[1]>20000000) throw new \InvalidArgumentException('Обложка не является поддерживаемым изображением.');
        $directory=$this->root.'/'.$owner;
        if (!is_dir($directory) && !mkdir($directory,0700,true) && !is_dir($directory)) throw new \RuntimeException('Не удалось сохранить обложку.');
        $relative=$owner.'/'.bin2hex(random_bytes(20)).'.'.$extension;
        if (file_put_contents($this->root.'/'.$relative,$bytes,LOCK_EX)!==strlen($bytes)) { @unlink($this->root.'/'.$relative); throw new \RuntimeException('Не удалось сохранить обложку.'); }
        chmod($this->root.'/'.$relative,0600);
        return ['path'=>$relative,'mime'=>$info['mime']];
    }

    public function path(string $relative): string
    {
        if (!preg_match('~^[1-9]\d*/[a-f0-9]{40}\.(?:mp3|wav|flac|ogg|opus|m4a|aac|webm|jpg|png|webp)$~D', $relative)) {
            throw new \InvalidArgumentException('Недопустимый путь файла.');
        }
        return $this->root . '/' . $relative;
    }

    public function delete(?string $relative): void
    {
        if (!$relative) return;
        $path = $this->path($relative);
        if (is_file($path)) @unlink($path);
        $directory = $this->coverCacheDirectory($relative);
        if (is_dir($directory)) {
            // These names are generated exclusively by coverVariant(). Never
            // recursively delete an owner directory or an arbitrary sidecar.
            foreach (glob($directory . '/*') ?: [] as $file) {
                if (preg_match('/^(?:[a-f0-9]{64}-(?:96|320|640)\.webp|generate\.lock)$/D', basename($file))) @unlink($file);
            }
            @rmdir($directory);
        }
    }

    /**
     * A bounded, private derivative of an authenticated source image. The caller
     * must establish access to the track/album/artist before using this method.
     * Originals are never re-encoded. Missing GD/WebP, limited memory, or a
     * malformed image fall back to the original instead of a broken image.
     *
     * @return array{path:string,mime:string}
     */
    public function coverVariant(string $relative, string $mime, int $maxEdge = 0): array
    {
        $path = $this->path($relative);
        $original = ['path' => $path, 'mime' => $mime];
        if (!is_file($path) || !in_array($maxEdge, self::COVER_SIZES, true)
            || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)
            || !function_exists('imagewebp') || !function_exists('imagecreatetruecolor')
            || ($mime === 'image/jpeg' && !function_exists('exif_read_data'))) return $original;
        $info = @getimagesize($path);
        if (!$info || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > 20000000) return $original;
        // Do not enlarge small originals or spend memory decoding them again.
        if (max($info[0], $info[1]) <= $maxEdge) return $original;
        $width = max(1, (int)round($info[0] * $maxEdge / max($info[0], $info[1])));
        $height = max(1, (int)round($info[1] * $maxEdge / max($info[0], $info[1])));
        $limit = $this->memoryLimit();
        $estimate = ($info[0] * $info[1] + $width * $height) * 6 + 8 * 1048576;
        if ($limit > 0 && memory_get_usage(true) + $estimate > $limit) return $original;
        $directory = $this->coverCacheDirectory($relative);
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) return $original;
        $lock = @fopen($directory . '/generate.lock', 'c');
        if (!$lock) return $original;
        @chmod($directory . '/generate.lock', 0600);
        $source = $image = null;
        $temporary = null;
        try {
            if (!flock($lock, LOCK_EX)) return $original;
            // Recheck after acquiring the per-image lock: a previous worker may
            // already have generated this size, or the original was removed.
            clearstatcache(true, $path);
            if (!is_file($path)) return $original;
            $fingerprint = hash_file('sha256', $path);
            if ($fingerprint === false) return $original;
            $target = $directory . '/' . $fingerprint . '-' . $maxEdge . '.webp';
            foreach (glob($directory . '/*.webp') ?: [] as $cached) {
                if (preg_match('/^[a-f0-9]{64}-(?:96|320|640)\.webp$/D', basename($cached))
                    && !str_starts_with(basename($cached), $fingerprint . '-')) @unlink($cached);
            }
            if (is_file($target)) return ['path' => $target, 'mime' => 'image/webp'];
            $source = match ($info['mime'] ?? '') {
                'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) : false,
                'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($path) : false,
                'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
                default => false,
            };
            if (!$source) return $original;
            $image = imagecreatetruecolor($width, $height);
            if (!$image) return $original;
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
            imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, $transparent);
            if (!imagecopyresampled($image, $source, 0, 0, 0, 0, $width, $height, $info[0], $info[1])) return $original;
            // Browser decoding honors camera EXIF orientation; GD does not.
            // Apply it to the small result, preserving the original and avoiding
            // another full-resolution allocation for phone photos.
            if (($info['mime'] ?? '') === 'image/jpeg' && function_exists('exif_read_data')) {
                $exif = @exif_read_data($path, 'IFD0');
                $orientation = (int)($exif['Orientation'] ?? 1);
                if (in_array($orientation, [2, 5, 7], true)) imageflip($image, IMG_FLIP_HORIZONTAL);
                elseif ($orientation === 4) imageflip($image, IMG_FLIP_VERTICAL);
                $angle = match ($orientation) { 3 => 180, 5, 8 => 90, 6, 7 => -90, default => 0 };
                if ($angle) {
                    $rotated = imagerotate($image, $angle, $transparent);
                    if (!$rotated) return $original;
                    imagedestroy($image);
                    $image = $rotated;
                    imagealphablending($image, false);
                    imagesavealpha($image, true);
                }
            }
            $temporary = tempnam($directory, '.variant-');
            if (!$temporary || !imagewebp($image, $temporary, 88) || filesize($temporary) === 0) return $original;
            // An already compact original is preferable to a larger derivative.
            if (filesize($temporary) >= filesize($path)) return $original;
            @chmod($temporary, 0600);
            if (!rename($temporary, $target)) return $original;
            $temporary = null;
            return ['path' => $target, 'mime' => 'image/webp'];
        } catch (\Throwable) {
            return $original;
        } finally {
            if ($source instanceof \GdImage) imagedestroy($source);
            if ($image instanceof \GdImage) imagedestroy($image);
            if ($temporary && is_file($temporary)) @unlink($temporary);
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function coverCacheDirectory(string $relative): string
    {
        // path() performs strict ownership-directory and random-name validation.
        $this->path($relative);
        return $this->root . '/' . dirname($relative) . '/.covers/' . pathinfo($relative, PATHINFO_FILENAME);
    }

    private function memoryLimit(): int
    {
        $value = trim((string)ini_get('memory_limit'));
        if ($value === '-1' || $value === '') return 0;
        $number = (int)$value;
        return $number * match (strtolower(substr($value, -1))) { 'g' => 1073741824, 'm' => 1048576, 'k' => 1024, default => 1 };
    }

    /** null means an invalid/unsatisfiable range, including multipart ranges. */
    public static function range(int $size, string $header): ?array
    {
        if ($size <= 0) return null;
        if ($header === '') return [0, $size - 1, false];
        if (!preg_match('/^bytes=(\d*)-(\d*)$/D', trim($header), $m) || ($m[1] === '' && $m[2] === '')) return null;
        if ($m[1] === '') {
            $suffix = (int)$m[2];
            return $suffix > 0 ? [max(0, $size - $suffix), $size - 1, true] : null;
        }
        $start = (int)$m[1];
        $end = $m[2] === '' ? $size - 1 : min($size - 1, (int)$m[2]);
        return $start < $size && $end >= $start ? [$start, $end, true] : null;
    }

    public function stream(string $relative, string $mime): never
    {
        $this->sendFile($relative, $mime, false);
    }

    /** The controller must check the current user's ownership before this call. */
    public function streamCover(string $relative, string $mime, int $maxEdge = 0): never
    {
        // Access was checked by the controller. Thumbnail generation must not
        // hold the PHP session and delay parallel audio or library requests.
        session()->close();
        $cover = $this->coverVariant($relative, $mime, $maxEdge);
        $this->sendPath($cover['path'], $cover['mime'], in_array($cover['mime'], ['image/jpeg', 'image/png', 'image/webp'], true));
    }

    private function sendFile(string $relative, string $mime, bool $cacheCover): never
    {
        $this->sendPath($this->path($relative), $mime, $cacheCover);
    }

    private function sendPath(string $path, string $mime, bool $cacheCover): never
    {
        if (!is_file($path)) abort('Файл не найден.', 404);
        $size = (int)filesize($path);
        $range = self::range($size, (string)($_SERVER['HTTP_RANGE'] ?? ''));
        session()->close();
        while (ob_get_level() > 0) ob_end_clean();
        header('Accept-Ranges: bytes');
        header('Cache-Control: ' . ($cacheCover ? 'private, no-cache, max-age=0, must-revalidate' : 'private, no-store, max-age=0'));
        header('X-Content-Type-Options: nosniff');
        if ($cacheCover) {
            // A browser may retain image bytes, but every reuse must pass the
            // authenticated controller again. Audio never enters this branch.
            $etag = '"' . hash_file('sha256', $path) . '"';
            $modified = (int)filemtime($path);
            header('ETag: ' . $etag);
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $modified) . ' GMT');
            $unchanged = false;
            if (isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
                foreach (explode(',', (string)$_SERVER['HTTP_IF_NONE_MATCH']) as $candidate) {
                    $candidate = trim($candidate);
                    if ($candidate === '*' || preg_replace('/^W\//', '', $candidate) === $etag) {
                        $unchanged = true;
                        break;
                    }
                }
            } elseif (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
                $since = strtotime((string)$_SERVER['HTTP_IF_MODIFIED_SINCE']);
                $unchanged = $since !== false && $since >= $modified;
            }
            if ($unchanged && in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
                http_response_code(304);
                exit;
            }
        }
        if ($range === null) {
            http_response_code(416);
            header('Content-Range: bytes */' . $size);
            exit;
        }
        [$start, $end, $partial] = $range;
        http_response_code($partial ? 206 : 200);
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . ($end - $start + 1));
        if ($partial) header("Content-Range: bytes {$start}-{$end}/{$size}");
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') exit;
        $handle = @fopen($path, 'rb');
        if (!is_resource($handle) || fseek($handle, $start) !== 0) {
            if (is_resource($handle)) fclose($handle);
            http_response_code(404); header('Content-Length: 0'); exit;
        }
        // Authorization and all path/header work have finished under the reader.
        // Keep the opened inode alive while the updater replaces application code.
        \FBL\UpdateLock::stream(static function() use ($handle,$start,$end): void {
            try {
                $remaining = $end - $start + 1;
                while ($remaining > 0 && !feof($handle) && !connection_aborted()) {
                    $chunk = fread($handle, min(65536, $remaining));
                    if ($chunk === false || $chunk === '') break;
                    $remaining -= strlen($chunk); echo $chunk; flush();
                }
            } finally { fclose($handle); }
        }, app()->db);
    }
}
