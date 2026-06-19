<?php
$article = (array)($article ?? []);
$relatedArticles = (array)($related_articles ?? []);
$categoryName = trim((string)($article['category_name'] ?? ''));
$categoryUrl = base_href('/support');
$articleId = (int)($article['id'] ?? 0);
$supportContactUrl = base_href('/support#support-question-form');
?>
<main class="content-wrapper support-page support-article-page">
    <section class="container pt-3 pt-sm-4">
        <div class="support-hero position-relative px-4 px-sm-5 px-xl-0 py-5">
            <span class="position-absolute top-0 start-0 w-100 h-100 rounded-5 d-none-dark rtl-flip" style="background: linear-gradient(-90deg, #accbee 0%, #e7f0fd 100%)"></span>
            <span class="position-absolute top-0 start-0 w-100 h-100 rounded-5 d-none d-block-dark rtl-flip" style="background: linear-gradient(-90deg, #1b273a 0%, #1f2632 100%)"></span>
            <div class="position-relative z-1">
                <h1 class="h2 text-center pt-md-2 pt-lg-3 pt-xl-4 mb-4"><?= print_translation('support_heading') ?></h1>
                <form class="position-relative mx-auto mb-4" style="max-width: 545px" action="<?= base_href('/support') ?>" method="get">
                    <input type="search" class="form-control form-control-lg" name="q" placeholder="<?= htmlSC(return_translation('support_search_placeholder')) ?>" aria-label="<?= htmlSC(return_translation('support_search_label')) ?>">
                </form>
            </div>
        </div>
    </section>

    <section class="container pb-5 mb-1 mb-sm-2 mb-md-3 mb-lg-4 mb-xl-5">
        <nav class="pt-3 my-3 my-md-4" aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= base_href('/support') ?>"><?= print_translation('support_page_title') ?></a></li>
                <?php if ($categoryName !== ''): ?>
                    <li class="breadcrumb-item"><a href="<?= htmlSC($categoryUrl) ?>"><?= htmlSC($categoryName) ?></a></li>
                <?php endif; ?>
                <li class="breadcrumb-item active" aria-current="page"><?= htmlSC($article['title'] ?? '') ?></li>
            </ol>
        </nav>

        <div class="row">
            <aside class="support-article-aside col-lg-4 order-lg-2 pb-3 mb-3 mb-md-4">
                <div class="support-article-aside__inner sticky-md-top ps-lg-4 ms-xl-3">
                    <h4 class="h5 mb-4 d-none d-lg-block"><?= print_translation('support_articles_in_section') ?></h4>
                    <div class="position-relative py-1 py-sm-2 px-3 px-sm-4 p-lg-0">
                        <span class="position-absolute top-0 start-0 w-100 h-100 border rounded-4 d-lg-none"></span>
                        <button type="button" class="btn btn-lg btn-outline-secondary position-relative z-1 w-100 justify-content-start border-0 px-0 d-lg-none" data-bs-toggle="collapse" data-bs-target="#support-articles" aria-expanded="false" aria-controls="support-articles">
                            <i class="ci-menu fs-lg me-2"></i>
                            <?= print_translation('support_articles_in_section') ?>
                            <i class="ci-chevron-down fs-lg ms-auto"></i>
                        </button>
                        <div class="collapse position-relative z-1 d-lg-block" id="support-articles">
                            <ul class="nav flex-column gap-3 pt-2 pb-3 pt-lg-0">
                                <?php foreach ($relatedArticles as $related): ?>
                                    <?php $isCurrent = (int)($related['id'] ?? 0) === (int)($article['id'] ?? 0); ?>
                                    <li>
                                        <a class="nav-link hover-effect-underline fw-normal p-0 <?= $isCurrent ? 'active fw-semibold text-body-emphasis' : '' ?>" href="<?= base_href('/support/articles/' . $related['slug']) ?>">
                                            <?= htmlSC($related['title']) ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </aside>

            <article class="col-lg-8 order-lg-1">
                <h2 class="pb-2 pb-sm-3 pb-lg-4"><?= htmlSC($article['title'] ?? '') ?></h2>
                <?php if (!empty($article['updated_at'])): ?>
                    <p class="h6">
                        <span class="text-body-secondary"><?= print_translation('support_article_last_updated') ?>:</span>
                        <?= htmlSC(date('d.m.Y', strtotime((string)$article['updated_at']))) ?>
                    </p>
                <?php endif; ?>
                <?php if (!empty($article['excerpt'])): ?>
                    <p><?= htmlSC($article['excerpt']) ?></p>
                <?php endif; ?>
                <div class="fs-lg lh-lg">
                    <?= nl2br(htmlSC((string)($article['content'] ?? ''))) ?>
                </div>

                <div
                    class="support-article-helpful"
                    data-support-feedback
                    data-article-id="<?= $articleId ?>"
                    data-feedback-url="<?= htmlSC(base_href('/support/articles/feedback')) ?>"
                    data-contact-url="<?= htmlSC($supportContactUrl) ?>"
                    data-thanks-message="<?= htmlSC(return_translation('support_article_feedback_thanks')) ?>"
                    data-contact-message="<?= htmlSC(return_translation('support_article_feedback_contact')) ?>"
                    data-duplicate-message="<?= htmlSC(return_translation('support_article_feedback_duplicate')) ?>"
                    data-error-message="<?= htmlSC(return_translation('support_article_feedback_error')) ?>"
                    data-contact-button="<?= htmlSC(return_translation('support_contact_button')) ?>"
                >
                    <div class="py-2 py-sm-3 mt-n3"><hr></div>
                    <h4 class="h5 mb-4"><?= print_translation('support_article_helpful') ?></h4>
                    <div class="d-flex flex-wrap gap-3 pb-3 mb-4">
                        <button type="button" class="btn btn-outline-secondary" data-support-feedback-vote="helpful">
                            <i class="ci-thumbs-up fs-base me-1 ms-n1"></i>
                            <?= print_translation('support_article_yes') ?>
                        </button>
                        <button type="button" class="btn btn-outline-secondary" data-support-feedback-vote="not_helpful">
                            <i class="ci-thumbs-down fs-base me-1 ms-n1"></i>
                            <?= print_translation('support_article_no') ?>
                        </button>
                    </div>
                    <div class="support-feedback-result" data-support-feedback-result hidden></div>
                </div>

                <div class="support-article-contact border rounded-4 p-4">
                    <div class="d-sm-flex align-items-center justify-content-between text-center text-sm-start p-md-3">
                        <div class="me-sm-3 me-md-4">
                            <h3 class="h4 pb-1 mb-2"><?= print_translation('support_question_heading') ?></h3>
                            <p class="pb-2 pb-sm-0 mb-sm-0"><?= print_translation('support_question_subtitle') ?></p>
                        </div>
                        <a class="btn btn-lg btn-primary" href="<?= htmlSC($supportContactUrl) ?>"><?= print_translation('support_contact_button') ?></a>
                    </div>
                </div>
            </article>
        </div>
    </section>
</main>
