<?php
$currentPath = current_path();
$navItems = [
    ['href' => base_href('/admin'), 'label' => return_translation('admin_nav_dashboard'), 'icon' => 'ci-layout'],
    ['href' => base_href('/admin/contact-requests'), 'label' => return_translation('admin_nav_contacts'), 'icon' => 'ci-mail'],
    ['href' => base_href('/admin/posts'), 'label' => return_translation('admin_nav_posts'), 'icon' => 'ci-file-text'],
    ['href' => base_href('/admin/categories'), 'label' => return_translation('admin_nav_categories'), 'icon' => 'ci-folder'],
    ['href' => base_href('/admin/users'), 'label' => return_translation('admin_nav_users'), 'icon' => 'ci-user'],
    ['href' => base_href('/admin/roles'), 'label' => return_translation('admin_nav_roles'), 'icon' => 'ci-shield'],
    ['href' => base_href('/admin/files'), 'label' => return_translation('admin_nav_files'), 'icon' => 'ci-folder-plus'],
    ['href' => base_href('/admin/updates'), 'label' => return_translation('admin_nav_updates'), 'icon' => 'ci-refresh-cw'],
    ['href' => base_href('/admin/settings'), 'label' => return_translation('admin_nav_settings'), 'icon' => 'ci-settings'],
];

$isActive = static function (string $href) use ($currentPath): bool {
    $routePath = parse_url($href, PHP_URL_PATH) ?: '/';
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
            <?php $active = $isActive($item['href']); ?>
            <a
                class="list-group-item list-group-item-action d-flex align-items-center gap-3 rounded-4 px-3 py-2 border-0 <?= $active ? 'active shadow-sm' : 'bg-transparent' ?>"
                href="<?= $item['href'] ?>"
            >
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle flex-shrink-0 admin-shell-nav-icon">
                    <i class="<?= htmlSC($item['icon']) ?>"></i>
                </span>
                <span class="fw-medium"><?= htmlSC($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>
