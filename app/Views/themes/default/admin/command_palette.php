<?php
$registeredAdminCommands = [
    [
        'href' => base_href('/admin/pages/create'),
        'label' => return_translation('admin_dashboard_create_page'),
        'category' => return_translation('admin_ui_command_actions'),
        'icon' => 'ci-file-plus',
    ],
    [
        'href' => base_href('/admin/posts/create'),
        'label' => return_translation('admin_dashboard_create_post'),
        'category' => return_translation('admin_ui_command_actions'),
        'icon' => 'ci-edit-3',
    ],
    [
        'href' => base_href('/admin/files'),
        'label' => return_translation('admin_dashboard_upload_media'),
        'category' => return_translation('admin_ui_command_actions'),
        'icon' => 'ci-image',
    ],
    [
        'href' => base_href('/admin/users/create'),
        'label' => return_translation('admin_dashboard_create_user'),
        'category' => return_translation('admin_ui_command_actions'),
        'icon' => 'ci-user-plus',
        'creator_only' => true,
    ],
];
$registeredAdminCommands = apply_filters('admin_command_palette_commands', $registeredAdminCommands, get_user() ?: []);
if (!is_array($registeredAdminCommands)) {
    $registeredAdminCommands = [];
}
?>

<div hidden data-fb-command-registry>
    <?php foreach ($registeredAdminCommands as $registeredCommand): ?>
        <?php
        if (!is_array($registeredCommand) || empty($registeredCommand['href']) || empty($registeredCommand['label'])) {
            continue;
        }
        if (!empty($registeredCommand['creator_only']) && !check_creator()) {
            continue;
        }
        ?>
        <a
            href="<?= htmlSC((string)$registeredCommand['href']) ?>"
            data-fb-command
            data-fb-command-label="<?= htmlSC((string)$registeredCommand['label']) ?>"
            data-fb-command-category="<?= htmlSC((string)($registeredCommand['category'] ?? return_translation('admin_ui_command_actions'))) ?>"
            data-fb-command-icon="<?= htmlSC((string)($registeredCommand['icon'] ?? 'ci-arrow-right')) ?>"
            data-fb-command-keywords="<?= htmlSC((string)($registeredCommand['keywords'] ?? '')) ?>"
        ></a>
    <?php endforeach; ?>
</div>

<div
    class="fb-command-palette"
    data-fb-command-palette
    data-empty-label="<?= htmlSC(return_translation('admin_ui_command_empty')) ?>"
    data-navigation-label="<?= htmlSC(return_translation('admin_ui_command_navigation')) ?>"
    data-actions-label="<?= htmlSC(return_translation('admin_ui_command_actions')) ?>"
    hidden
    aria-hidden="true"
>
    <div class="fb-command-backdrop" data-fb-command-close></div>
    <section
        class="fb-command-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="fbCommandTitle"
    >
        <h2 class="visually-hidden" id="fbCommandTitle"><?= print_translation('admin_ui_command_title') ?></h2>
        <div class="fb-command-search">
            <i class="ci-search" aria-hidden="true"></i>
            <input
                type="search"
                autocomplete="off"
                placeholder="<?= htmlSC(return_translation('admin_ui_command_placeholder')) ?>"
                aria-label="<?= htmlSC(return_translation('admin_ui_command_placeholder')) ?>"
                data-fb-command-input
            >
            <kbd>Esc</kbd>
        </div>
        <div class="fb-command-results" data-fb-command-results role="listbox"></div>
        <footer class="fb-command-footer">
            <span><kbd>↑</kbd><kbd>↓</kbd> <?= print_translation('admin_ui_command_move') ?></span>
            <span><kbd>↵</kbd> <?= print_translation('admin_ui_command_open') ?></span>
            <span><kbd>Esc</kbd> <?= print_translation('admin_btn_close') ?></span>
        </footer>
    </section>
</div>
