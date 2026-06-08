<?php

namespace App\Services;

final class SafeUploadService
{
    private const FORBIDDEN_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar',
        'exe', 'bat', 'cmd', 'sh', 'js', 'html', 'htm', 'svg',
    ];

    private const MIME_TYPES = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'gif' => ['image/gif'],
        'bmp' => ['image/bmp', 'image/x-ms-bmp'],
        'pdf' => ['application/pdf'],
        'txt' => ['text/plain'],
        'md' => ['text/plain', 'text/markdown'],
        'csv' => ['text/plain', 'text/csv', 'application/csv'],
        'json' => ['application/json', 'text/plain'],
        'xml' => ['application/xml', 'text/xml', 'text/plain'],
        'rtf' => ['application/rtf', 'text/rtf'],
        'doc' => ['application/msword', 'application/cdfv2', 'application/x-ole-storage'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xls' => ['application/vnd.ms-excel', 'application/cdfv2', 'application/x-ole-storage'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'ppt' => ['application/vnd.ms-powerpoint', 'application/cdfv2', 'application/x-ole-storage'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
        'odt' => ['application/vnd.oasis.opendocument.text', 'application/zip'],
        'ods' => ['application/vnd.oasis.opendocument.spreadsheet', 'application/zip'],
        'odp' => ['application/vnd.oasis.opendocument.presentation', 'application/zip'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
        'rar' => ['application/vnd.rar', 'application/x-rar', 'application/x-rar-compressed'],
        '7z' => ['application/x-7z-compressed'],
        'mp3' => ['audio/mpeg', 'audio/mp3'],
        'wav' => ['audio/wav', 'audio/x-wav', 'audio/wave'],
        'ogg' => ['audio/ogg', 'application/ogg'],
        'm4a' => ['audio/mp4', 'audio/x-m4a'],
        'flac' => ['audio/flac', 'audio/x-flac'],
        'aac' => ['audio/aac', 'audio/x-aac'],
        'mp4' => ['video/mp4'],
        'webm' => ['video/webm'],
        'mov' => ['video/quicktime'],
        'avi' => ['video/x-msvideo', 'video/avi'],
        'mkv' => ['video/x-matroska'],
        'mpeg' => ['video/mpeg'],
        'mpg' => ['video/mpeg'],
    ];

    public function validate(
        string $tmpPath,
        string $originalName,
        int $size,
        int $maxSize,
        ?array $allowedExtensions = null
    ): string {
        if ($tmpPath === '' || !is_file($tmpPath) || $size <= 0 || $size > $maxSize) {
            throw new \RuntimeException('Invalid uploaded file.');
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension === '' || in_array($extension, self::FORBIDDEN_EXTENSIONS, true)) {
            throw new \RuntimeException('This file type is not allowed.');
        }

        if ($allowedExtensions !== null && !in_array($extension, $allowedExtensions, true)) {
            throw new \RuntimeException('This file type is not allowed.');
        }

        $allowedMimeTypes = self::MIME_TYPES[$extension] ?? [];
        if ($allowedMimeTypes === [] || !class_exists(\finfo::class)) {
            throw new \RuntimeException('This file type cannot be verified safely.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = strtolower((string)$finfo->file($tmpPath));
        if ($mime === '' || !in_array($mime, $allowedMimeTypes, true)) {
            throw new \RuntimeException('The file content does not match its extension.');
        }

        if (str_starts_with($mime, 'image/') && @getimagesize($tmpPath) === false) {
            throw new \RuntimeException('The uploaded image is invalid.');
        }

        return $mime;
    }
}
