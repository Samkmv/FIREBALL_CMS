<?php
$normalizeAdminPath = static function (string $path): string {
    $normalizedPath = parse_url($path, PHP_URL_PATH) ?: $path;
    $normalizedPath = '/' . ltrim((string)$normalizedPath, '/');

    if ($normalizedPath === '//') {
        $normalizedPath = '/';
    }

    $segments = array_values(array_filter(explode('/', ltrim($normalizedPath, '/')), static function ($segment) {
        return $segment !== '';
    }));

    if (isset($segments[0]) && array_key_exists($segments[0], LANGS)) {
        array_shift($segments);
    }

    $result = '/' . implode('/', $segments);

    return $result === '//' ? '/' : (rtrim($result, '/') ?: '/');
};

$currentPath = $normalizeAdminPath(current_path());
$supportNewCount = 0;
try {
    $supportNewCount = (new \App\Models\ContactRequest())->countNew();
} catch (\Throwable $e) {
    $supportNewCount = 0;
}

$menuGroups = [
    'dashboard' => [
        'label' => return_translation('admin_nav_group_dashboard'),
        'order' => 10,
        'items' => [
            ['href' => base_href('/admin'), 'label' => return_translation('admin_nav_dashboard'), 'icon' => 'ci-layout', 'order' => 10],
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
            ['href' => base_href('/admin/files'), 'label' => return_translation('admin_nav_files'), 'icon' => 'ci-folder-plus', 'order' => 40],
            [
                'href' => base_href('/admin/support'),
                'label' => return_translation('admin_nav_support'),
                'icon' => 'ci-inbox',
                'badge' => $supportNewCount > 0 ? (string)$supportNewCount : '',
                'badge_class' => 'badge rounded-pill text-bg-warning',
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
            ['href' => base_href('/admin/themes'), 'label' => return_translation('admin_nav_themes'), 'icon' => 'ci-monitor', 'badge' => 'Beta', 'order' => 10],
            ['href' => base_href('/admin/plugins'), 'label' => return_translation('admin_nav_plugins'), 'icon' => 'ci-box', 'nav_key' => 'plugins', 'order' => 20],
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
            ['href' => base_href('/admin/updates'), 'label' => return_translation('admin_nav_updates'), 'icon' => 'ci-refresh-cw', 'creator_only' => true, 'order' => 10],
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

$isActive = static function (string $href) use ($currentPath, $normalizeAdminPath): bool {
    $routePath = $normalizeAdminPath((string)(parse_url($href, PHP_URL_PATH) ?: '/'));

    if ($routePath === '/admin/support' && $currentPath === '/admin/contact-requests') {
        return true;
    }

    if ($routePath === '/admin') {
        return $currentPath === '/admin';
    }

    return $currentPath === $routePath || str_starts_with($currentPath, rtrim($routePath, '/') . '/');
};
?>

<div class="border rounded-5 p-3 p-xl-4 admin-shell-nav" data-admin-nav>
    <div class="d-flex align-items-center justify-content-between gap-2 px-2 mb-3">
        <div>
            <div class="fw-bold"><?= print_translation('admin_dashboard_heading') ?></div>
        </div>
    </div>

    <div class="admin-shell-nav-groups">
        <?php foreach ($menuGroups as $groupKey => $group): ?>
            <?php if (empty($groupedItems[$groupKey])) { continue; } ?>
            <div class="admin-shell-nav-group" data-admin-nav-group="<?= htmlSC((string)$groupKey) ?>">
                <div class="admin-shell-nav-group-title"><?= htmlSC((string)($group['label'] ?? $groupKey)) ?></div>
                <div class="list-group list-group-flush gap-1">
                    <?php foreach ($groupedItems[$groupKey] as $item): ?>
                        <?php
                        $itemHref = (string)$item['href'];
                        $itemLabel = (string)$item['label'];
                        $icon = (string)$item['icon'];
                        if (!str_starts_with($icon, 'ci-')) {
                            $icon = 'ci-' . $icon;
                        }
                        $active = $isActive($itemHref);
                        ?>
                        <a
                            class="list-group-item list-group-item-action d-flex align-items-center gap-3 rounded-4 px-3 py-2 border-0 <?= $active ? 'active shadow-sm' : 'bg-transparent' ?>"
                            href="<?= htmlSC($itemHref) ?>"
                        >
                            <span class="d-inline-flex align-items-center justify-content-center rounded-circle flex-shrink-0 admin-shell-nav-icon">
                                <i class="<?= htmlSC($icon) ?>"></i>
                            </span>
                            <span class="fw-medium d-inline-flex align-items-center gap-2 min-w-0 admin-shell-nav-label">
                                <span class="text-truncate"><?= htmlSC($itemLabel) ?></span>
                                <?php if (!empty($item['badge'])): ?>
                                    <span class="<?= htmlSC((string)($item['badge_class'] ?? 'admin-shell-beta-badge')) ?>" title="<?= htmlSC((string)($item['badge_title'] ?? '')) ?>"><?= htmlSC($item['badge']) ?></span>
                                <?php endif; ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
