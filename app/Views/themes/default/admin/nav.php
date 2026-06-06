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
$navItems = [
    ['href' => base_href('/admin'), 'label' => return_translation('admin_nav_dashboard'), 'icon' => 'ci-layout'],
    ['href' => base_href('/admin/analytics'), 'label' => return_translation('admin_nav_analytics'), 'icon' => 'ci-activity'],
    ['href' => base_href('/admin/contact-requests'), 'label' => return_translation('admin_nav_contacts'), 'icon' => 'ci-mail'],
    ['href' => base_href('/admin/posts'), 'label' => return_translation('admin_nav_posts'), 'icon' => 'ci-file-text'],
    ['href' => base_href('/admin/pages'), 'label' => return_translation('admin_nav_pages'), 'icon' => 'ci-file'],
    ['href' => base_href('/admin/categories'), 'label' => return_translation('admin_nav_categories'), 'icon' => 'ci-folder'],
    ['href' => base_href('/admin/users'), 'label' => return_translation('admin_nav_users'), 'icon' => 'ci-user'],
    ['href' => base_href('/admin/roles'), 'label' => return_translation('admin_nav_roles'), 'icon' => 'ci-shield'],
    ['href' => base_href('/admin/files'), 'label' => return_translation('admin_nav_files'), 'icon' => 'ci-folder-plus'],
    ['href' => base_href('/admin/themes'), 'label' => return_translation('admin_nav_themes'), 'icon' => 'ci-monitor', 'badge' => 'Beta'],
    ['href' => base_href('/admin/theme-editor/default'), 'label' => return_translation('admin_nav_theme_editor'), 'icon' => 'ci-code', 'child' => true],
    ['href' => base_href('/admin/updates'), 'label' => return_translation('admin_nav_updates'), 'icon' => 'ci-refresh-cw'],
    ['href' => base_href('/admin/settings'), 'label' => return_translation('admin_nav_settings'), 'icon' => 'ci-settings'],
    ['href' => base_href('/admin/system/database-maintenance'), 'label' => return_translation('admin_nav_database_maintenance'), 'icon' => 'ci-database', 'creator_only' => true],
    ['href' => base_href('/admin/docs/themes'), 'label' => return_translation('admin_nav_docs'), 'icon' => 'ci-book-open'],
];

$isActive = static function (string $href) use ($currentPath, $normalizeAdminPath): bool {
    $routePath = $normalizeAdminPath((string)(parse_url($href, PHP_URL_PATH) ?: '/'));

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

    <div class="list-group list-group-flush gap-1">
        <?php foreach ($navItems as $item): ?>
            <?php if (!empty($item['creator_only']) && !check_creator()) { continue; } ?>
            <?php $active = $isActive($item['href']); ?>
            <a
                class="list-group-item list-group-item-action d-flex align-items-center gap-3 rounded-4 px-3 py-2 border-0 <?= $active ? 'active shadow-sm' : 'bg-transparent' ?> <?= !empty($item['child']) ? 'admin-shell-nav-child' : '' ?>"
                href="<?= $item['href'] ?>"
            >
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle flex-shrink-0 admin-shell-nav-icon">
                    <i class="<?= htmlSC($item['icon']) ?>"></i>
                </span>
                <span class="fw-medium d-inline-flex align-items-center gap-2 min-w-0 admin-shell-nav-label">
                    <span class="text-truncate"><?= htmlSC($item['label']) ?></span>
                    <?php if (!empty($item['badge'])): ?>
                        <span class="admin-shell-beta-badge" title="<?= htmlSC(return_translation('admin_nav_themes_beta_hint')) ?>"><?= htmlSC($item['badge']) ?></span>
                    <?php endif; ?>
                </span>
            </a>
        <?php endforeach; ?>
    </div>
</div>
