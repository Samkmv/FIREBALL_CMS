<?php

declare(strict_types=1);

namespace App\Models {
    class SiteSetting
    {
    }
}

namespace {
    use App\Models\SiteSetting;
    use App\Services\PwaService;

    function app_base_url(): string
    {
        return 'https://example.test/cms';
    }

    function pwa_start_assert(bool $condition, string $message): void
    {
        if (!$condition) {
            fwrite(STDERR, $message . PHP_EOL);
            exit(1);
        }
    }

    require_once dirname(__DIR__) . '/app/Services/PwaService.php';

    $service = new PwaService(new SiteSetting());

    pwa_start_assert(
        $service->normalizeStartPath('/chat?user_id=40&tab=files') === '/chat?user_id=40&tab=files',
        'A page path and its query parameters must be retained for PWA launch.'
    );
    pwa_start_assert(
        $service->normalizeStartPath('/cms/ru/posts/demo?preview=1') === '/ru/posts/demo?preview=1',
        'The configured application base path must not be duplicated in start_url.'
    );

    foreach (['https://evil.test/path', '//evil.test/path', '/../admin', '/%2e%2e/admin', '/path\\redirect'] as $unsafe) {
        pwa_start_assert(
            $service->normalizeStartPath($unsafe) === '/',
            'Unsafe PWA launch target was not rejected: ' . $unsafe
        );
    }

    $serviceSource = (string)file_get_contents(dirname(__DIR__) . '/app/Services/PwaService.php');
    $controllerSource = (string)file_get_contents(dirname(__DIR__) . '/app/Controllers/PwaController.php');
    pwa_start_assert(str_contains($serviceSource, "'id' => base_url('/')"), 'The dynamic manifest must keep one stable PWA identity.');
    pwa_start_assert(str_contains($serviceSource, "'start' => \$startPath"), 'The page-specific launch path is missing from the manifest URL.');
    pwa_start_assert(str_contains($controllerSource, "request()->get('start', '/')"), 'The manifest endpoint does not receive its requested launch path.');

    echo json_encode([
        'status' => 'ok',
        'current_page_start_url' => true,
        'same_origin_only' => true,
        'stable_pwa_id' => true,
    ], JSON_UNESCAPED_SLASHES), PHP_EOL;
}
