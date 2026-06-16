<?php

namespace App\Services\Themes;

final class ThemeAssets
{
    public function asset(string $path, array $theme, ?array $defaultTheme = null): string
    {
        if (trim($path) === '') {
            return base_url('/themes/' . rawurlencode((string)$theme['slug']) . '/assets');
        }

        $assetPath = $this->safePath($path);
        if ($assetPath === null) {
            return '';
        }

        if ($this->assetFile($theme, $assetPath) === null && $defaultTheme && (string)$theme['slug'] !== (string)$defaultTheme['slug']) {
            if ($this->assetFile($defaultTheme, $assetPath) !== null) {
                $theme = $defaultTheme;
            }
        }

        return base_url('/themes/' . rawurlencode((string)$theme['slug']) . '/assets/' . str_replace('%2F', '/', rawurlencode($assetPath)));
    }

    public function assetPath(string $path, array $theme, ?array $defaultTheme = null): string
    {
        $assetPath = $this->safePath($path);
        if ($assetPath === null) {
            return '';
        }

        $assetFile = $this->assetFile($theme, $assetPath);
        if ($assetFile !== null) {
            return $assetFile;
        }

        $defaultAsset = $defaultTheme ? $this->assetFile($defaultTheme, $assetPath) : null;

        return $defaultAsset ?: rtrim((string)$theme['path'], '/') . '/assets/' . $assetPath;
    }

    public function previewUrl(array $theme): string
    {
        $preview = $this->sanitizePreview((string)($theme['preview'] ?? 'preview.png'));

        return base_url('/themes/' . rawurlencode((string)$theme['slug']) . '/' . rawurlencode($preview));
    }

    private function assetFile(array $theme, string $path): ?string
    {
        $file = rtrim((string)$theme['path'], '/') . '/assets/' . $path;
        $realFile = realpath($file);
        $realBase = realpath(rtrim((string)$theme['path'], '/') . '/assets');
        if ($realFile === false || $realBase === false || !$this->isInside($realFile, $realBase) || !is_file($realFile)) {
            return null;
        }

        return $realFile;
    }

    private function safePath(string $path): ?string
    {
        $path = trim(str_replace('\\', '/', $path));
        $path = ltrim($path, '/');
        if ($path === '' || str_contains($path, '../') || str_contains($path, '..\\') || $path === '..' || str_contains($path, "\0")) {
            return null;
        }

        if (!preg_match('#^[A-Za-z0-9._/-]+$#', $path)) {
            return null;
        }

        return $path;
    }

    private function sanitizePreview(string $preview): string
    {
        $preview = $this->safePath($preview) ?? '';

        return $preview !== '' && preg_match('/\.(png|jpg|jpeg|webp|gif|svg)$/i', $preview) ? $preview : 'preview.png';
    }

    private function isInside(string $path, string $base): bool
    {
        $base = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return str_starts_with($path, $base);
    }
}
