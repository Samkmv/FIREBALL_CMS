<?php
namespace App\Services;

final class UploadPolicy
{
    public const MULTIPART_OVERHEAD = 65536;

    /** null denotes an unlimited PHP setting, never an unlimited CMS policy. */
    public static function parseIniBytes(string $value): ?int
    {
        $value = trim($value);
        if ($value === '0' || $value === '-1') return null;
        if (!preg_match('/^(\d+(?:\.\d+)?)\s*([KMG]?)B?$/iD', $value, $m)) return 0;
        $factor = match (strtoupper($m[2])) { 'K' => 1024, 'M' => 1048576, 'G' => 1073741824, default => 1 };
        return (int)min(PHP_INT_MAX, floor((float)$m[1] * $factor));
    }

    public static function limits(?int $cms = null, ?string $uploadIni = null, ?string $postIni = null): array
    {
        $cms ??= UploadSettings::maxFileSizeBytes();
        $cms = max(1, $cms);
        $upload = self::parseIniBytes($uploadIni ?? (string)ini_get('upload_max_filesize'));
        $post = self::parseIniBytes($postIni ?? (string)ini_get('post_max_size'));
        $effective = min($cms, $upload ?? PHP_INT_MAX, $post === null ? PHP_INT_MAX : max(0, $post - self::MULTIPART_OVERHEAD));
        return ['cms' => $cms, 'php_upload' => $upload, 'php_post' => $post, 'effective' => $effective];
    }

    public static function formatBytes(?int $bytes): string
    {
        return $bytes === null ? return_translation('upload_unlimited') : rtrim(rtrim(number_format($bytes / 1048576, 2, '.', ''), '0'), '.') . ' MiB';
    }

    public static function sizeException(?int $componentMax = null): UploadException
    {
        $limits = self::limits($componentMax === null ? null : min(UploadSettings::maxFileSizeBytes(), $componentMax));
        return new UploadException('upload_error_size', [
            'cms' => self::formatBytes($limits['cms']),
            'php' => self::formatBytes($limits['php_upload']),
            'post' => self::formatBytes($limits['php_post']),
            'effective' => self::formatBytes($limits['effective']),
        ]);
    }

    public static function assertUpload(int $error, int $size, ?int $componentMax = null): void
    {
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) throw self::sizeException($componentMax);
        $key = match ($error) {
            UPLOAD_ERR_OK => null,
            UPLOAD_ERR_PARTIAL => 'upload_error_partial',
            UPLOAD_ERR_NO_FILE => 'upload_error_no_file',
            UPLOAD_ERR_NO_TMP_DIR => 'upload_error_no_tmp',
            UPLOAD_ERR_CANT_WRITE => 'upload_error_write',
            UPLOAD_ERR_EXTENSION => 'upload_error_extension',
            default => 'upload_error_invalid',
        };
        if ($key !== null) throw new UploadException($key);
        if ($size <= 0) throw new UploadException('upload_error_invalid');
        $limits = self::limits($componentMax === null ? null : min(UploadSettings::maxFileSizeBytes(), $componentMax));
        if ($size > $limits['effective']) throw self::sizeException($componentMax);
    }

    public static function requestExceedsPostLimit(array $server): bool
    {
        $max = self::parseIniBytes((string)ini_get('post_max_size'));
        return $max !== null && str_starts_with(strtolower((string)($server['CONTENT_TYPE'] ?? '')), 'multipart/form-data')
            && (int)($server['CONTENT_LENGTH'] ?? 0) > $max;
    }
}
