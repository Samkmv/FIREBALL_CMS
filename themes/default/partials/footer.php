<!-- Page footer -->
<footer class="footer position-relative bg-dark<?= $hasMobileSidebarToggle ? ' mobile-sidebar-layout-footer' : '' ?>">
    <span class="position-absolute top-0 start-0 w-100 h-100 bg-body d-none d-block-dark"></span>
    <div class="container position-relative z-1 pt-sm-2 pt-md-3 pt-lg-4" data-bs-theme="dark">
        <div class="accordion py-5" id="footerLinks">
            <div class="row gy-4 gy-lg-0">
                <div class="col-lg-4 pe-lg-5">
                    <a class="d-inline-flex align-items-center text-dark-emphasis text-decoration-none fs-3 fw-semibold mb-3" href="<?= base_href('/') ?>">
                        <?= htmlSC($siteTitle) ?>
                    </a>
                    <p class="text-body mb-0"><?= htmlSC($footerDescription) ?></p>
                    <?php if (!empty($socialLinks)): ?>
                        <div class="pt-4">
                            <div class="text-dark-emphasis small text-uppercase fw-semibold mb-3" style="letter-spacing: .08em;"><?= print_translation('footer_heading_social') ?></div>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($socialLinks as $link): ?>
                                    <a
                                        class="btn btn-icon btn-outline-secondary rounded-circle"
                                        href="<?= htmlSC((string)$link['href']) ?>"
                                        <?= !empty($link['external']) ? 'target="_blank" rel="noopener noreferrer"' : '' ?>
                                        aria-label="<?= htmlSC((string)$link['label']) ?>"
                                    >
                                        <i class="<?= htmlSC((string)$link['icon']) ?>"></i>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-lg-8">
                    <div class="row row-cols-1 row-cols-sm-2 <?= !empty($legalInformationLinks) ? 'row-cols-lg-4' : 'row-cols-lg-3' ?> gx-3 gx-md-4">
                        <div class="accordion-item col border-0">
                            <h6 class="accordion-header" id="footerNavHeading">
                                <span class="text-dark-emphasis d-none d-sm-block"><?= print_translation('footer_heading_navigation') ?></span>
                                <button type="button" class="accordion-button py-3 d-sm-none collapsed" data-bs-toggle="collapse" data-bs-target="#footerNavLinks" aria-expanded="false" aria-controls="footerNavLinks"><?= print_translation('footer_heading_navigation') ?></button>
                            </h6>
                            <div class="accordion-collapse d-sm-block collapse" id="footerNavLinks" aria-labelledby="footerNavHeading" data-bs-parent="#footerLinks">
                                <ul class="nav flex-column gap-2 pt-sm-3 pb-3 mt-n1 mb-1">
                                    <?php foreach ($footerNavigationLinks as $link): ?>
                                        <li class="d-flex w-100 pt-1">
                                            <a class="nav-link animate-underline animate-target d-inline fw-normal text-truncate p-0" href="<?= $link['href'] ?>"><?= $link['label'] ?></a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <hr class="d-sm-none my-0">
                        </div>
                        <div class="accordion-item col border-0">
                            <h6 class="accordion-header" id="footerCategoryHeading">
                                <span class="text-dark-emphasis d-none d-sm-block"><?= print_translation('footer_heading_categories') ?></span>
                                <button type="button" class="accordion-button py-3 d-sm-none collapsed" data-bs-toggle="collapse" data-bs-target="#footerCategoryLinks" aria-expanded="false" aria-controls="footerCategoryLinks"><?= print_translation('footer_heading_categories') ?></button>
                            </h6>
                            <div class="accordion-collapse d-sm-block collapse" id="footerCategoryLinks" aria-labelledby="footerCategoryHeading" data-bs-parent="#footerLinks">
                                <ul class="nav flex-column gap-2 pt-sm-3 pb-3 mt-n1 mb-1">
                                    <li class="d-flex w-100 pt-1">
                                        <a class="nav-link animate-underline animate-target d-inline fw-normal text-truncate p-0" href="<?= $postCategoryUrl() ?>"><?= print_translation('tpl_menu_all_posts') ?></a>
                                    </li>
                                    <?php foreach ($footerCategoryLinks as $category): ?>
                                        <li class="d-flex w-100 pt-1">
                                            <a class="nav-link animate-underline animate-target d-inline fw-normal text-truncate p-0" href="<?= $postCategoryUrl($category['slug']) ?>"><?= htmlSC($category['label']) ?></a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <hr class="d-sm-none my-0">
                        </div>
                        <div class="accordion-item col border-0">
                            <h6 class="accordion-header" id="footerAccountHeading">
                                <span class="text-dark-emphasis d-none d-sm-block"><?= print_translation('footer_heading_account') ?></span>
                                <button type="button" class="accordion-button py-3 d-sm-none collapsed" data-bs-toggle="collapse" data-bs-target="#footerAccountLinks" aria-expanded="false" aria-controls="footerAccountLinks"><?= print_translation('footer_heading_account') ?></button>
                            </h6>
                            <div class="accordion-collapse d-sm-block collapse" id="footerAccountLinks" aria-labelledby="footerAccountHeading" data-bs-parent="#footerLinks">
                                <ul class="nav flex-column gap-2 pt-sm-3 pb-3 mt-n1 mb-1">
                                    <?php foreach ($footerAccountLinks as $link): ?>
                                        <li class="d-flex w-100 pt-1">
                                            <a class="nav-link animate-underline animate-target d-inline fw-normal text-truncate p-0" href="<?= $link['href'] ?>"><?= $link['label'] ?></a>
                                        </li>
                                    <?php endforeach; ?>
                                    <?php if (check_auth()): ?>
                                        <li class="d-flex w-100 pt-1">
                                            <form action="<?= $logoutAction ?>" method="post" class="w-100">
                                                <?= get_csrf_field() ?>
                                                <button class="nav-link animate-underline animate-target d-inline fw-normal text-truncate p-0 border-0 bg-transparent text-start" type="submit">
                                                    <?= print_translation('tpl_auth_logout') ?>
                                                </button>
                                            </form>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                            <hr class="d-sm-none my-0">
                        </div>
                        <?php if (!empty($legalInformationLinks)): ?>
                            <div class="accordion-item col border-0">
                                <h6 class="accordion-header" id="footerLegalHeading">
                                    <span class="text-dark-emphasis d-none d-sm-block"><?= print_translation('footer_heading_legal_information') ?></span>
                                    <button type="button" class="accordion-button py-3 d-sm-none collapsed" data-bs-toggle="collapse" data-bs-target="#footerLegalLinks" aria-expanded="false" aria-controls="footerLegalLinks"><?= print_translation('footer_heading_legal_information') ?></button>
                                </h6>
                                <div class="accordion-collapse d-sm-block collapse" id="footerLegalLinks" aria-labelledby="footerLegalHeading" data-bs-parent="#footerLinks">
                                    <ul class="nav flex-column gap-2 pt-sm-3 pb-3 mt-n1 mb-1">
                                        <?php foreach ($legalInformationLinks as $link): ?>
                                            <li class="d-flex w-100 pt-1">
                                                <a class="nav-link animate-underline animate-target d-inline fw-normal text-truncate p-0" href="<?= htmlSC($link['href']) ?>"><?= htmlSC($link['label']) ?></a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <hr class="d-sm-none my-0">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 border-top py-4">
            <p class="text-body fs-sm text-center text-md-start mb-0">
                <?= str_replace(
                    [':year', ':site'],
                    [(string)date('Y'), $siteTitle],
                    return_translation('footer_copyright')
                ) ?>
            </p>
        </div>
    </div>
</footer>
