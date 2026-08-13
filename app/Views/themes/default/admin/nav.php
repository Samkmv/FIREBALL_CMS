<?php
$normalizeAdminPath = static function (string $path): string {
    $normalizedPath = parse_url($path, PHP_URL_PATH) ?: $path;
    $normalizedPath = '/' . ltrim((string)$normalizedPath, '/');
    $segments = array_values(array_filter(explode('/', ltrim($normalizedPath, '/')), static fn($segment): bool => $segment !== ''));

    if (isset($segments[0]) && array_key_exists($segments[0], LANGS)) {
        array_shift($segments);
    }

    return rtrim('/' . implode('/', $segments), '/') ?: '/';
};

$currentPath = $normalizeAdminPath(current_path());
$supportNewCount = 0;
try {
    $supportNewCount = (new \App\Models\ContactRequest())->countNew();
} catch (\Throwable) {
    $supportNewCount = 0;
}

$coreUpdateAvailable = false;
if (check_creator()) {
    try {
        $coreUpdateState = (new \App\Services\UpdateCenter())->getLastCheckPayload();
        $coreUpdateAvailable = is_array($coreUpdateState)
            && ($coreUpdateState['status'] ?? '') === 'ok'
            && !empty($coreUpdateState['update_available']);
    } catch (\Throwable) {
        $coreUpdateAvailable = false;
    }
}

$pluginUpdateCount = 0;
$pluginUpdatesAvailable = 0;
try {
    $pluginUpdateSummary = (new \App\Services\PluginUpdateService())->getStoredUpdateSummary();
    $pluginUpdatesAvailable = max(0, (int)($pluginUpdateSummary['available'] ?? 0));
    $pluginUpdateCount = $pluginUpdatesAvailable;
} catch (\Throwable) {
    $pluginUpdateCount = 0;
}

$pluginUpdateBadgeTitle = str_replace(
    ':count',
    (string)$pluginUpdateCount,
    return_translation('admin_nav_plugin_updates_available')
);

$menuGroups = [
    'dashboard' => [
        'label' => return_translation('admin_nav_group_dashboard'),
        'order' => 10,
        'items' => [
            ['href' => base_href('/admin'), 'label' => return_translation('admin_nav_dashboard'), 'icon' => 'ci-home', 'order' => 10],
            ['href' => base_href('/admin/analytics'), 'label' => return_translation('admin_nav_analytics'), 'icon' => 'ci-activity', 'order' => 20],
        ],
    ],
    'content' => [
        'label' => return_translation('admin_nav_group_content'),
        'order' => 20,
        'items' => [
            ['href' => base_href('/admin/posts'), 'label' => return_translation('admin_nav_posts'), 'icon' => 'ci-file-text', 'order' => 10],
            ['href' => base_href('/admin/pages'), 'label' => return_translation('admin_nav_pages'), 'icon' => 'ci-file', 'order' => 20],
            ['href' => base_href('/admin/categories'), 'label' => return_translation('admin_nav_categories'), 'icon' => 'ci-folder', 'order' => 30],
            ['href' => base_href('/admin/files'), 'label' => return_translation('admin_nav_files'), 'icon' => 'ci-hard-drive', 'order' => 40],
            [
                'href' => base_href('/admin/support'),
                'label' => return_translation('admin_nav_support'),
                'icon' => 'ci-inbox',
                'badge' => $supportNewCount > 0 ? (string)$supportNewCount : '',
                'badge_class' => 'fb-nav-badge fb-nav-badge-warning',
                'badge_title' => return_translation('admin_support_new_count'),
                'order' => 50,
            ],
        ],
    ],
    'users' => [
        'label' => return_translation('admin_nav_group_users'),
        'order' => 30,
        'items' => [
            ['href' => base_href('/admin/users'), 'label' => return_translation('admin_nav_users'), 'icon' => 'ci-user', 'order' => 10],
            ['href' => base_href('/admin/roles'), 'label' => return_translation('admin_nav_roles'), 'icon' => 'ci-shield', 'order' => 20],
        ],
    ],
    'appearance' => [
        'label' => return_translation('admin_nav_group_appearance'),
        'order' => 40,
        'items' => [
            ['href' => base_href('/admin/themes'), 'label' => return_translation('admin_nav_themes'), 'icon' => 'ci-monitor', 'order' => 10],
            [
                'href' => base_href('/admin/plugins'),
                'label' => return_translation('admin_nav_plugins'),
                'icon' => 'ci-box',
                'nav_key' => 'plugins',
                'badge' => $pluginUpdateCount > 0 ? (string)$pluginUpdateCount : '',
                'badge_class' => 'fb-nav-badge fb-nav-badge-update',
                'badge_title' => $pluginUpdateBadgeTitle,
                'order' => 20,
            ],
        ],
    ],
    'applications' => [
        'label' => return_translation('admin_nav_group_applications'),
        'order' => 50,
        'items' => [],
    ],
    'system' => [
        'label' => return_translation('admin_nav_group_system'),
        'order' => 60,
        'items' => [
            [
                'href' => base_href('/admin/updates'),
                'label' => return_translation('admin_nav_updates'),
                'icon' => 'ci-refresh-cw',
                'creator_only' => true,
                'badge' => $coreUpdateAvailable ? '1' : '',
                'badge_class' => 'fb-nav-badge fb-nav-badge-update',
                'badge_title' => return_translation('admin_nav_core_update_available'),
                'order' => 10,
            ],
            ['href' => base_href('/admin/settings'), 'label' => return_translation('admin_nav_settings'), 'icon' => 'ci-settings', 'order' => 20],
            ['href' => base_href('/admin/security/logs'), 'label' => return_translation('admin_nav_security_logs'), 'icon' => 'ci-shield', 'order' => 30],
            ['href' => base_href('/admin/system/database-maintenance'), 'label' => return_translation('admin_nav_database_maintenance'), 'icon' => 'ci-database', 'creator_only' => true, 'order' => 40],
        ],
    ],
    'help' => [
        'label' => return_translation('admin_nav_group_help'),
        'order' => 70,
        'items' => [
            ['href' => base_href('/admin/docs'), 'label' => return_translation('admin_nav_docs'), 'icon' => 'ci-book-open', 'order' => 10],
        ],
    ],
];

$navItems = [];
foreach ($menuGroups as $groupKey => $group) {
    foreach ((array)($group['items'] ?? []) as $item) {
        $item['group'] = $item['group'] ?? $groupKey;
        $navItems[] = $item;
    }
    unset($menuGroups[$groupKey]['items']);
}

$menuGroups = apply_filters('admin_menu_groups', $menuGroups);
$navItems = apply_filters('admin_menu', $navItems);
$navItems = array_merge($navItems, \FBL\Menu::adminItems());

$normalizeItem = static function (array $item): array {
    if (empty($item['group'])) {
        $item['group'] = !empty($item['plugin_menu']) ? 'applications' : 'system';
    }

    $item['href'] = (string)($item['href'] ?? $item['url'] ?? '#');
    $item['label'] = (string)($item['label'] ?? $item['title'] ?? '');
    $item['icon'] = (string)($item['icon'] ?? 'ci-box');
    $item['order'] = (int)($item['order'] ?? 100);
    $item['children'] = array_values(array_filter((array)($item['children'] ?? []), 'is_array'));

    return $item;
};

$groupedItems = [];
foreach ($navItems as $item) {
    if (!is_array($item)) {
        continue;
    }

    $item = $normalizeItem($item);
    $group = (string)$item['group'];
    if (!isset($menuGroups[$group])) {
        $menuGroups[$group] = [
            'label' => (string)($item['group_label'] ?? ucwords(str_replace(['-', '_'], ' ', $group))),
            'order' => 100,
        ];
    }

    if (!empty($item['creator_only']) && !check_creator()) {
        continue;
    }

    $groupedItems[$group][] = $item;
}

uasort($menuGroups, static fn(array $a, array $b): int => ((int)($a['order'] ?? 100)) <=> ((int)($b['order'] ?? 100)));
foreach ($groupedItems as &$items) {
    usort($items, static fn(array $a, array $b): int => ((int)($a['order'] ?? 100)) <=> ((int)($b['order'] ?? 100)));
}
unset($items);

$isActive = static function (array $item) use ($currentPath, $normalizeAdminPath): bool {
    $href = (string)($item['href'] ?? '#');
    $routePath = $normalizeAdminPath((string)(parse_url($href, PHP_URL_PATH) ?: '/'));
    $navKey = (string)($item['nav_key'] ?? '');

    if ($routePath === '/admin/support' && $currentPath === '/admin/contact-requests') {
        return true;
    }
    if ($routePath === '/admin') {
        return $currentPath === '/admin';
    }
    if ($navKey === 'plugins' || $routePath === '/admin/plugins') {
        return $currentPath === '/admin/plugins';
    }

    return $routePath !== '/' && ($currentPath === $routePath || str_starts_with($currentPath, rtrim($routePath, '/') . '/'));
};

static $adminNavRenderIndex = 0;
$adminNavRenderIndex++;
$instanceId = 'fb-nav-' . $adminNavRenderIndex;
$variant = (string)($variant ?? 'desktop');

$renderLink = static function (array $item, string $groupLabel, bool $nested = false) use ($isActive): void {
    $itemHref = (string)$item['href'];
    $itemLabel = (string)$item['label'];
    $icon = (string)$item['icon'];
    if (!str_starts_with($icon, 'ci-')) {
        $icon = 'ci-' . $icon;
    }
    $active = $isActive($item);
    ?>
    <a
        class="fb-nav-link<?= $nested ? ' fb-nav-link-nested' : '' ?><?= $active ? ' active' : '' ?>"
        href="<?= htmlSC($itemHref) ?>"
        title="<?= htmlSC($itemLabel) ?>"
        <?= $active ? 'aria-current="page"' : '' ?>
        data-fb-command
        data-fb-command-label="<?= htmlSC($itemLabel) ?>"
        data-fb-command-category="<?= htmlSC($groupLabel) ?>"
        data-fb-command-icon="<?= htmlSC($icon) ?>"
        data-fb-command-kind="navigation"
    >
        <span class="fb-nav-icon"><i class="<?= htmlSC($icon) ?>" aria-hidden="true"></i></span>
        <span class="fb-nav-label"><?= htmlSC($itemLabel) ?></span>
        <?php if (!empty($item['badge'])): ?>
            <span
                class="<?= htmlSC((string)($item['badge_class'] ?? 'fb-nav-badge')) ?>"
                title="<?= htmlSC((string)($item['badge_title'] ?? '')) ?>"
                aria-label="<?= htmlSC((string)($item['badge_title'] ?? '')) ?>"
            >
                <?= htmlSC((string)$item['badge']) ?>
            </span>
        <?php endif; ?>
    </a>
    <?php
};
?>

<nav class="fb-nav" data-admin-nav data-fb-nav-variant="<?= htmlSC($variant) ?>">
    <?php foreach ($menuGroups as $groupKey => $group): ?>
        <?php if (empty($groupedItems[$groupKey])) { continue; } ?>
        <?php $groupLabel = (string)($group['label'] ?? $groupKey); ?>
        <section class="fb-nav-group" aria-labelledby="<?= htmlSC($instanceId . '-' . $groupKey) ?>">
            <h2 class="fb-nav-group-title" id="<?= htmlSC($instanceId . '-' . $groupKey) ?>"><?= htmlSC($groupLabel) ?></h2>
            <div class="fb-nav-list">
                <?php foreach ($groupedItems[$groupKey] as $itemIndex => $item): ?>
                    <?php
                    $children = (array)($item['children'] ?? []);
                    if ($children === []) {
                        $renderLink($item, $groupLabel);
                        continue;
                    }

                    $childItems = array_map($normalizeItem, $children);
                    $childActive = false;
                    foreach ($childItems as $childItem) {
                        if ($isActive($childItem)) {
                            $childActive = true;
                            break;
                        }
                    }
                    $submenuId = $instanceId . '-' . preg_replace('/[^a-z0-9_-]+/i', '-', (string)$groupKey) . '-' . $itemIndex;
                    ?>
                    <div class="fb-nav-parent<?= $childActive ? ' is-open' : '' ?>" data-fb-nav-parent>
                        <button
                            class="fb-nav-link fb-nav-toggle<?= $childActive ? ' active' : '' ?>"
                            type="button"
                            data-fb-nav-toggle
                            aria-expanded="<?= $childActive ? 'true' : 'false' ?>"
                            aria-controls="<?= htmlSC($submenuId) ?>"
                            title="<?= htmlSC((string)$item['label']) ?>"
                        >
                            <span class="fb-nav-icon"><i class="<?= htmlSC(str_starts_with((string)$item['icon'], 'ci-') ? (string)$item['icon'] : 'ci-' . (string)$item['icon']) ?>" aria-hidden="true"></i></span>
                            <span class="fb-nav-label"><?= htmlSC((string)$item['label']) ?></span>
                            <i class="ci-chevron-down fb-nav-chevron" aria-hidden="true"></i>
                        </button>
                        <div class="fb-nav-submenu" id="<?= htmlSC($submenuId) ?>">
                            <?php foreach ($childItems as $childItem): ?>
                                <?php $renderLink($childItem, $groupLabel, true); ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
</nav>
