<?php
$tab = 'products';
require __DIR__ . '/open.php';
$field = static function (string $key, string $default = '', int $limit = 190) use ($form, $product): string {
    $value = $form[$key] ?? $product[$key] ?? $default;
    return htmlSC(\Fireball\Commerce\Input::query(is_int($value) ? (string)$value : $value, $limit));
};
$categoryId = (int)($form['category_id'] ?? $product['category_id'] ?? 0);
$published = $form === [] ? !empty($product['is_active']) : !empty($form['is_active']);
$featured = $form === [] ? !empty($product['is_featured']) : !empty($form['is_featured']);
?>
<form class="fb-card p-3 p-lg-4" action="<?= $url('/admin/commerce/products/edit/' . $id) ?>" method="post" enctype="multipart/form-data">
    <?= get_csrf_field() ?>
    <input type="hidden" name="revision" value="<?= $field('revision', '0') ?>">
    <?php if ($formError !== ''): ?><div class="alert alert-danger" role="alert"><?= htmlSC($formError) ?></div><?php endif; ?>
    <div class="row g-3">
        <div class="col-12 col-md-8"><label class="form-label" for="commerce-name"><?= $t('name') ?></label>
            <input id="commerce-name" name="name" class="form-control" maxlength="190" value="<?= $field('name') ?>" required></div>
        <div class="col-12 col-md-4"><label class="form-label" for="commerce-sku"><?= $t('sku') ?></label>
            <input id="commerce-sku" name="sku" class="form-control" maxlength="80" value="<?= $field('sku', '', 80) ?>" required></div>
        <div class="col-sm-6"><label class="form-label" for="commerce-slug"><?= $t('slug') ?></label>
            <input id="commerce-slug" name="slug" class="form-control" maxlength="160" value="<?= $field('slug', '', 160) ?>" aria-describedby="commerce-slug-hint">
            <small class="form-text" id="commerce-slug-hint"><?= $t('slug_hint') ?></small></div>
        <div class="col-sm-6"><label class="form-label" for="commerce-category"><?= $t('category') ?></label>
            <select id="commerce-category" name="category_id" class="form-select">
                <option value="0"><?= $t('no_category') ?></option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int)$category['id'] ?>" <?= $categoryId === (int)$category['id'] ? 'selected' : '' ?>><?= htmlSC($category['name']) ?><?= $category['is_active'] ? '' : ' · ' . $t('hidden') ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="col-sm-6"><label class="form-label" for="commerce-price"><?= $t('price_rub') ?></label>
            <input id="commerce-price" name="price" class="form-control" inputmode="decimal" value="<?= $field('price', \Fireball\Commerce\Input::money((int)($product['price_minor'] ?? 0), form: true)) ?>" required></div>
        <div class="col-sm-6"><label class="form-label" for="commerce-stock"><?= $t('stock') ?></label>
            <input id="commerce-stock" name="stock" class="form-control" type="number" min="0" max="1000000" value="<?= $field('stock', '0') ?>" required></div>
        <?php if ($badgesReady): ?>
            <div class="col-sm-6">
                <label class="form-label" for="commerce-compare-at"><?= $t('compare_at') ?></label>
                <input id="commerce-compare-at" name="compare_at" class="form-control" inputmode="decimal" maxlength="11"
                       value="<?= $field('compare_at', isset($product['compare_at_minor']) ? \Fireball\Commerce\Input::money((int)$product['compare_at_minor'], form: true) : '', 11) ?>"
                       aria-describedby="commerce-compare-at-hint">
                <small class="form-text" id="commerce-compare-at-hint"><?= $t('compare_at_hint') ?></small>
            </div>
            <div class="col-sm-6 d-flex align-items-center">
                <label class="form-check form-switch mb-0">
                    <input type="hidden" name="is_new" value="0">
                    <input class="form-check-input" type="checkbox" name="is_new" value="1" <?= !empty($form['is_new'] ?? $product['is_new'] ?? null) ? 'checked' : '' ?>>
                    <?= $t('mark_new') ?>
                </label>
            </div>
        <?php endif; ?>
        <div class="col-12"><label class="form-label" for="commerce-description"><?= $t('description') ?></label>
            <textarea id="commerce-description" name="description" class="form-control" rows="5" maxlength="15000"><?= $field('description', '', 15000) ?></textarea></div>
        <?php if ($specificationsReady): ?><div class="col-12"><label class="form-label" for="commerce-specifications"><?= $t('product_details') ?></label>
            <textarea id="commerce-specifications" name="specifications" class="form-control" rows="4" maxlength="8000" aria-describedby="commerce-specifications-hint"><?= $field('specifications', '', 8000) ?></textarea>
            <small class="form-text" id="commerce-specifications-hint"><?= $t('specifications_hint') ?></small></div><?php endif; ?>
        <div class="col-md-6">
            <label class="form-label" for="commerce-image"><?= $t('image') ?></label>
            <input id="commerce-image" name="image" type="file" accept="image/jpeg,image/png,image/webp" class="form-control">
            <small class="form-text"><?= $t('image_hint') ?></small>
            <?php if (!empty($product['image_path'])): ?>
                <div class="d-flex align-items-center gap-3 mt-3">
                    <img class="rounded-3 object-fit-cover" src="<?= $url($product['image_path']) ?>" width="72" height="72" alt="">
                    <label class="form-check mb-0"><input class="form-check-input" type="checkbox" name="remove_image" value="1"> <?= $t('remove_image') ?></label>
                </div>
            <?php endif; ?>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="commerce-hover-image"><?= $t('hover_photo') ?></label>
            <input id="commerce-hover-image" name="hover_image" type="file" accept="image/jpeg,image/png,image/webp" class="form-control">
            <small class="form-text"><?= $t('image_hint') ?></small>
            <?php if (!empty($product['hover_image_path'])): ?>
                <div class="d-flex align-items-center gap-3 mt-3">
                    <img class="rounded-3 object-fit-cover" src="<?= $url($product['hover_image_path']) ?>" width="72" height="72" alt="">
                    <label class="form-check mb-0"><input class="form-check-input" type="checkbox" name="remove_hover_image" value="1"> <?= $t('remove_image') ?></label>
                </div>
            <?php endif; ?>
        </div>
        <?php if ($galleryReady): ?>
            <div class="col-12">
                <label class="form-label" for="commerce-gallery"><?= $t('product_gallery') ?></label>
                <input id="commerce-gallery" name="gallery[]" type="file" accept="image/jpeg,image/png,image/webp" class="form-control"
                       multiple aria-describedby="commerce-gallery-hint">
                <small class="form-text" id="commerce-gallery-hint"><?= $t('gallery_hint') ?></small>
                <?php if ($gallery !== []): ?>
                    <div class="d-flex flex-wrap gap-3 mt-3">
                        <?php foreach ($gallery as $index => $photo): ?>
                            <label class="d-flex flex-column gap-2 align-items-start">
                                <img class="rounded-3 object-fit-cover" src="<?= htmlSC(base_url($photo)) ?>" width="88" height="88"
                                     alt="<?= htmlSC(sprintf(FireballPluginCommerce::t('gallery_photo'), $index + 1)) ?>">
                                <span class="form-check mb-0">
                                    <input type="checkbox" class="form-check-input" name="remove_gallery[]" value="<?= htmlSC($photo) ?>"
                                           <?= in_array($photo, (array)($form['remove_gallery'] ?? []), true) ? 'checked' : '' ?>><?= $t('remove') ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <div class="col-md-6">
            <label class="form-label" for="commerce-variant-group"><?= $t('variant_group') ?></label>
            <input id="commerce-variant-group" name="variant_group" class="form-control" maxlength="80" value="<?= $field('variant_group', '', 80) ?>">
            <small class="form-text"><?= $t('variant_group_hint') ?></small>
        </div>
        <div class="col-7 col-md-4">
            <label class="form-label" for="commerce-color-name"><?= $t('color_name') ?></label>
            <input id="commerce-color-name" name="color_name" class="form-control" maxlength="60" value="<?= $field('color_name', '', 60) ?>">
        </div>
        <div class="col-5 col-md-2">
            <label class="form-label" for="commerce-color-hex"><?= $t('color_hex') ?></label>
            <input id="commerce-color-hex" name="color_hex" class="form-control" maxlength="7" pattern="#[0-9A-Fa-f]{6}" placeholder="#123456" value="<?= $field('color_hex', '', 7) ?>">
        </div>
        <div class="col-12"><label class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_featured" value="1" <?= $featured ? 'checked' : '' ?>><?= $t('feature_product') ?>
        </label></div>
        <div class="col-12"><label class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" <?= $published ? 'checked' : '' ?>><?= $t('publish') ?>
        </label></div>
    </div>
    <div class="d-flex flex-wrap gap-2 mt-3">
        <button class="btn btn-primary" type="submit"><?= $t('save') ?></button>
        <a class="btn btn-outline-secondary" href="<?= $url('/admin/commerce/products') ?>"><?= $t('back') ?></a>
    </div>
</form>
<?= view()->renderPartial('admin/shell_close') ?>
