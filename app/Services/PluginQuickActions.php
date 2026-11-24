<?php

namespace App\Services;

use FBL\Localization;

final class PluginQuickActions
{
    /** Manifest actions open admin pages or forms. Mutations stay behind their normal forms. */
    public static function fromMetadata(array $metadata): array
    {
        $category = self::localized($metadata, 'name', (string)($metadata['slug'] ?? ''));
        $actions = is_array($metadata['quick_actions'] ?? null) ? $metadata['quick_actions'] : [];
        $menu = $metadata['admin_menu'] ?? null;
        if (is_array($menu) && !empty($menu['route'])) {
            array_unshift($actions, [
                'route' => $menu['route'],
                'label' => $category,
                'icon' => $menu['icon'] ?? 'ci-box',
                'creator_only' => !empty($menu['creator_only']),
            ]);
        }

        $commands = [];
        foreach ($actions as $action) {
            if (!is_array($action)) {
                continue;
            }
            $route = trim((string)($action['route'] ?? ''));
            if (!str_starts_with($route, '/admin/') || str_contains($route, '\\') || preg_match('/[\x00-\x20]/', $route)) {
                continue;
            }
            $label = self::localized($action, 'label');
            if (!empty($action['label_key'])) {
                $translated = return_translation((string)$action['label_key']);
                if ($translated !== $action['label_key']) {
                    $label = $translated;
                }
            }
            if ($label === '') {
                continue;
            }

            $commands[] = [
                'href' => base_href($route),
                'label' => $label,
                'category' => $category,
                'icon' => (string)($action['icon'] ?? 'ci-arrow-right'),
                'keywords' => trim((string)($metadata['slug'] ?? '') . ' ' . (string)($action['keywords'] ?? '')),
                'creator_only' => !empty($action['creator_only']),
            ];
        }

        return $commands;
    }

    private static function localized(array $data, string $field, string $fallback = ''): string
    {
        $translations = is_array($data[$field . '_i18n'] ?? null) ? $data[$field . '_i18n'] : [];
        foreach (Localization::localeCandidates(null, ['en']) as $locale) {
            if (is_string($translations[$locale] ?? null) && trim($translations[$locale]) !== '') {
                return trim($translations[$locale]);
            }
        }

        return trim((string)($data[$field] ?? $fallback));
    }
}
