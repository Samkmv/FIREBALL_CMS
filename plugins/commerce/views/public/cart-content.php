<?php require __DIR__ . '/context.php'; ?>
<?php if ($review['lines'] !== []): ?>
    <form class="mb-3 text-end" method="post" action="<?= $url('/shop/cart/clear') ?>" data-commerce-cart-clear>
        <?= get_csrf_field() ?>
        <button class="btn btn-sm btn-outline-secondary rounded-pill" type="submit"><?= $t('clear_cart') ?></button>
    </form>
<?php endif; ?>
<?php if ($review['lines'] === []): ?>
    <div class="border rounded-4 p-4"><p><?= $t('empty_cart') ?></p><a class="btn btn-dark" href="<?= $url('/shop') ?>"><?= $t('to_catalog') ?></a></div>
<?php else: ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <form method="post" action="<?= $url('/shop/cart/update') ?>" data-commerce-cart-update>
                <?= get_csrf_field() ?>
                <ul class="list-group list-group-flush mb-3">
                    <?php foreach ($review['lines'] as $line): ?>
                        <li class="list-group-item px-0 py-3">
                            <div class="commerce-cart-line">
                                <?php if (!empty($line['image_path'])): ?>
                                    <img class="commerce-cart-thumb rounded-3" src="<?= $media($line['image_path']) ?>" alt="">
                                <?php else: ?><span class="commerce-cart-thumb rounded-3"><i class="ci-image" aria-hidden="true"></i></span><?php endif; ?>
                                <div class="text-break">
                                    <?php if ($line['available']): ?>
                                        <a class="fw-semibold text-body-emphasis" href="<?= $url('/shop/product/' . $line['slug']) ?>"><?= htmlSC($line['name']) ?></a>
                                    <?php else: ?><strong><?= htmlSC($line['name']) ?></strong><?php endif; ?>
                                    <span class="d-block small text-body-secondary"><?= $money((int)$line['price_minor']) ?></span>
                                    <?php if (!$line['valid']): ?>
                                        <span class="small text-danger"><?= $line['available'] && $line['stock'] > 0
                                            ? htmlSC(sprintf(FireballPluginCommerce::t('available_quantity'), (int)$line['stock'])) : $t('unavailable') ?></span>
                                    <?php endif; ?>
                                </div>
                                <label class="visually-hidden" for="qty-<?= (int)$line['id'] ?>"><?= $t('quantity') ?> · <?= htmlSC($line['name']) ?></label>
                                <input class="form-control commerce-quantity" type="number" id="qty-<?= (int)$line['id'] ?>" name="quantities[<?= (int)$line['id'] ?>]" min="0" max="99" value="<?= (int)$line['quantity'] ?>" required>
                                <strong class="text-nowrap"><?= $money((int)$line['line_minor']) ?></strong>
                                <button class="btn btn-icon btn-sm btn-outline-secondary" type="submit" name="remove" value="<?= (int)$line['id'] ?>"
                                        formnovalidate aria-label="<?= $t('remove') ?> · <?= htmlSC($line['name']) ?>"><i class="ci-trash" aria-hidden="true"></i></button>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <button class="btn btn-outline-secondary" type="submit"><?= $t('update_cart') ?></button>
            </form>
        </div>
        <div class="col-lg-4">
            <aside class="commerce-summary rounded-4 border p-3 p-lg-4">
                <h2 class="h5 mb-3"><?= $t('summary') ?></h2>
                <div class="d-flex justify-content-between align-items-baseline gap-2 mb-3"><span><?= $t('items_total') ?></span><strong class="fs-5"><?= $money($review['subtotal']) ?></strong></div>
                <?php if ($review['valid']): ?>
                    <a class="btn btn-dark w-100" href="<?= $url('/shop/checkout') ?>"><?= $t('checkout') ?></a>
                <?php else: ?><p class="text-danger mb-0"><?= $t('error_stock') ?></p><?php endif; ?>
                <a class="d-block text-center small mt-3" href="<?= $url('/shop') ?>"><?= $t('continue_shopping') ?></a>
            </aside>
        </div>
    </div>
<?php endif; ?>
