<?php
$formAction = $is_edit
    ? base_href('/admin/roles/edit/' . (int)$role['id'])
    : base_href('/admin/roles/create');
$isSystem = (int)($role['is_system'] ?? 0) === 1;
$isProtectedCreatorRole = ($role['slug'] ?? '') === 'creator';
$hasAssignedUsers = (int)($role['users_count'] ?? 0) > 0;
$canDeleteRole = $is_edit && !$isSystem && !$hasAssignedUsers && !$isProtectedCreatorRole;
$deleteBlockedMessage = $isProtectedCreatorRole
    ? return_translation('admin_roles_creator_protected')
    : ($isSystem
    ? return_translation('admin_roles_delete_system_blocked')
    : return_translation('admin_roles_delete_assigned_blocked'));
?>

<section class="container py-5 my-2 my-md-4 my-lg-5">
    <div class="d-flex align-items-end justify-content-between flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1"><?= $is_edit ? print_translation('admin_role_edit_heading') : print_translation('admin_role_create_heading') ?></h1>
            <p class="text-body-secondary mb-0"><?= print_translation('admin_role_form_subtitle') ?></p>
        </div>
        <a class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" href="<?= base_href('/admin/roles') ?>"><i class="ci-arrow-left"></i><?= print_translation('admin_btn_back') ?></a>
    </div>

    <?= view()->renderPartial('admin/nav') ?>

    <div class="border rounded-5 p-3 p-md-4">
        <?php if ($isProtectedCreatorRole): ?>
            <div class="alert alert-warning mb-4"><?= print_translation('admin_roles_creator_protected') ?></div>
        <?php endif; ?>
        <form id="roleForm" action="<?= $formAction ?>" method="post">
            <?= get_csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label"><?= print_translation('admin_roles_col_name') ?></label>
                    <input class="form-control <?= get_validation_class('name') ?>" type="text" id="role_name" name="name" value="<?= old('name') ?: htmlSC($role['name'] ?? '') ?>" data-slug-source="#role_slug" <?= $isProtectedCreatorRole ? 'readonly' : '' ?> required>
                    <?= get_errors('name') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Slug</label>
                    <input class="form-control <?= get_validation_class('slug') ?>" type="text" id="role_slug" name="slug" value="<?= old('slug') ?: htmlSC($role['slug'] ?? '') ?>" data-slug-input <?= ($isSystem || $isProtectedCreatorRole) ? 'readonly' : '' ?> required>
                    <?php if ($isSystem): ?>
                        <div class="form-text"><?= print_translation('admin_roles_system_slug_hint') ?></div>
                    <?php endif; ?>
                    <?= get_errors('slug') ?>
                </div>
            </div>
        </form>
        <div class="row g-3">
            <div class="col-12 d-flex flex-wrap align-items-center justify-content-between gap-2 pt-3">
                <div class="d-flex flex-wrap gap-2">
                    <?php if (!$isProtectedCreatorRole): ?>
                        <button class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" type="submit" form="roleForm"><i class="ci-save"></i><?= print_translation('admin_btn_save') ?></button>
                    <?php endif; ?>
                    <a class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" href="<?= base_href('/admin/roles') ?>"><i class="ci-close"></i><?= print_translation('admin_btn_cancel') ?></a>
                </div>
                <?php if ($is_edit): ?>
                    <?php if ($canDeleteRole): ?>
                        <form
                            action="<?= base_href('/admin/roles/delete') ?>"
                            method="post"
                            data-admin-delete-form
                            data-delete-message="<?= htmlSC(return_translation('admin_confirm_delete_role')) ?>"
                            data-delete-item="<?= htmlSC(get_user_role_label($role['slug'])) ?>"
                        >
                            <?= get_csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$role['id'] ?>">
                            <button class="btn btn-outline-danger rounded-pill d-inline-flex align-items-center gap-2" type="submit">
                                <i class="ci-trash"></i><?= print_translation('admin_btn_delete') ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <span class="small text-body-secondary"><?= htmlSC($deleteBlockedMessage) ?></span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
