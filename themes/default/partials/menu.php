<nav class="offcanvas offcanvas-start" id="navbarNav" tabindex="-1" aria-labelledby="navbarNavLabel">
    <div class="offcanvas-header py-3">
        <h5 class="offcanvas-title" id="navbarNavLabel"><?= print_translation('tpl_menu_nav') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body pt-0 pb-3">

        <!-- Navbar nav -->
        <div class="accordion" id="navigation">

            <!-- Categories collapse visible on screens < 992px wide (lg breakpoint) -->
            <div class="accordion-item border-0 d-lg-none">
                <div class="accordion-header" id="headingCategories">
                    <button type="button" class="accordion-button animate-underline fw-medium collapsed py-2" data-bs-toggle="collapse" data-bs-target="#categoriesMenu" aria-expanded="false" aria-controls="categoriesMenu">
                        <i class="ci-grid fs-lg me-2"></i>
                        <span class="d-block animate-target py-1"><?= print_translation('tpl_menu_btn_catalog') ?></span>
                    </button>
                </div>
                <div class="accordion-collapse collapse" id="categoriesMenu" aria-labelledby="headingCategories" data-bs-parent="#navigation">
                    <div class="accordion-body pb-3">
                        <div class="dropdown-menu show position-static d-flex flex-column gap-2 shadow-none p-4">
                            <a class="nav-link animate-underline d-inline-flex justify-content-between gap-3 p-0 <?= $currentPostCategorySlug === '' ? 'fw-semibold' : '' ?>" href="<?= $postCategoryUrl() ?>">
                                <span class="animate-target"><?= print_translation('tpl_menu_all_posts') ?></span>
                            </a>
                            <?php foreach ($postNavigationCategories as $category): ?>
                                <a class="nav-link animate-underline d-inline-flex justify-content-between gap-3 p-0 <?= $currentPostCategorySlug === $category['slug'] ? 'fw-semibold' : '' ?>" href="<?= $postCategoryUrl($category['slug']) ?>">
                                    <span class="animate-target"><?= htmlSC($category['label']) ?></span>
                                    <span class="text-body-tertiary fs-xs"><?= (int)$category['total'] ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main menu -->
        <?php echo $this->partial('menu-links', [
            'headerPageLinks' => $headerPageLinks,
        ]) ?>

    </div>

    <!-- Account button visible on screens < 768px wide (md breakpoint) -->
    <div class="offcanvas-header flex-column align-items-start d-md-none">
        <?php if (check_auth()): ?>
            <a class="btn btn-lg btn-outline-secondary w-100 rounded-pill mb-2 d-inline-flex align-items-center justify-content-center gap-2" href="<?= base_href('/profile') ?>">
                <img
                    src="<?= $currentUserAvatar ?>"
                    alt="<?= htmlSC($currentUser['name'] ?? '') ?>"
                    class="rounded-circle object-fit-cover ms-n1"
                    style="width: 28px; height: 28px;"
                >
                <?= print_translation('tpl_auth_profile') ?>
            </a>
            <?php if (check_admin()): ?>
                <a class="btn btn-lg btn-outline-secondary w-100 rounded-pill mb-2 d-inline-flex align-items-center justify-content-center gap-2" href="<?= base_href('/admin') ?>">
                    <i class="ci-layout fs-lg ms-n1"></i>
                    <?= print_translation('tpl_auth_admin') ?>
                </a>
            <?php endif; ?>
            <a class="btn btn-lg btn-outline-secondary w-100 rounded-pill mb-2 d-inline-flex align-items-center justify-content-center gap-2" href="<?= base_href('/chat') ?>">
                <i class="ci-chat fs-lg ms-n1"></i>
                <?= print_translation('tpl_auth_chat') ?>
            </a>
            <form action="<?= $logoutAction ?>" method="post" class="w-100">
                <?= get_csrf_field() ?>
                <button class="btn btn-lg btn-dark w-100 rounded-pill d-inline-flex align-items-center justify-content-center gap-2" type="submit">
                    <i class="ci-log-out fs-lg ms-n1"></i>
                    <?= print_translation('tpl_auth_logout') ?>
                </button>
            </form>
        <?php else: ?>
            <a class="btn btn-lg btn-outline-secondary w-100 rounded-pill mb-2 d-inline-flex align-items-center justify-content-center gap-2" href="<?= base_href('/login') ?>">
                <i class="ci-log-in fs-lg ms-n1"></i>
                <?= print_translation('tpl_auth_login') ?>
            </a>
            <a class="btn btn-lg btn-dark w-100 rounded-pill d-inline-flex align-items-center justify-content-center gap-2" href="<?= base_href('/register') ?>">
                <i class="ci-user-plus fs-lg ms-n1"></i>
                <?= print_translation('tpl_auth_register') ?>
            </a>
        <?php endif; ?>
    </div>
</nav>
