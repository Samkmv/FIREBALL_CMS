<?php
// Exercise the real renderer/sanitizer without starting the CMS or accessing its database.
namespace FBL {
    final class Language {
        public static function get($key): string {
            static $translations;
            $translations ??= require __DIR__ . '/../app/Languages/ru.php';
            return $translations[$key] ?? $key;
        }
    }
}
namespace App\Modules\BlockEditor {
    function apply_filters(string $hook, mixed $value, mixed ...$args): mixed { return $value; }
}
namespace {
    require __DIR__ . '/../helpers/helpers.php';
    require __DIR__ . '/../app/Models/FileManager.php';
    require __DIR__ . '/../app/Modules/BlockEditor/BlockRenderer.php';
    require __DIR__ . '/../app/Modules/BlockEditor/BlockEditorService.php';
    function check(bool $condition, string $message): void {
        if (!$condition) throw new \RuntimeException($message);
    }
    $block = ['id' => 'files', 'type' => 'downloads', 'data' => [
        'title' => 'Полезные файлы',
        'description' => 'Скачайте необходимые материалы по проекту.',
        'items' => [
            ['url' => '/uploads/posts/downloads/company.pdf', 'name' => 'Презентация компании.pdf', 'size' => 2516582],
            ['url' => '/uploads/files/offer.docx', 'name' => 'Коммерческое предложение.docx', 'size' => 1153434],
            ['url' => '/uploads/files/prices.xlsx', 'name' => 'Прайс-лист.xlsx', 'size' => 364544],
        ],
    ]];
    $renderer = new \App\Modules\BlockEditor\BlockRenderer();
    $html = $renderer->renderPublicContent(json_encode(['version' => 2, 'blocks' => [$block]], JSON_UNESCAPED_UNICODE));
    if (in_array('--render', $argv, true)) {
        echo '<!doctype html><html lang="ru" data-bs-theme="light"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/assets/default/css/theme.min.css"><link rel="stylesheet" href="/assets/default/css/style.css"><link rel="stylesheet" href="/assets/default/icons/cartzilla-icons.min.css"></head><body><main class="container py-4" style="max-width:900px">' . $html . '</main></body></html>';
        exit;
    }
    check(substr_count($html, ' download=') === 3, 'All files retain download attributes after public sanitization');
    check(str_contains($html, '2.4 MB') && str_contains($html, '356 KB'), 'File sizes are readable');
    check(str_contains($html, 'Скачать'), 'Public button is localized');
    $snapshot = '<template data-fb-editor-state="2">' . base64_encode(json_encode(['version' => 2, 'blocks' => [$block]])) . '</template>';
    check($renderer->renderPublicContent($snapshot) === $html, 'Saved editor snapshot and JSON render identically');
    $block['data']['showIcon'] = false;
    $block['data']['showSize'] = false;
    $hidden = $renderer->renderBlock($block);
    check(!str_contains($hidden, 'fb-downloads__icon') && !str_contains($hidden, '2.4 MB'), 'Display toggles are respected');
    $block['data']['items'][] = ['url' => 'javascript:alert(1).pdf', 'name' => 'unsafe'];
    $block['data']['items'][] = ['url' => '/uploads/unsafe.html', 'name' => 'unsafe'];
    $block['data']['items'][] = ['url' => 'mailto:unsafe.pdf', 'name' => 'unsafe'];
    $block['data']['title'] = '<script>alert(1)</script>';
    $block['data']['items'][0]['name'] = '"><img src=x onerror=alert(1)>.pdf';
    $safe = $renderer->renderPublicContent(json_encode(['blocks' => [$block]]));
    check(!str_contains($safe, 'javascript:') && !str_contains($safe, 'unsafe.html') && !str_contains($safe, 'mailto:'), 'Unsafe file URLs are rejected');
    check(!str_contains($safe, '<script>') && !str_contains($safe, '<img '), 'Titles and names cannot inject markup');
    $block['data']['items'] = [];
    check($renderer->renderBlock($block) === '', 'Empty download block is not published');
    $service = new \App\Modules\BlockEditor\BlockEditorService();
    check($service->isAllowedBlockType('downloads'), 'New type is accepted by save validation');
    check($service->validateContentJson('{"blocks":[{"type":"downloads"}]}'), 'Downloads save alongside existing block types');
    foreach (['ru', 'en', 'de', 'zh-cn'] as $language) {
        $translations = require __DIR__ . '/../app/Languages/' . $language . '.php';
        foreach (['block_downloads', 'downloads_title', 'downloads_description', 'downloads_show_icon', 'downloads_show_size', 'downloads_drop_hint', 'downloads_upload', 'downloads_uploading', 'downloads_upload_failed', 'downloads_download'] as $key) {
            check(!empty($translations['editor_' . $key]), 'Missing translation: ' . $language . '/' . $key);
        }
    }
    echo "PASS downloads: public rendering, snapshots, settings, safety, save validation and four languages\n";
}
