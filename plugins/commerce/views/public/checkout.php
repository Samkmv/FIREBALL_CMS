<?php
require __DIR__ . '/open.php';
$field = static fn(string $key, int $length = 190): string => htmlSC(\Fireball\Commerce\Input::query($form[$key] ?? '', $length));
$methods = array_filter(['pickup' => (bool)$settings['pickup_enabled'], 'delivery' => (bool)$settings['delivery_enabled']]);
$method = isset($form['fulfillment']) && is_string($form['fulfillment']) && isset($methods[$form['fulfillment']])
    ? $form['fulfillment'] : array_key_first($methods);
$fee = $method === 'delivery' ? (int)$settings['delivery_minor'] : 0;
$shipmentText = $fee > 0 ? $money($fee) : $t('checkout_free');
?>
<nav aria-label="<?= $t('location') ?>" class="mb-4">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="<?= $url('/') ?>"><?= htmlSC(return_translation('tpl_menu_nav_index')) ?></a></li>
        <li class="breadcrumb-item"><a href="<?= $url('/shop') ?>"><?= $t('catalog') ?></a></li>
        <li class="breadcrumb-item"><a href="<?= $url('/shop/cart') ?>"><?= $t('cart') ?></a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= $t('checkout') ?></li>
    </ol>
</nav>
<h1 class="h3 mb-4"><?= $t('checkout') ?></h1>
<?php if ($formError !== ''): ?><div class="alert alert-danger" role="alert"><?= htmlSC($formError) ?></div><?php endif; ?>
<?php if ($methods === []): ?>
    <p class="alert alert-warning"><?= $t('error_fulfillment') ?></p>
<?php else: ?>
    <div class="border rounded-4 p-4" data-commerce-checkout-empty hidden>
        <p><?= $t('empty_cart') ?></p><a class="btn btn-primary rounded-pill" href="<?= $url('/shop') ?>"><?= $t('to_catalog') ?></a>
    </div>
    <form method="post" action="<?= $url('/shop/checkout') ?>" class="row gy-4 gx-lg-5"
          data-commerce-checkout data-subtotal="<?= (int)$review['subtotal'] ?>" data-free-label="<?= $t('checkout_free') ?>">
        <?= get_csrf_field() ?>
        <input type="hidden" name="key" value="<?= htmlSC($key) ?>">
        <input type="hidden" name="quote" value="<?= htmlSC($review['quote']) ?>">

        <div class="col-lg-8 col-xl-7">
            <fieldset class="border-0 pb-4 mb-4">
                <legend class="h5 mb-3"><?= $t('fulfillment') ?></legend>
                <div class="commerce-checkout-options d-flex flex-wrap gap-2 gap-sm-3">
                    <?php foreach ($methods as $option => $_): ?>
                        <input type="radio" class="btn-check" id="commerce-fulfillment-<?= $option ?>" name="fulfillment" value="<?= $option ?>"
                               data-fee="<?= $option === 'delivery' ? (int)$settings['delivery_minor'] : 0 ?>" <?= $method === $option ? 'checked' : '' ?> required>
                        <label class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" for="commerce-fulfillment-<?= $option ?>">
                            <i class="<?= $option === 'delivery' ? 'ci-truck' : 'ci-map-pin' ?>" aria-hidden="true"></i><?= $t($option) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php if (isset($methods['pickup'])): ?>
                    <div class="mt-3 d-flex gap-2 align-items-start" data-commerce-pickup>
                        <i class="ci-map-pin text-primary mt-1" aria-hidden="true"></i>
                        <span class="text-break"><?= htmlSC($settings['pickup_address'] ?: FireballPluginCommerce::t('pickup_address_pending')) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (isset($methods['delivery'])): ?>
                    <div class="mt-3" data-commerce-address>
                        <label class="form-label" for="commerce-address"><?= $t('delivery_address') ?></label>
                        <textarea id="commerce-address" class="form-control form-control-lg rounded-4" name="address" rows="2" autocomplete="street-address" maxlength="500"
                                  <?= count($methods) === 1 ? 'required' : '' ?>><?= $field('address', 500) ?></textarea>
                    </div>
                <?php endif; ?>
            </fieldset>

            <fieldset class="border-0 pb-4 mb-4">
                <legend class="h5 mb-3"><?= $t('contacts') ?></legend>
                <div class="row gy-3 gx-sm-4">
                    <div class="col-sm-6">
                        <label class="form-label" for="commerce-first-name"><?= $t('first_name') ?></label>
                        <input id="commerce-first-name" class="form-control form-control-lg rounded-pill" name="first_name" autocomplete="given-name" maxlength="95" value="<?= $field('first_name', 95) ?>" required>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label" for="commerce-last-name"><?= $t('last_name') ?></label>
                        <input id="commerce-last-name" class="form-control form-control-lg rounded-pill" name="last_name" autocomplete="family-name" maxlength="94" value="<?= $field('last_name', 94) ?>" required>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label" for="commerce-phone"><?= $t('phone') ?></label>
                        <input id="commerce-phone" class="form-control form-control-lg rounded-pill" type="tel" name="phone" autocomplete="tel" maxlength="45" value="<?= $field('phone', 45) ?>" required>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label" for="commerce-email"><?= $t('email') ?></label>
                        <input id="commerce-email" class="form-control form-control-lg rounded-pill" type="email" name="email" autocomplete="email" maxlength="190" value="<?= $field('email') ?>" required>
                    </div>
                </div>
            </fieldset>

            <section aria-labelledby="commerce-payment-title">
                <h2 class="h5 mb-3" id="commerce-payment-title"><?= $t('paid_label') ?></h2>
                <div class="d-flex align-items-center gap-2">
                    <i class="ci-wallet text-primary fs-lg" aria-hidden="true"></i><span><?= $t('payment_on_receipt') ?></span>
                </div>
            </section>
        </div>

        <aside class="col-lg-4 offset-xl-1" aria-labelledby="commerce-checkout-summary">
            <div class="commerce-checkout-summary">
                <div class="d-flex flex-wrap align-items-baseline justify-content-between gap-2 border-bottom pb-3 mb-3">
                    <h2 class="h5 mb-0" id="commerce-checkout-summary"><?= $t('summary') ?></h2>
                    <a class="fs-sm" href="<?= $url('/shop/cart') ?>"><?= $t('checkout_edit_cart') ?></a>
                </div>
                <ul class="list-unstyled mb-0" data-commerce-checkout-lines>
                    <?php require __DIR__ . '/checkout-lines.php'; ?>
                </ul>
                <div class="border-top pt-3 mt-3 fs-sm">
                    <div class="d-flex justify-content-between align-items-baseline gap-2 mb-2">
                        <span><?= $t('cart_subtotal') ?></span><span class="text-nowrap" data-commerce-checkout-subtotal><?= $money((int)$review['subtotal']) ?></span>
                    </div>
                    <?php $hideUntilScript = count($methods) > 1 ? 'hidden' : ''; ?>
                    <div class="d-flex justify-content-between align-items-baseline gap-2 mb-3" data-commerce-live-total <?= $hideUntilScript ?>>
                        <span><?= $t('delivery_cost') ?></span><span class="text-nowrap" data-commerce-fee><?= $shipmentText ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-baseline gap-2 border-top pt-3 mb-4 fs-5 fw-semibold" data-commerce-live-total aria-live="polite" <?= $hideUntilScript ?>>
                        <span><?= $t('total') ?></span><span class="text-nowrap" data-commerce-total><?= $money((int)$review['subtotal'] + $fee) ?></span>
                    </div>
                    <?php if (count($methods) > 1): ?>
                        <noscript>
                            <dl class="border-top pt-3 mb-4">
                                <?php foreach ($methods as $option => $_): ?>
                                    <div class="d-flex justify-content-between gap-2">
                                        <dt><?= $t($option) ?></dt><dd><?= $money((int)$review['subtotal'] + ($option === 'delivery' ? (int)$settings['delivery_minor'] : 0)) ?></dd>
                                    </div>
                                <?php endforeach; ?>
                            </dl>
                        </noscript>
                    <?php endif; ?>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="commerce-note"><?= $t('note') ?></label>
                    <textarea id="commerce-note" class="form-control rounded-4" name="note" rows="2" maxlength="1500"><?= $field('note', 1500) ?></textarea>
                </div>
                <p class="text-danger small" data-commerce-checkout-warning hidden><?= $t('error_stock') ?></p>
                <button class="btn btn-lg btn-primary w-100 rounded-pill d-inline-flex justify-content-center align-items-center gap-2" type="submit" data-commerce-place-order>
                    <?= $t('place_order') ?><i class="ci-chevron-right" aria-hidden="true"></i>
                </button>
            </div>
        </aside>
    </form>
<?php endif; ?>
</div>
