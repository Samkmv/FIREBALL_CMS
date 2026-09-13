<?php

namespace App\Services;

use App\Models\Page;
use App\Models\Post;

/** Only public navigation data; user, CSRF, flash, URL and hook results remain request-local. */
final class PublicLayoutContext
{
    public function navigation(): array
    {
        $key = \FBL\Localization::localeCacheKey('layout', 'navigation:v1');
        $cached = cache()->get($key);
        if (is_array($cached)) return $cached;
        $pages = new Page();
        $data = [
            'postNavigationCategories' => array_values(array_filter((new Post())->getNavigationCategories(), static fn(array $category): bool => (int)($category['total'] ?? 0) > 0)),
            'headerPageLinks' => $pages->getMenuPages('header'),
            'footerPageLinks' => $pages->getMenuPages('footer'),
            'legalInformationLinks' => $pages->getLegalInformationMenu(),
        ];
        cache()->set($key, $data, 600);
        return $data;
    }

    public static function invalidate(): void
    {
        foreach (array_keys(LANGS) as $locale) {
            cache()->remove(\FBL\Localization::localeCacheKey('layout', 'navigation:v1', $locale));
        }
    }
}
