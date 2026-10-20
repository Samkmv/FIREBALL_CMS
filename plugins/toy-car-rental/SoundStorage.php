<?php

use App\Services\SafeUploadService;
use App\Services\UploadPolicy;

final class FireballToyRentalSoundStorage
{
    public const MAX_BYTES = 5 * 1048576;
    private const TYPES = ['mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'ogg' => 'audio/ogg', 'm4a' => 'audio/mp4'];

    public function __construct(private ?string $root = null)
    {
        $this->root ??= STORAGE . '/toy-car-rental/sounds';
    }

    public static function validFilename(string $filename): bool
    {
        return (bool)preg_match('/^[a-f0-9]{40}\.(?:mp3|wav|ogg|m4a)$/D', $filename);
    }

    public function upload(array $file): array
    {
        foreach (['error', 'size', 'tmp_name', 'name'] as $key) {
            if (!isset($file[$key]) || !is_scalar($file[$key])) {
                throw new RuntimeException(FireballPluginToyCarRental::t('toy_rental_error_sound_type'));
            }
        }
        UploadPolicy::assertUpload((int)$file['error'], (int)$file['size'], self::MAX_BYTES);
        $temporary = (string)$file['tmp_name'];
        if (!is_uploaded_file($temporary)) {
            throw new RuntimeException(FireballPluginToyCarRental::t('toy_rental_error_sound_type'));
        }
        $size = filesize($temporary);
        if ($size === false || $size > self::MAX_BYTES) {
            throw new RuntimeException(FireballPluginToyCarRental::t('toy_rental_error_sound_size'));
        }
        (new SafeUploadService())->validate($temporary, (string)$file['name'], $size, self::MAX_BYTES, array_keys(self::TYPES));
        $extension = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        if (!is_dir($this->root) && !mkdir($this->root, 0700, true) && !is_dir($this->root)) {
            throw new RuntimeException(FireballPluginToyCarRental::t('toy_rental_error_sound_save'));
        }
        $filename = bin2hex(random_bytes(20)) . '.' . $extension;
        if (!move_uploaded_file($temporary, $this->root . '/' . $filename)) {
            throw new RuntimeException(FireballPluginToyCarRental::t('toy_rental_error_sound_save'));
        }
        chmod($this->root . '/' . $filename, 0600);
        $name = basename(str_replace('\\', '/', (string)$file['name']));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
        return ['file' => $filename, 'name' => mb_substr($name, 0, 255)];
    }

    public function asset(string $filename): ?array
    {
        if (!self::validFilename($filename)) return null;
        $base = realpath($this->root);
        $path = realpath($this->root . '/' . $filename);
        if ($base === false || $path === false || !str_starts_with($path, $base . DIRECTORY_SEPARATOR) || !is_file($path)) return null;
        return ['path' => $path, 'mime' => self::TYPES[pathinfo($filename, PATHINFO_EXTENSION)], 'size' => (int)filesize($path)];
    }

    public function delete(string $filename): void
    {
        $asset = $this->asset($filename);
        if ($asset !== null) @unlink($asset['path']);
    }

    public function serve(string $filename): never
    {
        $asset = $this->asset($filename);
        if ($asset === null || $asset['size'] <= 0) abort();
        $size = $asset['size'];
        $start = 0;
        $end = $size - 1;
        $range = trim((string)request()->header('Range', ''));
        if ($range !== '') {
            $valid = preg_match('/^bytes=(\d*)-(\d*)$/D', $range, $parts);
            if ($valid && ($parts[1] !== '' || $parts[2] !== '')) {
                if ($parts[1] === '') {
                    $length = (int)$parts[2];
                    $start = max(0, $size - $length);
                    $valid = $length > 0;
                } else {
                    $start = (int)$parts[1];
                    if ($parts[2] !== '') $end = min($end, (int)$parts[2]);
                }
                $valid = $valid && $start < $size && $start <= $end;
            } else $valid = false;
            if (!$valid) {
                http_response_code(416);
                header('Content-Range: bytes */' . $size);
                header('Content-Length: 0');
                exit;
            }
            http_response_code(206);
            header("Content-Range: bytes $start-$end/$size");
        }
        header('Content-Type: ' . $asset['mime']);
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: inline; filename="rental-alert.' . pathinfo($filename, PATHINFO_EXTENSION) . '"');
        header('Cache-Control: private, max-age=3600');
        header('Accept-Ranges: bytes');
        header('Content-Length: ' . ($end - $start + 1));
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        $stream = fopen($asset['path'], 'rb');
        if ($stream !== false) {
            fseek($stream, $start);
            $remaining = $end - $start + 1;
            while ($remaining > 0 && !feof($stream) && !connection_aborted()) {
                $bytes = fread($stream, min(65536, $remaining));
                if ($bytes === false || $bytes === '') break;
                echo $bytes;
                $remaining -= strlen($bytes);
            }
            fclose($stream);
        }
        exit;
    }
}
