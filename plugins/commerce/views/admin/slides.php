<?php $tab = 'slides'; require __DIR__ . '/open.php'; ?>
<div class="row g-4">
    <div class="col-lg-7">
        <?php
        $rows = [];
        foreach ($slides as $slide) {
            $rows[] = ['cells' => [
                ['html' => '<a class="d-inline-flex align-items-center gap-2 fw-semibold text-break" href="' . $url('/admin/commerce/slides?edit=' . (int)$slide['id']) . '">'
                    . '<img class="rounded-2 object-fit-cover" src="' . $url($slide['image_path']) . '" width="48" height="48" alt="">'
                    . htmlSC($slide['title']) . '</a>'],
                ['value' => $slide['category_name'] ?? FireballPluginCommerce::t('all_products')],
                ['value' => (string)$slide['sort_order']],
                ['html' => '<span class="badge rounded-pill text-bg-' . ($slide['is_active'] ? 'success' : 'secondary') . '">'
                    . $t($slide['is_active'] ? 'published' : 'hidden') . '</span>'],
            ]];
        }
        ?>
        <?= view()->renderPartial('admin/partials/table', [
            'columns' => array_map(static fn(string $key): array => ['label' => FireballPluginCommerce::t($key)], ['slide_title', 'category', 'display_order', 'status']),
            'rows' => $rows, 'empty_text' => FireballPluginCommerce::t('empty_slides'),
        ]) ?>
    </div>
    <div class="col-lg-5">
        <form class="fb-card p-3" method="post" enctype="multipart/form-data" action="<?= $url('/admin/commerce/slides/' . (int)($selected['id'] ?? 0)) ?>">
            <?= get_csrf_field() ?>
            <input type="hidden" name="revision" value="<?= (int)($selected['revision'] ?? 0) ?>">
            <h2 class="h5 mb-3"><?= $t($selected ? 'edit_slide' : 'add_slide') ?></h2>
            <?php foreach (['title' => ['slide_title', 160], 'subtitle' => ['slide_subtitle', 160], 'button_label' => ['slide_button', 60]] as $field => [$label, $maxLength]): ?>
                <label class="form-label" for="commerce-slide-<?= $field ?>"><?= $t($label) ?></label>
                <input id="commerce-slide-<?= $field ?>" name="<?= $field ?>" class="form-control mb-3" value="<?= htmlSC($selected[$field] ?? '') ?>" maxlength="<?= $maxLength ?>" <?= $field === 'title' ? 'required' : '' ?>>
            <?php endforeach; ?>
            <label class="form-label" for="commerce-slide-category"><?= $t('category') ?></label>
            <select id="commerce-slide-category" name="category_id" class="form-select mb-3">
                <option value="0"><?= $t('all_products') ?></option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int)$category['id'] ?>" <?= (int)($selected['category_id'] ?? 0) === (int)$category['id'] ? 'selected' : '' ?>><?= htmlSC($category['name']) ?><?= $category['is_active'] ? '' : ' · ' . $t('hidden') ?></option>
                <?php endforeach; ?>
            </select>
            <label class="form-label" for="commerce-slide-order"><?= $t('display_order') ?></label>
            <input id="commerce-slide-order" name="sort_order" type="number" min="0" max="1000000" class="form-control mb-3" value="<?= (int)($selected['sort_order'] ?? 0) ?>" required>
            <label class="form-label" for="commerce-slide-image"><?= $t('slide_photo') ?></label>
            <input id="commerce-slide-image" name="image" type="file" accept="image/jpeg,image/png,image/webp" class="form-control" <?= $selected ? '' : 'required' ?>>
            <small class="form-text"><?= $t('image_hint') ?></small>
            <label class="form-check form-switch my-3">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" <?= ($selected['is_active'] ?? 1) ? 'checked' : '' ?>><?= $t('publish') ?>
            </label>
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-primary" type="submit"><?= $t('save') ?></button>
                <?php if ($selected): ?><a class="btn btn-outline-secondary" href="<?= $url('/admin/commerce/slides') ?>"><?= $t('add_slide') ?></a><?php endif; ?>
            </div>
        </form>
        <?php if ($selected): ?>
            <form method="post" action="<?= $url('/admin/commerce/slides/' . (int)$selected['id'] . '/delete') ?>" class="mt-3" data-admin-delete-form data-delete-message="<?= $t('confirm_delete_slide') ?>" data-delete-item="<?= htmlSC($selected['title']) ?>">
                <?= get_csrf_field() ?>
                <input type="hidden" name="revision" value="<?= (int)$selected['revision'] ?>">
                <button type="submit" class="btn btn-outline-danger btn-sm"><?= $t('delete_slide') ?></button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?= view()->renderPartial('admin/shell_close') ?>
