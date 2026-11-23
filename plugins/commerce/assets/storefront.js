(() => {
    const form = document.querySelector('[data-commerce-checkout]');
    if (!form) return;
    const address = form.querySelector('[data-commerce-address]');
    const pickup = form.querySelector('[data-commerce-pickup]');
    const shipping = form.querySelector('[data-commerce-fee]');
    const total = form.querySelector('[data-commerce-total]');
    const price = new Intl.NumberFormat('ru-RU', {
        style: 'currency', currency: 'RUB', minimumFractionDigits: 0, maximumFractionDigits: 2,
    });
    const refresh = () => {
        const selected = form.querySelector('[name="fulfillment"]:checked');
        if (!selected) return;
        const fee = Number(selected.dataset.fee);
        shipping.textContent = fee > 0 ? price.format(fee / 100) : form.dataset.freeLabel;
        total.textContent = price.format((Number(form.dataset.subtotal) + fee) / 100);
        if (pickup) {
            pickup.hidden = selected.value !== 'pickup';
        }
        if (address) {
            const input = address.querySelector('textarea');
            input.required = selected.value === 'delivery';
            input.disabled = !input.required;
            address.hidden = input.disabled;
        }
    };
    form.querySelectorAll('[data-commerce-live-total]').forEach(node => { node.hidden = false; });
    form.addEventListener('commerce:cart-updated', refresh);
    form.addEventListener('change', event => {
        if (event.target.name === 'fulfillment') refresh();
    });
    refresh();
})();

(function () {
    'use strict';

    if (typeof window.Swiper !== 'function') {
        return;
    }

    const hero = document.querySelector('[data-commerce-hero]');
    const dots = hero?.querySelector('[data-commerce-hero-dots]');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const speed = reducedMotion ? 0 : 400;

    if (dots) {
        const gallery = new window.Swiper(hero.querySelector('[data-commerce-hero-gallery]'), {
            effect: 'fade',
            fadeEffect: { crossFade: true },
            allowTouchMove: false,
            a11y: { enabled: false },
            speed,
        });
        const copy = new window.Swiper(hero.querySelector('[data-commerce-hero-copy]'), {
            loop: true,
            spaceBetween: 24,
            speed,
            autoplay: reducedMotion ? false : { delay: 5500, disableOnInteraction: false, pauseOnMouseEnter: true },
            pagination: {
                el: dots,
                clickable: true,
                renderBullet(index, className) {
                    return '<button type="button" class="' + className + '"></button>';
                },
            },
            a11y: { paginationBulletMessage: dots.dataset.slideLabel },
            on: {
                slideChange(swiper) {
                    gallery.slideTo(swiper.realIndex, speed);
                },
            },
        });
        const pause = hero.querySelector('[data-commerce-hero-pause]');
        if (pause && !reducedMotion && copy.autoplay?.running) {
            pause.hidden = false;
            const setAutoplay = (running) => {
                if (running) {
                    copy.autoplay.start();
                } else {
                    copy.autoplay.stop();
                }
                pause.setAttribute('aria-label', running ? pause.dataset.labelPause : pause.dataset.labelPlay);
                pause.firstElementChild.className = running ? 'ci-pause' : 'ci-play';
            };
            pause.addEventListener('click', () => setAutoplay(!copy.autoplay.running));
            hero.addEventListener('focusin', (event) => {
                if (copy.autoplay.running && event.target !== pause) {
                    setAutoplay(false);
                }
            });
        }
    }

    const popular = document.querySelector('[data-commerce-popular]');
    if (popular) {
        const previous = popular.querySelector('[data-commerce-prev]');
        const next = popular.querySelector('[data-commerce-next]');
        new window.Swiper(popular.querySelector('[data-commerce-popular-slider]'), {
            slidesPerView: 2,
            spaceBetween: 16,
            rewind: true,
            watchOverflow: true,
            speed,
            breakpoints: {
                768: { slidesPerView: 3, spaceBetween: 24 },
                1200: { slidesPerView: 4, spaceBetween: 24 },
            },
            navigation: { prevEl: previous, nextEl: next },
            a11y: { prevSlideMessage: previous.ariaLabel, nextSlideMessage: next.ariaLabel },
        });
    }
}());

(function () {
    'use strict';

    const gallery = document.querySelector('[data-commerce-gallery]');
    if (gallery && typeof window.Swiper === 'function') {
        const strip = gallery.querySelector('[data-gallery-thumbs]');
        const thumbs = Array.from(gallery.querySelectorAll('[data-photo-index]'));
        const previous = gallery.querySelector('[data-gallery-prev]');
        const next = gallery.querySelector('[data-gallery-next]');
        const swiping = new window.Swiper(gallery.querySelector('[data-commerce-gallery-slider]'), {
            rewind: true,
            speed: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 300,
            navigation: { prevEl: previous, nextEl: next },
            a11y: { prevSlideMessage: previous.ariaLabel, nextSlideMessage: next.ariaLabel },
            on: {
                slideChange(swiper) {
                    thumbs.forEach((button, index) => button.setAttribute('aria-pressed', String(index === swiper.activeIndex)));
                    const active = thumbs[swiper.activeIndex]?.getBoundingClientRect();
                    if (active && strip) {
                        const bounds = strip.getBoundingClientRect();
                        strip.scrollBy({left: Math.min(0, active.left - bounds.left) + Math.max(0, active.right - bounds.right)});
                    }
                },
            },
        });
        thumbs.forEach((button, index) => button.addEventListener('click', () => swiping.slideTo(index)));
    }

    const purchaseBar = document.querySelector('[data-commerce-sticky-purchase]');
    const purchaseForm = document.querySelector('[data-commerce-purchase]');
    if (purchaseBar && purchaseForm && 'IntersectionObserver' in window) {
        const header = document.querySelector('.public-navbar');
        new IntersectionObserver(([entry]) => {
            purchaseBar.hidden = entry.isIntersecting || entry.boundingClientRect.bottom > (header?.getBoundingClientRect().bottom ?? 0);
        }, { rootMargin: '-' + Math.round(header?.getBoundingClientRect().height ?? 0) + 'px 0px 0px' }).observe(purchaseForm);
    }
}());

(() => {
    const toolbar = document.querySelector('[data-commerce-catalog-toolbar]');
    if (!toolbar) return;
    const filters = toolbar.querySelector('[data-commerce-filter-bar]');
    if (filters?.requestSubmit) {
        filters.querySelector('[data-commerce-filter-submit]').hidden = true;
        filters.addEventListener('change', () => filters.requestSubmit());
    }

    const header = document.querySelector('.public-navbar');
    if (header) {
        const updateOffset = () => toolbar.style.setProperty('--commerce-nav-height', header.getBoundingClientRect().height + 'px');
        updateOffset();
        if ('ResizeObserver' in window) new ResizeObserver(updateOffset).observe(header);
    }

    const grid = document.querySelector('[data-commerce-product-grid]');
    if (!grid || !window.fetch) return;
    let loading = false;
    document.addEventListener('click', async (event) => {
        const link = event.target.closest('[data-commerce-more]');
        if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.altKey || event.shiftKey) return;
        event.preventDefault();
        if (loading) return;
        const pagination = link.closest('[data-commerce-progress]');
        loading = true;
        pagination.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(link.href, {credentials: 'same-origin'});
            if (!response.ok) throw new Error('Catalog page unavailable');
            const nextPage = new DOMParser().parseFromString(await response.text(), 'text/html');
            const nextGrid = nextPage.querySelector('[data-commerce-product-grid]');
            const nextPagination = nextPage.querySelector('[data-commerce-progress]');
            if (!nextGrid || !nextPagination) throw new Error('Invalid catalog page');
            const focusesPageLink = document.activeElement === link;
            grid.append(...nextGrid.children);
            grid.dispatchEvent(new Event('commerce:products-added', {bubbles: true}));
            pagination.replaceWith(nextPagination);
            if (focusesPageLink) {
                (nextPagination.querySelector('[data-commerce-more]') || grid.lastElementChild?.querySelector('a'))?.focus();
            }
        } catch {
            window.location.assign(link.href);
        } finally {
            loading = false;
            pagination.removeAttribute('aria-busy');
        }
    });
})();
