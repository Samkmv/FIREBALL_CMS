<?php
$contactsHeading = site_setting('contacts_page_heading', return_translation('contacts_heading'));
$contactsSubheading = site_setting('contacts_page_subheading', return_translation('contacts_subheading'));
$contactsImage = site_setting('contacts_page_image', '/assets/img/slider/2.png');
$contactsPhoneCustomers = site_setting('contacts_phone_customers', '+1 50 537 53 082');
$contactsPhoneFranchise = site_setting('contacts_phone_franchise', '+1 50 537 53 000');
$contactsEmailCustomers = site_setting('contacts_email_customers', 'info@cartzilla.com');
$contactsEmailFranchise = site_setting('contacts_email_franchise', 'franchise@cartzilla.com');
$contactsLocationCity = site_setting('contacts_location_city', return_translation('contacts_location_city'));
$contactsLocationAddress = site_setting('contacts_location_address', return_translation('contacts_location_address'));
$contactsHoursWeekdays = site_setting('contacts_hours_weekdays', return_translation('contacts_hours_weekdays'));
$contactsHoursWeekends = site_setting('contacts_hours_weekends', return_translation('contacts_hours_weekends'));
$contactsSupportTitle = site_setting('contacts_support_title', return_translation('contacts_support_title'));
$contactsSupportText = site_setting('contacts_support_text', return_translation('contacts_support_text'));
$contactsImageUrl = filter_var($contactsImage, FILTER_VALIDATE_URL) ? $contactsImage : base_url($contactsImage);
$phoneHref = static function (string $value): string {
    $sanitized = preg_replace('/[^0-9+]/', '', $value) ?? '';

    return $sanitized !== '' ? $sanitized : $value;
};
?>
<main class="content-wrapper">
    <div class="container py-5 mb-2 mb-sm-3 mb-md-4 mb-lg-5 mt-lg-3 mt-xl-4">

        <h1 class="text-center"><?= htmlSC($contactsHeading) ?></h1>
        <p class="text-center pb-2 pb-sm-3"><?= htmlSC($contactsSubheading) ?></p>

        <section class="row row-cols-1 row-cols-md-2 g-0 overflow-hidden rounded-5">
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
                        <select class="form-select form-select-lg rounded-pill <?= get_validation_class('subject') ?>" id="subject" name="subject" required>
                            <option value="" <?= $selectedSubject === '' ? 'selected' : '' ?>><?= print_translation('contacts_subject_placeholder') ?></option>
                            <option value="<?= return_translation('contacts_subject_general_inquiry') ?>" <?= $selectedSubject === return_translation('contacts_subject_general_inquiry') ? 'selected' : '' ?>><?= print_translation('contacts_subject_general_inquiry') ?></option>
                            <option value="<?= return_translation('contacts_subject_order_status') ?>" <?= $selectedSubject === return_translation('contacts_subject_order_status') ? 'selected' : '' ?>><?= print_translation('contacts_subject_order_status') ?></option>
                            <option value="<?= return_translation('contacts_subject_product_information') ?>" <?= $selectedSubject === return_translation('contacts_subject_product_information') ? 'selected' : '' ?>><?= print_translation('contacts_subject_product_information') ?></option>
                            <option value="<?= return_translation('contacts_subject_technical_support') ?>" <?= $selectedSubject === return_translation('contacts_subject_technical_support') ? 'selected' : '' ?>><?= print_translation('contacts_subject_technical_support') ?></option>
                            <option value="<?= return_translation('contacts_subject_website_feedback') ?>" <?= $selectedSubject === return_translation('contacts_subject_website_feedback') ? 'selected' : '' ?>><?= print_translation('contacts_subject_website_feedback') ?></option>
                            <option value="<?= return_translation('contacts_subject_account_assistance') ?>" <?= $selectedSubject === return_translation('contacts_subject_account_assistance') ? 'selected' : '' ?>><?= print_translation('contacts_subject_account_assistance') ?></option>
                            <option value="<?= return_translation('contacts_subject_security_concerns') ?>" <?= $selectedSubject === return_translation('contacts_subject_security_concerns') ? 'selected' : '' ?>><?= print_translation('contacts_subject_security_concerns') ?></option>
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

            <div class="col position-relative bg-body-tertiary" style="min-height: 320px;">
                <img src="<?= htmlSC($contactsImageUrl) ?>" class="position-absolute top-0 start-0 w-100 h-100 object-fit-cover" alt="<?= htmlSC(return_translation('contacts_image_alt')) ?>">
            </div>
        </section>

        <section class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-4 pt-5 pb-3 pb-md-4 pb-lg-3 mt-lg-0 mt-xxl-4">
            <div class="col text-center pt-1 pt-sm-2 pt-md-3">
                <div class="position-relative d-inline-block bg-body-tertiary text-dark-emphasis fs-xl rounded-circle p-4 mb-3">
                    <i class="ci-phone-outgoing position-absolute top-50 start-50 translate-middle"></i>
                </div>
                <h3 class="h6"><?= print_translation('contacts_block_call_title') ?></h3>
                <ul class="list-unstyled m-0">
                    <li class="nav animate-underline justify-content-center">
                        <?= print_translation('contacts_label_customers') ?>:
                        <a class="nav-link animate-target fs-base ms-1 p-0" href="tel:<?= htmlSC($phoneHref($contactsPhoneCustomers)) ?>"><?= htmlSC($contactsPhoneCustomers) ?></a>
                    </li>
                    <li class="nav animate-underline justify-content-center">
                        <?= print_translation('contacts_label_franchise') ?>:
                        <a class="nav-link animate-target fs-base ms-1 p-0" href="tel:<?= htmlSC($phoneHref($contactsPhoneFranchise)) ?>"><?= htmlSC($contactsPhoneFranchise) ?></a>
                    </li>
                </ul>
            </div>
            <div class="col text-center pt-1 pt-sm-2 pt-md-3">
                <div class="position-relative d-inline-block bg-body-tertiary text-dark-emphasis fs-xl rounded-circle p-4 mb-3">
                    <i class="ci-mail position-absolute top-50 start-50 translate-middle"></i>
                </div>
                <h3 class="h6"><?= print_translation('contacts_block_message_title') ?></h3>
                <ul class="list-unstyled m-0">
                    <li class="nav animate-underline justify-content-center">
                        <?= print_translation('contacts_label_customers') ?>:
                        <a class="nav-link animate-target fs-base ms-1 p-0" href="mailto:<?= htmlSC($contactsEmailCustomers) ?>"><?= htmlSC($contactsEmailCustomers) ?></a>
                    </li>
                    <li class="nav animate-underline justify-content-center">
                        <?= print_translation('contacts_label_franchise') ?>:
                        <a class="nav-link animate-target fs-base ms-1 p-0" href="mailto:<?= htmlSC($contactsEmailFranchise) ?>"><?= htmlSC($contactsEmailFranchise) ?></a>
                    </li>
                </ul>
            </div>
            <div class="col text-center pt-1 pt-sm-2 pt-md-3">
                <div class="position-relative d-inline-block bg-body-tertiary text-dark-emphasis fs-xl rounded-circle p-4 mb-3">
                    <i class="ci-map-pin position-absolute top-50 start-50 translate-middle"></i>
                </div>
                <h3 class="h6"><?= print_translation('contacts_block_location_title') ?></h3>
                <ul class="list-unstyled m-0">
                    <li><?= htmlSC($contactsLocationCity) ?></li>
                    <li><?= htmlSC($contactsLocationAddress) ?></li>
                </ul>
            </div>
            <div class="col text-center pt-1 pt-sm-2 pt-md-3">
                <div class="position-relative d-inline-block bg-body-tertiary text-dark-emphasis fs-xl rounded-circle p-4 mb-3">
                    <i class="ci-clock position-absolute top-50 start-50 translate-middle"></i>
                </div>
                <h3 class="h6"><?= print_translation('contacts_block_hours_title') ?></h3>
                <ul class="list-unstyled m-0">
                    <li><?= htmlSC($contactsHoursWeekdays) ?></li>
                    <li><?= htmlSC($contactsHoursWeekends) ?></li>
                </ul>
            </div>
        </section>

        <hr class="my-lg-5">

        <section class="text-center pb-xxl-3 pt-4 pt-lg-3">
            <h2 class="pt-md-2 pt-lg-0"><?= htmlSC($contactsSupportTitle) ?></h2>
            <p class="pb-2 pb-sm-3"><?= htmlSC($contactsSupportText) ?></p>
            <a class="btn btn-lg btn-outline-dark rounded-pill" href="<?= check_auth() ? base_href('/chat') : base_href('/login') ?>"><?= print_translation('contacts_support_button') ?></a>
        </section>
    </div>
</main>
