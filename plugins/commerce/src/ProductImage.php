<?php

namespace Fireball\Commerce;

use App\Services\SafeUploadService;
use App\Services\UploadException;
use App\Services\UploadPolicy;
use FireballPluginCommerce as Plugin;

final class ProductImage
{
    private const DIRECTORY = '/uploads/commerce';
    private const FILE_PATH = '~\A/uploads/commerce/[0-9a-f]{32}\.(?:jpg|jpeg|png|webp)\z~';
    public const GALLERY_MIGRATION = '005_product_gallery.sql';
    public const MAX_GALLERY = 12;

    public static function gallery(array $product): array
    {
        $paths = json_decode((string)($product['gallery_images'] ?? ''), true);
        return is_array($paths) ? array_values(array_filter($paths, static fn($path): bool =>
            is_string($path) && preg_match(self::FILE_PATH, $path))) : [];
    }

    public static function photos(array $product): array
    {
        return array_values(array_unique(array_filter([
            $product['image_path'] ?? null, $product['hover_image_path'] ?? null, ...self::gallery($product),
        ])));
    }

    public static function uploadGallery(array $files, int $availableSlots): array
    {
        $errors = (array)($files['error'] ?? []);
        $uploads = array_filter($errors, static fn($error): bool => $error !== UPLOAD_ERR_NO_FILE);
        if (count($uploads) > $availableSlots) {
            throw new ValidationError(sprintf(Plugin::t('error_gallery_limit'), self::MAX_GALLERY));
        }
        $paths = [];
        try {
            foreach ($uploads as $index => $error) {
                $path = self::upload([
                    'error' => $error, 'name' => $files['name'][$index] ?? '', 'tmp_name' => $files['tmp_name'][$index] ?? '',
                    'size' => $files['size'][$index] ?? 0,
                ]);
                if ($path !== null) {
                    $paths[] = $path;
                }
            }
        } catch (\Throwable $error) {
            foreach ($paths as $path) {
                self::remove($path);
            }
            throw $error;
        }
        return $paths;
    }

    public static function upload(array $file): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        try {
            $original = (string)($file['name'] ?? '');
            $temporary = (string)($file['tmp_name'] ?? '');
            $size = (int)($file['size'] ?? 0);
            $limit = 8 * 1024 * 1024;
            UploadPolicy::assertUpload((int)$file['error'], $size, $limit);
            if (!is_uploaded_file($temporary)) {
                throw new UploadException('upload_error_invalid');
            }
            (new SafeUploadService())->validate($temporary, $original, $size, $limit, ['jpg', 'jpeg', 'png', 'webp']);
            [$width, $height] = getimagesize($temporary);
            if ($width * $height > 25000000) {
                throw new UploadException('upload_error_image');
            }
        } catch (UploadException $error) {
            throw new ValidationError(Plugin::t('error_image'), previous: $error);
        }
        $directory = WWW . self::DIRECTORY;
        if ((!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory))
            || realpath($directory) !== realpath(WWW) . self::DIRECTORY) {
            throw new \RuntimeException('Commerce upload directory unavailable');
        }
        $path = self::DIRECTORY . '/' . bin2hex(random_bytes(16)) . '.' . strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!move_uploaded_file($temporary, WWW . $path)) {
            throw new \RuntimeException('Commerce upload could not be saved');
        }
        return $path;
    }

    public static function remove(?string $path): void
    {
        if ($path && preg_match(self::FILE_PATH, $path)
            && realpath(WWW . self::DIRECTORY) === realpath(WWW) . self::DIRECTORY) {
            @unlink(WWW . $path);
        }
    }
}
