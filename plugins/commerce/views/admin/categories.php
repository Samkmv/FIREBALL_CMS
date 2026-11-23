<?php $tab = 'categories'; require __DIR__ . '/open.php'; ?>
<div class="row g-4">
    <div class="col-lg-7">
        <?php
        $rows = [];
        foreach ($categories as $category) {
            $rows[] = ['cells' => [
                ['html' => '<a class="fw-semibold text-break" href="' . $url('/admin/commerce/categories?edit=' . (int)$category['id']) . '">' . htmlSC($category['name']) . '</a>'],
                ['value' => $category['slug']], ['html' => '<span class="badge rounded-pill text-bg-' . ($category['is_active'] ? 'success' : 'secondary') . '">'
                    . $t($category['is_active'] ? 'published' : 'hidden') . '</span>'],
            ]];
        }
        ?>
        <?= view()->renderPartial('admin/partials/table', [
            'columns' => array_map(static fn(string $key): array => ['label' => FireballPluginCommerce::t($key)], ['name', 'slug', 'status']),
            'rows' => $rows, 'empty_text' => FireballPluginCommerce::t('empty_categories'),
        ]) ?>
    </div>
    <div class="col-lg-5">
        <form class="fb-card p-3" method="post" enctype="multipart/form-data" action="<?= $url('/admin/commerce/categories/' . (int)($selected['id'] ?? 0)) ?>">
            <?= get_csrf_field() ?>
            <input type="hidden" name="revision" value="<?= (int)($selected['revision'] ?? 0) ?>">
            <h2 class="h5"><?= $t($selected ? 'edit_category' : 'add_category') ?></h2>
            <label class="form-label" for="commerce-category-name"><?= $t('name') ?></label>
            <input id="commerce-category-name" name="name" class="form-control mb-3" value="<?= htmlSC($selected['name'] ?? '') ?>" maxlength="160" required>
            <label class="form-label" for="commerce-category-slug"><?= $t('slug') ?></label>
            <input id="commerce-category-slug" name="slug" class="form-control" value="<?= htmlSC($selected['slug'] ?? '') ?>" maxlength="160">
            <small class="form-text"><?= $t('slug_hint') ?></small>
            <label class="form-label mt-3" for="commerce-category-image"><?= $t('category_photo') ?></label>
            <input id="commerce-category-image" name="image" type="file" accept="image/jpeg,image/png,image/webp" class="form-control">
            <small class="form-text"><?= $t('image_hint') ?></small>
            <?php if (!empty($selected['image_path'])): ?>
                <div class="d-flex align-items-center gap-3 mt-3">
                    <img class="rounded-circle object-fit-cover" src="<?= $url($selected['image_path']) ?>" width="64" height="64" alt="">
                    <label class="form-check mb-0"><input class="form-check-input" type="checkbox" name="remove_image" value="1"> <?= $t('remove_image') ?></label>
                </div>
            <?php endif; ?>
            <label class="form-check form-switch mt-3 mb-3">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" <?= ($selected['is_active'] ?? 1) ? 'checked' : '' ?>><?= $t('show_category') ?>
            </label>
            <button class="btn btn-primary" type="submit"><?= $t('save') ?></button>
            <?php if ($selected): ?><a class="btn btn-outline-secondary" href="<?= $url('/admin/commerce/categories') ?>"><?= $t('back') ?></a><?php endif; ?>
        </form>
    </div>
</div>
<?= view()->renderPartial('admin/shell_close') ?>
