<?php

namespace App\Services;

final class UploadSettings
{
    public const DEFAULT_MAX_FILE_SIZE_BYTES = 50 * 1024 * 1024;

    public static function maxFileSizeBytes(): int
    {
        $settings = config_value('UPLOAD_SETTINGS', []);
        $size = is_array($settings) ? (int)($settings['max_file_size'] ?? 0) : 0;

        // Security fallback: invalid local values must not disable upload limits.
        return $size > 0 ? $size : self::DEFAULT_MAX_FILE_SIZE_BYTES;
    }

    public static function maxFileSizeMb(): int
    {
        return (int)ceil(self::maxFileSizeBytes() / 1024 / 1024);
    }
}
