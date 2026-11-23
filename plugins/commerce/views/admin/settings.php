<?php $tab = 'settings'; require __DIR__ . '/open.php'; ?>
<form class="fb-card p-3 p-lg-4" method="post" action="<?= $url('/admin/commerce/settings') ?>">
    <?= get_csrf_field() ?>
    <div class="row g-3">
        <div class="col-12">
            <label class="form-label" for="commerce-store-name"><?= $t('store_name') ?></label>
            <input id="commerce-store-name" name="name" class="form-control" value="<?= htmlSC($settings['name']) ?>" maxlength="190">
        </div>
        <div class="col-12">
            <label class="form-label" for="commerce-introduction"><?= $t('store_intro') ?></label>
            <textarea id="commerce-introduction" name="introduction" class="form-control" rows="2" maxlength="500"><?= htmlSC($settings['introduction']) ?></textarea>
        </div>
        <div class="col-md-7">
            <label class="form-check form-switch fw-semibold" for="commerce-pickup">
                <input id="commerce-pickup" class="form-check-input" type="checkbox" name="pickup_enabled" value="1" <?= $settings['pickup_enabled'] ? 'checked' : '' ?>><?= $t('pickup') ?>
            </label>
            <label class="form-label mt-2" for="commerce-pickup-address"><?= $t('pickup_address') ?></label>
            <input id="commerce-pickup-address" name="pickup_address" class="form-control" value="<?= htmlSC($settings['pickup_address']) ?>" maxlength="255">
        </div>
        <div class="col-md-5">
            <label class="form-check form-switch fw-semibold" for="commerce-delivery">
                <input id="commerce-delivery" class="form-check-input" type="checkbox" name="delivery_enabled" value="1" <?= $settings['delivery_enabled'] ? 'checked' : '' ?>><?= $t('delivery') ?>
            </label>
            <label class="form-label mt-2" for="commerce-delivery-price"><?= $t('delivery_rub') ?></label>
            <input id="commerce-delivery-price" name="delivery_price" class="form-control" inputmode="decimal"
                   value="<?= htmlSC(\Fireball\Commerce\Input::money((int)$settings['delivery_minor'], form: true)) ?>" required>
        </div>
    </div>
    <p class="form-text mt-3"><?= $t('pay_on_receipt') ?></p>
    <button class="btn btn-primary mt-2" type="submit"><?= $t('save') ?></button>
</form>
<?= view()->renderPartial('admin/shell_close') ?>
