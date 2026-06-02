<?php

namespace App\Services;

/**
 * Готовит SEO-тексты для публичного вывода записи.
 */
class PostSeo
{

    public function description(string $excerpt, string $content, string $title): string
    {
        $excerpt = trim(preg_replace('/\s+/', ' ', strip_tags($excerpt)));
        if ($excerpt !== '') {
            return $this->excerptFromHtml($excerpt, 160);
        }

        $contentExcerpt = $this->excerptFromHtml($content, 160);
        if ($contentExcerpt !== '') {
            return $contentExcerpt;
        }

        return $title;
    }

    protected function excerptFromHtml(string $content, int $limit = 180): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($content)));
        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $limit - 1)) . '...';
    }

}
