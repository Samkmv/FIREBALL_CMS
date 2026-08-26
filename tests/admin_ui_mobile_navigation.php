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
$adminNavigation = (string)file_get_contents($root . '/app/Views/themes/default/admin/nav.php');
$pluginUpdateService = (string)file_get_contents($root . '/app/Services/PluginUpdateService.php');
$pluginsPage = (string)file_get_contents($root . '/app/Views/themes/default/admin/plugins.php');
$mobileNavigation = (string)file_get_contents($root . '/app/Views/themes/default/admin/mobile_bottom_nav.php');
$commandPalette = (string)file_get_contents($root . '/app/Views/themes/default/admin/command_palette.php');
$styles = (string)file_get_contents($root . '/public/assets/default/css/admin-ui.css');
$blockEditorStyles = (string)file_get_contents($root . '/public/assets/default/css/block-editor.css');
$scripts = (string)file_get_contents($root . '/public/assets/default/js/admin-ui.js');
$mainScripts = (string)file_get_contents($root . '/public/assets/default/js/main.js');
$deleteModalScripts = (string)file_get_contents($root . '/public/assets/default/js/admin-delete-modal.js');
$defaultLayout = (string)file_get_contents($root . '/app/Views/layouts/default.php');
$filesPage = (string)file_get_contents($root . '/app/Views/themes/default/admin/files.php');
$themeHeader = (string)file_get_contents($root . '/themes/default/partials/header.php');
$pwaService = (string)file_get_contents($root . '/app/Services/PwaService.php');
$routes = (string)file_get_contents($root . '/config/routes.php');
$notificationController = (string)file_get_contents($root . '/app/Controllers/NotificationController.php');
$notificationCenter = (string)file_get_contents($root . '/app/Models/NotificationCenter.php');
$brandIconPath = $root . '/public/assets/default/icons/fireball-cms.svg';
$brandIcon = (string)file_get_contents($brandIconPath);
$adminBrandIconPath = $root . '/public/assets/default/icons/fireball-admin.svg';
$adminBrandIcon = (string)file_get_contents($adminBrandIconPath);

admin_ui_assert(str_contains($topbar, 'fb-language-switcher'), 'The admin language switcher is missing.');
admin_ui_assert(is_file($brandIconPath) && str_contains($brandIcon, '<svg') && str_contains($brandIcon, 'fireballSurface'), 'The FIREBALL vector brand icon is missing or invalid.');
admin_ui_assert(is_file($adminBrandIconPath) && str_contains($adminBrandIcon, '<svg') && str_contains($adminBrandIcon, 'adminFireballSurface') && str_contains($adminBrandIcon, 'adminFireballMark') && !str_contains($adminBrandIcon, '<linearGradient') && !str_contains($adminBrandIcon, '<filter'), 'The admin-only FIREBALL vector icon is missing, invalid, or still uses glossy effects.');
admin_ui_assert(str_contains($topbar, '/assets/default/icons/fireball-admin.svg') && str_contains($sidebar, '/assets/default/icons/fireball-admin.svg'), 'The admin shell does not use its dedicated FIREBALL vector icon.');
admin_ui_assert(!str_contains($topbar, '/assets/default/icons/fireball-cms.svg') && !str_contains($sidebar, '/assets/default/icons/fireball-cms.svg'), 'The admin shell still uses the public FIREBALL vector icon.');
admin_ui_assert(!str_contains($defaultLayout, '/assets/default/icons/fireball-admin.svg') && !str_contains($themeHeader, '/assets/default/icons/fireball-admin.svg'), 'The admin-only FIREBALL vector icon leaked into the public site.');
admin_ui_assert(str_contains($pwaService, "WWW . '/assets/default/icons/fireball-cms.svg'") && str_contains($pwaService, "'type' => \$useDefaultVector ? 'image/svg+xml' : 'image/png'"), 'The default FIREBALL SVG is not connected to the site favicon.');
admin_ui_assert(str_contains($topbar, 'ci-globe'), 'The admin language switcher has no globe icon.');
admin_ui_assert(str_contains($topbar, "return_translation('admin_ui_language')"), 'The language switcher label is not localized.');
admin_ui_assert(!str_contains($sidebar, "base_href('/chat')"), 'Chat must not remain in the admin sidebar.');
admin_ui_assert(str_contains($adminNavigation, 'getLastCheckPayload'), 'The CMS update indicator is not connected to the stored update state.');
admin_ui_assert(str_contains($adminNavigation, 'getStoredUpdateSummary'), 'The plugin update indicator is not connected to stored plugin states.');
admin_ui_assert(str_contains($adminNavigation, '$pluginUpdateCount = $pluginUpdatesAvailable;') && !str_contains($adminNavigation, '$pluginUpdatesAvailable + $pluginSourcesOlder'), 'The plugin menu badge must count only real available updates.');
admin_ui_assert(str_contains($adminNavigation, 'fb-nav-badge-update'), 'Update badges are missing from the admin navigation.');
admin_ui_assert(str_contains($pluginUpdateService, 'public function getStoredUpdateSummary'), 'Plugin updates have no network-free menu summary.');
admin_ui_assert(str_contains($styles, '.fb-nav-badge-update'), 'The admin update badge has no visual style.');
admin_ui_assert(str_contains($styles, '.fb-nav-badge-update {') && str_contains($styles, 'font-size: 0;'), 'Collapsed admin navigation does not retain an update dot.');
admin_ui_assert(str_contains($pluginsPage, 'fb-plugin-overview'), 'The plugins page has no compact update overview.');
admin_ui_assert(str_contains($pluginsPage, '<details class="fb-plugin-details"'), 'Long plugin information is not collapsible.');
admin_ui_assert(!str_contains($pluginsPage, 'card h-100 rounded-5'), 'Plugin cards still stretch to the tallest item.');
admin_ui_assert(str_contains($styles, '.fb-plugin-release-notes ul') && str_contains($styles, 'max-height: 230px;'), 'Long plugin release notes are not height-limited.');
admin_ui_assert(str_contains($styles, '.fb-plugin-card-description {') && str_contains($styles, 'overflow-wrap: anywhere;'), 'Plugin descriptions cannot grow to fit their text.');
admin_ui_assert(!str_contains($styles, '-webkit-line-clamp: 3;'), 'Plugin descriptions are still forcibly truncated.');
admin_ui_assert(str_contains($styles, '@container (max-width: 430px)') && str_contains($styles, 'container-type: inline-size;'), 'Plugin card actions do not adapt to narrow cards.');
admin_ui_assert(str_contains($pluginsPage, 'data-confirm-title=') && str_contains($pluginsPage, 'data-confirm-variant="warning"') && str_contains($pluginsPage, 'data-confirm-icon="ci-download"'), 'Plugin updates still use the destructive delete confirmation appearance.');
admin_ui_assert(str_contains($defaultLayout, 'data-admin-delete-modal-title') && str_contains($defaultLayout, 'data-admin-delete-modal-item-label') && str_contains($defaultLayout, 'data-admin-delete-modal-hint'), 'The shared confirmation modal has no dynamic content hooks.');
admin_ui_assert(str_contains($deleteModalScripts, "formValue(form, 'data-confirm-title'") && str_contains($deleteModalScripts, "applyVariant(variant, icon)"), 'The shared confirmation modal cannot switch from delete to update mode.');

$chatPosition = strpos($mobileNavigation, "base_href('/chat')");
$profilePosition = strpos($mobileNavigation, "base_href('/profile')");
admin_ui_assert($chatPosition !== false, 'Chat is missing from the mobile navigation.');
admin_ui_assert($profilePosition !== false && $chatPosition < $profilePosition, 'Chat must appear immediately before the mobile profile item.');
admin_ui_assert(!str_contains($mobileNavigation, 'data-fb-notifications-open'), 'Notifications must not be duplicated in the mobile bottom navigation.');
admin_ui_assert(str_contains($styles, 'grid-template-columns: repeat(5, minmax(0, 1fr));'), 'The mobile navigation does not provide five equal columns.');
admin_ui_assert(str_contains($styles, '.fb-mobile-nav-action i'), 'The highlighted mobile action button is missing.');
admin_ui_assert(str_contains($styles, 'background: linear-gradient(145deg, #ff7455 0%, var(--fb-color-primary) 72%);'), 'The mobile action button lost its highlighted background.');
admin_ui_assert(str_contains($styles, '.fb-topbar-action > i'), 'Topbar icons do not share a normalized size.');
admin_ui_assert(str_contains($topbar, 'fb-icon-button fb-topbar-action theme-icon-active'), 'The theme switcher is not available as a mobile topbar action.');
admin_ui_assert(str_contains($commandPalette, 'data-fb-command-close'), 'The admin command palette has no explicit close button.');
admin_ui_assert(str_contains($mobileNavigation, 'data-fb-command-mode="actions"'), 'The mobile action button does not open quick actions first.');
admin_ui_assert(str_contains($commandPalette, 'data-site-suggest-url'), 'The command palette is not connected to site-wide search.');
admin_ui_assert(str_contains($scripts, 'loadSiteCommands'), 'The command palette cannot load public site results.');
admin_ui_assert(str_contains($styles, 'html.pwa-standalone .fb-topbar'), 'The PWA admin topbar is not pinned below the safe area.');
admin_ui_assert(
    str_contains($styles, '--fb-modal-mobile-top: max(.75rem, calc(env(safe-area-inset-top, 0px) + .35rem));')
    && str_contains($styles, 'min-height: calc(100dvh - var(--fb-modal-mobile-top) - var(--fb-modal-mobile-bottom));')
    && str_contains($styles, 'max-height: calc(100dvh - var(--fb-modal-mobile-top) - var(--fb-modal-mobile-bottom));')
    && str_contains($styles, ".fb-admin-body .modal-body {\n        min-height: 0;\n        overflow-y: auto;")
    && str_contains($styles, 'border-radius: var(--fb-radius-xl) !important;'),
    'Mobile admin dialogs do not stay inside the display safe area.'
);
admin_ui_assert(
    str_contains($filesPage, 'max-height: calc(100dvh - var(--fb-modal-mobile-top, .75rem) - var(--fb-modal-mobile-bottom, .75rem));'),
    'The file preview modal overrides the shared mobile safe area.'
);
admin_ui_assert(
    str_contains($blockEditorStyles, '--fb-editor-modal-mobile-top: max(.75rem, calc(env(safe-area-inset-top, 0px) + .35rem));')
    && str_contains($blockEditorStyles, 'inset: var(--fb-editor-modal-mobile-top) .625rem var(--fb-editor-modal-mobile-bottom);')
    && str_contains($blockEditorStyles, 'max-height: calc(100dvh - var(--fb-editor-modal-mobile-top) - var(--fb-editor-modal-mobile-bottom));'),
    'Native block editor dialogs do not stay inside the mobile display safe area.'
);
admin_ui_assert(str_contains($scripts, 'const isChat ='), 'The mobile chat item cannot receive its active state.');
admin_ui_assert(str_contains($topbar, 'data-notifications-clear') && str_contains($topbar, "notification_clear_all"), 'The notification center has no clear action.');
admin_ui_assert(str_contains($routes, "post('/notifications/clear'") && str_contains($notificationController, 'clearForUser'), 'The notification clear endpoint is not connected.');
admin_ui_assert(str_contains($notificationCenter, 'markAllAsReadForUser') && str_contains($notificationCenter, 'markAllViewed'), 'Notification clearing must preserve chats and requests by marking them viewed.');
admin_ui_assert(str_contains($mainScripts, "notificationCenter.data('clear-url')") && str_contains($mainScripts, 'data-notifications-clear'), 'The notification clear action has no client handler.');

foreach (['ru', 'en', 'de', 'zh-cn'] as $locale) {
    $translations = (string)file_get_contents($root . '/app/Languages/' . $locale . '.php');
    admin_ui_assert(str_contains($translations, "'admin_ui_language' =>"), 'Missing admin language label for ' . $locale . '.');
    admin_ui_assert(str_contains($translations, "'notification_clear_all' =>"), 'Missing notification clear label for ' . $locale . '.');
    admin_ui_assert(str_contains($translations, "'admin_nav_core_update_available' =>"), 'Missing CMS update menu label for ' . $locale . '.');
    admin_ui_assert(str_contains($translations, "'admin_nav_plugin_updates_available' =>"), 'Missing plugin update menu label for ' . $locale . '.');
    admin_ui_assert(str_contains($translations, "'admin_nav_plugin_sources_older' =>") && str_contains($translations, "'admin_nav_plugin_updates_attention' =>"), 'Missing plugin update attention labels for ' . $locale . '.');
    admin_ui_assert(str_contains($translations, "'admin_plugins_details' =>"), 'Missing plugin details label for ' . $locale . '.');
    admin_ui_assert(str_contains($translations, "'admin_plugin_updates_modal_title' =>") && str_contains($translations, "'admin_plugin_updates_modal_hint' =>"), 'Missing plugin update confirmation labels for ' . $locale . '.');
}

echo json_encode([
    'status' => 'ok',
    'language_switcher' => true,
    'chat_mobile_navigation' => true,
    'notifications_topbar_only' => true,
    'notifications_clear' => true,
    'update_menu_badges' => true,
    'compact_plugin_cards' => true,
    'mobile_theme_switcher' => true,
    'command_close_button' => true,
    'quick_actions_mode' => true,
    'site_wide_search' => true,
    'pwa_pinned_topbar' => true,
    'normalized_icons' => true,
    'highlighted_action_button' => true,
    'translations' => ['ru', 'en', 'de', 'zh-cn'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
