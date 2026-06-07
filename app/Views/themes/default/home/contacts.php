<?php
$contactsHeading = site_setting('contacts_page_heading', return_translation('contacts_heading'));
$contactsSubheading = site_setting('contacts_page_subheading', return_translation('contacts_subheading'));
$contactsImage = site_setting('contacts_page_image', '');
$contactsPhoneCustomers = site_setting('contacts_phone_customers', '');
$contactsPhoneSupport = site_setting('contacts_phone_support', site_setting('contacts_phone_franchise', ''));
$contactsEmailCustomers = site_setting('contacts_email_customers', '');
$contactsEmailSupport = site_setting('contacts_email_support', site_setting('contacts_email_franchise', ''));
$contactsLocationCity = site_setting('contacts_location_city', '');
$contactsLocationAddress = site_setting('contacts_location_address', '');
$contactsHoursWeekdays = site_setting('contacts_hours_weekdays', '');
$contactsHoursWeekends = site_setting('contacts_hours_weekends', '');
$contactsSupportTitle = site_setting('contacts_support_title', '');
$contactsSupportText = site_setting('contacts_support_text', '');
$contactSubjects = array_values(array_filter(array_map(
    static fn(mixed $subject): string => trim((string)$subject),
    (array)($contact_subjects ?? [])
)));
$contactsImageUrl = $contactsImage !== ''
    ? (filter_var($contactsImage, FILTER_VALIDATE_URL) ? $contactsImage : base_url($contactsImage))
    : '';
$phoneHref = static function (string $value): string {
    $sanitized = preg_replace('/[^0-9+]/', '', $value) ?? '';

    return $sanitized !== '' ? $sanitized : $value;
};
$contactBlocks = [
    [
        'is_visible' => $contactsPhoneCustomers !== '' || $contactsPhoneSupport !== '',
        'icon' => 'ci-phone-outgoing',
        'title' => return_translation('contacts_block_call_title'),
        'items' => [
            [
                'value' => $contactsPhoneCustomers,
                'label' => return_translation('contacts_label_customers'),
                'href' => 'tel:' . $phoneHref($contactsPhoneCustomers),
            ],
            [
                'value' => $contactsPhoneSupport,
                'label' => return_translation('contacts_label_support'),
                'href' => 'tel:' . $phoneHref($contactsPhoneSupport),
            ],
        ],
    ],
    [
        'is_visible' => $contactsEmailCustomers !== '' || $contactsEmailSupport !== '',
        'icon' => 'ci-mail',
        'title' => return_translation('contacts_block_message_title'),
        'items' => [
            [
                'value' => $contactsEmailCustomers,
                'label' => return_translation('contacts_label_customers'),
                'href' => 'mailto:' . $contactsEmailCustomers,
            ],
            [
                'value' => $contactsEmailSupport,
                'label' => return_translation('contacts_label_support'),
                'href' => 'mailto:' . $contactsEmailSupport,
            ],
        ],
    ],
    [
        'is_visible' => $contactsLocationCity !== '' || $contactsLocationAddress !== '',
        'icon' => 'ci-map-pin',
        'title' => return_translation('contacts_block_location_title'),
        'items' => [
            ['value' => $contactsLocationCity, 'label' => '', 'href' => ''],
            ['value' => $contactsLocationAddress, 'label' => '', 'href' => ''],
        ],
    ],
    [
        'is_visible' => $contactsHoursWeekdays !== '' || $contactsHoursWeekends !== '',
        'icon' => 'ci-clock',
        'title' => return_translation('contacts_block_hours_title'),
        'items' => [
            ['value' => $contactsHoursWeekdays, 'label' => '', 'href' => ''],
            ['value' => $contactsHoursWeekends, 'label' => '', 'href' => ''],
        ],
    ],
];
$visibleContactBlocks = array_values(array_filter($contactBlocks, static fn(array $block): bool => (bool)$block['is_visible']));
$hasSupportBlock = $contactsSupportTitle !== '' || $contactsSupportText !== '';
?>
<main class="content-wrapper">
    <div class="container py-5 mb-2 mb-sm-3 mb-md-4 mb-lg-5 mt-lg-3 mt-xl-4">

        <h1 class="text-center"><?= htmlSC($contactsHeading) ?></h1>
        <p class="text-center pb-2 pb-sm-3"><?= htmlSC($contactsSubheading) ?></p>

        <section class="row row-cols-1 <?= $contactsImageUrl !== '' ? 'row-cols-md-2' : '' ?> g-0 overflow-hidden rounded-5">
            <div class="col bg-body-tertiary py-5 px-4 px-xl-5">
                <form class="needs-validation py-md-2 px-md-1 px-lg-3 mx-lg-3" action="<?= base_href('/contacts') ?>" method="post" novalidate data-contact-form>
                    <?= get_csrf_field() ?>
                    <div class="position-relative mb-4">
                        <label for="name" class="form-label"><?= print_translation('contacts_form_name') ?> *</label>
                        <input type="text" class="form-control form-control-lg rounded-pill <?= get_validation_class('name') ?>" id="name" name="name" value="<?= old('name') ?>" required>
                        <div class="invalid-tooltip bg-transparent z-0 py-0 ps-3"><?= print_translation('contacts_validation_name') ?></div>
                        <?= get_errors('name') ?>
                    </div>
                    <div class="position-relative mb-4">
                        <label for="email" class="form-label"><?= print_translation('contacts_form_email') ?> *</label>
                        <input type="email" class="form-control form-control-lg rounded-pill <?= get_validation_class('email') ?>" id="email" name="email" value="<?= old('email') ?>" required>
                        <div class="invalid-tooltip bg-transparent z-0 py-0 ps-3"><?= print_translation('contacts_validation_email') ?></div>
                        <?= get_errors('email') ?>
                    </div>
                    <div class="position-relative mb-4">
                        <label for="subject" class="form-label"><?= print_translation('contacts_form_subject') ?> *</label>
                        <?php $selectedSubject = old('subject'); ?>
                        <select class="form-select form-select-lg rounded-pill <?= get_validation_class('subject') ?>" id="subject" name="subject" data-select aria-label="<?= htmlSC(return_translation('contacts_form_subject')) ?>" required>
                            <option value="" <?= $selectedSubject === '' ? 'selected' : '' ?>><?= print_translation('contacts_subject_placeholder') ?></option>
                            <?php foreach ($contactSubjects as $subject): ?>
                                <option value="<?= htmlSC($subject) ?>" <?= $selectedSubject === $subject ? 'selected' : '' ?>><?= htmlSC($subject) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-tooltip bg-transparent z-0 py-0 ps-3"><?= print_translation('contacts_validation_subject') ?></div>
                        <?= get_errors('subject') ?>
                    </div>
                    <div class="position-relative mb-4">
                        <label for="message" class="form-label"><?= print_translation('contacts_form_message') ?> *</label>
                        <textarea class="form-control form-control-lg rounded-6 <?= get_validation_class('message') ?>" id="message" name="message" rows="5" required><?= old('message') ?></textarea>
                        <div class="invalid-tooltip bg-transparent z-0 py-0 ps-3"><?= print_translation('contacts_validation_message') ?></div>
                        <?= get_errors('message') ?>
                    </div>
                    <div class="pt-2">
                        <button type="submit" class="btn btn-lg btn-dark rounded-pill"><?= print_translation('contacts_form_submit') ?></button>
                    </div>
                </form>
            </div>

            <?php if ($contactsImageUrl !== ''): ?>
                <div class="col position-relative bg-body-tertiary" style="min-height: 320px;">
                    <img src="<?= htmlSC($contactsImageUrl) ?>" class="position-absolute top-0 start-0 w-100 h-100 object-fit-cover" alt="<?= htmlSC(return_translation('contacts_image_alt')) ?>">
                </div>
            <?php endif; ?>
        </section>

        <?php if (!empty($visibleContactBlocks)): ?>
            <section class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-4 pt-5 pb-3 pb-md-4 pb-lg-3 mt-lg-0 mt-xxl-4">
                <?php foreach ($visibleContactBlocks as $block): ?>
                    <div class="col text-center pt-1 pt-sm-2 pt-md-3">
                        <div class="position-relative d-inline-block bg-body-tertiary text-dark-emphasis fs-xl rounded-circle p-4 mb-3">
                            <i class="<?= htmlSC($block['icon']) ?> position-absolute top-50 start-50 translate-middle"></i>
                        </div>
                        <h3 class="h6"><?= htmlSC($block['title']) ?></h3>
                        <ul class="list-unstyled m-0">
                            <?php foreach ($block['items'] as $item): ?>
                                <?php if (trim((string)$item['value']) === '') continue; ?>
                                <li class="<?= $item['href'] !== '' ? 'nav animate-underline justify-content-center' : '' ?>">
                                    <?php if ($item['label'] !== ''): ?>
                                        <?= htmlSC($item['label']) ?>:
                                    <?php endif; ?>
                                    <?php if ($item['href'] !== ''): ?>
                                        <a class="nav-link animate-target fs-base ms-1 p-0" href="<?= htmlSC($item['href']) ?>"><?= htmlSC($item['value']) ?></a>
                                    <?php else: ?>
                                        <?= htmlSC($item['value']) ?>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <?php if ($hasSupportBlock): ?>
            <hr class="my-lg-5">

            <section class="text-center pb-xxl-3 pt-4 pt-lg-3">
                <?php if ($contactsSupportTitle !== ''): ?>
                    <h2 class="pt-md-2 pt-lg-0"><?= htmlSC($contactsSupportTitle) ?></h2>
                <?php endif; ?>
                <?php if ($contactsSupportText !== ''): ?>
                    <p class="pb-2 pb-sm-3"><?= htmlSC($contactsSupportText) ?></p>
                <?php endif; ?>
                <a class="btn btn-lg btn-outline-dark rounded-pill" href="<?= check_auth() ? base_href('/chat') : base_href('/login') ?>"><?= print_translation('contacts_support_button') ?></a>
            </section>
        <?php endif; ?>
    </div>
</main>
