<?php
declare(strict_types=1);

namespace App\Modules\BlockEditor {
    function apply_filters(string $hook, mixed $value, mixed ...$args): mixed { return $value; }
}

namespace {
    // Real renderer, sanitizer and layout asset conditions; no application or database.
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    $root = dirname(__DIR__, 2);
    require $root . '/vendor/autoload.php';
    require $root . '/helpers/helpers.php';
    $fallback = ($argv[1] ?? '') === 'fallback';
    $format = $argv[2] ?? 'snapshot';
    $blocks = [
        ['type' => 'gallery', 'data' => ['items' => [
            ['src' => '/photos/one.jpg', 'alt' => 'Первое фото', 'caption' => 'Первая подпись'],
            ['src' => '/photos/two.jpg', 'alt' => 'Второе фото', 'caption' => 'Текст <img src=x onerror="window.galleryInjected=true">'],
            ['src' => '/photos/view?id=3', 'alt' => 'Фото без расширения', 'caption' => 'Третья подпись'],
        ]]],
        ['type' => 'gallery', 'data' => ['items' => [
            ['src' => '/photos/four.jpg', 'alt' => 'Другая галерея'],
            ['src' => '/photos/five.jpg', 'alt' => 'Другое фото'],
        ]]],
        ['type' => 'gallery', 'data' => ['items' => [['src' => '/photos/six.jpg', 'alt' => 'Одна картинка']]]],
    ];
    $json = json_encode(['version' => 2, 'blocks' => $blocks], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    $raw = $format === 'json' ? $json : '<p>Fallback</p><template data-fb-editor-state="2">' . base64_encode($json) . '</template>';
    if ($format === 'legacy') {
        $raw = '';
        foreach ($blocks as $block) {
            $raw .= '<div data-fb-gallery="1">';
            foreach ($block['data']['items'] as $item) {
                $raw .= '<figure><img src="' . htmlSC($item['src']) . '" alt="' . htmlSC($item['alt']) . '">'
                    . (isset($item['caption']) ? '<figcaption>' . htmlSC($item['caption']) . '</figcaption>' : '') . '</figure>';
            }
            $raw .= '</div>';
        }
    }
    $content = (new App\Modules\BlockEditor\BlockRenderer())->renderPublicContent($raw);
    $requiredAssets = App\Services\FrontendAssets::requirements($content);
    $layout = file_get_contents($root . ($fallback ? '/app/Views/layouts/default.php' : '/themes/default/templates/layout.php'));
    $defaultAsset = static fn(string $path): string => $path;
    $fixtureAsset = static fn(string $path): string => '/theme-assets/' . $path;
    // Evaluate the actual conditional includes, keeping missing assets visible to the test.
    preg_match_all('~<\?php if \([^\r\n]*requiredAssets\[\'glightbox\'\][^\r\n]*\): \?>.*?<\?php endif; \?>~s', $layout, $includes);
    ob_start();
    foreach ($includes[0] as $include) eval('?>' . str_replace('theme_asset_versioned(', '$fixtureAsset(', $include));
    $lightboxAssets = ob_get_clean();
    $assets = $fallback ? '/assets/default/' : '/theme-assets/';
    echo '<!doctype html><html lang="ru" dir="ltr" data-bs-theme="dark"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<link rel="stylesheet" href="' . $assets . 'css/theme.min.css">'
        . '<link rel="stylesheet" href="' . $assets . 'css/style.css">'
        . '<link rel="stylesheet" href="' . $assets . 'icons/cartzilla-icons.min.css"></head>'
        . '<body><main class="container py-4">' . $content . '</main>' . $lightboxAssets
        . '<script src="' . $assets . 'js/theme.min.js"></script></body></html>';
}
