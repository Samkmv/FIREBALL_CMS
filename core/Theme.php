<?php

namespace FBL;

/**
 * Static facade for theme rendering helpers used by controllers and templates.
 */
class Theme
{
    public static function getThemes(): array
    {
        return theme()->getThemes();
    }

    public static function getActiveTheme(): array
    {
        return theme()->getActiveTheme();
    }

    public static function getTheme(string $slug): ?array
    {
        return theme()->getTheme($slug);
    }

    public static function createTheme(array $data): array
    {
        return theme()->createTheme($data);
    }

    public static function updateTheme(string $slug, array $data): bool
    {
        return theme()->updateTheme($slug, $data);
    }

    public static function deleteTheme(string $slug): bool
    {
        return theme()->deleteTheme($slug);
    }

    public static function canDeleteTheme(string $slug): bool
    {
        return theme()->canDeleteTheme($slug);
    }

    public static function listThemeFiles(string $slug): array
    {
        return theme()->listThemeFiles($slug);
    }

    public static function preview($slug): ?string
    {
        return theme()->preview($slug);
    }

    public static function export($slug): string
    {
        return theme()->export($slug);
    }

    public static function import($zipFile): array
    {
        return theme()->import($zipFile);
    }

    public static function validatePackage($path): array
    {
        return theme()->validatePackage($path);
    }

    public static function validateThemeStructure($slug): bool
    {
        return theme()->validateThemeStructure($slug);
    }

    public static function activate($slug): bool
    {
        return theme()->activate($slug);
    }

    public static function render($template, $data = []): string
    {
        return theme()->render($template, $data);
    }

    public static function partial($name, $data = []): string
    {
        return theme()->partial($name, $data);
    }

    public static function asset($path): string
    {
        return theme()->asset($path);
    }
}
