<?php

/**
 * Layout Template
 *
 * Available variables:
 *
 * $content
 * $settings
 * $user
 * $locale
 */

$postNavigationCategories = (new \App\Models\Post())->getNavigationCategories();
$pageNavigationModel = new \App\Models\Page();
$headerPageLinks = $pageNavigationModel->getMenuPages('header');
$footerPageLinks = $pageNavigationModel->getMenuPages('footer');
$legalInformationLinks = $pageNavigationModel->getLegalInformationMenu();
$currentPostCategorySlug = trim((string)request()->get('category', ''));
$siteTitle = site_setting('site_title', SITE_NAME);
$siteDescription = site_setting('site_description', '');
$socialLinks = site_social_links();
$seoHomeTitle = site_setting('seo_home_title', '');
$seoDefaultTitleSuffix = site_setting('seo_default_title_suffix', '');
$seoMetaDescription = site_setting('seo_meta_description', '');
$seoMetaKeywords = site_setting('seo_meta_keywords', '');
$seoMetaAuthor = site_setting('seo_meta_author', '');
$seoRobots = site_setting('seo_robots', 'index,follow');
$seoOgImage = site_setting('seo_og_image', '');
$seoTwitterCard = site_setting('seo_twitter_card', 'summary_large_image');
$pageTitle = trim((string)($title ?? ''));
$pageSeoTitle = trim((string)($seo_title ?? ''));
$pageSeoDescription = trim((string)($seo_description ?? ''));
$pageSeoKeywords = trim((string)($seo_keywords ?? ''));
$pageSeoImage = trim((string)($seo_image ?? ''));
$pageSeoRobots = trim((string)($seo_robots ?? ''));
$pageSeoCanonical = trim((string)($seo_canonical ?? ''));
$isHomePage = uri_without_lang() === '';
$homePageTitle = $pageTitle !== '' ? $pageTitle : return_translation('home_index_title');
$homeTitleBase = ($seoHomeTitle !== '' && $seoHomeTitle !== $siteTitle)
    ? $seoHomeTitle
    : ($homePageTitle !== 'home_index_title' ? $homePageTitle : $siteTitle);
$resolvedTitleBase = $pageSeoTitle !== ''
    ? $pageSeoTitle
    : ($isHomePage ? $homeTitleBase : ($pageTitle !== '' ? $pageTitle : $siteTitle));
$resolvedTitleSuffix = $seoDefaultTitleSuffix !== '' ? $seoDefaultTitleSuffix : $siteTitle;
$documentTitle = $resolvedTitleBase !== $resolvedTitleSuffix
    ? $resolvedTitleBase . ' - ' . $resolvedTitleSuffix
    : $resolvedTitleBase;
$metaDescription = $pageSeoDescription !== ''
    ? $pageSeoDescription
    : ($seoMetaDescription !== '' ? $seoMetaDescription : ($siteDescription !== '' ? $siteDescription : $siteTitle));
$metaKeywords = $pageSeoKeywords !== '' ? $pageSeoKeywords : $seoMetaKeywords;
$metaAuthor = $seoMetaAuthor !== '' ? $seoMetaAuthor : $siteTitle;
$metaRobots = $pageSeoRobots !== '' ? $pageSeoRobots : $seoRobots;
$canonicalUrl = $pageSeoCanonical !== '' ? $pageSeoCanonical : base_href(uri_without_lang());
$normalizeSeoImage = static function (string $value): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (filter_var($value, FILTER_VALIDATE_URL)) {
        return $value;
    }

    if (str_starts_with($value, '/')) {
        return base_url($value);
    }

    return get_image(ltrim($value, '/'));
};
$metaImage = $normalizeSeoImage($pageSeoImage !== '' ? $pageSeoImage : $seoOgImage);
$currentLangCode = app()->get('lang')['code'] ?? 'ru';
$ogLocale = match ($currentLangCode) {
    'en' => 'en_US',
    'de' => 'de_DE',
    'zh-cn' => 'zh_CN',
    default => 'ru_RU',
};
$currentUser = check_auth() ? get_user() : null;
if ($currentUser) {
    \FBL\Auth::touchPresence();
}
$isAdmin = check_admin();
$hasMobileSidebarToggle = str_contains((string)$this->content, 'data-bs-target="#adminSidebar"')
    || str_contains((string)$this->content, 'data-bs-target="#blogSidebar"')
    || str_contains((string)$this->content, 'data-bs-target="#accountSidebar"');
$canViewVideoStatus = can_view_video_diagnostics(isset($video_owner_id) ? (int)$video_owner_id : null);
$currentUserAvatar = get_user_avatar($currentUser['avatar'] ?? null, 'sm');
$logoutAction = base_href('/logout');
$siteFaviconUrl = site_favicon_url();
$siteFaviconType = site_favicon_type();
$footerDescription = $siteDescription !== ''
    ? $siteDescription
    : return_translation('footer_description_fallback');
$footerNavigationLinks = [
    [
        'href' => base_href('/'),
        'label' => return_translation('tpl_menu_nav_index'),
    ],
    [
        'href' => base_href('/posts'),
        'label' => return_translation('footer_nav_posts'),
    ],
    [
        'href' => base_href('/contacts'),
        'label' => return_translation('tpl_menu_nav_contacts'),
    ],
];
$footerNavigationLinks = array_merge($footerNavigationLinks, $footerPageLinks);
$footerCategoryLinks = array_slice($postNavigationCategories, 0, 6);
$postCategoryUrl = static function (?string $slug = null): string {
    $url = base_href('/posts');
    if ($slug === null || $slug === '') {
        return $url;
    }

    return $url . '?category=' . rawurlencode($slug);
};

?>
<!DOCTYPE html><html lang="<?= htmlSC(app()->get('lang')['code'] ?? 'en') ?>" data-bs-theme="light" data-pwa="true"><head>
    <meta charset="utf-8">

    <?= get_csrf_meta() ?>

    <!-- Viewport -->
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <!-- SEO Meta Tags -->
    <title><?= htmlSC($documentTitle) ?></title>
    <meta name="description" content="<?= htmlSC($metaDescription) ?>">
    <?php if ($metaKeywords !== ''): ?>
        <meta name="keywords" content="<?= htmlSC($metaKeywords) ?>">
    <?php endif; ?>
    <meta name="author" content="<?= htmlSC($metaAuthor) ?>">
    <meta name="robots" content="<?= htmlSC($metaRobots) ?>">
    <link rel="canonical" href="<?= htmlSC($canonicalUrl) ?>">
    <meta property="og:locale" content="<?= htmlSC($ogLocale) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= htmlSC($siteTitle) ?>">
    <meta property="og:title" content="<?= htmlSC($documentTitle) ?>">
    <meta property="og:description" content="<?= htmlSC($metaDescription) ?>">
    <meta property="og:url" content="<?= htmlSC($canonicalUrl) ?>">
    <?php if ($metaImage !== ''): ?>
        <meta property="og:image" content="<?= htmlSC($metaImage) ?>">
        <meta property="og:image:alt" content="<?= htmlSC($documentTitle) ?>">
    <?php endif; ?>
    <meta name="twitter:card" content="<?= htmlSC($seoTwitterCard !== '' ? $seoTwitterCard : 'summary_large_image') ?>">
    <meta name="twitter:title" content="<?= htmlSC($documentTitle) ?>">
    <meta name="twitter:description" content="<?= htmlSC($metaDescription) ?>">
    <?php if ($metaImage !== ''): ?>
        <meta name="twitter:image" content="<?= htmlSC($metaImage) ?>">
    <?php endif; ?>

    <!-- Webmanifest + Favicon / App icons -->
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <link rel="manifest" href="<?= theme_asset('manifest.json') ?>">
    <link rel="icon" type="<?= htmlSC($siteFaviconType) ?>" href="<?= htmlSC($siteFaviconUrl) ?>">
    <link rel="shortcut icon" href="<?= htmlSC($siteFaviconUrl) ?>">
    <link rel="apple-touch-icon" href="<?= htmlSC($siteFaviconUrl) ?>">

    <!-- Theme switcher (color modes) -->
    <script src="<?= theme_asset('js/theme-switcher.js') ?>"></script>

    <!-- Font icons -->
    <link rel="preload" href="<?= theme_asset('icons/cartzilla-icons.woff2') ?>" as="font" type="font/woff2" crossorigin="">
    <link rel="stylesheet" href="<?= theme_asset('icons/cartzilla-icons.min.css') ?>">

    <!-- Vendor styles -->
    <link rel="stylesheet" href="<?= theme_asset('vendor/choices.js/choices.min.css') ?>">
    <link rel="stylesheet" href="<?= theme_asset('vendor/simplebar/simplebar.min.css') ?>">
    <link rel="stylesheet" href="<?= theme_asset('vendor/swiper/swiper-bundle.min.css') ?>">
    <link rel="stylesheet" href="<?= theme_asset('vendor/plyr/plyr.css') . '?v=' . filemtime(theme()->assetPath('vendor/plyr/plyr.css')) ?>">
    <link rel="stylesheet" href="<?= theme_asset('vendor/highlight.js/styles/atom-one-dark.min.css') ?>">

    <?php if (!empty($styles)): ?>
        <?php foreach ($styles as $style): ?>

            <link rel="stylesheet" href="<?= $style; ?>">

        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Bootstrap + Theme styles -->
    <link rel="stylesheet" href="<?= theme_asset('css/theme.min.css') ?>" id="theme-styles">

    <!-- Customs styles -->
    <link rel="stylesheet" href="<?= theme_asset('vendor/toastr/toastr.min.css') ?>">
    <link rel="stylesheet" href="<?= theme_asset('css/style.css') . '?v=' . filemtime(theme()->assetPath('css/style.css')) ?>">

    <!-- Header scripts -->
    <?php if (!empty($header_scripts)): ?>
        <?php foreach ($header_scripts as $header_script): ?>

            <script src="<?= $header_script; ?>"></script>

        <?php endforeach; ?>
    <?php endif; ?>

</head>


<!-- Body -->
<body
    data-toast-success-title="<?= htmlSC(return_translation('toast_success_title')) ?>"
    data-toast-error-title="<?= htmlSC(return_translation('toast_error_title')) ?>"
    data-toast-info-title="<?= htmlSC(return_translation('toast_info_title')) ?>"
    data-toast-warning-title="<?= htmlSC(return_translation('toast_warning_title')) ?>"
    data-code-copy-label="<?= htmlSC(return_translation('code_copy_button')) ?>"
    data-code-copied-label="<?= htmlSC(return_translation('code_copied_button')) ?>"
>

<?= $this->partial('menu', get_defined_vars()) ?>

<?= $this->partial('header', get_defined_vars()) ?>

<!-- Вызов быстрых flash уведомлений -->
<?php get_alerts(); ?>

<?= $this->content; ?>

<?= $this->partial('footer', get_defined_vars()) ?>

<?php if ($isAdmin): ?>
    <div class="modal fade" id="adminDeleteModal" tabindex="-1" aria-hidden="true" data-admin-delete-modal>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-5 overflow-hidden">
                <div class="modal-body p-4 p-md-5 text-center">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-danger-subtle text-danger mb-4" style="width: 72px; height: 72px;">
                        <i class="ci-trash fs-2"></i>
                    </div>
                    <h2 class="h4 mb-2"><?= print_translation('admin_delete_modal_title') ?></h2>
                    <p class="text-body-secondary mb-3" data-admin-delete-modal-message><?= print_translation('admin_delete_modal_default_message') ?></p>
                    <div class="rounded-4 bg-body-tertiary px-3 py-3 mb-3 d-none" data-admin-delete-modal-item-wrap>
                        <div class="text-uppercase small fw-semibold text-body-tertiary mb-1" style="letter-spacing: .08em;"><?= print_translation('admin_delete_modal_item_label') ?></div>
                        <div class="fw-semibold text-break" data-admin-delete-modal-item></div>
                    </div>
                    <p class="small text-body-secondary mb-0"><?= print_translation('admin_delete_modal_hint') ?></p>
                </div>
                <div class="modal-footer border-0 justify-content-center gap-2 px-4 pb-4 pt-0">
                    <button type="button" class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" data-bs-dismiss="modal">
                        <i class="ci-close"></i><?= print_translation('admin_btn_cancel') ?>
                    </button>
                    <button type="button" class="btn btn-danger rounded-pill d-inline-flex align-items-center gap-2" data-admin-delete-modal-confirm>
                        <i class="ci-trash"></i><span data-admin-delete-modal-confirm-label><?= print_translation('admin_btn_delete') ?></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Back to top button -->
<div class="floating-buttons position-fixed top-50 end-0 z-sticky me-3 me-xl-4 pb-4">
    <a class="btn-scroll-top btn btn-sm bg-body border-0 rounded-pill shadow animate-slide-end" href="#top">
        Top
        <i class="ci-arrow-right fs-base ms-1 me-n1 animate-target"></i>
        <span class="position-absolute top-0 start-0 w-100 h-100 border rounded-pill z-0"></span>
        <svg class="position-absolute top-0 start-0 w-100 h-100 z-1" viewbox="0 0 62 32" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x=".75" y=".75" width="60.5" height="30.5" rx="15.25" stroke="currentColor" stroke-width="1.5" stroke-miterlimit="10"></rect>
        </svg>
    </a>
</div>

<script>
    const baseUrl = '<?= base_url(); ?>';
    const themeAssetsUrl = '<?= theme_asset('') ?>';
    window.canViewVideoStatus = <?= $canViewVideoStatus ? 'true' : 'false'; ?>;
    window.canViewVideoDiagnostics = window.canViewVideoStatus;
</script>

<script src="<?= theme_asset('js/jquery-3.7.1.min.js') ?>"></script>

<!-- Vendor scripts -->
<script src="<?= theme_asset('vendor/choices.js/choices.min.js') ?>"></script>
<script src="<?= theme_asset('vendor/simplebar/simplebar.min.js') ?>"></script>
<script>
    if (window.Choices && !window.Choices.__fireballHardened) {
        const FireballChoicesBase = window.Choices;
        const FireballChoices = function (element, options) {
            const allowHtml = element && element.getAttribute && element.getAttribute('data-select-allow-html') === 'true';
            return new FireballChoicesBase(element, Object.assign({}, options || {}, {
                allowHTML: allowHtml
            }));
        };
        FireballChoices.prototype = FireballChoicesBase.prototype;
        Object.keys(FireballChoicesBase).forEach(function (key) {
            FireballChoices[key] = FireballChoicesBase[key];
        });
        FireballChoices.__fireballHardened = true;
        window.Choices = FireballChoices;
    }
</script>
<script src="<?= theme_asset('vendor/swiper/swiper-bundle.min.js') ?>"></script>
<script src="<?= theme_asset('vendor/toastr/toastr.min.js') ?>"></script>
<script src="<?= theme_asset('vendor/plyr/plyr.polyfilled.js') . '?v=' . filemtime(theme()->assetPath('vendor/plyr/plyr.polyfilled.js')) ?>"></script>
<script src="<?= theme_asset('vendor/highlight.js/highlight.min.js') ?>"></script>

<?php if (!empty($footer_scripts)): ?>
    <?php foreach ($footer_scripts as $footer_script): ?>

        <script src="<?= $footer_script; ?>"></script>

    <?php endforeach; ?>
<?php endif; ?>

<!-- Bootstrap + Theme scripts -->
<script src="<?= theme_asset('js/theme.min.js') ?>"></script>

<!-- Customs scripts -->
<script src="<?= theme_asset('js/select-init.js') . '?v=' . filemtime(theme()->assetPath('js/select-init.js')) ?>"></script>
<script src="<?= theme_asset('js/plyr-init.js') . '?v=' . filemtime(theme()->assetPath('js/plyr-init.js')) ?>"></script>
<?php if ($isAdmin): ?>
    <script src="<?= theme_asset('js/admin-delete-modal.js') . '?v=' . filemtime(theme()->assetPath('js/admin-delete-modal.js')) ?>"></script>
<?php endif; ?>
<script src="<?= theme_asset('js/main.js') . '?v=' . filemtime(theme()->assetPath('js/main.js')) ?>"></script>


</body></html>
