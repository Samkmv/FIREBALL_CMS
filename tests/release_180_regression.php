<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Search/SearchText.php';
require_once dirname(__DIR__) . '/core/Plugins/HookManager.php';

use App\Search\SearchText;
use FBL\Plugins\HookManager;

function releaseAssert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "release_180_regression: {$message}\n");
        exit(1);
    }
}

$snippet = SearchText::plainText(json_encode([
    'html' => '<p>Readable camera description for visitors.</p>',
    'src' => 'https://private.example/live/master.m3u8?token=secret',
    'provider_payload' => 'internal-provider-data',
    'token' => '012345678901234567890123456789012345678901234567',
    'caption' => 'Public caption https://example.test/internal/key',
], JSON_UNESCAPED_SLASHES));
releaseAssert(str_contains($snippet, 'Readable camera description'), 'public prose is missing from the search snippet');
releaseAssert(str_contains($snippet, 'Public caption'), 'public caption is missing from the search snippet');
releaseAssert(!str_contains($snippet, 'private.example'), 'a media URL leaked into the search snippet');
releaseAssert(!str_contains($snippet, 'internal-provider-data'), 'provider data leaked into the search snippet');
releaseAssert(!str_contains($snippet, '0123456789'), 'a token leaked into the search snippet');
releaseAssert(str_ends_with(SearchText::excerpt(str_repeat('readable phrase ', 30)), '…'), 'long excerpts must end with an ellipsis');

$editorSnapshot = base64_encode((string)json_encode([
    'version' => 2,
    'blocks' => [['type' => 'video', 'data' => ['src' => '/uploads/private-video.mp4']]],
], JSON_UNESCAPED_SLASHES));
$editorHtml = '<div data-fb-block="text"><p>Подъезд № 4</p></div>'
    . '<template data-fb-editor-state="2">' . $editorSnapshot . '</template>';
$editorSnippet = SearchText::plainText($editorHtml);
releaseAssert($editorSnippet === 'Подъезд № 4', 'the hidden editor snapshot leaked into the search snippet');
$staleIndexedSnippet = SearchText::plainText('Подъезд № 4' . $editorSnapshot);
releaseAssert($staleIndexedSnippet === 'Подъезд № 4', 'an already indexed editor snapshot leaked into search results');

$hooks = new HookManager();
$hooks->addFilter('release_test', static fn(int $value): int => $value + 1);
$hooks->addFilter('release_test', static function (): never { throw new RuntimeException('plugin failure'); });
$hooks->addFilter('release_test', static fn(int $value): int => $value + 2);
releaseAssert($hooks->applyFiltersSafely('release_test', 0) === 3, 'a faulty plugin interrupted later dashboard widgets');

$root = dirname(__DIR__);
$read = static fn(string $path): string => (string)file_get_contents($root . '/' . $path);
$userModel = $read('app/Models/User.php');
$subscriptionPlugin = $read('plugins/subscriptions/Plugin.php');
$editor = $read('public/assets/default/js/editor2/editor.js');
$editorView = $read('app/Modules/BlockEditor/views/editor.php');
$dashboard = $read('app/Views/themes/default/admin/dashboard.php');
$tracker = $read('app/Components/AnalyticsTracker.php');
$fileManagerView = $read('app/Views/themes/default/admin/file_manager_browser.php');

releaseAssert(str_contains($userModel, "do_action('admin_user_deleting'"), 'user deletion lifecycle hook is missing');
releaseAssert(str_contains($subscriptionPlugin, "add_action('admin_user_deleting'"), 'subscription user cleanup is not registered');
releaseAssert(str_contains($subscriptionPlugin, "'video', (string)\$block['id']"), 'per-video access rules are not stored');
releaseAssert(str_contains($editorView, 'data-editor-delete-dialog') && str_contains($editor, 'data-editor-social-add'), 'editor modal or social settings are missing');
releaseAssert(str_contains($dashboard, 'array_slice($analyticsPages, 0, 15)') && str_contains($dashboard, 'array_slice($analyticsLatest, 0, 15)'), 'dashboard does not show 15 analytics rows');
releaseAssert(str_contains($tracker, 'yandex_metrika_enabled') && str_contains($tracker, "^/(?:admin|api|assets|uploads|install)"), 'Yandex Metrika public-only guard is missing');
releaseAssert(!str_contains($fileManagerView, 'data-file-manager-delete-selected'), 'the layout-shifting delete button still exists');

$en = require $root . '/plugins/subscriptions/lang/en.php';
foreach (['ru', 'de', 'zh-cn'] as $locale) {
    $translated = require $root . '/plugins/subscriptions/lang/' . $locale . '.php';
    releaseAssert(array_keys($translated) === array_keys($en), "subscription translations are incomplete for {$locale}");
}

echo json_encode([
    'status' => 'ok',
    'safe_search' => true,
    'safe_plugin_widgets' => true,
    'user_deletion' => true,
    'editor_social_and_modal' => true,
    'per_video_access' => true,
    'subscriptions_admin' => true,
    'yandex_metrika' => true,
], JSON_UNESCAPED_SLASHES) . PHP_EOL;
