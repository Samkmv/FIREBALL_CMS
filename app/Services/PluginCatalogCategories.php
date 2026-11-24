<?php

namespace App\Services;

/** Category identifiers are shared by manifests, filters and translated labels. */
final class PluginCatalogCategories
{
    private const CATEGORIES = ['commerce', 'media', 'productivity', 'network', 'other'];
    private const BUNDLED_PLUGINS = [
        'commerce' => 'commerce',
        'subscriptions' => 'commerce',
        'toy-car-rental' => 'commerce',
        'reel-player' => 'media',
        'camera-manager' => 'media',
        'calendar' => 'productivity',
        'vpn-manager-v2' => 'network',
    ];

    public static function normalize(mixed $category): string
    {
        return is_string($category) && in_array($category, self::CATEGORIES, true) ? $category : '';
    }

    public static function resolve(array $metadata): string
    {
        return self::normalize($metadata['category'] ?? '')
            ?: (self::BUNDLED_PLUGINS[$metadata['slug'] ?? ''] ?? 'other');
    }

    public static function labels(): array
    {
        $labels = [];
        foreach (self::CATEGORIES as $category) {
            $labels[$category] = return_translation('admin_plugin_category_' . $category);
        }
        return $labels;
    }
}
