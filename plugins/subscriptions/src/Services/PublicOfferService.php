<?php

namespace Fireball\Subscriptions\Services;

final class PublicOfferService
{
    public const LEGACY_URL = 'https://docs.robokassa.ru/media/1550/%D0%BE%D1%84%D0%B5%D1%80%D1%82%D0%B0-itv.pdf';

    public function pages(): array
    {
        return db()->query('SELECT id, title, slug FROM pages WHERE is_published = 1 ORDER BY title ASC, id ASC')->get() ?: [];
    }

    public function publishedPage(int $id): ?array
    {
        $page = db()->query('SELECT id, title, slug FROM pages WHERE id = ? AND is_published = 1 LIMIT 1', [$id])->getOne();

        return is_array($page) ? $page : null;
    }

    public function url(): string
    {
        $pageId = (int)plugin_setting('subscriptions', 'public_offer_page_id', 0);
        if ($pageId > 0 && ($page = $this->publishedPage($pageId))) {
            return base_href('/' . ltrim((string)$page['slug'], '/'));
        }
        $url = trim((string)plugin_setting('subscriptions', 'public_offer_url', self::LEGACY_URL));
        if (!$this->validUrl($url)) {
            return self::LEGACY_URL;
        }

        return str_starts_with($url, '/') ? base_href($url) : $url;
    }

    public function validUrl(string $url): bool
    {
        if ($url === '' || preg_match('/[\x00-\x20\x7f\\\\]/', $url)) {
            return false;
        }
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return true;
        }
        $parts = parse_url($url);

        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string)($parts['scheme'] ?? '')), ['https', 'http'], true)
            && !isset($parts['user']) && !isset($parts['pass']);
    }
}
