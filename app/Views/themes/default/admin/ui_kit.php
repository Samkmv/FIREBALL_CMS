<?php
$tr = static fn(string $key): string => return_translation('admin_ui_kit_' . $key);
$t = static fn(string $key): string => htmlSC($tr($key));
$sections = ['foundations', 'geometry', 'buttons', 'forms', 'choices', 'validation', 'cards', 'tables', 'navigation', 'feedback', 'overlays', 'loading', 'icons', 'reuse'];
$sample = static function (string $id, string $titleKey, string $hint = '', ?string $code = null) use ($tr): void {
    $content = (string)ob_get_clean();
    echo view()->renderPartial('admin/partials/ui_sample', [
        'id' => 'ui-code-' . $id,
        'title' => $tr($titleKey),
        'hint' => $hint,
        'content' => $content,
        'code' => $code ?? $content,
    ]);
};
$openSection = static function (string $key, string $keywords = '') use ($t): void {
    echo '<section class="fb-ui-section" id="ui-' . htmlSC($key) . '" data-ui-section data-ui-keywords="' . htmlSC($key . ' ' . $keywords) . '">';
    echo '<h2>' . $t($key) . '</h2><div class="fb-ui-grid">';
};
$closeSection = static function (): void { echo '</div></section>'; };
?>
<?= view()->renderPartial('admin/shell_open', [
    'title' => $tr('title'),
    'subtitle' => $tr('subtitle'),
]) ?>

<div class="fb-ui-kit" data-ui-kit
     data-count-label="<?= $t('count') ?>"
     data-copied-label="<?= $t('copied') ?>"
     data-copy-error-label="<?= $t('copy_error') ?>"
     data-demo-message="<?= $t('demo_message') ?>"
     data-invalid-label="<?= $t('invalid') ?>"
     data-selection-label="<?= $t('selection') ?>">
    <div class="fb-card">
        <div class="fb-card-body">
            <p class="mb-2"><?= $t('intro') ?></p>
            <p class="small text-body-secondary mb-4"><?= $t('demo_notice') ?></p>
            <div class="fb-ui-toolbar">
                <div class="fb-ui-search position-relative">
                    <label class="visually-hidden" for="ui-search"><?= $t('search') ?></label>
                    <i class="ci-search" aria-hidden="true"></i>
                    <input class="form-control" id="ui-search" type="search" placeholder="<?= $t('search_placeholder') ?>" data-ui-search aria-controls="ui-foundations ui-geometry ui-buttons ui-forms ui-choices ui-validation ui-cards ui-tables ui-navigation ui-feedback ui-overlays ui-loading ui-icons ui-reuse">
                </div>
                <button type="button" class="btn btn-outline-secondary" data-ui-reset><?= $t('reset_search') ?></button>
                <span class="small text-body-secondary" data-ui-count role="status" aria-live="polite"></span>
            </div>
            <nav aria-label="<?= $t('index') ?>">
                <ul class="fb-ui-index">
                    <?php foreach ($sections as $section): ?>
                        <li data-ui-index="ui-<?= htmlSC($section) ?>"><a href="#ui-<?= htmlSC($section) ?>"><?= $t($section) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        </div>
    </div>
    <div class="fb-ui-status" data-ui-status role="status" aria-live="polite" aria-atomic="true"></div>
    <div class="fb-card fb-empty-state" data-ui-empty hidden>
        <span class="fb-empty-state-icon"><i class="ci-search" aria-hidden="true"></i></span>
        <p class="mb-0"><?= $t('empty_search') ?></p>
    </div>

    <?php $openSection('foundations', 'tokens colors typography палитра цвета шрифт theme'); ?>
    <div class="fb-ui-wide">
        <?php ob_start(); ?>
        <div class="fb-ui-tokens">
            <?php foreach (['primary', 'primary-hover', 'primary-active', 'primary-soft', 'success', 'success-soft', 'warning', 'warning-soft', 'danger', 'danger-soft', 'info', 'info-soft', 'purple', 'purple-soft', 'pink', 'pink-soft'] as $color): $token = '--fb-color-' . $color; ?>
                <div class="fb-ui-token">
                    <div class="fb-ui-token-swatch" style="background: var(<?= htmlSC($token) ?>)"></div>
                    <code><?= htmlSC($token) ?></code>
                    <span class="fb-ui-token-value" data-ui-token-value="<?= htmlSC($token) ?>"></span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php $sample('colors', 'colors', '', '.my-component { color: var(--fb-color-primary); background: var(--fb-color-primary-soft); }'); ?>
    </div>
    <?php ob_start(); ?>
    <div class="fb-ui-tokens">
        <?php foreach (['background', 'surface', 'surface-secondary', 'surface-elevated', 'text', 'text-secondary', 'text-tertiary', 'border', 'border-strong'] as $color): $token = '--fb-color-' . $color; ?>
            <div class="fb-ui-token">
                <div class="fb-ui-token-swatch" style="background: var(<?= htmlSC($token) ?>)"></div>
                <code><?= htmlSC($token) ?></code>
                <span class="fb-ui-token-value" data-ui-token-value="<?= htmlSC($token) ?>"></span>
            </div>
        <?php endforeach; ?>
    </div>
    <?php $sample('surfaces', 'surfaces', '', '.my-component { background: var(--fb-color-surface); color: var(--fb-color-text); border: 1px solid var(--fb-color-border); }'); ?>
    <?php ob_start(); ?>
    <div class="d-grid gap-3">
        <h2 class="h3 mb-0"><?= $t('type_example') ?></h2>
        <h3 class="h5 mb-0"><?= $t('card_title') ?></h3>
        <p class="mb-0"><?= $t('body_example') ?></p>
        <p class="text-body-secondary mb-0"><?= $t('secondary_example') ?></p>
        <small><?= $t('field_hint') ?></small>
        <a href="#ui-reuse"><?= $t('link_example') ?> <i class="ci-arrow-right" aria-hidden="true"></i></a>
        <code>--fb-font-family</code>
    </div>
    <?php $sample('typography', 'type'); ?>
    <?php $closeSection(); ?>

    <?php $openSection('geometry', 'space radius shadow отступы скругления тени'); ?>
    <?php ob_start(); ?>
    <div class="d-grid gap-2">
        <?php for ($i = 1; $i <= 8; $i++): $token = '--fb-space-' . $i; ?>
            <div class="fb-ui-metric"><span class="fb-ui-space" style="width: var(<?= $token ?>)"></span><code><?= $token ?></code><span data-ui-token-value="<?= $token ?>"></span></div>
        <?php endfor; ?>
    </div>
    <?php $sample('spacing', 'spacing', '', '.my-component { padding: var(--fb-space-5); gap: var(--fb-space-4); }'); ?>
    <?php ob_start(); ?>
    <div class="d-grid gap-2">
        <?php foreach (['sm', 'md', 'lg', 'xl', 'pill'] as $size): $token = '--fb-radius-' . $size; ?>
            <div class="fb-ui-metric"><span class="fb-ui-radius" style="border-radius: var(<?= $token ?>)"></span><code><?= $token ?></code><span data-ui-token-value="<?= $token ?>"></span></div>
        <?php endforeach; ?>
    </div>
    <?php $sample('radii', 'radii', '', '.my-component { border-radius: var(--fb-radius-lg); }'); ?>
    <div class="fb-ui-wide">
        <?php ob_start(); ?>
        <div class="row g-4">
            <?php foreach (['sm', 'md', 'lg'] as $size): ?>
                <div class="col-md-4"><div class="fb-ui-shadow" style="box-shadow: var(--fb-shadow-<?= $size ?>)"><code>--fb-shadow-<?= $size ?></code></div></div>
            <?php endforeach; ?>
        </div>
        <?php $sample('shadows', 'shadows', '', '.my-component { box-shadow: var(--fb-shadow-md); transition: box-shadow var(--fb-transition-base); }'); ?>
    </div>
    <?php $closeSection(); ?>

    <?php $openSection('buttons', 'btn primary secondary outline size active disabled loading кнопки'); ?>
    <?php ob_start(); ?>
    <div class="d-flex flex-wrap gap-2">
        <?php foreach (['primary', 'secondary', 'success', 'danger', 'warning', 'info'] as $variant): ?>
            <button type="button" class="btn btn-<?= $variant ?>"><?= $t($variant) ?></button>
        <?php endforeach; ?>
        <button type="button" class="btn btn-light">Light</button>
        <button type="button" class="btn btn-dark">Dark</button>
        <button type="button" class="btn btn-link">Link</button>
    </div>
    <?php $sample('buttons-solid', 'solid'); ?>
    <?php ob_start(); ?>
    <div class="d-flex flex-wrap gap-2">
        <?php foreach (['primary', 'secondary', 'success', 'danger', 'warning', 'info'] as $variant): ?>
            <button type="button" class="btn btn-outline-<?= $variant ?>"><?= $t($variant) ?></button>
        <?php endforeach; ?>
    </div>
    <?php $sample('buttons-outline', 'outline'); ?>
    <?php ob_start(); ?>
    <div class="d-grid gap-3">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <button type="button" class="btn btn-primary btn-sm"><?= $t('small') ?></button>
            <button type="button" class="btn btn-primary"><?= $t('regular') ?></button>
            <button type="button" class="btn btn-primary btn-lg"><?= $t('large') ?></button>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-primary active" aria-pressed="true"><?= $t('active') ?></button>
            <button type="button" class="btn btn-primary" disabled><?= $t('disabled') ?></button>
            <button type="button" class="btn btn-outline-secondary" disabled><?= $t('disabled') ?></button>
            <button type="button" class="btn btn-primary" disabled aria-busy="true"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span><?= $t('loading_label') ?></button>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-primary"><i class="ci-plus me-2" aria-hidden="true"></i><?= $t('create') ?></button>
            <button type="button" class="btn btn-icon btn-outline-secondary" aria-label="<?= $t('edit') ?>"><i class="ci-edit" aria-hidden="true"></i></button>
            <button type="button" class="btn btn-icon btn-outline-danger" aria-label="<?= $t('delete') ?>"><i class="ci-trash" aria-hidden="true"></i></button>
        </div>
    </div>
    <?php $sample('buttons-sizes', 'sizes'); ?>
    <?php ob_start(); ?>
    <div class="d-grid gap-3">
        <div class="btn-group" role="group" aria-label="<?= $t('actions') ?>">
            <button type="button" class="btn btn-outline-secondary"><i class="ci-edit me-1" aria-hidden="true"></i><?= $t('edit') ?></button>
            <button type="button" class="btn btn-outline-secondary"><i class="ci-copy me-1" aria-hidden="true"></i><?= $t('copy') ?></button>
        </div>
        <div class="dropdown">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><?= $t('actions') ?></button>
            <ul class="dropdown-menu">
                <li><button type="button" class="dropdown-item"><i class="ci-edit me-2" aria-hidden="true"></i><?= $t('edit') ?></button></li>
                <li><button type="button" class="dropdown-item"><i class="ci-download me-2" aria-hidden="true"></i><?= $t('download') ?></button></li>
                <li><hr class="dropdown-divider"></li>
                <li><button type="button" class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#ui-confirm-modal"><?= $t('delete') ?></button></li>
            </ul>
        </div>
    </div>
    <?php $sample('buttons-groups', 'groups'); ?>
    <?php $closeSection(); ?>

    <?php $openSection('forms', 'input textarea password email number date time input-group upload color range'); ?>
    <?php ob_start(); ?>
    <div class="row g-3">
        <div class="col-sm-6"><label class="form-label" for="ui-name"><?= $t('name') ?></label><input class="form-control" id="ui-name" placeholder="<?= $t('name_placeholder') ?>" aria-describedby="ui-name-hint"><div class="form-text" id="ui-name-hint"><?= $t('field_hint') ?></div></div>
        <div class="col-sm-6"><label class="form-label" for="ui-email"><?= $t('email') ?></label><input class="form-control" type="email" id="ui-email" placeholder="demo@example.test"></div>
        <div class="col-sm-6">
            <?= view()->renderPartial('incs/password_field', ['id' => 'ui-password', 'name' => 'ui_demo_password', 'label' => $tr('password'), 'autocomplete' => 'off', 'placeholder' => '••••••••']) ?>
        </div>
        <div class="col-sm-6"><label class="form-label" for="ui-url">URL</label><input class="form-control" type="url" id="ui-url" placeholder="https://example.test"></div>
        <div class="col-12"><label class="form-label" for="ui-description"><?= $t('description_label') ?></label><textarea class="form-control" id="ui-description" rows="3" placeholder="<?= $t('secondary_example') ?>"></textarea></div>
    </div>
    <?php $sample('form-text', 'text_fields'); ?>
    <?php ob_start(); ?>
    <div class="row g-3">
        <div class="col-sm-6"><label class="form-label" for="ui-number"><?= $t('number') ?></label><input class="form-control" type="number" id="ui-number" min="0" max="100" value="10"></div>
        <div class="col-sm-6"><label class="form-label" for="ui-date"><?= $t('date') ?></label><input class="form-control" type="date" id="ui-date" value="2026-10-11"></div>
        <div class="col-sm-6"><label class="form-label" for="ui-time"><?= $t('time') ?></label><input class="form-control" type="time" id="ui-time" value="12:30"></div>
        <div class="col-sm-6"><label class="form-label" for="ui-datetime"><?= $t('datetime') ?></label><input class="form-control" type="datetime-local" id="ui-datetime" value="2026-10-11T12:30"></div>
    </div>
    <?php $sample('form-special', 'special_fields'); ?>
    <?php ob_start(); ?>
    <div class="d-grid gap-3">
        <div><label class="form-label" for="ui-group-email"><?= $t('email') ?></label><div class="input-group"><span class="input-group-text" id="ui-email-prefix">@</span><input class="form-control" id="ui-group-email" aria-describedby="ui-email-prefix" placeholder="demo"></div></div>
        <div><label class="form-label" for="ui-group-search"><?= $t('search') ?></label><div class="input-group"><input class="form-control" type="search" id="ui-group-search" placeholder="<?= $t('name_placeholder') ?>"><button type="button" class="btn btn-primary" aria-label="<?= $t('search') ?>"><i class="ci-search" aria-hidden="true"></i></button></div></div>
        <div><label class="form-label" for="ui-group-number"><?= $t('number') ?></label><div class="input-group"><input class="form-control" type="number" id="ui-group-number" value="25" aria-describedby="ui-number-unit"><span class="input-group-text" id="ui-number-unit">%</span></div></div>
    </div>
    <?php $sample('form-groups', 'input_groups'); ?>
    <?php ob_start(); ?>
    <div class="d-grid gap-3">
        <div><label class="form-label" for="ui-file"><?= $t('file') ?></label><input class="form-control" type="file" id="ui-file"></div>
        <div><label class="form-label" for="ui-color"><?= $t('color') ?></label><input class="form-control form-control-color" type="color" id="ui-color" value="#ff5a3c"></div>
        <div><label class="form-label" for="ui-range"><?= $t('range') ?>: <output for="ui-range" data-ui-range-value>60</output></label><input class="form-range" type="range" id="ui-range" min="0" max="100" value="60" data-ui-range></div>
    </div>
    <?php $sample('form-files', 'files'); ?>
    <?php ob_start(); ?>
    <div class="d-grid gap-3">
        <div><label class="form-label" for="ui-input-small"><?= $t('small') ?></label><input class="form-control form-control-sm" id="ui-input-small" placeholder="<?= $t('name_placeholder') ?>"></div>
        <div><label class="form-label" for="ui-input-regular"><?= $t('regular') ?></label><input class="form-control" id="ui-input-regular" placeholder="<?= $t('name_placeholder') ?>"></div>
        <div><label class="form-label" for="ui-input-large"><?= $t('large') ?></label><input class="form-control form-control-lg" id="ui-input-large" placeholder="<?= $t('name_placeholder') ?>"></div>
    </div>
    <?php $sample('form-sizes', 'field_sizes'); ?>
    <?php ob_start(); ?>
    <div class="d-grid gap-3">
        <div class="form-floating"><input class="form-control" type="email" id="ui-floating-email" placeholder="demo@example.test"><label for="ui-floating-email"><?= $t('email') ?></label></div>
        <div class="form-floating"><textarea class="form-control" id="ui-floating-description" placeholder="<?= $t('description_label') ?>"></textarea><label for="ui-floating-description"><?= $t('description_label') ?></label></div>
    </div>
    <?php $sample('floating-labels', 'floating_labels'); ?>
    <?php $closeSection(); ?>

    <?php $openSection('choices', 'select choices multiselect checkbox radio switch form-check'); ?>
    <?php ob_start(); ?>
    <div class="d-grid gap-3">
        <div><label class="form-label" for="ui-native-select"><?= $t('native_select') ?></label><select class="form-select" id="ui-native-select"><option value=""><?= $t('select_option') ?></option><?php foreach (['published', 'draft', 'archived'] as $status): ?><option value="<?= $status ?>"><?= $t($status) ?></option><?php endforeach; ?></select></div>
        <div><label class="form-label" for="ui-select-small"><?= $t('small') ?></label><select class="form-select form-select-sm" id="ui-select-small"><option><?= $t('published') ?></option><option><?= $t('draft') ?></option></select></div>
        <div><label class="form-label" for="ui-select-disabled"><?= $t('disabled') ?></label><select class="form-select" id="ui-select-disabled" disabled><option><?= $t('published') ?></option></select></div>
    </div>
    <?php $sample('select-native', 'native_select'); ?>
    <?php ob_start(); ?>
    <div class="d-grid gap-3">
        <div><label class="form-label" for="ui-search-select"><?= $t('enhanced_select') ?></label><select class="form-select" id="ui-search-select" data-select='{"searchEnabled":true}' data-select-floating><?php foreach (['published', 'draft', 'archived'] as $status): ?><option value="<?= $status ?>"><?= $t($status) ?></option><?php endforeach; ?></select></div>
        <div><label class="form-label" for="ui-multi-select"><?= $t('multiselect') ?></label><select class="form-select" id="ui-multi-select" multiple data-select='{"removeItemButton":true,"searchEnabled":true}' data-select-floating><?php foreach (['posts', 'users', 'plugins', 'requests'] as $index => $option): ?><option value="<?= $option ?>" <?= $index < 2 ? 'selected' : '' ?>><?= $t($option) ?></option><?php endforeach; ?></select></div>
    </div>
    <?php $sample('select-enhanced', 'enhanced_select', $tr('select_hint')); ?>
    <?php ob_start(); ?>
    <div class="d-grid gap-4">
        <div class="d-grid gap-2">
            <div class="form-check"><input class="form-check-input" type="checkbox" id="ui-check" checked><label class="form-check-label" for="ui-check"><?= $t('check_option') ?></label></div>
            <div class="form-check"><input class="form-check-input" type="checkbox" id="ui-check-disabled" disabled><label class="form-check-label" for="ui-check-disabled"><?= $t('disabled') ?></label></div>
        </div>
        <fieldset class="d-grid gap-2"><legend class="form-label"><?= $t('choices') ?></legend>
            <div class="form-check"><input class="form-check-input" type="radio" name="ui-demo-access" id="ui-radio-public" checked><label class="form-check-label" for="ui-radio-public"><?= $t('radio_option') ?></label></div>
            <div class="form-check"><input class="form-check-input" type="radio" name="ui-demo-access" id="ui-radio-private"><label class="form-check-label" for="ui-radio-private"><?= $t('radio_other') ?></label></div>
        </fieldset>
    </div>
    <?php $sample('checks', 'checks'); ?>
    <?php ob_start(); ?>
    <div class="d-grid gap-3">
        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="ui-switch-notifications" checked><label class="form-check-label" for="ui-switch-notifications"><?= $t('notifications') ?></label></div>
        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="ui-switch-autosave"><label class="form-check-label" for="ui-switch-autosave"><?= $t('auto_save') ?></label></div>
        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="ui-switch-disabled" checked disabled><label class="form-check-label" for="ui-switch-disabled"><?= $t('disabled') ?></label></div>
    </div>
    <?php $sample('switches', 'switches'); ?>
    <?php $closeSection(); ?>

    <?php $openSection('validation', 'is-valid is-invalid feedback readonly disabled required форма валидация ошибки'); ?>
    <?php ob_start(); ?>
    <div class="d-grid gap-3">
        <div><label class="form-label" for="ui-valid"><?= $t('valid') ?></label><input class="form-control is-valid" id="ui-valid" value="FIREBALL" aria-describedby="ui-valid-feedback"><div class="valid-feedback" id="ui-valid-feedback"><?= $t('valid') ?></div></div>
        <div><label class="form-label" for="ui-invalid"><?= $t('name') ?></label><input class="form-control is-invalid" id="ui-invalid" aria-invalid="true" aria-describedby="ui-invalid-feedback"><div class="invalid-feedback" id="ui-invalid-feedback"><?= $t('invalid') ?></div></div>
        <div><label class="form-label" for="ui-readonly"><?= $t('readonly') ?></label><input class="form-control" id="ui-readonly" value="FIREBALL CMS" readonly></div>
        <div><label class="form-label" for="ui-disabled"><?= $t('disabled') ?></label><input class="form-control" id="ui-disabled" value="FIREBALL CMS" disabled></div>
    </div>
    <?php $sample('field-states', 'field_states'); ?>
    <?php ob_start(); ?>
    <form data-ui-demo-form novalidate>
        <div class="mb-3"><label class="form-label" for="ui-required-name"><?= $t('name') ?> *</label><input class="form-control" id="ui-required-name" required aria-describedby="ui-required-name-feedback"><div class="invalid-feedback" id="ui-required-name-feedback"><?= $t('invalid') ?></div></div>
        <div class="mb-3"><label class="form-label" for="ui-required-email"><?= $t('email') ?> *</label><input class="form-control" type="email" id="ui-required-email" required aria-describedby="ui-required-email-feedback"><div class="invalid-feedback" id="ui-required-email-feedback"><?= $t('invalid') ?></div></div>
        <div class="mb-4"><label class="form-label" for="ui-required-status"><?= $t('status') ?> *</label><select class="form-select" id="ui-required-status" required aria-describedby="ui-required-status-feedback"><option value=""><?= $t('select_option') ?></option><option value="published"><?= $t('published') ?></option><option value="draft"><?= $t('draft') ?></option></select><div class="invalid-feedback" id="ui-required-status-feedback"><?= $t('invalid') ?></div></div>
        <div class="d-flex flex-wrap gap-2"><button type="submit" class="btn btn-primary"><?= $t('save') ?></button><button type="reset" class="btn btn-outline-secondary"><?= $t('reset') ?></button></div>
    </form>
    <?php $sample('live-validation', 'live_form', $tr('live_form_hint')); ?>
    <?php $closeSection(); ?>

    <?php $openSection('cards', 'card stat statistics quick actions dashboard activity system'); ?>
    <?php ob_start(); ?>
    <div class="fb-card">
        <div class="fb-card-header"><div><h4 class="fb-card-title"><?= $t('card_title') ?></h4><p class="fb-card-subtitle"><?= $t('card_subtitle') ?></p></div><button type="button" class="btn btn-sm btn-outline-secondary" aria-label="<?= $t('edit') ?>"><i class="ci-edit" aria-hidden="true"></i></button></div>
        <div class="fb-card-body"><p class="mb-0"><?= $t('card_body') ?></p></div>
        <div class="fb-card-footer"><button type="button" class="btn btn-primary"><?= $t('save') ?></button></div>
    </div>
    <?php $sample('basic-card', 'basic_card'); ?>
    <?php ob_start(); ?>
    <div class="fb-quick-actions">
        <?php foreach (['posts' => 'file-text', 'users' => 'user', 'settings' => 'settings'] as $label => $icon): ?>
            <a href="#ui-reuse" class="fb-quick-action" style="--fb-action-color: var(--fb-color-primary); --fb-action-soft: var(--fb-color-primary-soft)"><span class="fb-quick-action-icon"><i class="ci-<?= $icon ?>" aria-hidden="true"></i></span><span><?= $t($label) ?></span><i class="ci-chevron-right ms-auto" aria-hidden="true"></i></a>
        <?php endforeach; ?>
    </div>
    <?php $sample('quick-actions', 'quick_actions'); ?>
    <div class="fb-ui-wide">
        <?php ob_start(); ?>
        <div class="fb-stat-grid">
            <?php foreach ([['posts', 'file-text', '', '128'], ['users', 'user', 'is-blue', '64'], ['plugins', 'box', 'is-purple', '8'], ['requests', 'inbox', 'is-green', '12']] as [$label, $icon, $variant, $value]): ?>
                <a class="fb-stat-card <?= $variant ?>" href="#ui-reuse"><span class="fb-stat-icon"><i class="ci-<?= $icon ?>" aria-hidden="true"></i></span><span class="fb-stat-copy"><span class="fb-stat-value"><?= $value ?></span><span class="fb-stat-label"><?= $t($label) ?></span><span class="fb-stat-meta">Demo</span></span></a>
            <?php endforeach; ?>
        </div>
        <?php $sample('stat-cards', 'stats'); ?>
    </div>
    <?php ob_start(); ?>
    <ul class="fb-activity-list">
        <?php foreach (['activity_created' => 'file', 'activity_updated' => 'settings'] as $label => $icon): ?>
            <li class="fb-activity-item"><span class="fb-activity-icon"><i class="ci-<?= $icon ?>" aria-hidden="true"></i></span><div class="fb-activity-copy"><div class="fb-activity-title"><?= $t($label) ?></div><div class="fb-activity-meta"><?= $t('activity_time') ?></div></div></li>
        <?php endforeach; ?>
    </ul>
    <?php $sample('activity-feed', 'activity'); ?>
    <?php ob_start(); ?>
    <div class="d-grid">
        <?php foreach (['FIREBALL CMS' => '2.0 · Demo', 'PHP' => '8.2 · Demo', $tr('system_status') => $tr('connected')] as $label => $value): ?>
            <div class="fb-system-row"><i class="ci-check-circle fb-system-status" aria-hidden="true"></i><span class="fb-system-label"><?= htmlSC($label) ?></span><span class="fb-system-value"><?= htmlSC($value) ?></span></div>
        <?php endforeach; ?>
    </div>
    <?php $sample('system-status', 'system_status'); ?>
    <?php $closeSection(); ?>

    <?php $openSection('tables', 'table responsive mobile cards checkbox selection list таблица список'); ?>
    <div class="fb-ui-wide">
        <?php ob_start();
        $rows = [];
        foreach (['published', 'draft', 'archived'] as $index => $status) {
            $badgeClass = ['success', 'warning', 'secondary'][$index];
            $rows[] = ['cells' => [
                ['value' => '#' . (101 + $index)],
                ['html' => '<a href="#ui-reuse">' . $t('sample_page') . ' ' . ($index + 1) . '</a>'],
                ['html' => '<span class="badge bg-' . $badgeClass . '-subtle text-' . $badgeClass . '">' . $t($status) . '</span>'],
                ['value' => '11.10.2026'],
                ['html' => '<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#ui-example-modal"><i class="ci-edit me-1" aria-hidden="true"></i>' . $t('edit') . '</button>'],
            ]];
        }
        echo view()->renderPartial('admin/partials/table', [
            'caption' => $tr('responsive_table'),
            'columns' => [['label' => 'ID'], ['label' => $tr('name')], ['label' => $tr('status')], ['label' => $tr('updated')], ['label' => $tr('actions')]],
            'rows' => $rows,
        ]);
        $sample('responsive-table', 'responsive_table', $tr('responsive_hint'), <<<'CODE'
<?= view()->renderPartial('admin/partials/table', [
    'caption' => return_translation('admin_pages_title'),
    'columns' => [
        ['label' => 'ID'],
        ['label' => return_translation('admin_pages_col_title')],
        ['label' => return_translation('admin_posts_col_status')],
    ],
    'rows' => [
        ['cells' => [
            ['value' => $page['id']],
            ['value' => $page['title']],
            ['value' => $page['status']],
        ]],
    ],
]) ?>
CODE
        ); ?>
    </div>
    <div class="fb-ui-wide">
        <?php ob_start(); ?>
        <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
            <div class="flex-grow-1"><label class="visually-hidden" for="ui-table-search"><?= $t('search') ?></label><input class="form-control" type="search" id="ui-table-search" placeholder="<?= $t('search') ?>" data-ui-table-search></div>
            <span class="small text-body-secondary" data-ui-selection role="status" aria-live="polite"></span>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <caption class="visually-hidden"><?= $t('table_controls') ?></caption>
                <thead><tr><th scope="col"><input class="form-check-input" type="checkbox" aria-label="<?= $t('select_all') ?>" data-ui-check-all></th><th scope="col"><?= $t('name') ?></th><th scope="col"><?= $t('status') ?></th></tr></thead>
                <tbody>
                <?php foreach (['published', 'draft', 'archived'] as $index => $status): ?>
                    <tr data-ui-selectable-row><td><input class="form-check-input" type="checkbox" aria-label="<?= $t('select_row') ?> <?= $index + 1 ?>" data-ui-row-check></td><th scope="row"><?= $t('sample_page') ?> <?= $index + 1 ?></th><td><span class="badge bg-secondary-subtle text-body-secondary"><?= $t($status) ?></span></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php $sample('table-selection', 'table_controls'); ?>
    </div>
    <?php ob_start(); ?>
    <ul class="fb-simple-list">
        <?php foreach (['posts' => 'file-text', 'users' => 'user', 'plugins' => 'box'] as $label => $icon): ?>
            <li class="fb-simple-list-item"><span class="fb-simple-list-leading"><i class="ci-<?= $icon ?>" aria-hidden="true"></i></span><div class="fb-simple-list-copy"><a class="fb-simple-list-title" href="#ui-reuse"><?= $t($label) ?></a><div class="fb-simple-list-meta"><?= $t('secondary_example') ?></div></div><span class="fb-list-status"><?= $t('connected') ?></span></li>
        <?php endforeach; ?>
    </ul>
    <?php $sample('simple-list', 'lists'); ?>
    <?php ob_start(); ?>
    <div class="d-flex flex-wrap align-items-center gap-3">
        <img src="<?= base_url('/assets/img/avatar.jpg') ?>" class="rounded-circle" width="32" height="32" alt="Demo">
        <img src="<?= base_url('/assets/img/avatar.jpg') ?>" class="rounded-circle" width="48" height="48" alt="Demo">
        <img src="<?= base_url('/assets/img/avatar.jpg') ?>" class="rounded-circle" width="64" height="64" alt="Demo">
        <img src="<?= base_url('/assets/img/no-image.png') ?>" class="img-thumbnail" width="80" height="80" alt="Demo">
    </div>
    <?php $sample('avatars', 'avatars'); ?>
    <?php $closeSection(); ?>

    <?php $openSection('navigation', 'breadcrumb nav tabs pills pagination accordion collapse навигация вкладки'); ?>
    <?php ob_start(); ?>
    <nav aria-label="<?= $t('breadcrumb') ?>"><ol class="breadcrumb mb-0"><li class="breadcrumb-item"><a href="<?= base_href('/admin/docs') ?>"><?= htmlSC(return_translation('admin_nav_docs')) ?></a></li><li class="breadcrumb-item active" aria-current="page"><?= $t('title') ?></li></ol></nav>
    <?php $sample('breadcrumb', 'breadcrumb'); ?>
    <?php ob_start(); ?>
    <nav aria-label="<?= $t('pagination') ?>"><ul class="pagination mb-0"><li class="page-item disabled"><span class="page-link" aria-disabled="true"><i class="ci-chevron-left" aria-hidden="true"></i></span></li><?php for ($page = 1; $page <= 3; $page++): ?><li class="page-item <?= $page === 1 ? 'active' : '' ?>"><button type="button" class="page-link" data-ui-page <?= $page === 1 ? 'aria-current="page"' : '' ?>><?= $page ?></button></li><?php endfor; ?></ul></nav>
    <?php $sample('pagination', 'pagination'); ?>
    <?php ob_start(); ?>
    <ul class="nav nav-tabs mb-3" role="tablist"><li class="nav-item" role="presentation"><button class="nav-link active" id="ui-tab-overview" type="button" data-bs-toggle="tab" data-bs-target="#ui-pane-overview" role="tab" aria-controls="ui-pane-overview" aria-selected="true"><?= $t('overview') ?></button></li><li class="nav-item" role="presentation"><button class="nav-link" id="ui-tab-settings" type="button" data-bs-toggle="tab" data-bs-target="#ui-pane-settings" role="tab" aria-controls="ui-pane-settings" aria-selected="false"><?= $t('settings') ?></button></li><li class="nav-item" role="presentation"><button class="nav-link" type="button" role="tab" aria-selected="false" disabled><?= $t('disabled') ?></button></li></ul>
    <div class="tab-content"><div class="tab-pane fade show active" id="ui-pane-overview" role="tabpanel" aria-labelledby="ui-tab-overview" tabindex="0"><?= $t('tab_overview') ?></div><div class="tab-pane fade" id="ui-pane-settings" role="tabpanel" aria-labelledby="ui-tab-settings" tabindex="0"><?= $t('tab_settings') ?></div></div>
    <?php $sample('tabs', 'tabs'); ?>
    <?php ob_start(); ?>
    <ul class="nav nav-pills mb-3" role="tablist"><li class="nav-item" role="presentation"><button class="nav-link active" id="ui-pill-overview" type="button" data-bs-toggle="pill" data-bs-target="#ui-pill-pane-overview" role="tab" aria-controls="ui-pill-pane-overview" aria-selected="true"><?= $t('overview') ?></button></li><li class="nav-item" role="presentation"><button class="nav-link" id="ui-pill-settings" type="button" data-bs-toggle="pill" data-bs-target="#ui-pill-pane-settings" role="tab" aria-controls="ui-pill-pane-settings" aria-selected="false"><?= $t('settings') ?></button></li></ul>
    <div class="tab-content"><div class="tab-pane fade show active" id="ui-pill-pane-overview" role="tabpanel" aria-labelledby="ui-pill-overview" tabindex="0"><?= $t('tab_overview') ?></div><div class="tab-pane fade" id="ui-pill-pane-settings" role="tabpanel" aria-labelledby="ui-pill-settings" tabindex="0"><?= $t('tab_settings') ?></div></div>
    <?php $sample('pills', 'pills'); ?>
    <div class="fb-ui-wide">
        <?php ob_start(); ?>
        <div class="accordion" id="ui-accordion">
            <div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#ui-accordion-first" aria-expanded="true" aria-controls="ui-accordion-first"><?= $t('accordion_question') ?></button></h3><div id="ui-accordion-first" class="accordion-collapse collapse show" data-bs-parent="#ui-accordion"><div class="accordion-body"><?= $t('accordion_answer') ?></div></div></div>
            <div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ui-accordion-second" aria-expanded="false" aria-controls="ui-accordion-second"><?= $t('accordion_question_two') ?></button></h3><div id="ui-accordion-second" class="accordion-collapse collapse" data-bs-parent="#ui-accordion"><div class="accordion-body"><?= $t('edit_hint') ?></div></div></div>
        </div>
        <?php $sample('accordion', 'accordion'); ?>
    </div>
    <?php $closeSection(); ?>

    <?php $openSection('feedback', 'badge alert toast status уведомления сообщения метки'); ?>
    <?php ob_start(); ?>
    <div class="d-grid gap-3">
        <div class="d-flex flex-wrap gap-2"><?php foreach (['primary', 'secondary', 'success', 'danger', 'warning', 'info'] as $variant): ?><span class="badge bg-<?= $variant ?>"><?= $t($variant) ?></span><?php endforeach; ?></div>
        <div class="d-flex flex-wrap gap-2"><?php foreach (['success' => 'published', 'warning' => 'draft', 'secondary' => 'archived'] as $variant => $label): ?><span class="badge fb-badge bg-<?= $variant ?>-subtle text-<?= $variant ?>"><?= $t($label) ?></span><?php endforeach; ?></div>
        <div><span class="fb-list-status"><?= $t('connected') ?></span></div>
    </div>
    <?php $sample('badges', 'badges'); ?>
    <?php ob_start(); ?>
    <div class="d-grid gap-3">
        <?php foreach (['success' => 'check-circle', 'danger' => 'alert-triangle', 'warning' => 'alert-triangle', 'info' => 'info'] as $variant => $icon): ?>
            <div class="alert alert-<?= $variant ?> mb-0" role="alert"><i class="ci-<?= $icon ?> me-2" aria-hidden="true"></i><?= $t('alert_' . $variant) ?></div>
        <?php endforeach; ?>
    </div>
    <?php $sample('alerts', 'alerts'); ?>
    <div class="fb-ui-wide">
        <?php ob_start(); ?>
        <div class="d-flex flex-wrap gap-2"><?php foreach (['success', 'error', 'warning', 'info'] as $variant): ?><button type="button" class="btn btn-outline-<?= $variant === 'error' ? 'danger' : $variant ?>" data-ui-toast="<?= $variant ?>"><?= $t($variant === 'error' ? 'danger' : $variant) ?></button><?php endforeach; ?></div>
        <?php $sample('toasts', 'toasts', $tr('toast_hint'), "// Client-side notification\nwindow.toastr.success(message);\nwindow.toastr.error(message);\nwindow.toastr.warning(message);\nwindow.toastr.info(message);\n\n// Server operation: keep the existing flash + redirect flow.\nsession()->setFlash('success', return_translation('admin_settings_saved'));"); ?>
    </div>
    <?php $closeSection(); ?>

    <?php $openSection('overlays', 'modal dialog confirmation offcanvas tooltip popover dropdown окно подсказка панель'); ?>
    <?php ob_start(); ?>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ui-example-modal"><?= $t('open_modal') ?></button>
    <div class="modal fade" id="ui-example-modal" tabindex="-1" aria-labelledby="ui-example-modal-title" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h4 class="modal-title" id="ui-example-modal-title"><?= $t('modal_title') ?></h4><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $t('close') ?>"></button></div><div class="modal-body"><p class="mb-0"><?= $t('modal_body') ?></p></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= $t('cancel') ?></button><button type="button" class="btn btn-primary" data-bs-dismiss="modal"><?= $t('save') ?></button></div></div></div></div>
    <?php $sample('modal', 'modal'); ?>
    <?php ob_start(); ?>
    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#ui-confirm-modal"><i class="ci-trash me-2" aria-hidden="true"></i><?= $t('confirm') ?></button>
    <div class="modal fade" id="ui-confirm-modal" tabindex="-1" aria-labelledby="ui-confirm-modal-title" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h4 class="modal-title" id="ui-confirm-modal-title"><?= $t('confirm') ?></h4><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $t('close') ?>"></button></div><div class="modal-body"><?= $t('confirm_body') ?></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= $t('cancel') ?></button><button type="button" class="btn btn-danger" data-bs-dismiss="modal"><?= $t('delete') ?></button></div></div></div></div>
    <?php $sample('confirm', 'confirm'); ?>
    <?php ob_start(); ?>
    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="offcanvas" data-bs-target="#ui-example-panel" aria-controls="ui-example-panel"><?= $t('open_offcanvas') ?></button>
    <div class="offcanvas offcanvas-end" tabindex="-1" id="ui-example-panel" aria-labelledby="ui-panel-title"><div class="offcanvas-header"><h4 class="offcanvas-title" id="ui-panel-title"><?= $t('offcanvas') ?></h4><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?= $t('close') ?>"></button></div><div class="offcanvas-body"><p><?= $t('modal_body') ?></p><label class="form-label" for="ui-panel-name"><?= $t('name') ?></label><input class="form-control mb-3" id="ui-panel-name"><button type="button" class="btn btn-primary" data-bs-dismiss="offcanvas"><?= $t('save') ?></button></div></div>
    <?php $sample('offcanvas', 'offcanvas'); ?>
    <?php ob_start(); ?>
    <div class="d-flex flex-wrap gap-2"><button type="button" class="btn btn-outline-secondary" data-bs-toggle="tooltip" data-bs-title="<?= $t('tooltip_body') ?>"><?= $t('tooltip') ?></button><button type="button" class="btn btn-outline-secondary" data-bs-toggle="popover" data-bs-trigger="focus" data-bs-title="<?= $t('info') ?>" data-bs-content="<?= $t('popover_body') ?>"><?= $t('popover') ?></button></div>
    <?php $sample('hints', 'hints'); ?>
    <?php $closeSection(); ?>

    <?php $openSection('loading', 'empty state skeleton progress spinner loading загрузка пусто'); ?>
    <?php ob_start(); ?>
    <div class="fb-empty-state"><span class="fb-empty-state-icon"><i class="ci-inbox" aria-hidden="true"></i></span><h4 class="h6"><?= $t('empty_title') ?></h4><p class="small mb-3"><?= $t('empty_body') ?></p><button type="button" class="btn btn-primary"><i class="ci-plus me-2" aria-hidden="true"></i><?= $t('create') ?></button></div>
    <?php $sample('empty', 'empty_state'); ?>
    <?php ob_start(); ?>
    <div aria-busy="true" aria-label="<?= $t('loading_label') ?>"><div class="d-flex align-items-center gap-3 mb-4" aria-hidden="true"><span class="fb-skeleton rounded-circle" style="width: 48px; height: 48px; flex: 0 0 48px"></span><div class="flex-grow-1"><span class="fb-skeleton mb-2 w-75"></span><span class="fb-skeleton w-50"></span></div></div><div class="d-grid gap-3" aria-hidden="true"><span class="fb-skeleton"></span><span class="fb-skeleton"></span><span class="fb-skeleton w-75"></span><span class="fb-skeleton" style="height: 100px"></span></div></div>
    <?php $sample('skeleton', 'skeleton'); ?>
    <div class="fb-ui-wide">
        <?php ob_start(); ?>
        <div class="d-grid gap-4"><div class="d-flex flex-wrap align-items-center gap-3"><span class="spinner-border text-primary" role="status"><span class="visually-hidden"><?= $t('loading_label') ?></span></span><span class="spinner-border spinner-border-sm text-secondary" role="status"><span class="visually-hidden"><?= $t('loading_label') ?></span></span><span class="spinner-grow text-success" role="status"><span class="visually-hidden"><?= $t('loading_label') ?></span></span><span><?= $t('loading_label') ?></span></div><div class="progress" role="progressbar" aria-label="<?= $t('progress') ?>" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bg-primary" style="width: 60%">60%</div></div><div class="progress" role="progressbar" aria-label="<?= $t('progress') ?>" aria-valuenow="80" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar progress-bar-striped progress-bar-animated bg-success" style="width: 80%">80%</div></div></div>
        <?php $sample('progress', 'progress'); ?>
    </div>
    <?php $closeSection(); ?>

    <?php $openSection('icons', 'ci icons cartzilla иконки'); ?>
    <div class="fb-ui-wide">
        <?php ob_start(); ?>
        <div class="fb-ui-icons">
            <?php foreach (['home', 'file-text', 'folder', 'grid', 'layout', 'user', 'shield', 'box', 'settings', 'sliders', 'search', 'plus', 'edit', 'trash', 'copy', 'download', 'upload', 'bell', 'inbox', 'clock', 'eye', 'link', 'check-circle', 'alert-triangle', 'info', 'monitor', 'smartphone', 'activity', 'sun', 'moon', 'code', 'more-horizontal'] as $icon): ?>
                <div class="fb-ui-icon"><i class="ci-<?= $icon ?>" aria-hidden="true"></i><code>ci-<?= $icon ?></code></div>
            <?php endforeach; ?>
        </div>
        <?php $sample('icons', 'icons', '', '<i class="ci-settings" aria-hidden="true"></i>' . "\n" . '<button type="button" class="btn btn-icon btn-outline-secondary" aria-label="Settings"><i class="ci-settings" aria-hidden="true"></i></button>'); ?>
    </div>
    <?php $closeSection(); ?>

    <?php $openSection('reuse', 'shell partial htmlSC csrf base_href admin-ui css js integration повторное использование'); ?>
    <div class="fb-ui-wide">
        <?php ob_start(); ?>
        <p><?= $t('reuse_hint') ?></p>
        <p class="text-body-secondary"><?= $t('edit_hint') ?></p>
        <p class="small mb-0"><?= $t('reuse_rules') ?></p>
        <?php $sample('shell', 'shell', '', <<<'CODE'
<?= view()->renderPartial('admin/shell_open', [
    'title' => return_translation('my_page_title'),
    'subtitle' => return_translation('my_page_subtitle'),
]) ?>

<div class="fb-card">
    <div class="fb-card-header">
        <h2 class="fb-card-title"><?= htmlSC($heading) ?></h2>
    </div>
    <div class="fb-card-body">
        <form method="post" action="<?= base_href('/admin/my-page') ?>">
            <?= get_csrf_field() ?>
            <label class="form-label" for="page-title">
                <?= htmlSC(return_translation('admin_page_field_title')) ?>
            </label>
            <input class="form-control mb-3" id="page-title" name="title"
                   value="<?= htmlSC($page['title'] ?? '') ?>" required>
            <button class="btn btn-primary" type="submit">
                <?= htmlSC(return_translation('admin_btn_save')) ?>
            </button>
        </form>
    </div>
</div>

<?= view()->renderPartial('admin/shell_close') ?>
CODE
        ); ?>
    </div>
    <?php $closeSection(); ?>
</div>

<?= view()->renderPartial('admin/shell_close') ?>
