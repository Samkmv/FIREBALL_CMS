<?php
$activeSettingsTab = (string)($active ?? 'general');
$settingsTabs = [
    [
        'key' => 'general',
        'href' => base_href('/admin/settings'),
        'label' => return_translation('admin_settings_tab_general'),
        'icon' => 'ci-settings',
    ],
    [
        'key' => 'privacy',
        'href' => base_href('/admin/settings/privacy'),
        'label' => return_translation('admin_settings_tab_privacy'),
        'icon' => 'ci-shield',
    ],
];
?>

<nav class="nav nav-pills flex-nowrap gap-2 overflow-x-auto mb-4 pb-1" aria-label="<?= htmlSC(return_translation('admin_settings_tabs_label')) ?>">
    <?php foreach ($settingsTabs as $tab): ?>
        <a
            class="nav-link rounded-pill d-inline-flex align-items-center gap-2 text-nowrap <?= $activeSettingsTab === $tab['key'] ? 'active' : '' ?>"
            href="<?= htmlSC($tab['href']) ?>"
        >
            <i class="<?= htmlSC($tab['icon']) ?>"></i>
            <?= htmlSC($tab['label']) ?>
        </a>
    <?php endforeach; ?>
</nav>
