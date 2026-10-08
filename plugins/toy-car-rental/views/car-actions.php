<div class="dropdown admin-post-actions-dropdown" data-admin-post-actions-dropdown>
    <button class="btn btn-sm btn-outline-secondary btn-icon rounded-circle" type="button" data-bs-toggle="dropdown" data-bs-display="static" data-bs-boundary="viewport" aria-expanded="false" aria-label="<?= htmlSC(FireballPluginToyCarRental::t('toy_rental_table_actions')) ?>">
        <i class="ci-more-vertical"></i>
    </button>
    <div class="dropdown-menu dropdown-menu-end shadow-sm rounded-4">
        <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_href('/admin/toy-rental/cars/edit/' . (int)$car['id']) ?>">
            <i class="ci-edit"></i><span><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_edit')) ?></span>
        </a>
        <?php if ((string)$car['status'] !== 'hidden'): ?>
            <form action="<?= base_href('/admin/toy-rental/cars/hide') ?>" method="post" data-admin-delete-form data-delete-message="<?= htmlSC(FireballPluginToyCarRental::t('toy_rental_hide_confirm')) ?>" data-delete-item="<?= htmlSC((string)$car['name']) ?>">
                <?= get_csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$car['id'] ?>">
                <button class="dropdown-item d-flex align-items-center gap-2" type="submit"><i class="ci-eye-off"></i><span><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_hide')) ?></span></button>
            </form>
        <?php endif; ?>
        <hr class="dropdown-divider">
        <form action="<?= base_href('/admin/toy-rental/cars/delete') ?>" method="post" data-admin-delete-form data-confirm-title="<?= htmlSC(FireballPluginToyCarRental::t('toy_rental_delete_confirm')) ?>" data-delete-message="<?= htmlSC(FireballPluginToyCarRental::t('toy_rental_delete_hint')) ?>" data-delete-item="<?= htmlSC((string)$car['name']) ?>" data-delete-confirm-label="<?= htmlSC(FireballPluginToyCarRental::t('toy_rental_delete')) ?>">
            <?= get_csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$car['id'] ?>">
            <button class="dropdown-item d-flex align-items-center gap-2 text-danger" type="submit" <?= (string)$car['status'] === 'rented' ? 'disabled title="' . htmlSC(FireballPluginToyCarRental::t('toy_rental_error_delete_rented_car')) . '"' : '' ?>><i class="ci-trash"></i><span><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_delete')) ?></span></button>
        </form>
    </div>
</div>
