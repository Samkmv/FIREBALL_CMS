<?php
declare(strict_types=1);

// Standalone registry and real-template regression check: php tests/widget_catalog.php
require dirname(__DIR__) . '/vendor/autoload.php';
use App\Widgets\WidgetContext;
use App\Widgets\WidgetManager;
use App\Widgets\Types\HtmlWidget;

$translations = require dirname(__DIR__) . '/app/Languages/ru.php';
function return_translation(string $key): string { return $GLOBALS['translations'][$key] ?? $key; }
function htmlSC(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function base_href(string $path): string { return $path; }
function print_translation(string $key): string { return return_translation($key); }
function get_alerts(): void {}
function get_csrf_field(): string { return '<input type="hidden" name="needCSRFToken" value="fixture-token">'; }
function log_error_details(string $message, array $context = [], ?Throwable $exception = null): void { throw new RuntimeException($message, 0, $exception); }
function app(): object {
    return (object)['hooks' => new class {
        public function doActionSafely(string $hook, $types, $areas): void {
            $types->register('example.notice', new HtmlWidget(), [
                'label' => 'Объявление', 'description' => 'Важная информация для посетителей.',
                'category' => 'announcements', 'category_label' => 'Объявления',
                'icon' => 'ci-file-text', 'areas' => ['*'],
            ]);
            $types->register('example.legacy', new HtmlWidget(), ['label' => 'Дополнительный блок', 'areas' => ['posts.sidebar']]);
            $types->register('example.unavailable', new HtmlWidget(), [
                'label' => 'Виджет отдельной темы', 'areas' => ['example.missing'], 'icon' => 'ci-box" onclick="bad()',
            ]);
            $areas->register('example.footer', 'Подвал', ['example.page' => 'Страница']);
        }
    }];
}
function widgets(): WidgetManager { return $GLOBALS['manager']; }
function view(): object {
    return new class {
        public function renderPartial(string $name, array $data = []): string {
            if (in_array($name, ['admin/mobile_bottom_nav', 'admin/command_palette'], true)) return '';
            if ($name === 'admin/sidebar') return '<div class="fb-sidebar-inner"><div class="fb-sidebar-head"><a class="fb-brand" href="#"><span class="fb-brand-mark">🔥</span><span class="fb-brand-copy"><strong>FIREBALL</strong><span>CMS</span></span></a></div><nav class="fb-nav p-3"><p class="text-secondary small">ПАНЕЛЬ УПРАВЛЕНИЯ</p><a class="fb-nav-link" href="#"><i class="ci-grid"></i><span class="fb-nav-label">Обзор</span></a><p class="text-secondary small mt-4">КОНТЕНТ</p><a class="fb-nav-link" href="#"><i class="ci-file-text"></i><span class="fb-nav-label">Записи</span></a><a class="fb-nav-link" href="#"><i class="ci-folder"></i><span class="fb-nav-label">Категории</span></a><p class="text-secondary small mt-4">ВНЕШНИЙ ВИД</p><a class="fb-nav-link active" href="#"><i class="ci-grid"></i><span class="fb-nav-label">Виджеты</span></a><a class="fb-nav-link" href="#"><i class="ci-box"></i><span class="fb-nav-label">Плагины</span></a></nav></div>';
            if ($name === 'admin/topbar') return '<header class="fb-topbar"><div class="fb-topbar-left"><span class="d-lg-none">FIREBALL CMS</span></div><button class="fb-global-search fb-topbar-action"><i class="ci-search"></i><span>Поиск по всему CMS…</span><kbd>⌘K</kbd></button><div class="fb-topbar-actions"><i class="ci-moon"></i><span class="ms-3">admin</span></div></header>';
            extract($data, EXTR_SKIP);
            ob_start();
            require dirname(__DIR__) . '/app/Views/themes/default/' . $name . '.php';
            return (string)ob_get_clean();
        }
    };
}
function expect(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$manager = new WidgetManager();
$types = $manager->types;
expect(count($types->all()) === 9, 'Core and plugin widgets must appear automatically.');
expect($types->get('example.legacy')['category'] === 'custom', 'Legacy types need a default category.');
expect($types->get('example.legacy')['description'] === '', 'Description must remain optional.');
expect($types->get('example.unavailable')['icon'] === 'ci-box', 'Invalid icon classes must be rejected.');
expect($types->categories()['announcements'] === 'Объявления', 'Custom category labels must be included.');
try {
    $types->register('example.invalid', new HtmlWidget(), ['areas' => ['*'], 'category' => '<script>']);
    throw new RuntimeException('Invalid category accepted.');
} catch (InvalidArgumentException $expected) {}
expect($types->allows('weather', $manager->areas->get('example.footer')), 'Compatible custom areas must remain installable.');
expect(!$types->allows('posts.latest', $manager->areas->get('example.footer')), 'Area restrictions must be preserved.');
$instances = $manager->instances->fallback();
foreach ($instances as $i => &$instance) $instance['id'] = $i + 1;
unset($instance);
$instances[] = ['id' => 4, 'type' => 'html', 'area' => 'posts.sidebar', 'position' => 3, 'settings' => ['title' => '<script>alert("title")</script>', 'html' => '<b>Example</b>'], 'enabled' => false, 'visibility' => ['posts.index' => true, 'posts.show' => true], 'system_key' => null];
$context = new WidgetContext('admin', ['categories' => [['slug' => 'news', 'name' => 'Новости'], ['slug' => 'guides', 'name' => 'Инструкции']]]);
$snapshot = ['revision' => 10, 'instances' => $instances];
$editor = view()->renderPartial('admin/widgets_editor', ['snapshot' => $snapshot, 'widget_context' => $context]);
$dom = new DOMDocument();
@$dom->loadHTML('<?xml encoding="utf-8" ?>' . $editor);
$xpath = new DOMXPath($dom);
expect($xpath->query('//*[@data-widget-catalog-item]')->length === 9, 'Catalog must render all registered types.');
expect($xpath->query('//*[@data-widget-id]')->length === 4, 'Installed view must render instances only.');
expect($xpath->query('//details[@open]')->length === 0, 'Settings must start collapsed.');
expect(!str_contains($editor, '<script>alert'), 'Instance titles must be escaped.');
expect($xpath->query('//*[@data-widget-catalog-item][@data-widget-category-id="announcements"]')->length === 1, 'Custom category metadata must reach filters.');
expect($xpath->query('//form[@data-widget-add]')->length === 5, 'System and incompatible widgets must not have install actions.');
expect($xpath->query('//select[@name="area"]/option[@value="example.footer"]')->length === 3, 'Compatible areas must be selectable.');
expect(str_contains($editor, 'Добавить ещё'), 'Installed types must support independent additional instances.');
expect(str_contains($editor, 'Нет подходящей области'), 'Incompatible types need an explanatory state.');
foreach (['en', 'de', 'zh-cn'] as $locale) {
    $language = require dirname(__DIR__) . '/app/Languages/' . $locale . '.php';
    foreach (array_keys($translations) as $key) {
        if (str_starts_with($key, 'widgets_')) expect(isset($language[$key]), 'Missing translation: ' . $locale . ':' . $key);
    }
}
if (isset($GLOBALS['widget_fixture_output'])) {
    $instances[3]['settings']['title'] = 'О проекте';
    $snapshot['instances'] = $instances;
    $editor = view()->renderPartial('admin/widgets_editor', ['snapshot' => $snapshot, 'widget_context' => $context]);
    $page = view()->renderPartial('admin/widgets', ['snapshot' => $snapshot, 'widget_context' => $context, 'editor_error' => '']);
    $instances[] = ['id' => 5, 'type' => 'weather', 'area' => 'posts.sidebar', 'position' => 4, 'settings' => $types->get('weather')['widget']->defaults(), 'enabled' => true, 'visibility' => ['posts.index' => true, 'posts.show' => true], 'system_key' => null];
    $added = view()->renderPartial('admin/widgets_editor', ['snapshot' => ['revision' => 11, 'instances' => $instances], 'widget_context' => $context]);
    file_put_contents($GLOBALS['widget_fixture_output'], json_encode(['page' => $page, 'editor' => $editor, 'added' => $added], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
}
echo "Widget catalog registry and template checks passed.\n";
