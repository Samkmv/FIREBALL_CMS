<?php
$formData = session()->get('form_data') ?: [];
$value = static function (string $key, string $default = '') use ($formData, $settings): string {
    return (string)($formData[$key] ?? ($settings[$key] ?? $default));
};
$cookieEnabled = $value('cookie_enabled', '0') === '1';
$cookieMessage = $value(
    'cookie_message',
    'Мы используем файлы cookie для корректной работы сайта. Продолжая пользоваться сайтом, вы соглашаетесь с их использованием.'
);
$cookieButtonText = $value('cookie_button_text', 'Принять');
$cookiePolicyPageId = (int)$value('cookie_policy_page_id', '0');
$cookiePolicyUseOnRegistration = $value('cookie_policy_use_on_registration', '0') === '1';
$cookieStyle = in_array($value('cookie_style', 'card'), ['card', 'bar'], true)
    ? $value('cookie_style', 'card')
    : 'card';
$cookiePosition = in_array($value('cookie_position', 'bottom_right'), ['bottom_right', 'bottom_left', 'bottom_center', 'top'], true)
    ? $value('cookie_position', 'bottom_right')
    : 'bottom_right';
$cookieExpirationDays = max(1, (int)$value('cookie_expiration_days', '365'));
$publishedPages = (array)($published_pages ?? []);
$selectedPolicyUrl = '';
foreach ($publishedPages as $publishedPage) {
    if ((int)($publishedPage['id'] ?? 0) === $cookiePolicyPageId) {
        $selectedPolicyUrl = base_href('/' . ltrim((string)($publishedPage['slug'] ?? ''), '/'));
        break;
    }
}
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => return_translation('admin_privacy_heading'),
    'subtitle' => return_translation('admin_privacy_subtitle'),
    'actions' => '',
]) ?>

    <?= view()->renderPartial('admin/settings_tabs', ['active' => 'privacy']) ?>

    <form action="<?= base_href('/admin/settings/privacy') ?>" method="post" data-cookie-consent-settings>
        <?= get_csrf_field() ?>
        <div class="row g-4 align-items-start">
            <div class="col-xl-7">
                <div class="border rounded-5 p-3 p-md-4">
                    <div class="form-check form-switch mb-4">
                        <input type="hidden" name="cookie_enabled" value="0">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            role="switch"
                            id="cookie-enabled"
                            name="cookie_enabled"
                            value="1"
                            data-cookie-preview-enabled
                            <?= $cookieEnabled ? 'checked' : '' ?>
                        >
                        <label class="form-check-label fw-semibold" for="cookie-enabled">
                            <?= print_translation('admin_privacy_cookie_enabled') ?>
                        </label>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="cookie-message"><?= print_translation('admin_privacy_cookie_message') ?></label>
                        <textarea
                            class="form-control <?= get_validation_class('cookie_message') ?>"
                            id="cookie-message"
                            name="cookie_message"
                            rows="5"
                            maxlength="2000"
                            data-cookie-preview-message
                            required
                        ><?= htmlSC($cookieMessage) ?></textarea>
                        <?= get_errors('cookie_message') ?>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label" for="cookie-button-text"><?= print_translation('admin_privacy_cookie_button_text') ?></label>
                            <input
                                class="form-control <?= get_validation_class('cookie_button_text') ?>"
                                id="cookie-button-text"
                                type="text"
                                name="cookie_button_text"
                                value="<?= htmlSC($cookieButtonText) ?>"
                                maxlength="100"
                                data-cookie-preview-button
                                required
                            >
                            <?= get_errors('cookie_button_text') ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="cookie-expiration-days"><?= print_translation('admin_privacy_cookie_expiration') ?></label>
                            <div class="input-group">
                                <input
                                    class="form-control <?= get_validation_class('cookie_expiration_days') ?>"
                                    id="cookie-expiration-days"
                                    type="number"
                                    name="cookie_expiration_days"
                                    value="<?= $cookieExpirationDays ?>"
                                    min="1"
                                    max="3650"
                                    step="1"
                                    required
                                >
                                <span class="input-group-text"><?= print_translation('admin_privacy_days') ?></span>
                            </div>
                            <?= get_errors('cookie_expiration_days') ?>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="cookie-policy-page"><?= print_translation('admin_privacy_policy_page') ?></label>
                        <select
                            class="form-select <?= get_validation_class('cookie_policy_page_id') ?>"
                            id="cookie-policy-page"
                            name="cookie_policy_page_id"
                            data-cookie-preview-policy
                        >
                            <option value="0" data-url=""><?= print_translation('admin_privacy_policy_page_none') ?></option>
                            <?php foreach ($publishedPages as $publishedPage): ?>
                                <?php $pageUrl = base_href('/' . ltrim((string)$publishedPage['slug'], '/')); ?>
                                <option
                                    value="<?= (int)$publishedPage['id'] ?>"
                                    data-url="<?= htmlSC($pageUrl) ?>"
                                    <?= $cookiePolicyPageId === (int)$publishedPage['id'] ? 'selected' : '' ?>
                                >
                                    <?= htmlSC((string)$publishedPage['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text"><?= print_translation('admin_privacy_policy_page_hint') ?></div>
                        <?= get_errors('cookie_policy_page_id') ?>
                        <div class="form-check form-switch mt-3">
                            <input type="hidden" name="cookie_policy_use_on_registration" value="0">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                role="switch"
                                id="cookie-policy-use-on-registration"
                                name="cookie_policy_use_on_registration"
                                value="1"
                                data-cookie-policy-registration
                                <?= $cookiePolicyUseOnRegistration ? 'checked' : '' ?>
                                <?= $cookiePolicyPageId <= 0 ? 'disabled' : '' ?>
                            >
                            <label class="form-check-label" for="cookie-policy-use-on-registration">
                                <?= print_translation('admin_privacy_policy_use_on_registration') ?>
                            </label>
                            <div class="form-text"><?= print_translation('admin_privacy_policy_use_on_registration_hint') ?></div>
                            <?= get_errors('cookie_policy_use_on_registration') ?>
                        </div>
                    </div>

                    <fieldset class="mb-4">
                        <legend class="form-label mb-3"><?= print_translation('admin_privacy_style') ?></legend>
                        <div class="row g-2">
                            <?php foreach (['card', 'bar'] as $style): ?>
                                <div class="col-sm-6">
                                    <label class="cookie-settings-option">
                                        <input
                                            class="form-check-input"
                                            type="radio"
                                            name="cookie_style"
                                            value="<?= $style ?>"
                                            data-cookie-preview-style
                                            <?= $cookieStyle === $style ? 'checked' : '' ?>
                                        >
                                        <span>
                                            <strong><?= print_translation('admin_privacy_style_' . $style) ?></strong>
                                            <small><?= print_translation('admin_privacy_style_' . $style . '_hint') ?></small>
                                        </span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>

                    <fieldset class="mb-4">
                        <legend class="form-label mb-3"><?= print_translation('admin_privacy_position') ?></legend>
                        <div class="row g-2">
                            <?php foreach (['bottom_right', 'bottom_left', 'bottom_center', 'top'] as $position): ?>
                                <div class="col-sm-6">
                                    <label class="cookie-settings-option">
                                        <input
                                            class="form-check-input"
                                            type="radio"
                                            name="cookie_position"
                                            value="<?= $position ?>"
                                            data-cookie-preview-position
                                            <?= $cookiePosition === $position ? 'checked' : '' ?>
                                        >
                                        <span><strong><?= print_translation('admin_privacy_position_' . $position) ?></strong></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>

                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" type="submit">
                            <i class="ci-save"></i><?= print_translation('admin_btn_save') ?>
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-xl-5">
                <div class="position-sticky cookie-preview-sticky">
                    <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                        <h2 class="h5 mb-0"><?= print_translation('admin_privacy_preview') ?></h2>
                        <span class="badge rounded-pill text-bg-secondary" data-cookie-preview-status>
                            <?= $cookieEnabled ? print_translation('admin_privacy_preview_enabled') : print_translation('admin_privacy_preview_disabled') ?>
                        </span>
                    </div>
                    <div
                        class="cookie-preview-stage"
                        data-cookie-preview-stage
                        data-enabled-label="<?= htmlSC(return_translation('admin_privacy_preview_enabled')) ?>"
                        data-disabled-label="<?= htmlSC(return_translation('admin_privacy_preview_disabled')) ?>"
                    >
                        <div
                            class="cookie-preview-banner cookie-preview-banner--<?= htmlSC($cookieStyle) ?> cookie-preview-banner--<?= htmlSC(str_replace('_', '-', $cookiePosition)) ?>"
                            data-cookie-preview-banner
                        >
                            <p data-cookie-preview-message-output><?= nl2br(htmlSC($cookieMessage)) ?></p>
                            <div class="cookie-preview-actions">
                                <button type="button" class="btn btn-sm btn-primary rounded-pill" data-cookie-preview-button-output><?= htmlSC($cookieButtonText) ?></button>
                                <a
                                    href="<?= htmlSC($selectedPolicyUrl !== '' ? $selectedPolicyUrl : '#') ?>"
                                    class="btn btn-sm btn-outline-secondary rounded-pill <?= $selectedPolicyUrl === '' ? 'd-none' : '' ?>"
                                    data-cookie-preview-policy-output
                                ><?= print_translation('cookie_consent_more') ?></a>
                            </div>
                        </div>
                    </div>
                    <p class="small text-body-secondary mt-3 mb-0"><?= print_translation('admin_privacy_preview_hint') ?></p>
                </div>
            </div>
        </div>
    </form>

<?= view()->renderPartial('admin/shell_close') ?>
