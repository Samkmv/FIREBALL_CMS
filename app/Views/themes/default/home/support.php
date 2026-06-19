<?php
$search = trim((string)($search ?? ''));
$faqItems = (array)($faq_items ?? []);
$kbCategories = (array)($kb_categories ?? []);
$supportCategories = array_values(array_filter(array_map(
    static fn(mixed $category): string => trim((string)$category),
    (array)($support_categories ?? [])
)));
?>
<main class="content-wrapper">
    <section class="container pt-3 pt-sm-4">
        <div class="position-relative px-4 px-sm-5 px-xl-0 py-5">
            <span class="position-absolute top-0 start-0 w-100 h-100 rounded-5 d-none-dark rtl-flip" style="background: linear-gradient(-90deg, #accbee 0%, #e7f0fd 100%)"></span>
            <span class="position-absolute top-0 start-0 w-100 h-100 rounded-5 d-none d-block-dark rtl-flip" style="background: linear-gradient(-90deg, #1b273a 0%, #1f2632 100%)"></span>
            <div class="position-relative z-1">
                <h1 class="h2 text-center pt-md-2 pt-lg-3 pt-xl-4 mb-4"><?= print_translation('support_heading') ?></h1>
                <form class="position-relative mx-auto mb-4" style="max-width: 545px" action="<?= base_href('/support') ?>" method="get">
                    <input type="search" class="form-control form-control-lg" name="q" value="<?= htmlSC($search) ?>" placeholder="<?= htmlSC(return_translation('support_search_placeholder')) ?>" aria-label="<?= htmlSC(return_translation('support_search_label')) ?>">
                </form>
            </div>
        </div>
    </section>

    <section class="container pt-4">
        <?php if (empty($kbCategories)): ?>
            <div class="text-center text-body-secondary py-5"><?= print_translation('support_empty') ?></div>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4 g-sm-3 g-md-4">
                <?php foreach ($kbCategories as $category): ?>
                    <div class="col">
                        <div class="card h-100 bg-body-tertiary border-0 p-md-2">
                            <div class="card-body">
                                <h3 class="h5 mb-4">
                                    <?= htmlSC($category['name']) ?>
                                </h3>
                                <ul class="nav flex-column gap-3">
                                    <?php foreach ((array)$category['articles'] as $article): ?>
                                        <li>
                                            <a class="nav-link hover-effect-underline fw-normal p-0" href="<?= base_href('/support/articles/' . $article['slug']) ?>"><?= htmlSC($article['title']) ?></a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <hr class="my-0 my-sm-2 my-md-3 my-lg-4">

    <section class="container py-5 mb-1 mb-sm-2 mb-md-3 mb-lg-4 mb-xl-5">
        <div class="row pt-xl-2">
            <div class="col-md-4 col-xl-3 mb-4 mb-md-0" style="margin-top: -120px">
                <div class="sticky-md-top text-center text-md-start pe-md-4 pe-lg-5 pe-xl-0" style="padding-top: 120px;">
                    <h2><?= print_translation('support_faq_heading') ?></h2>
                    <p class="pb-2 pb-md-3"><?= print_translation('support_faq_subtitle') ?></p>
                    <a class="btn btn-lg btn-primary" href="#support-question-form"><?= print_translation('support_contact_button') ?></a>
                </div>
            </div>
            <div class="col-md-8 offset-xl-1">
                <?php if (empty($faqItems)): ?>
                    <div class="text-body-secondary py-4"><?= print_translation('support_faq_empty') ?></div>
                <?php else: ?>
                    <div class="accordion" id="support-faq">
                        <?php foreach ($faqItems as $index => $item): ?>
                            <?php $isOpen = $index === 0; ?>
                            <div class="accordion-item">
                                <h3 class="accordion-header" id="supportFaqHeading-<?= $index ?>">
                                    <button type="button" class="accordion-button hover-effect-underline <?= $isOpen ? '' : 'collapsed' ?>" data-bs-toggle="collapse" data-bs-target="#supportFaqCollapse-<?= $index ?>" aria-expanded="<?= $isOpen ? 'true' : 'false' ?>" aria-controls="supportFaqCollapse-<?= $index ?>">
                                        <span class="me-2"><?= htmlSC($item['question']) ?></span>
                                    </button>
                                </h3>
                                <div class="accordion-collapse collapse <?= $isOpen ? 'show' : '' ?>" id="supportFaqCollapse-<?= $index ?>" aria-labelledby="supportFaqHeading-<?= $index ?>" data-bs-parent="#support-faq">
                                    <div class="accordion-body"><?= nl2br(htmlSC($item['answer'])) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="container pb-5 mb-2 mb-md-4" id="support-question-form">
        <div class="row row-cols-1 row-cols-lg-2 g-0 overflow-hidden rounded-5 bg-body-tertiary">
            <div class="col p-4 p-md-5">
                <h2 class="h3 mb-3"><?= print_translation('support_question_heading') ?></h2>
                <p class="text-body-secondary mb-0"><?= print_translation('support_question_subtitle') ?></p>
            </div>
            <div class="col p-4 p-md-5">
                <form class="needs-validation" action="<?= base_href('/support') ?>" method="post" novalidate data-contact-form>
                    <?= get_csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="support-name"><?= print_translation('support_form_name') ?> *</label>
                        <input id="support-name" class="form-control rounded-pill <?= get_validation_class('name') ?>" type="text" name="name" value="<?= old('name') ?>" required>
                        <?= get_errors('name') ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="support-email"><?= print_translation('support_form_email') ?> *</label>
                        <input id="support-email" class="form-control rounded-pill <?= get_validation_class('email') ?>" type="email" name="email" value="<?= old('email') ?>" required>
                        <?= get_errors('email') ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="support-subject"><?= print_translation('support_form_subject') ?> *</label>
                        <?php $selectedSubject = old('subject'); ?>
                        <select id="support-subject" class="form-select rounded-pill <?= get_validation_class('subject') ?>" name="subject" required>
                            <option value="" <?= $selectedSubject === '' ? 'selected' : '' ?>><?= print_translation('support_form_subject_placeholder') ?></option>
                            <?php foreach ($supportCategories as $subject): ?>
                                <option value="<?= htmlSC($subject) ?>" <?= $selectedSubject === $subject ? 'selected' : '' ?>><?= htmlSC($subject) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= get_errors('subject') ?>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="support-message"><?= print_translation('support_form_message') ?> *</label>
                        <textarea id="support-message" class="form-control rounded-6 <?= get_validation_class('message') ?>" name="message" rows="5" required><?= old('message') ?></textarea>
                        <?= get_errors('message') ?>
                    </div>
                    <button class="btn btn-lg btn-dark rounded-pill" type="submit"><?= print_translation('support_question_submit') ?></button>
                </form>
            </div>
        </div>
    </section>
</main>
