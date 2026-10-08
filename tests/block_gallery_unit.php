<?php
declare(strict_types=1);
namespace App\Modules\BlockEditor {
    function apply_filters(string $hook, mixed $value, mixed ...$args): mixed { return $value; }
}
namespace {
    require dirname(__DIR__) . '/vendor/autoload.php';
    require dirname(__DIR__) . '/helpers/helpers.php';
    $checks = 0;
    $check = static function (bool $ok, string $label) use (&$checks): void {
        $checks++; if (!$ok) throw new RuntimeException($label);
    };
    $renderer = new App\Modules\BlockEditor\BlockRenderer();
    $blocks = [
        ['type' => 'gallery', 'data' => ['items' => [
            ['src' => '/one.jpg', 'alt' => 'A "quote"', 'caption' => 'Text <img src=x onerror="alert(1)">'],
            ['src' => '/view?id=2', 'alt' => 'Second'],
            ['src' => 'javascript:alert(1)'],
        ]]],
        ['type' => 'gallery', 'data' => ['items' => [['src' => '/other.png']]]],
        ['type' => 'gallery', 'hidden' => true, 'data' => ['items' => [['src' => '/hidden.jpg']]]],
    ];
    $json = json_encode(['version' => 2, 'blocks' => $blocks], JSON_THROW_ON_ERROR);
    foreach ([$json, '<p>Fallback</p><template data-fb-editor-state="2">' . base64_encode($json) . '</template>'] as $raw) {
        $html = $renderer->renderPublicContent($raw);
        $document = new DOMDocument(); @$document->loadHTML($html);
        $xpath = new DOMXPath($document);
        $links = $xpath->query('//a[@data-glightbox]');
        $check($links->length === 3, 'Hidden and dangerous images excluded');
        $check($links[0]->getAttribute('data-gallery') === $links[1]->getAttribute('data-gallery'), 'Same block grouped');
        $check($links[0]->getAttribute('data-gallery') !== $links[2]->getAttribute('data-gallery'), 'Separate blocks isolated');
        $check($links[0]->getAttribute('data-description') === htmlSC($blocks[0]['data']['items'][0]['caption']), 'Lightbox HTML descriptions receive escaped plain text');
        $check($links[0]->getAttribute('data-alt') === 'A "quote"', 'Viewer receives original alt text');
        $check($links[1]->getAttribute('data-type') === 'image', 'Extensionless URLs explicitly treated as images');
        $check(str_contains($html, 'row row-cols-2 row-cols-sm-3 g-3 g-xl-4') && str_contains($html, 'ci-zoom-in'), 'Reference grid and hover icon');
        $check(str_contains($html, '--cz-aspect-ratio: 100%'), 'Square preview ratio reserved before images load');
        $check(!empty(App\Services\FrontendAssets::requirements($html)['glightbox']), 'Rendered gallery opts into bundled viewer');
        $check(!str_contains($html, 'data-fb-editor-state') && !str_contains($html, '/hidden.jpg') && !str_contains($html, 'javascript:'), 'No editor internals or unsafe links published');
    }
    $legacy = '<div data-fb-gallery="1"><figure><img src="/legacy.jpg" alt="Legacy"><figcaption>Old &lt;text&gt;</figcaption></figure><figure><img src="/legacy-2.jpg"></figure></div>';
    $html = $renderer->renderPublicContent($legacy);
    $check(substr_count($html, 'data-glightbox') === 2 && substr_count($html, 'data-gallery=') === 2, 'Legacy gallery upgraded without resaving');
    $check(str_contains($html, 'data-description="Old &amp;lt;text&amp;gt;"'), 'Legacy plain-text angle brackets retained safely');
    $check($renderer->renderPublicContent('{"blocks":[{"type":"gallery","data":{"items":[]}}]}') === '', 'Empty galleries do not load assets');
    $check(empty(App\Services\FrontendAssets::requirements('<p>Plain content</p>')['glightbox']), 'Unrelated pages omit viewer');
    $check(str_contains($renderer->renderBlock(['type' => 'slider', 'data' => ['items' => [['src' => '/slide.jpg']]]]), 'swiper'), 'Separate slider renderer preserved');
    foreach (['theme' => '/themes/default/templates/layout.php', 'fallback' => '/app/Views/layouts/default.php'] as $name => $file) {
        $layout = file_get_contents(dirname(__DIR__) . $file);
        $vendor = strpos($layout, 'vendor/glightbox/glightbox.min.js');
        $check($vendor !== false && $vendor < strpos($layout, 'js/theme.min.js'), $name . ': viewer loaded before theme initializer');
        $check(str_contains($layout, 'vendor/glightbox/glightbox.min.css'), $name . ': viewer CSS included');
    }
    echo "$checks public gallery unit checks passed\n";
}
