<?php
namespace Fireball\Subscriptions\Services;

use App\Services\SafeUploadService;

final class BusinessImageService
{
    public function upload(string $field, int $userId): ?string
    {
        $file = request()->files[$field] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) { return null; }
        if (($file['error'] ?? -1) !== UPLOAD_ERR_OK || !is_uploaded_file((string)($file['tmp_name'] ?? ''))) {
            throw new \InvalidArgumentException(\FireballPluginSubscriptions::t('business_image_error'));
        }
        try {
            (new SafeUploadService())->validate($file['tmp_name'], $file['name'], (int)$file['size'], 5 * 1024 * 1024, ['jpg','jpeg','png','webp']);
            $dimensions = getimagesize($file['tmp_name']);
            if (!$dimensions || $dimensions[0] > 10000 || $dimensions[1] > 10000) { throw new \RuntimeException(); }
            $directory = rtrim(UPLOADS, '/') . '/business/' . $userId;
            if (!is_dir($directory) && !mkdir($directory, 0755, true)) { throw new \RuntimeException(); }
            $name = bin2hex(random_bytes(16)) . '.' . strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $name)) { throw new \RuntimeException(); }
            return ltrim(str_replace(rtrim(WWW, '/'), '', $directory . '/' . $name), '/');
        } catch (\Throwable $exception) {
            throw new \InvalidArgumentException(\FireballPluginSubscriptions::t('business_image_error'), 0, $exception);
        }
    }

    public function remove(string $path, int $userId): void
    {
        $directory = realpath(rtrim(UPLOADS, '/') . '/business/' . $userId);
        $file = realpath(rtrim(WWW, '/') . '/' . ltrim($path, '/'));
        if ($directory && $file && str_starts_with($file, $directory . '/') && is_file($file)) { unlink($file); }
    }
}
