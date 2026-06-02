<?php

namespace App\Modules\BlockEditor;

final class BlockRenderer
{
    public function renderPublicContent(string $content): string
    {
        $content = trim($content);
        if ($content === '') {
            return '';
        }

        if ($content[0] !== '{') {
            return sanitize_content_html($content);
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded) || !isset($decoded['blocks']) || !is_array($decoded['blocks'])) {
            return sanitize_content_html($content);
        }

        $html = '';
        foreach ($decoded['blocks'] as $block) {
            if (!is_array($block) || !empty($block['hidden'])) {
                continue;
            }

            $html .= $this->renderBlock($block);
        }

        return sanitize_content_html($html);
    }

    public function renderBlock(array $block): string
    {
        $type = (string)($block['type'] ?? 'text');
        $data = is_array($block['data'] ?? null) ? $block['data'] : [];

        return match ($type) {
            'heading' => $this->heading($data),
            'image' => $this->image($data),
            'newsletter' => $this->newsletter($data),
            'html' => (string)($data['html'] ?? ''),
            'code' => '<pre><code>' . htmlSC((string)($data['code'] ?? '')) . '</code></pre>',
            default => (string)($data['html'] ?? ''),
        };
    }

    private function heading(array $data): string
    {
        $level = in_array((string)($data['level'] ?? 'h2'), ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true)
            ? (string)$data['level']
            : 'h2';

        return '<' . $level . '>' . sanitize_content_html((string)($data['html'] ?? '')) . '</' . $level . '>';
    }

    private function image(array $data): string
    {
        $src = trim((string)($data['src'] ?? ''));
        if ($src === '') {
            return '';
        }

        $alt = htmlSC((string)($data['alt'] ?? ''));
        $image = '<img class="img-fluid rounded-4" src="' . htmlSC($src) . '" alt="' . $alt . '">';
        $caption = trim((string)($data['caption'] ?? ''));

        if ($caption !== '') {
            $image .= '<figcaption class="figure-caption mt-2">' . htmlSC($caption) . '</figcaption>';
        }

        return '<figure class="figure d-block">' . $image . '</figure>';
    }

    private function newsletter(array $data): string
    {
        $title = trim((string)($data['title'] ?? '')) ?: 'Sign up to our newsletter';
        $text = trim((string)($data['text'] ?? '')) ?: 'Receive our latest updates about our products & promotions';
        $buttonText = trim((string)($data['buttonText'] ?? '')) ?: 'Subscribe';
        $buttonUrl = trim((string)($data['buttonUrl'] ?? ''));
        $buttonIcon = trim((string)($data['buttonIcon'] ?? 'ci-mail')) ?: 'ci-mail';
        $buttonTag = $buttonUrl !== '' ? 'a href="' . htmlSC($buttonUrl) . '"' : 'button type="button"';

        return '<div class="d-sm-flex align-items-center justify-content-between bg-body-tertiary rounded-4 py-5 px-4 px-md-5" data-fb-newsletter-block>' .
            '<div class="mb-4 mb-sm-0 me-sm-4">' .
            '<h3 class="h5 mb-2" data-fb-newsletter-title>' . htmlSC($title) . '</h3>' .
            '<p class="fs-sm mb-0" data-fb-newsletter-text>' . htmlSC($text) . '</p>' .
            '</div>' .
            '<' . $buttonTag . ' class="btn btn-dark" data-fb-newsletter-button>' .
            '<i class="' . htmlSC($buttonIcon) . ' fs-base ms-n1 me-2"></i>' . htmlSC($buttonText) .
            '</' . ($buttonUrl !== '' ? 'a' : 'button') . '>' .
            '</div>';
    }
}
