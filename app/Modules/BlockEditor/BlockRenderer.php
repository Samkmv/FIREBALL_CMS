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
        $pluginHtml = apply_filters('fireball_editor_render_block', null, $block, $this);
        if (is_string($pluginHtml)) {
            return $this->wrapBlock($block, $pluginHtml);
        }

        $html = match ($type) {
            'heading' => $this->heading($data),
            'bulletList' => $this->listBlock($data, false),
            'orderedList' => $this->listBlock($data, true),
            'checklist' => $this->checklist($data),
            'quote' => $this->quote($data),
            'image' => $this->image($data),
            'gallery', 'slider' => $this->gallery($data, $type),
            'video' => $this->video($data),
            'audio' => $this->audio($data),
            'table' => $this->table($data),
            'alert' => $this->alert($data),
            'newsletter' => $this->newsletter($data),
            'html' => (string)($data['html'] ?? ''),
            'code' => '<pre><code>' . htmlSC((string)($data['code'] ?? '')) . '</code></pre>',
            'divider' => '<hr>',
            'faq' => $this->faq($data),
            'button' => $this->button($data),
            'embed' => $this->embed($data),
            'social' => $this->social($data),
            default => $this->textHtml((string)($data['html'] ?? '')),
        };

        return $this->wrapBlock($block, $html);
    }

    private function heading(array $data): string
    {
        $level = in_array((string)($data['level'] ?? 'h2'), ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true)
            ? (string)$data['level']
            : 'h2';

        return '<' . $level . '>' . sanitize_content_html($this->cleanEditorTypography((string)($data['html'] ?? ''))) . '</' . $level . '>';
    }

    private function textHtml(string $html): string
    {
        return $this->cleanEditorTypography($html);
    }

    private function listBlock(array $data, bool $ordered): string
    {
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];
        if ($items === []) {
            return '';
        }

        $tag = $ordered ? 'ol' : 'ul';
        $html = '<' . $tag . '>';
        foreach ($items as $item) {
            $html .= '<li>' . $this->cleanEditorTypography((string)$item) . '</li>';
        }

        return $html . '</' . $tag . '>';
    }

    private function checklist(array $data): string
    {
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];
        if ($items === []) {
            return '';
        }

        $html = '<ul data-fb-checklist="1">';
        foreach ($items as $item) {
            $item = is_array($item) ? $item : ['text' => (string)$item, 'checked' => false];
            $checked = !empty($item['checked']);
            $html .= '<li data-checked="' . ($checked ? '1' : '0') . '">' .
                ($checked ? '☑ ' : '☐ ') .
                $this->cleanEditorTypography((string)($item['text'] ?? '')) .
                '</li>';
        }

        return $html . '</ul>';
    }

    private function quote(array $data): string
    {
        $html = $this->cleanEditorTypography((string)($data['html'] ?? ''));
        $cite = trim((string)($data['cite'] ?? ''));
        if ($html === '' && $cite === '') {
            return '';
        }

        return '<blockquote>' . $html . ($cite !== '' ? '<cite>' . htmlSC($cite) . '</cite>' : '') . '</blockquote>';
    }

    private function cleanEditorTypography(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $html = preg_replace('/<\s*font\b[^>]*>/i', '<span>', $html) ?? $html;
        $html = preg_replace('/<\s*\/\s*font\s*>/i', '</span>', $html) ?? $html;
        $html = preg_replace('/\s+(class|id|data-[a-z0-9_-]+|aria-[a-z0-9_-]+)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;

        $html = preg_replace_callback(
            '/\s+style\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i',
            static function (array $matches): string {
                $style = trim($matches[1], "\"' \t\n\r\0\x0B");
                $allowed = [
                    'color',
                    'background-color',
                    'text-align',
                    'font-weight',
                    'font-style',
                    'font-family',
                    'font-size',
                    'text-decoration',
                    'text-decoration-line',
                    'vertical-align',
                ];
                $clean = [];

                foreach (explode(';', $style) as $declaration) {
                    $separator = strpos($declaration, ':');
                    if ($separator === false) {
                        continue;
                    }

                    $property = strtolower(trim(substr($declaration, 0, $separator)));
                    $value = trim(substr($declaration, $separator + 1));

                    if (!in_array($property, $allowed, true) || $value === '' || preg_match('/(?:expression\s*\(|javascript\s*:|url\s*\()/i', $value)) {
                        continue;
                    }

                    $clean[] = $property . ': ' . $value;
                }

                return $clean === [] ? '' : ' style="' . htmlSC(implode('; ', $clean)) . '"';
            },
            $html
        ) ?? $html;

        return trim($html);
    }

    private function image(array $data): string
    {
        $src = trim((string)($data['src'] ?? ''));
        if ($src === '' || !is_safe_content_url($src, true)) {
            return '';
        }

        $alt = htmlSC((string)($data['alt'] ?? ''));
        $title = trim((string)($data['title'] ?? ''));
        $width = max(0, min(8000, (int)($data['width'] ?? 0)));
        $height = max(0, min(8000, (int)($data['height'] ?? 0)));
        $crop = in_array((string)($data['crop'] ?? 'none'), ['cover', 'contain'], true) ? (string)$data['crop'] : '';
        $image = '<img class="img-fluid rounded-4" src="' . htmlSC($src) . '" alt="' . $alt . '"' .
            ($title !== '' ? ' title="' . htmlSC($title) . '"' : '') .
            ' loading="' . (!array_key_exists('lazy', $data) || !empty($data['lazy']) ? 'lazy' : 'eager') . '"' .
            ($width > 0 ? ' width="' . $width . '"' : '') .
            ($height > 0 ? ' height="' . $height . '"' : '') .
            ($crop !== '' ? ' style="object-fit: ' . $crop . '"' : '') .
            '>';
        $caption = trim((string)($data['caption'] ?? ''));
        $link = trim((string)($data['link'] ?? ''));
        if ($link !== '' && is_safe_content_url($link)) {
            $image = '<a href="' . htmlSC($link) . '" rel="noopener noreferrer">' . $image . '</a>';
        }

        if ($caption !== '') {
            $image .= '<figcaption class="figure-caption mt-2">' . htmlSC($caption) . '</figcaption>';
        }

        return '<figure class="figure d-block">' . $image . '</figure>';
    }

    private function gallery(array $data, string $type): string
    {
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];
        if ($items === []) {
            return '';
        }

        $html = '<div data-fb-' . htmlSC($type) . '="1">';
        foreach ($items as $item) {
            $item = is_array($item) ? $item : ['src' => (string)$item];
            $src = trim((string)($item['src'] ?? $item['image'] ?? ''));
            if ($src === '' || !is_safe_content_url($src, true)) {
                continue;
            }
            $caption = trim((string)($item['caption'] ?? ''));
            $html .= '<figure><img src="' . htmlSC($src) . '" alt="' . htmlSC((string)($item['alt'] ?? '')) . '" loading="lazy">' .
                ($caption !== '' ? '<figcaption>' . htmlSC($caption) . '</figcaption>' : '') .
                '</figure>';
        }

        return $html . '</div>';
    }

    private function video(array $data): string
    {
        $src = trim((string)($data['src'] ?? ''));
        if ($src === '' || !is_safe_content_url($src)) {
            return '';
        }

        $caption = trim((string)($data['caption'] ?? ''));
        $embed = $this->trustedEmbedUrl($src);
        if ($embed !== '') {
            return '<figure><div class="ratio ratio-16x9"><iframe src="' . htmlSC($embed) . '" title="' .
                htmlSC($caption !== '' ? $caption : 'Video') .
                '" loading="lazy" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div>' .
                ($caption !== '' ? '<figcaption>' . htmlSC($caption) . '</figcaption>' : '') .
                '</figure>';
        }

        $poster = trim((string)($data['poster'] ?? ''));
        if ($poster !== '' && !is_safe_content_url($poster, true)) {
            $poster = '';
        }

        $ratio = in_array((string)($data['aspectRatio'] ?? '16:9'), ['16:9', '4:3', '1:1', '9:16'], true)
            ? (string)$data['aspectRatio']
            : '16:9';
        $videoAttributes = (!array_key_exists('controls', $data) || !empty($data['controls']) ? ' controls' : '') .
            (!empty($data['autoplay']) ? ' autoplay' : '') .
            (!empty($data['muted']) || !empty($data['autoplay']) ? ' muted' : '') .
            (!empty($data['loop']) ? ' loop' : '');

        return '<figure data-aspect-ratio="' . htmlSC($ratio) . '"><video' . $videoAttributes . ' preload="metadata" src="' . htmlSC($src) . '"' .
            ($poster !== '' ? ' poster="' . htmlSC($poster) . '"' : '') .
            (!empty($data['hls']) ? ' data-hls="1"' : '') .
            '></video>' .
            ($caption !== '' ? '<figcaption>' . htmlSC($caption) . '</figcaption>' : '') .
            '</figure>';
    }

    private function audio(array $data): string
    {
        $src = trim((string)($data['src'] ?? ''));
        if ($src === '') {
            return '';
        }

        $caption = trim((string)($data['caption'] ?? ''));
        $audioOptions = htmlSC('{"controls":["play","progress","current-time","duration","mute","volume"]}');
        $audio = '<div data-plyr-player-wrap="" data-plyr-media="audio" data-plyr-lazy="true"><audio controls preload="metadata" data-plyr-player="" data-plyr-options="' . $audioOptions . '">' .
            '<source src="' . htmlSC($src) . '" type="' . htmlSC($this->audioMimeType($src)) . '">' .
            '</audio></div>';

        if ($caption !== '') {
            $audio .= '<p>' . htmlSC($caption) . '</p>';
        }

        return $audio;
    }

    private function table(array $data): string
    {
        $rows = is_array($data['rows'] ?? null) ? $data['rows'] : [];
        if ($rows === []) {
            return '';
        }

        $hasHeader = !empty($data['header']);
        $head = '';
        $body = '';
        foreach ($rows as $rowIndex => $row) {
            if (!is_array($row)) {
                continue;
            }
            $tag = $hasHeader && $rowIndex === 0 ? 'th' : 'td';
            $cells = '';
            foreach ($row as $cell) {
                $cells .= '<' . $tag . '>' . $this->cleanEditorTypography((string)$cell) . '</' . $tag . '>';
            }
            if ($cells === '') {
                continue;
            }
            if ($hasHeader && $rowIndex === 0) {
                $head .= '<tr>' . $cells . '</tr>';
            } else {
                $body .= '<tr>' . $cells . '</tr>';
            }
        }

        $table = '<table class="table' . (!array_key_exists('bordered', $data) || !empty($data['bordered']) ? ' table-bordered' : '') . '">' .
            ($head !== '' ? '<thead>' . $head . '</thead>' : '') .
            '<tbody>' . $body . '</tbody></table>';

        return !array_key_exists('responsive', $data) || !empty($data['responsive'])
            ? '<div class="table-responsive">' . $table . '</div>'
            : $table;
    }

    private function audioMimeType(string $src): string
    {
        $path = strtolower((string)(parse_url($src, PHP_URL_PATH) ?: $src));

        return match (pathinfo($path, PATHINFO_EXTENSION)) {
            'ogg', 'oga' => 'audio/ogg',
            'wav' => 'audio/wav',
            'm4a', 'aac' => 'audio/mp4',
            'flac' => 'audio/flac',
            'webm' => 'audio/webm',
            default => 'audio/mpeg',
        };
    }

    private function alert(array $data): string
    {
        $variants = [
            'primary' => 'ci-bell',
            'secondary' => 'ci-clock',
            'success' => 'ci-check-circle',
            'danger' => 'ci-banned',
            'warning' => 'ci-alert-triangle',
            'info' => 'ci-info',
            'light' => 'ci-unlock',
            'dark' => 'ci-map-pin',
        ];
        $variant = strtolower(trim((string)($data['variant'] ?? 'primary')));
        if (!isset($variants[$variant])) {
            $variant = 'primary';
        }

        $icon = trim((string)($data['icon'] ?? $variants[$variant]));
        if (!preg_match('/^ci-[a-z0-9-]+$/i', $icon)) {
            $icon = $variants[$variant];
        }

        $title = trim((string)($data['title'] ?? ''));
        $text = trim((string)($data['text'] ?? ''));
        if ($title === '' && $text === '') {
            return '';
        }

        return '<div class="alert d-flex alert-' . htmlSC($variant) . '" role="alert" data-fb-alert-block="1" data-alert-variant="' . htmlSC($variant) . '">' .
            '<i class="' . htmlSC($icon) . ' fs-lg pe-1 mt-1 me-2" aria-hidden="true"></i>' .
            '<div class="min-w-0">' .
            ($title !== '' ? '<div class="fw-semibold mb-1" data-fb-alert-title="1">' . htmlSC($title) . '</div>' : '') .
            ($text !== '' ? '<div data-fb-alert-text="1">' . nl2br(htmlSC($text), false) . '</div>' : '') .
            '</div>' .
            '</div>';
    }

    private function newsletter(array $data): string
    {
        $title = trim((string)($data['title'] ?? ''))
            ?: $this->translateOrFallback('admin_post_builder_newsletter_default_title', 'Sign up to our newsletter');
        $text = trim((string)($data['text'] ?? ''))
            ?: $this->translateOrFallback('admin_post_builder_newsletter_default_text', 'Receive our latest updates about our products & promotions');
        $buttonText = trim((string)($data['buttonText'] ?? ''))
            ?: $this->translateOrFallback('admin_post_builder_newsletter_default_button', 'Subscribe');
        $buttonUrl = trim((string)($data['buttonUrl'] ?? ''));
        $buttonIcon = trim((string)($data['buttonIcon'] ?? 'ci-mail')) ?: 'ci-mail';
        if (!preg_match('/^ci-[a-z0-9-]+$/i', $buttonIcon)) {
            $buttonIcon = 'ci-mail';
        }
        $buttonInner = '<i class="' . htmlSC($buttonIcon) . ' fs-base ms-n1 me-2"></i>' . htmlSC($buttonText);
        $buttonHtml = $buttonUrl !== '' && is_safe_content_url($buttonUrl)
            ? '<a href="' . htmlSC($buttonUrl) . '" class="btn btn-dark" target="_blank" rel="noopener noreferrer" data-fb-newsletter-button="1">' . $buttonInner . '</a>'
            : '<button type="button" class="btn btn-dark" data-fb-newsletter-button="1">' . $buttonInner . '</button>';

        return '<div class="d-sm-flex align-items-center justify-content-between bg-body-tertiary rounded-4 py-5 px-4 px-md-5" data-fb-newsletter-block="1" data-button-text="' . htmlSC($buttonText) . '" data-button-url="' . htmlSC($buttonUrl) . '" data-button-icon="' . htmlSC($buttonIcon) . '">' .
            '<div class="mb-4 mb-sm-0 me-sm-4">' .
            '<h3 class="h5 mb-2" data-fb-newsletter-title="1">' . htmlSC($title) . '</h3>' .
            '<p class="fs-sm mb-0" data-fb-newsletter-text="1">' . htmlSC($text) . '</p>' .
            '</div>' .
            $buttonHtml .
            '</div>';
    }

    private function faq(array $data): string
    {
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];
        $html = '';
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $question = trim((string)($item['question'] ?? ''));
            $answer = $this->cleanEditorTypography((string)($item['answer'] ?? ''));
            if ($question === '' && $answer === '') {
                continue;
            }
            $html .= '<details><summary>' . htmlSC($question) . '</summary><div>' . $answer . '</div></details>';
        }

        return $html !== '' ? '<div data-fb-faq="1">' . $html . '</div>' : '';
    }

    private function button(array $data): string
    {
        $text = trim((string)($data['text'] ?? ''));
        if ($text === '') {
            return '';
        }
        $url = trim((string)($data['url'] ?? ''));
        $style = preg_match('/^[a-z0-9-]+$/i', (string)($data['style'] ?? 'primary'))
            ? (string)$data['style']
            : 'primary';
        $align = in_array((string)($data['align'] ?? 'left'), ['left', 'center', 'right'], true)
            ? (string)$data['align']
            : 'left';
        $button = $url !== '' && is_safe_content_url($url)
            ? '<a class="btn btn-' . htmlSC($style) . '" href="' . htmlSC($url) . '" rel="noopener noreferrer">' . htmlSC($text) . '</a>'
            : '<span class="btn btn-' . htmlSC($style) . '">' . htmlSC($text) . '</span>';

        return '<div class="text-' . htmlSC($align) . '">' . $button . '</div>';
    }

    private function embed(array $data): string
    {
        $url = $this->trustedEmbedUrl((string)($data['url'] ?? ''));
        if ($url === '') {
            return '';
        }
        $caption = trim((string)($data['caption'] ?? ''));
        $accessibleTitle = $caption !== ''
            ? $caption
            : $this->translateOrFallback('editor_block_embed', 'Embedded content');

        return '<figure><div class="ratio ratio-16x9"><iframe src="' . htmlSC($url) . '" title="' .
            htmlSC($accessibleTitle) .
            '" loading="lazy" allowfullscreen></iframe></div>' .
            ($caption !== '' ? '<figcaption>' . htmlSC($caption) . '</figcaption>' : '') .
            '</figure>';
    }

    private function social(array $data): string
    {
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];
        $html = '';
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $url = trim((string)($item['url'] ?? ''));
            if ((string)($item['network'] ?? '') === 'phone') {
                $url = normalize_phone_href($url);
            }
            if ($url === '' || !is_safe_content_url($url)) {
                continue;
            }
            $icon = trim((string)($item['icon'] ?? 'ci-globe'));
            $hasThemeIcon = $this->themeIconExists($icon);
            $storedIcon = $hasThemeIcon ? $icon : 'fb-social-vector';
            $iconHtml = $hasThemeIcon
                ? '<i class="fb-social-buttons__icon ' . htmlSC($icon) . '" aria-hidden="true"></i>'
                : '<span class="fb-social-buttons__icon fb-social-buttons__icon--svg" aria-hidden="true"></span>';
            $network = trim((string)($item['network'] ?? 'custom'));
            $externalAttributes = $network === 'phone' ? '' : ' target="_blank" rel="noopener noreferrer"';
            $html .= '<a class="fb-social-buttons__item" href="' . htmlSC($url) . '"' . $externalAttributes .
                ' data-network="' . htmlSC($network !== '' ? $network : 'custom') . '" data-icon="' . htmlSC($storedIcon) . '">' .
                $iconHtml .
                '<span class="fb-social-buttons__label">' . htmlSC((string)($item['label'] ?? '')) . '</span>' .
                '</a>';
        }

        return $html !== '' ? '<div class="fb-social-buttons" data-fb-social="1" data-fb-social-buttons="1">' . $html . '</div>' : '';
    }

    private function themeIconExists(string $icon): bool
    {
        if (!preg_match('/^ci-[a-z0-9-]+$/i', $icon)) {
            return false;
        }

        static $iconCss = null;
        static $iconCssLoaded = false;
        if (!$iconCssLoaded) {
            $iconCssLoaded = true;
            $iconPath = dirname(__DIR__, 3) . '/public/assets/default/icons/cartzilla-icons.min.css';
            $iconCss = is_file($iconPath) ? file_get_contents($iconPath) : false;
        }

        return !is_string($iconCss) || str_contains($iconCss, '.' . $icon . ':before{');
    }

    private function trustedEmbedUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '' || !is_safe_content_url($url)) {
            return '';
        }

        $parts = parse_url($url);
        $host = strtolower(preg_replace('/^www\./', '', (string)($parts['host'] ?? '')));
        $path = (string)($parts['path'] ?? '');
        if ($host === 'youtu.be') {
            $id = trim($path, '/');
            return preg_match('/^[a-zA-Z0-9_-]+$/', $id) ? 'https://www.youtube-nocookie.com/embed/' . rawurlencode($id) : '';
        }
        if (in_array($host, ['youtube.com', 'm.youtube.com', 'youtube-nocookie.com'], true)) {
            parse_str((string)($parts['query'] ?? ''), $query);
            $id = (string)($query['v'] ?? '');
            if ($id === '' && preg_match('~/(?:embed|shorts)/([^/?]+)~', $path, $matches)) {
                $id = (string)$matches[1];
            }
            return preg_match('/^[a-zA-Z0-9_-]+$/', $id) ? 'https://www.youtube-nocookie.com/embed/' . rawurlencode($id) : '';
        }
        if (in_array($host, ['vimeo.com', 'player.vimeo.com'], true) && preg_match('/(\d+)/', $path, $matches)) {
            return 'https://player.vimeo.com/video/' . rawurlencode((string)$matches[1]);
        }

        $allowedHosts = (array)apply_filters('fireball_editor_embed_hosts', []);
        if (in_array($host, array_map('strtolower', $allowedHosts), true)) {
            return $url;
        }

        return '';
    }

    private function wrapBlock(array $block, string $html): string
    {
        if ($html === '') {
            return '';
        }

        $type = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($block['type'] ?? 'text')) ?: 'text';
        $settings = is_array($block['settings'] ?? null) ? $block['settings'] : [];
        $classes = ['fb-content-block', 'fb-content-block--' . $type];
        $width = in_array((string)($settings['width'] ?? ''), ['default', 'wide', 'full'], true)
            ? (string)$settings['width']
            : 'default';
        $classes[] = 'fb-content-block--width-' . $width;
        $customClass = trim((string)($settings['className'] ?? ''));
        if ($customClass !== '' && preg_match('/^[a-zA-Z0-9 _-]+$/', $customClass)) {
            $classes[] = $customClass;
        }
        foreach ((array)($settings['hiddenOn'] ?? []) as $device) {
            if (in_array($device, ['desktop', 'tablet', 'mobile'], true)) {
                $classes[] = 'fb-hide-' . $device;
            }
        }

        $styles = [];
        $numericStyles = [
            'marginTop' => 'margin-top',
            'marginBottom' => 'margin-bottom',
            'indent' => 'margin-left',
        ];
        foreach ($numericStyles as $key => $property) {
            if (isset($settings[$key]) && is_numeric($settings[$key])) {
                $styles[] = $property . ':' . max(0, min(160, (int)$settings[$key])) . 'px';
            }
        }
        if (in_array((string)($settings['align'] ?? ''), ['left', 'center', 'right', 'justify'], true)) {
            $styles[] = 'text-align:' . $settings['align'];
        }
        if (!empty($settings['lineHeight']) && is_numeric($settings['lineHeight'])) {
            $styles[] = 'line-height:' . max(0.5, min(3, (float)$settings['lineHeight']));
        }
        if (!empty($settings['indentFirstLine'])) {
            $styles[] = 'text-indent:2em';
        }

        $anchor = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($settings['anchor'] ?? ''));

        return '<div class="' . htmlSC(implode(' ', $classes)) . '" data-fb-block="' . htmlSC($type) . '"' .
            ($anchor !== '' ? ' id="' . htmlSC($anchor) . '"' : '') .
            ($styles !== [] ? ' style="' . htmlSC(implode(';', $styles)) . '"' : '') .
            '>' . $html . '</div>';
    }

    private function translateOrFallback(string $key, string $fallback): string
    {
        if (!function_exists('\return_translation')) {
            return $fallback;
        }

        try {
            $value = \return_translation($key);
            return $value !== '' && $value !== $key ? $value : $fallback;
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
