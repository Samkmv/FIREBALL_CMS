<?php if ($cart = \App\Helpers\Cart\Cart::getCart()): ?>

    <?php foreach ($cart as $item): ?>

        <!-- Item -->
        <div class="d-flex align-items-center">
            <div class="position-relative flex-shrink-0">
                <img style="object-fit: cover;" src="<?= $item['image'] ?>" width="110" alt="Thumbnail">
            </div>
            <div class="w-100 ps-3">
                <h5 class="fs-sm fw-medium lh-base mb-2">
                    <a class="hover-effect-underline" href="<?= base_href("/product/{$item['slug']}") ?>"><?= htmlSC($item['title']) ?></a>
                </h5>
                <div class="h6 pb-1 mb-2">$<?= $item['price'] ?></div>
                <div class="d-flex align-items-center justify-content-between">
                    <div class="count-input rounded-pill">
                        <button type="button" class="btn btn-icon btn-sm" data-decrement="" aria-label="Decrement quantity">
                            <i class="ci-minus"></i>
                        </button>
                        <input type="number" class="form-control form-control-sm" value="<?= $item['quantity'] ?>" readonly="">
                        <button type="button" class="btn btn-icon btn-sm" data-increment="" aria-label="Increment quantity">
                            <i class="ci-plus"></i>
                        </button>
                    </div>
                    <button type="button" class="btn-close fs-sm btn-remove"
                            data-id="<?= $item['id'] ?>"
                            data-bs-toggle="tooltip"
                            data-bs-custom-class="tooltip-sm"
                            data-bs-title="Удалить"
                            aria-label="Remove from cart">
                    </button>
                </div>
            </div>
        </div>

    <?php endforeach; ?>

    <!-- Footer -->
    <div class="offcanvas-header flex-column align-items-start">
        <div class="d-flex align-items-center justify-content-between w-100 mb-3 mb-md-4">
            <span class="text-light-emphasis"><?= print_translation('tpl_menu_cart_total') ?></span>
            <span class="h6 mb-0">$<?= \App\Helpers\Cart\Cart::getCartSum() ?></span>
        </div>
        <div class="d-flex w-100 gap-3">
            <a class="btn btn-lg btn-secondary w-100 rounded-pill" href=""><?= print_translation('tpl_menu_btn_cart_details') ?></a>
            <a class="btn btn-lg btn-primary w-100 rounded-pill" href=""><?= print_translation('tpl_menu_btn_cart_buy') ?></a>
        </div>
    </div>

<?php else: ?>

    <div class="offcanvas-body text-center">
        <svg class="d-block mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" width="60" viewBox="0 0 29.5 30"><path class="text-body-tertiary" d="M17.8 4c.4 0 .8-.3.8-.8v-2c0-.4-.3-.8-.8-.8-.4 0-.8.3-.8.8v2c0 .4.3.8.8.8zm3.2.6c.4.2.8 0 1-.4l.4-.9c.2-.4 0-.8-.4-1s-.8 0-1 .4l-.4.9c-.2.4 0 .9.4 1zm-7.5-.4c.2.4.6.6 1 .4s.6-.6.4-1l-.4-.9c-.2-.4-.6-.6-1-.4s-.6.6-.4 1l.4.9z" fill="currentColor"></path><path class="text-body-emphasis" d="M10.7 24.5c-1.5 0-2.8 1.2-2.8 2.8S9.2 30 10.7 30s2.8-1.2 2.8-2.8-1.2-2.7-2.8-2.7zm0 4c-.7 0-1.2-.6-1.2-1.2s.6-1.2 1.2-1.2 1.2.6 1.2 1.2-.5 1.2-1.2 1.2zm11.1-4c-1.5 0-2.8 1.2-2.8 2.8a2.73 2.73 0 0 0 2.8 2.8 2.73 2.73 0 0 0 2.8-2.8c0-1.6-1.3-2.8-2.8-2.8zm0 4c-.7 0-1.2-.6-1.2-1.2s.6-1.2 1.2-1.2 1.2.6 1.2 1.2-.6 1.2-1.2 1.2zM8.7 18h16c.3 0 .6-.2.7-.5l4-10c.2-.5-.2-1-.7-1H9.3c-.4 0-.8.3-.8.8s.4.7.8.7h18.3l-3.4 8.5H9.3L5.5 1C5.4.7 5.1.5 4.8.5h-4c-.5 0-.8.3-.8.7s.3.8.8.8h3.4l3.7 14.6a3.24 3.24 0 0 0-2.3 3.1C5.5 21.5 7 23 8.7 23h16c.4 0 .8-.3.8-.8s-.3-.8-.8-.8h-16a1.79 1.79 0 0 1-1.8-1.8c0-1 .9-1.6 1.8-1.6z" fill="currentColor"></path></svg>
        <h6 class="mb-2"><?= print_translation('tpl_menu_cart_title') ?></h6>
        <p class="fs-sm mb-4"><?= print_translation('tpl_menu_cart_desc') ?></p>
    </div>

<?php endif; ?>



