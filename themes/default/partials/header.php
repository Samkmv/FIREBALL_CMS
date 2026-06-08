<!-- Shopping cart offcanvas -->
<div class="offcanvas offcanvas-end pb-sm-2 px-sm-2" id="shoppingCart" tabindex="-1" aria-labelledby="shoppingCartLabel" style="width: 500px">

    <!-- Header -->
    <div class="offcanvas-header flex-column align-items-start py-3 pt-lg-4">
        <div class="d-flex align-items-center justify-content-between w-100 mb-3 mb-lg-4">
            <h4 class="offcanvas-title" id="shoppingCartLabel"><?= print_translation('tpl_menu_cart') ?></h4>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
    </div>

    <!-- Items -->
    <div class="offcanvas-body d-flex flex-column gap-4 pt-2">

        <?= $this->partial('mini-cart') ?>

    </div>

</div>

<header class="navbar navbar-expand navbar-sticky sticky-top d-block bg-body z-fixed py-1 py-lg-0 py-xl-1 px-0" data-sticky-element="">
    <div class="container justify-content-start py-2 py-lg-3">

        <!-- Offcanvas menu toggler (Hamburger) -->
        <button type="button" class="navbar-toggler d-block flex-shrink-0 me-3 me-sm-4 position-relative" data-bs-toggle="offcanvas" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Navbar brand (Logo) -->
        <a class="navbar-brand fs-2 p-0 pe-lg-2 pe-xxl-0 me-0 me-sm-3 me-md-4 me-xxl-5" href="<?= base_href('/')?>"><?= htmlSC($siteTitle) ?></a>

        <!-- Categories dropdown visible on screens > 991px wide (lg breakpoint) -->
        <div class="dropdown d-none d-lg-block w-100 me-4" style="max-width: 240px">
            <button type="button" class="btn btn-lg btn-secondary w-100 border-0 rounded-pill" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="ci-grid fs-lg me-2 ms-n1"></i>
                <?= print_translation('tpl_menu_btn_catalog') ?>
                <i class="ci-chevron-down fs-lg me-2 ms-auto me-n1"></i>
            </button>

            <div class="dropdown-menu rounded-4 p-4" style="--cz-dropdown-spacer: .75rem; min-width: 280px;">
                <div class="d-flex flex-column gap-2" style="max-height: 600px; overflow: auto;">
                    <a class="nav-link animate-underline d-flex justify-content-between gap-3 p-0 <?= $currentPostCategorySlug === '' ? 'fw-semibold' : '' ?>" href="<?= $postCategoryUrl() ?>">
                        <span class="animate-target"><?= print_translation('tpl_menu_all_posts') ?></span>
                    </a>
                    <?php foreach ($postNavigationCategories as $category): ?>
                        <a class="nav-link animate-underline d-flex justify-content-between gap-3 p-0 <?= $currentPostCategorySlug === $category['slug'] ? 'fw-semibold' : '' ?>" href="<?= $postCategoryUrl($category['slug']) ?>">
                            <span class="animate-target text-truncate"><?= htmlSC($category['label']) ?></span>
                            <span class="text-body-tertiary fs-xs"><?= (int)$category['total'] ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

        <!-- Search bar visible on screens > 768px wide (md breakpoint) -->
        <form action="<?= base_href('/search') ?>" method="get" class="position-relative w-100 d-none d-md-block me-3 me-xl-4" data-search-suggest data-suggest-url="<?= base_href('/search/suggest') ?>">
            <input
                type="search"
                name="q"
                value="<?= htmlSC((string)request()->get('q', '')) ?>"
                class="form-control form-control-lg rounded-pill"
                placeholder="<?= print_translation('tpl_menu_search') ?>"
                aria-label="Search"
                autocomplete="off"
                data-search-suggest-input
            >
            <button type="submit" class="btn btn-icon btn-ghost fs-lg btn-secondary text-bo border-0 position-absolute top-0 end-0 rounded-circle mt-1 me-1" aria-label="Search button">
                <i class="ci-search"></i>
            </button>
            <div class="position-absolute top-100 start-0 end-0 mt-2 d-none z-3" data-search-suggest-results></div>
        </form>

        <!-- Button group -->
        <div class="d-flex align-items-center gap-md-1 gap-lg-2 ms-auto">

            <!-- Theme switcher (light/dark/auto) -->
            <div class="dropdown">
                <button type="button" class="theme-switcher btn btn-icon btn-outline-secondary fs-lg border-0 rounded-circle animate-scale" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Toggle theme (light)">
              <span class="theme-icon-active d-flex animate-target">
                <i class="ci-sun"></i>
              </span>
                </button>
                <ul class="dropdown-menu" style="--cz-dropdown-min-width: 9rem">
                    <li>
                        <button type="button" class="dropdown-item active" data-bs-theme-value="light" aria-pressed="true">
                  <span class="theme-icon d-flex fs-base me-2">
                    <i class="ci-sun"></i>
                  </span>
                            <span class="theme-label"><?= print_translation('tpl_menu_btn_switch_light') ?></span>
                            <i class="item-active-indicator ci-check ms-auto"></i>
                        </button>
                    </li>
                    <li>
                        <button type="button" class="dropdown-item" data-bs-theme-value="dark" aria-pressed="false">
                  <span class="theme-icon d-flex fs-base me-2">
                    <i class="ci-moon"></i>
                  </span>
                            <span class="theme-label"><?= print_translation('tpl_menu_btn_switch_dark') ?></span>
                            <i class="item-active-indicator ci-check ms-auto"></i>
                        </button>
                    </li>
                    <li>
                        <button type="button" class="dropdown-item" data-bs-theme-value="auto" aria-pressed="false">
                  <span class="theme-icon d-flex fs-base me-2">
                    <i class="ci-auto"></i>
                  </span>
                            <span class="theme-label"><?= print_translation('tpl_menu_btn_switch_auto') ?></span>
                            <i class="item-active-indicator ci-check ms-auto"></i>
                        </button>
                    </li>
                </ul>
            </div>

            <!-- Language switcher -->
            <?php if (MULTILANGS): ?>

                <div class="dropdown">
                    <button type="button" class="btn btn-icon btn-outline-secondary fs-lg border-0 rounded-circle animate-scale" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="d-flex animate-target">
                            <i class="ci-globe"></i>
                        </span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">

                <?php
                $request_uri = uri_without_lang();
                $headerCanonicalUrl = (string)($canonicalUrl ?? $seo_canonical ?? '');
                $homeCanonicalUrls = array_unique(array_map(
                    static fn(string $url): string => rtrim($url, '/'),
                    [base_href('/'), base_url('/')]
                ));
                if ($request_uri === '' || ($headerCanonicalUrl !== '' && in_array(rtrim($headerCanonicalUrl, '/'), $homeCanonicalUrls, true))) {
                    $request_uri = '/';
                }
                $headerLanguageSwitchHref = static function (string $key, array $language) use ($request_uri): string {
                    if ((int)($language['base'] ?? 0) === 1) {
                        return base_url($request_uri);
                    }

                    return base_url('/' . $key . ($request_uri === '/' ? '/' : $request_uri));
                };
                ?>

                <?php foreach (LANGS as $key => $val): ?>

                    <?php if (app()->get('lang')['code'] == $key) continue; ?>

                    <li>
                        <a class="dropdown-item" href="<?= htmlSC($headerLanguageSwitchHref((string)$key, $val)); ?>"><?= htmlSC($val['title']); ?></a>
                    </li>

                <?php endforeach; ?>

                    </ul>
                </div>

            <?php endif; ?>

            <!-- Search toggle button visible on screens < 768px wide (md breakpoint) -->
            <button type="button" class="btn btn-icon fs-xl btn-outline-secondary border-0 rounded-circle animate-scale d-md-none" data-bs-toggle="collapse" data-bs-target="#searchBar" aria-controls="searchBar" aria-label="Toggle search bar">
                <i class="ci-search animate-target"></i>
            </button>

            <?php if (check_auth()): ?>
                <div
                    class="dropdown"
                    data-notifications-center
                    data-feed-url="<?= base_href('/notifications/feed') ?>"
                    data-empty-text="<?= htmlSC(return_translation('notification_empty')) ?>"
                    data-chat-source-label="<?= htmlSC(return_translation('notification_source_chat')) ?>"
                >
                    <button
                        type="button"
                        class="btn btn-icon fs-xl btn-outline-secondary border-0 rounded-circle animate-scale position-relative"
                        data-bs-toggle="dropdown"
                        aria-expanded="false"
                        aria-label="<?= htmlSC(return_translation('tpl_notifications')) ?>"
                    >
                        <i class="ci-bell animate-target"></i>
                        <span
                            class="position-absolute top-0 start-100 badge fs-xs text-bg-danger rounded-pill ms-n3 z-2 d-none"
                            style="--cz-badge-padding-y: .25em; --cz-badge-padding-x: .42em"
                            data-notifications-badge
                        >0</span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-0 overflow-hidden" style="--cz-dropdown-min-width: 24rem; max-width: min(24rem, calc(100vw - 2rem));">
                        <div class="px-3 py-3 border-bottom bg-body">
                            <strong class="d-block"><?= print_translation('tpl_notifications') ?></strong>
                        </div>
                        <div class="list-group list-group-flush" data-notifications-list>
                            <div class="px-3 py-3 text-body-secondary small"><?= print_translation('notification_loading') ?></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Account button visible on screens > 768px wide (md breakpoint) -->
            <div class="dropdown d-none d-md-block">
                <button type="button" class="btn btn-icon fs-lg btn-outline-secondary border-0 rounded-circle animate-scale position-relative" data-bs-toggle="dropdown" aria-expanded="false">
                    <?php if (check_auth()): ?>
                        <span class="d-block w-100 h-100 rounded-circle overflow-hidden">
                            <img
                                src="<?= $currentUserAvatar ?>"
                                alt="<?= htmlSC($currentUser['name'] ?? '') ?>"
                                class="w-100 h-100 object-fit-cover rounded-circle"
                            >
                        </span>
                    <?php else: ?>
                        <i class="ci-user animate-target"></i>
                    <?php endif; ?>
                    <span class="visually-hidden"><?= print_translation('tpl_auth_account') ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <?php if (check_auth()): ?>
                        <li>
                            <div class="dropdown-header d-flex align-items-center gap-2">
                                <img
                                    src="<?= $currentUserAvatar ?>"
                                    alt="<?= htmlSC($currentUser['name'] ?? '') ?>"
                                    class="rounded-circle object-fit-cover border"
                                    style="width: 32px; height: 32px;"
                                >
                                <div><?= htmlSC($currentUser['name'] ?? '') ?></div>
                            </div>
                        </li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_href('/profile') ?>">
                                <i class="ci-user fs-base"></i>
                                <span><?= print_translation('tpl_auth_profile') ?></span>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_href('/chat') ?>">
                                <i class="ci-chat fs-base"></i>
                                <span><?= print_translation('tpl_auth_chat') ?></span>
                            </a>
                        </li>
                        <?php if (check_admin()): ?>
                            <li>
                                <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_href('/admin') ?>">
                                    <i class="ci-layout fs-base"></i>
                                    <span><?= print_translation('tpl_auth_admin') ?></span>
                                </a>
                            </li>
                        <?php endif; ?>
                        <li>
                            <form action="<?= $logoutAction ?>" method="post">
                                <?= get_csrf_field() ?>
                                <button class="dropdown-item d-flex align-items-center gap-2 w-100" type="submit">
                                    <i class="ci-log-out fs-base"></i>
                                    <span><?= print_translation('tpl_auth_logout') ?></span>
                                </button>
                            </form>
                        </li>
                    <?php else: ?>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_href('/login') ?>">
                                <i class="ci-log-in fs-base"></i>
                                <span><?= print_translation('tpl_auth_login') ?></span>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_href('/register') ?>">
                                <i class="ci-user-plus fs-base"></i>
                                <span><?= print_translation('tpl_auth_register') ?></span>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Cart button -->
<!--            <button type="button" class="btn btn-icon fs-xl btn-outline-secondary position-relative border-0 rounded-circle animate-scale" data-bs-toggle="offcanvas" data-bs-target="#shoppingCart" aria-controls="shoppingCart" aria-label="Shopping cart">-->
<!--                <span id="countCart" class="position-absolute top-0 start-100 badge fs-xs text-bg-primary rounded-pill ms-n3 z-2" style="--cz-badge-padding-y: .25em; --cz-badge-padding-x: .42em">-->
<!--                    --><?php //= \App\Helpers\Cart\Cart::getCartQuantityTotal() ?>
<!--                </span>-->
<!--                <i class="ci-shopping-cart animate-target"></i>-->
<!--            </button>-->

        </div>
    </div>

    <!-- Search collapse available on screens < 768px wide (md breakpoint) -->
    <div class="collapse d-md-none" id="searchBar">
        <div class="container pt-2 pb-3">
            <form action="<?= base_href('/search') ?>" method="get" class="position-relative" data-search-suggest data-suggest-url="<?= base_href('/search/suggest') ?>">
                <i class="ci-search position-absolute top-50 translate-middle-y d-flex fs-lg ms-3"></i>
                <input
                    type="search"
                    name="q"
                    value="<?= htmlSC((string)request()->get('q', '')) ?>"
                    class="form-control form-icon-start rounded-pill pe-5"
                    placeholder="<?= print_translation('tpl_menu_search') ?>"
                    data-autofocus="collapse"
                    autocomplete="off"
                    data-search-suggest-input
                >
                <button type="submit" class="btn btn-icon btn-ghost fs-lg btn-secondary border-0 position-absolute top-50 end-0 translate-middle-y rounded-circle me-2" aria-label="Search button">
                    <i class="ci-search"></i>
                </button>
                <div class="position-absolute top-100 start-0 end-0 mt-2 d-none z-3" data-search-suggest-results></div>
            </form>
        </div>
    </div>
</header>
