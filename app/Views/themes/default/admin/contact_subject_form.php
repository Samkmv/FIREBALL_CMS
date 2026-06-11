<?php
$formData = session()->get('form_data') ?: [];
$name = $formData['name'] ?? ($subject['name'] ?? '');
$isActive = array_key_exists('is_active', $formData)
    ? (int)$formData['is_active'] === 1
    : (int)($subject['is_active'] ?? 1) === 1;
$sortOrder = $formData['sort_order'] ?? ($subject['sort_order'] ?? 0);
$action = $is_edit
    ? base_href('/admin/settings/contact-subjects/edit/' . (int)$subject['id'])
    : base_href('/admin/settings/contact-subjects/create');
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => return_translation($is_edit
        ? 'admin_contact_subject_edit_heading'
        : 'admin_contact_subject_create_heading'),
    'subtitle' => return_translation('admin_contact_subject_form_subtitle'),
    'actions' => '',
]) ?>

    <?= view()->renderPartial('admin/settings_tabs', ['active' => 'contact_subjects']) ?>

    <form class="border rounded-5 p-3 p-md-4" action="<?= htmlSC($action) ?>" method="post">
        <?= get_csrf_field() ?>
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label" for="contact-subject-name">
                    <?= print_translation('admin_contact_subject_name') ?> *
                </label>
                <input
                    class="form-control <?= get_validation_class('name') ?>"
                    id="contact-subject-name"
                    type="text"
                    name="name"
                    value="<?= htmlSC($name) ?>"
                    maxlength="190"
                    required
                >
                <?= get_errors('name') ?>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="contact-subject-status">
                    <?= print_translation('admin_contact_subject_status') ?>
                </label>
                <select class="form-select" id="contact-subject-status" name="is_active">
                    <option value="1" <?= $isActive ? 'selected' : '' ?>>
                        <?= print_translation('admin_contact_subject_status_active') ?>
                    </option>
                    <option value="0" <?= !$isActive ? 'selected' : '' ?>>
                        <?= print_translation('admin_contact_subject_status_inactive') ?>
                    </option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="contact-subject-sort-order">
                    <?= print_translation('admin_contact_subject_sort_order') ?>
                </label>
                <input
                    class="form-control <?= get_validation_class('sort_order') ?>"
                    id="contact-subject-sort-order"
                    type="number"
                    name="sort_order"
                    value="<?= htmlSC((string)$sortOrder) ?>"
                    min="-999999"
                    max="999999"
                    step="1"
                >
                <div class="form-text"><?= print_translation('admin_contact_subject_sort_order_hint') ?></div>
                <?= get_errors('sort_order') ?>
            </div>
            <div class="col-12 d-flex flex-wrap gap-2 pt-2">
                <button class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" type="submit">
                    <i class="ci-save"></i><?= print_translation('admin_btn_save') ?>
                </button>
                <a class="btn btn-outline-secondary rounded-pill" href="<?= base_href('/admin/settings/contact-subjects') ?>">
                    <?= print_translation('admin_btn_cancel') ?>
                </a>
            </div>
        </div>
    </form>

<?= view()->renderPartial('admin/shell_close') ?>
