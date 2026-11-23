(() => {
    const drawer = document.getElementById('shoppingCart');
    const button = document.querySelector('[data-commerce-cart-toggle]');
    const body = drawer?.querySelector('[data-commerce-cart-body]');
    const feedback = drawer?.querySelector('[data-commerce-cart-message]');
    const badge = button?.querySelector('[data-commerce-cart-count]');
    if (!body || !feedback || !badge || !window.bootstrap?.Offcanvas || !window.fetch) return;

    const offcanvas = window.bootstrap.Offcanvas.getOrCreateInstance(drawer);
    const page = document.querySelector('[data-commerce-cart-page]');
    const pageMessage = document.querySelector('[data-commerce-cart-page-message]');
    const checkout = document.querySelector('[data-commerce-checkout]');
    const checkoutEmpty = document.querySelector('[data-commerce-checkout-empty]');

    const syncPurchaseControls = () => {
        const inCart = new Set(Array.from(body.querySelectorAll('[data-commerce-cart-row]'), (row) => row.dataset.commerceCartRow));
        document.querySelectorAll('[data-commerce-cart-buy]').forEach((control) => {
            control.hidden = inCart.has(control.dataset.commerceCartBuy);
        });
        document.querySelectorAll('[data-commerce-cart-open]').forEach((control) => {
            control.hidden = !inCart.has(control.dataset.commerceCartOpen);
        });
    };

    document.addEventListener('commerce:products-added', syncPurchaseControls);
    document.addEventListener('click', (event) => {
        const link = event.target.closest('[data-commerce-cart-open]');
        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        drawer.addEventListener('hidden.bs.offcanvas', () => {
            (link.isConnected && !link.hidden ? link : button).focus();
        }, {once: true});
        offcanvas.show(link);
    });

    const updateCheckout = (cart) => {
        if (!checkout || !cart.checkout) return;
        checkout.hidden = cart.count === 0;
        if (checkoutEmpty) checkoutEmpty.hidden = !checkout.hidden;
        checkout.querySelector('[data-commerce-checkout-lines]').innerHTML = cart.checkout.lines;
        checkout.querySelector('[data-commerce-checkout-subtotal]').textContent = cart.checkout.subtotalText;
        checkout.querySelector('[name="key"]').value = cart.checkout.key;
        checkout.querySelector('[name="quote"]').value = cart.checkout.quote;
        checkout.querySelector('[data-commerce-place-order]').disabled = !cart.checkout.valid;
        checkout.querySelector('[data-commerce-checkout-warning]').hidden = cart.count === 0 || cart.checkout.valid;
        checkout.dataset.subtotal = String(cart.checkout.subtotal);
        checkout.dispatchEvent(new Event('commerce:cart-updated'));
    };

    let updating = false;
    document.addEventListener('submit', async (event) => {
        const form = event.target;
        const adding = form.matches('[data-commerce-add]');
        if (!adding && !form.matches('[data-commerce-cart-line], [data-commerce-cart-clear], [data-commerce-cart-update]')) return;
        event.preventDefault();
        if (updating) return;

        const submitter = event.submitter;
        const fields = new URLSearchParams(new FormData(form));
        if (submitter?.name) fields.set(submitter.name, submitter.value);
        if (page || checkout) fields.set('context', page ? 'cart' : 'checkout');
        const activeId = fields.get('id');
        const restoreFocus = drawer.contains(document.activeElement);
        updating = true;
        body.setAttribute('aria-busy', 'true');
        if (submitter) submitter.disabled = true;

        const showFeedback = (error, message = '') => {
            feedback.textContent = error || message;
            feedback.classList.toggle('text-danger', Boolean(error));
            if (pageMessage) pageMessage.textContent = error;
        };

        try {
            const result = await fetch(form.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                body: fields,
            });
            const cart = await result.json();
            if (typeof cart.panel !== 'string' || !Number.isInteger(cart.count)) throw new Error('Invalid basket response');

            body.innerHTML = cart.panel;
            badge.textContent = cart.count > 99 ? '99+' : String(cart.count);
            badge.hidden = cart.count === 0;
            button.setAttribute('aria-label', button.dataset.label + ' (' + cart.count + ')');
            syncPurchaseControls();
            if (page && typeof cart.page === 'string') page.innerHTML = cart.page;
            updateCheckout(cart);
            showFeedback(cart.error, cart.message);
            if (adding) offcanvas.show(button);

            if (restoreFocus) {
                const row = Array.from(body.querySelectorAll('[data-commerce-cart-row]')).find((item) => item.dataset.commerceCartRow === activeId);
                const controls = Array.from(row?.querySelectorAll('button') ?? []);
                const nextControl = controls.find((control) => control.value === submitter?.value && !control.disabled)
                    || controls.find((control) => !control.disabled);
                (nextControl || drawer.querySelector('[data-bs-dismiss="offcanvas"]'))?.focus();
            }
        } catch {
            showFeedback(drawer.dataset.cartError);
            if (adding) offcanvas.show(button);
        } finally {
            updating = false;
            body.removeAttribute('aria-busy');
            if (submitter) submitter.disabled = false;
        }
    });
})();

(() => {
    if (!window.fetch) return;
    // Serialize writes so repeated clicks and different product cards stay in submission order.
    let pending = Promise.resolve();
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!form.matches('[data-commerce-favorite]')) return;
        event.preventDefault();
        const button = form.querySelector('button[aria-pressed]');
        if (!button || button.disabled) return;
        button.disabled = true;
        pending = pending.then(async () => {
            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                    body: new URLSearchParams(new FormData(form)),
                });
                const result = await response.json();
                if (!response.ok || typeof result.favorite !== 'boolean') throw new Error('Invalid favorite response');

                document.querySelectorAll('[data-commerce-favorite]').forEach((item) => {
                    if (item.elements.namedItem('id')?.value !== String(result.id)) return;
                    const toggle = item.querySelector('button[aria-pressed]');
                    toggle.setAttribute('aria-pressed', String(result.favorite));
                    toggle.classList.toggle('btn-dark', result.favorite);
                    toggle.classList.toggle('btn-outline-secondary', !result.favorite);
                });

                const favorites = document.querySelector('[data-commerce-favorites-page]');
                if (favorites && !result.favorite) {
                    const column = form.closest('[data-commerce-favorite-column]');
                    column?.remove();
                    favorites.querySelector('[data-commerce-favorites-empty]').hidden = Boolean(favorites.querySelector('[data-commerce-favorite-column]'));
                }
            } catch {
                if (window.toastr) {
                    window.toastr.error(form.dataset.favoriteError);
                } else {
                    let status = form.querySelector('[data-favorite-feedback]');
                    if (!status) {
                        status = document.createElement('span');
                        status.dataset.favoriteFeedback = '';
                        status.className = 'd-block mt-1 text-danger fs-xs';
                        status.setAttribute('role', 'status');
                        form.append(status);
                    }
                    status.textContent = form.dataset.favoriteError;
                }
            } finally {
                button.disabled = false;
            }
        });
    });
})();
