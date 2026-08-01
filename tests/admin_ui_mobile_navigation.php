<?php

declare(strict_types=1);

$root = dirname(__DIR__);

function admin_ui_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$topbar = (string)file_get_contents($root . '/app/Views/themes/default/admin/topbar.php');
$sidebar = (string)file_get_contents($root . '/app/Views/themes/default/admin/sidebar.php');
$mobileNavigation = (string)file_get_contents($root . '/app/Views/themes/default/admin/mobile_bottom_nav.php');
$commandPalette = (string)file_get_contents($root . '/app/Views/themes/default/admin/command_palette.php');
$styles = (string)file_get_contents($root . '/public/assets/default/css/admin-ui.css');
$scripts = (string)file_get_contents($root . '/public/assets/default/js/admin-ui.js');

admin_ui_assert(str_contains($topbar, 'fb-language-switcher'), 'The admin language switcher is missing.');
admin_ui_assert(str_contains($topbar, 'ci-globe'), 'The admin language switcher has no globe icon.');
admin_ui_assert(str_contains($topbar, "return_translation('admin_ui_language')"), 'The language switcher label is not localized.');
admin_ui_assert(!str_contains($sidebar, "base_href('/chat')"), 'Chat must not remain in the admin sidebar.');

$chatPosition = strpos($mobileNavigation, "base_href('/chat')");
$profilePosition = strpos($mobileNavigation, "base_href('/profile')");
admin_ui_assert($chatPosition !== false, 'Chat is missing from the mobile navigation.');
admin_ui_assert($profilePosition !== false && $chatPosition < $profilePosition, 'Chat must appear immediately before the mobile profile item.');
admin_ui_assert(!str_contains($mobileNavigation, 'data-fb-notifications-open'), 'Notifications must not be duplicated in the mobile bottom navigation.');
admin_ui_assert(str_contains($styles, 'grid-template-columns: repeat(5, minmax(0, 1fr));'), 'The mobile navigation does not provide five equal columns.');
admin_ui_assert(str_contains($styles, '.fb-topbar-action > i'), 'Topbar icons do not share a normalized size.');
admin_ui_assert(str_contains($topbar, 'fb-icon-button fb-topbar-action theme-icon-active'), 'The theme switcher is not available as a mobile topbar action.');
admin_ui_assert(str_contains($commandPalette, 'data-fb-command-close'), 'The admin command palette has no explicit close button.');
admin_ui_assert(str_contains($scripts, 'const isChat ='), 'The mobile chat item cannot receive its active state.');

foreach (['ru', 'en', 'de', 'zh-cn'] as $locale) {
    $translations = (string)file_get_contents($root . '/app/Languages/' . $locale . '.php');
    admin_ui_assert(str_contains($translations, "'admin_ui_language' =>"), 'Missing admin language label for ' . $locale . '.');
}

echo json_encode([
    'status' => 'ok',
    'language_switcher' => true,
    'chat_mobile_navigation' => true,
    'notifications_topbar_only' => true,
    'mobile_theme_switcher' => true,
    'command_close_button' => true,
    'normalized_icons' => true,
    'translations' => ['ru', 'en', 'de', 'zh-cn'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
