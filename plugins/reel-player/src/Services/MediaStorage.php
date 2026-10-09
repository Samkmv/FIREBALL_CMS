<?php
declare(strict_types=1);
namespace Fireball\ReelPlayer\Services;

use App\Services\UploadPolicy;

final class MediaStorage
{
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

    public function path(string $relative): string
    {
        if (!preg_match('~^[1-9]\d*/[a-f0-9]{40}\.(?:mp3|wav|flac|ogg|opus|m4a|aac|webm|jpg|png|webp)$~D', $relative)) {
            throw new \InvalidArgumentException('Недопустимый путь файла.');
        }
        return $this->root . '/' . $relative;
    }

    public function delete(?string $relative): void
    {
        if ($relative && is_file($path = $this->path($relative))) @unlink($path);
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
        $path = $this->path($relative);
        if (!is_file($path)) abort('Файл не найден.', 404);
        $size = (int)filesize($path);
        $range = self::range($size, (string)($_SERVER['HTTP_RANGE'] ?? ''));
        session()->close();
        while (ob_get_level() > 0) ob_end_clean();
        header('Accept-Ranges: bytes');
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
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
        $handle = fopen($path, 'rb');
        fseek($handle, $start);
        $remaining = $end - $start + 1;
        while ($remaining > 0 && !feof($handle) && !connection_aborted()) {
            $chunk = fread($handle, min(65536, $remaining));
            if ($chunk === false || $chunk === '') break;
            $remaining -= strlen($chunk);
            echo $chunk;
            flush();
        }
        fclose($handle);
        exit;
    }
}
