<nav class="fb-mobile-nav d-lg-none" aria-label="<?= htmlSC(return_translation('admin_ui_mobile_navigation')) ?>">
    <a class="fb-mobile-nav-item" href="<?= base_href('/admin') ?>">
        <i class="ci-home" aria-hidden="true"></i>
        <span><?= print_translation('admin_nav_dashboard') ?></span>
    </a>
    <button class="fb-mobile-nav-item" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminSidebar" aria-controls="adminSidebar">
        <i class="ci-file-text" aria-hidden="true"></i>
        <span><?= print_translation('admin_nav_group_content') ?></span>
    </button>
    <button class="fb-mobile-nav-item fb-mobile-nav-action" type="button" data-fb-command-open aria-label="<?= htmlSC(return_translation('admin_ui_command_title')) ?>">
        <i class="ci-plus" aria-hidden="true"></i>
        <span><?= print_translation('admin_ui_actions') ?></span>
    </button>
    <button class="fb-mobile-nav-item" type="button" data-fb-notifications-open>
        <i class="ci-bell" aria-hidden="true"></i>
        <span><?= print_translation('tpl_notifications') ?></span>
    </button>
    <a class="fb-mobile-nav-item" href="<?= base_href('/profile') ?>">
        <i class="ci-user" aria-hidden="true"></i>
        <span><?= print_translation('tpl_auth_profile') ?></span>
    </a>
</nav>
