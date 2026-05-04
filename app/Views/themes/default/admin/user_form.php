<?php
$formAction = base_href('/admin/users/edit/' . (int)$user_item['id']);
$currentAvatar = $user_item['avatar'] ?? '';
$isProtectedCreator = ($user_item['role'] ?? 'user') === 'creator';
$isCurrentUser = (int)$user_item['id'] === (int)(get_user()['id'] ?? 0);
$isLastAdmin = in_array(($user_item['role'] ?? 'user'), ['creator', 'admin'], true) && (int)($user_item['other_admins_count'] ?? 0) === 0;
$canDeleteUser = !$isProtectedCreator && !$isCurrentUser && !$isLastAdmin;
$deleteBlockedMessage = $isCurrentUser
    ? return_translation('admin_users_delete_self_blocked')
    : ($isProtectedCreator
        ? return_translation('admin_users_creator_protected')
        : return_translation('admin_users_delete_last_admin_blocked'));
?>
<?php ob_start(); ?>
<a class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" href="<?= base_href('/admin/users') ?>"><i class="ci-arrow-left"></i><?= print_translation('admin_btn_back') ?></a>
<?php $adminPageActions = ob_get_clean(); ?>
<?= view()->renderPartial('admin/shell_open', [
    'title' => return_translation('admin_user_edit_heading'),
    'subtitle' => return_translation('admin_user_form_subtitle'),
    'actions' => $adminPageActions,
]) ?>

    <div class="border rounded-5 p-3 p-md-4">
        <?php if ($isProtectedCreator): ?>
            <div class="alert alert-warning mb-4"><?= print_translation('admin_users_creator_protected') ?></div>
        <?php endif; ?>
        <form id="userForm" action="<?= $formAction ?>" method="post" enctype="multipart/form-data">
            <?= get_csrf_field() ?>
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label"><?= print_translation('admin_user_avatar') ?></label>
                    <div class="d-flex align-items-center gap-3 flex-wrap mb-3">
                        <img
                            src="<?= get_user_avatar($currentAvatar, 'lg') ?>"
                            alt="<?= htmlSC($user_item['name'] ?? '') ?>"
                            class="rounded-circle border object-fit-cover"
                            style="width: 88px; height: 88px;"
                        >
                        <div class="small text-body-secondary"><?= print_translation('admin_user_avatar_hint') ?></div>
                    </div>
                    <input class="form-control <?= get_validation_class('avatar_file') ?>" type="file" name="avatar_file" accept="image/jpeg,image/png,image/webp,image/gif" <?= $isProtectedCreator ? 'disabled' : '' ?>>
                    <?= get_errors('avatar_file') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label"><?= print_translation('admin_users_col_name') ?></label>
                    <input class="form-control <?= get_validation_class('name') ?>" type="text" name="name" value="<?= old('name') ?: htmlSC($user_item['name'] ?? '') ?>" <?= $isProtectedCreator ? 'readonly' : '' ?> required>
                    <?= get_errors('name') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label"><?= print_translation('admin_users_col_login') ?></label>
                    <input class="form-control <?= get_validation_class('login') ?>" type="text" name="login" value="<?= old('login') ?: htmlSC($user_item['login'] ?? '') ?>" <?= $isProtectedCreator ? 'readonly' : '' ?> required>
                    <?= get_errors('login') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label"><?= print_translation('admin_users_col_email') ?></label>
                    <input class="form-control <?= get_validation_class('email') ?>" type="email" name="email" value="<?= old('email') ?: htmlSC($user_item['email'] ?? '') ?>" <?= $isProtectedCreator ? 'readonly' : '' ?> required>
                    <?= get_errors('email') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label"><?= print_translation('admin_users_col_role') ?></label>
                    <?php $selectedRole = old('role') ?: ($user_item['role'] ?? 'user'); ?>
                    <select class="form-select <?= get_validation_class('role') ?>" name="role" <?= $isProtectedCreator ? 'disabled' : '' ?> required>
                        <?php foreach ($roles as $role): ?>
                            <?php if (($role['slug'] ?? '') === 'creator' && !$isProtectedCreator) { continue; } ?>
                            <option value="<?= htmlSC($role['slug']) ?>" <?= $selectedRole === $role['slug'] ? 'selected' : '' ?>>
                                <?= htmlSC(get_user_role_label($role['slug'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($isProtectedCreator): ?>
                        <input type="hidden" name="role" value="<?= htmlSC($selectedRole) ?>">
                    <?php endif; ?>
                    <?= get_errors('role') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label"><?= print_translation('admin_user_password') ?></label>
                    <input class="form-control <?= get_validation_class('password') ?>" type="password" name="password" autocomplete="new-password" <?= $isProtectedCreator ? 'disabled' : '' ?>>
                    <div class="form-text"><?= print_translation('admin_user_password_hint') ?></div>
                    <?= get_errors('password') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label"><?= print_translation('admin_user_password_confirmation') ?></label>
                    <input class="form-control <?= get_validation_class('password_confirmation') ?>" type="password" name="password_confirmation" autocomplete="new-password" <?= $isProtectedCreator ? 'disabled' : '' ?>>
                    <?= get_errors('password_confirmation') ?>
                </div>
            </div>
        </form>
        <div class="row g-3">
            <div class="col-12 d-flex flex-wrap align-items-center justify-content-between gap-2 pt-3">
                <div class="d-flex flex-wrap gap-2">
                    <?php if (!$isProtectedCreator): ?>
                        <button class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" type="submit" form="userForm"><i class="ci-save"></i><?= print_translation('admin_btn_save') ?></button>
                    <?php endif; ?>
                    <a class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" href="<?= base_href('/admin/users') ?>"><i class="ci-close"></i><?= print_translation('admin_btn_cancel') ?></a>
                </div>
                <?php if ($canDeleteUser): ?>
                    <form
                        action="<?= base_href('/admin/users/delete') ?>"
                        method="post"
                        data-admin-delete-form
                        data-delete-message="<?= htmlSC(return_translation('admin_confirm_delete_user')) ?>"
                        data-delete-item="<?= htmlSC($user_item['name'] ?? '') ?>"
                    >
                        <?= get_csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$user_item['id'] ?>">
                        <button class="btn btn-outline-danger rounded-pill d-inline-flex align-items-center gap-2" type="submit">
                            <i class="ci-trash"></i><?= print_translation('admin_btn_delete') ?>
                        </button>
                    </form>
                <?php else: ?>
                    <span class="small text-body-secondary"><?= htmlSC($deleteBlockedMessage) ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?= view()->renderPartial('admin/shell_close') ?>
