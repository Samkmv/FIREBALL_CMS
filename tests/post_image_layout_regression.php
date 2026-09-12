<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$template = (string)file_get_contents($root . '/themes/default/templates/post.php');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

$assert(str_contains($template, 'post-cover-frame'), 'The post cover frame is missing a stable layout hook.');
$assert(str_contains($template, 'aspect-ratio: 856 / 560'), 'The post cover frame has no inline aspect ratio fallback.');
$assert(str_contains($template, 'position: absolute; inset: 0;'), 'The post cover image is not pinned to its frame before stylesheets finish loading.');
$assert(str_contains($template, 'width="856" height="560"'), 'The post cover image still uses its unbounded source dimensions as the initial layout size.');
$assert(str_contains($template, 'loading="eager" fetchpriority="high"'), 'The above-the-fold post cover is still deferred as a lazy image.');

echo json_encode([
    'status' => 'ok',
    'stable_cover_frame' => true,
    'eager_cover_loading' => true,
], JSON_UNESCAPED_SLASHES), PHP_EOL;
