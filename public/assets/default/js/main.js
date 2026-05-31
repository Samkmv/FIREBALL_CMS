$(function(){

    let addToCart = $('.add-to-cart');

    const escapeHtml = (text) => $('<div>').text(text).html();
    const getCsrfToken = () => $('meta[name="needCSRFToken"]').attr('content') || '';
    const searchForms = $('[data-search-suggest]');
    const unreadBadges = $('[data-chat-unread-badge]');
    const notificationCenter = $('[data-notifications-center]').first();
    const notificationBadges = $('[data-notifications-badge]');
    const notificationList = notificationCenter.find('[data-notifications-list]');
    const autoDismissAlerts = $('[data-auto-dismiss-alert]');
    const originalTitle = document.title;
    const bodyDataset = document.body ? document.body.dataset : {};
    const codeCopyLabel = bodyDataset.codeCopyLabel || 'Copy';
    const codeCopiedLabel = bodyDataset.codeCopiedLabel || 'Copied';
    const seenNotificationKeys = new Set();
    let unreadBadgesReady = false;
    let lastUnreadTotal = 0;
    let notificationsReady = false;
    const slugMap = {
        'а': 'a', 'б': 'b', 'в': 'v', 'г': 'g', 'д': 'd',
        'е': 'e', 'ё': 'e', 'ж': 'zh', 'з': 'z', 'и': 'i',
        'й': 'y', 'к': 'k', 'л': 'l', 'м': 'm', 'н': 'n',
        'о': 'o', 'п': 'p', 'р': 'r', 'с': 's', 'т': 't',
        'у': 'u', 'ф': 'f', 'х': 'h', 'ц': 'c', 'ч': 'ch',
        'ш': 'sh', 'щ': 'shch', 'ъ': '', 'ы': 'y', 'ь': '',
        'э': 'e', 'ю': 'yu', 'я': 'ya'
    };

    const makeSlug = (value) => String(value || '')
        .trim()
        .toLowerCase()
        .replace(/[а-яё]/g, (letter) => slugMap[letter] || '')
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/[\s-]+/g, '-')
        .replace(/^-+|-+$/g, '');

    const transliterateToLatin = (value) => String(value || '')
        .trim()
        .toLowerCase()
        .replace(/[а-яё]/g, (letter) => slugMap[letter] || '')
        .replace(/[^a-z0-9\s-]/g, ' ')
        .replace(/\s+/g, ' ')
        .replace(/\b[a-z]/g, (letter) => letter.toUpperCase());

    const initPostContentSliders = () => {
        if (typeof Swiper === 'undefined') {
            return;
        }

        document.querySelectorAll('[data-fb-slider]').forEach((slider, index) => {
            const slides = slider.querySelectorAll('.swiper-slide');
            if (slides.length <= 1) {
                return;
            }

            const shell = slider.closest('[data-fb-slider-shell]') || slider.parentElement;
            if (!shell) {
                return;
            }

            let prevButton = shell.querySelector('[data-fb-slider-prev]');
            let nextButton = shell.querySelector('[data-fb-slider-next]');
            const wrapper = slider.querySelector('.swiper-wrapper');
            const sliderId = slider.id || `fb-post-slider-${index + 1}`;

            slider.id = sliderId;

            if (wrapper && !wrapper.id) {
                wrapper.id = `${sliderId}-wrapper`;
            }

            const makeButton = (direction) => {
                const isPrev = direction === 'prev';
                const button = document.createElement('button');
                button.type = 'button';
                button.className = `btn btn-icon btn-outline-secondary rounded-circle animate-slide-${isPrev ? 'start' : 'end'} position-absolute top-50 ${isPrev ? 'start-0' : 'end-0'} translate-middle-y mt-n3 z-2`;
                button.setAttribute(`data-fb-slider-${direction}`, '1');
                button.setAttribute('aria-label', isPrev ? 'Previous slide' : 'Next slide');
                if (wrapper && wrapper.id) {
                    button.setAttribute('aria-controls', wrapper.id);
                }
                button.innerHTML = `<i class="ci-chevron-${isPrev ? 'left' : 'right'} fs-lg animate-target"></i>`;
                shell.insertBefore(button, slider);
                return button;
            };

            if (!prevButton) {
                prevButton = makeButton('prev');
            }
            if (!nextButton) {
                nextButton = makeButton('next');
            }

            const navigationConfig = {
                prevEl: prevButton,
                nextEl: nextButton,
            };
            const swiper = slider.swiper;

            if (swiper) {
                swiper.params.navigation = Object.assign({}, swiper.params.navigation || {}, navigationConfig);
                if (swiper.navigation) {
                    try {
                        swiper.navigation.destroy();
                        swiper.navigation.init();
                        swiper.navigation.update();
                    } catch (error) {
                        prevButton.addEventListener('click', () => swiper.slidePrev());
                        nextButton.addEventListener('click', () => swiper.slideNext());
                    }
                } else {
                    prevButton.addEventListener('click', () => swiper.slidePrev());
                    nextButton.addEventListener('click', () => swiper.slideNext());
                }
                swiper.update();
                return;
            }

            let options = {};
            try {
                options = slider.dataset.swiper ? JSON.parse(slider.dataset.swiper) : {};
            } catch (error) {
                options = {};
            }

            options.navigation = Object.assign({}, options.navigation || {}, navigationConfig);
            new Swiper(slider, options);
        });
    };

    const copyText = (text) => {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }

        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'fixed';
        textarea.style.top = '-9999px';
        document.body.appendChild(textarea);
        textarea.select();

        return new Promise((resolve, reject) => {
            try {
                document.execCommand('copy') ? resolve() : reject(new Error('Copy command failed'));
            } catch (error) {
                reject(error);
            } finally {
                textarea.remove();
            }
        });
    };

    const getCodeBlockLanguage = (pre, code) => {
        const classSource = `${pre.getAttribute('class') || ''} ${code ? code.getAttribute('class') || '' : ''}`;
        const classMatch = classSource.match(/\blanguage-([a-z0-9_-]+)\b/i);
        if (classMatch) {
            return classMatch[1].toLowerCase();
        }

        const dataLanguage = String(pre.dataset.language || (code ? code.dataset.language || '' : '') || '').trim();
        if (dataLanguage) {
            return dataLanguage.toLowerCase();
        }

        const text = String(code ? code.textContent || '' : pre.textContent || '').trim();
        if (/^<[\s\S]+>$/.test(text) || /<\/?[a-z][\s\S]*>/i.test(text)) {
            return 'html';
        }

        return 'plaintext';
    };

    const initPostCodeBlocks = () => {
        document.querySelectorAll('.post-content pre').forEach((pre) => {
            if (pre.dataset.fbCodeReady === '1') {
                return;
            }

            pre.dataset.fbCodeReady = '1';
            pre.classList.add('fb-code-block');

            let code = pre.querySelector('code');
            if (!code) {
                code = document.createElement('code');
                code.textContent = pre.textContent || '';
                pre.textContent = '';
                pre.appendChild(code);
            }

            const language = getCodeBlockLanguage(pre, code);
            if (language && language !== 'plaintext') {
                code.classList.add(`language-${language}`);
            }

            if (code && typeof hljs !== 'undefined' && hljs.highlightElement) {
                if (typeof hljs.configure === 'function') {
                    hljs.configure({ ignoreUnescapedHTML: true });
                }
                delete code.dataset.highlighted;
                hljs.highlightElement(code);
            }

            let shell = pre.closest('.fb-code-shell');
            if (!shell) {
                shell = document.createElement('div');
                shell.className = 'fb-code-shell';
                pre.parentNode.insertBefore(shell, pre);
                shell.appendChild(pre);
            }

            if (shell.querySelector('.fb-code-copy')) {
                return;
            }

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'fb-code-copy';
            button.innerHTML = '<i class="ci-copy" aria-hidden="true"></i><span>' + escapeHtml(codeCopyLabel) + '</span>';
            shell.appendChild(button);

            button.addEventListener('click', () => {
                copyText(code ? code.textContent || '' : pre.textContent || '').then(() => {
                    button.classList.add('is-copied');
                    button.innerHTML = '<i class="ci-check" aria-hidden="true"></i><span>' + escapeHtml(codeCopiedLabel) + '</span>';
                    setTimeout(() => {
                        button.classList.remove('is-copied');
                        button.innerHTML = '<i class="ci-copy" aria-hidden="true"></i><span>' + escapeHtml(codeCopyLabel) + '</span>';
                    }, 1800);
                }).catch(() => {});
            });
        });
    };

    initPostContentSliders();
    initPostCodeBlocks();

    autoDismissAlerts.each(function () {
        const alert = $(this);
        const delay = Number(alert.data('auto-dismiss-delay')) || 5000;

        setTimeout(function () {
            if (window.bootstrap && window.bootstrap.Toast) {
                const toast = window.bootstrap.Toast.getOrCreateInstance(alert[0], { autohide: true, delay: 0 });
                alert.one('hidden.bs.toast', function () {
                    alert.closest('.container').remove();
                });
                toast.hide();
                return;
            }

            alert.fadeOut(200, function () {
                alert.closest('.container').remove();
            });
        }, delay);
    });

    const updateDocumentTitle = (count) => {
        const total = Number(count) || 0;
        document.title = total > 0 ? `(${total}) ${originalTitle}` : originalTitle;
    };

    const getActiveChatContactId = () => Number(window.__chatAppState && window.__chatAppState.activeContactId || 0);
    const isSameChatOpen = (senderId) => $('[data-chat-app]').length && getActiveChatContactId() === Number(senderId || 0);

    const updateUnreadBadges = (count) => {
        const total = Number(count) || 0;

        unreadBadges.each(function () {
            const badge = $(this);
            badge.text(total);
            badge.toggleClass('d-none', total <= 0);
        });

        if (!notificationCenter.length) {
            updateDocumentTitle(total);
        }

        lastUnreadTotal = total;
        unreadBadgesReady = true;
    };

    const renderNotificationItems = (items) => {
        if (!notificationList.length) {
            return;
        }

        if (!Array.isArray(items) || !items.length) {
            notificationList.html(
                `<div class="px-3 py-3 text-body-secondary small">${escapeHtml(notificationCenter.data('empty-text') || 'No notifications')}</div>`
            );
            return;
        }

        let html = '';

        items.forEach((item) => {
            const type = String(item.type || '');
            let sourceClass = 'text-bg-primary';
            if (type === 'contact_request') {
                sourceClass = 'text-bg-warning';
            } else if (type === 'update') {
                sourceClass = 'text-bg-success';
            }

            const avatar = item.avatar
                ? `<img src="${escapeHtml(item.avatar)}" alt="" class="rounded-circle object-fit-cover border flex-shrink-0" style="width: 40px; height: 40px;">`
                : '';

            html += `
                <a class="list-group-item list-group-item-action px-3 py-3" href="${escapeHtml(item.url || '#')}">
                    <div class="d-flex align-items-start justify-content-between gap-3">
                        <div class="d-flex align-items-start gap-3 min-w-0 flex-grow-1">
                            ${avatar}
                            <div class="min-w-0 flex-grow-1">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="badge ${sourceClass} rounded-pill">${escapeHtml(item.source_label || '')}</span>
                                </div>
                                <div class="fw-semibold text-truncate">${escapeHtml(item.title || '')}</div>
                                <div class="small text-body-secondary text-wrap">${escapeHtml(item.text || '')}</div>
                            </div>
                        </div>
                        <div class="small text-body-tertiary text-nowrap">${escapeHtml(item.time || item.created_at || '')}</div>
                    </div>
                </a>
            `;
        });

        notificationList.html(html);
    };

    const notifyChatItems = (items) => {
        if (!Array.isArray(items) || typeof toastr.chat !== 'function') {
            notificationsReady = true;
            return;
        }

        items.forEach((item) => {
            if (String(item.type || '') !== 'chat') {
                return;
            }

            const key = `chat:${Number(item.sender_id || 0)}:${Number(item.sort_id || 0)}`;
            if (!notificationsReady) {
                seenNotificationKeys.add(key);
                return;
            }

            if (seenNotificationKeys.has(key) || isSameChatOpen(item.sender_id)) {
                seenNotificationKeys.add(key);
                return;
            }

            seenNotificationKeys.add(key);
            toastr.chat({
                title: item.title || notificationCenter.data('chat-source-label') || '',
                message: item.text || '',
                avatar: item.avatar || '',
                time: item.time || item.created_at || '',
                href: item.url || '#',
            });
        });

        notificationsReady = true;
    };

    const updateNotificationBadges = (count) => {
        const total = Number(count) || 0;

        notificationBadges.each(function () {
            const badge = $(this);
            badge.text(total);
            badge.toggleClass('d-none', total <= 0);
        });

        updateDocumentTitle(total);
    };

    const applyNotificationFeed = (response) => {
        if (!response || !response.status) {
            return;
        }

        updateUnreadBadges(response.chat_unread_count || 0);
        updateNotificationBadges(response.total_unread_count || 0);
        renderNotificationItems(response.items || []);
        notifyChatItems(response.items || []);
    };

    const pollNotificationFeed = () => {
        if (!notificationCenter.length) {
            return;
        }

        const feedUrl = notificationCenter.data('feed-url');
        if (!feedUrl) {
            return;
        }

        $.ajax({
            url: feedUrl,
            method: 'GET',
            dataType: 'json',
            success: function (response) {
                applyNotificationFeed(response);
            }
        });
    };

    const pollUnreadCount = () => {
        if (!unreadBadges.length) {
            return;
        }

        const unreadUrl = unreadBadges.first().data('unread-url');
        if (!unreadUrl) {
            return;
        }

        $.ajax({
            url: unreadUrl,
            method: 'GET',
            dataType: 'json',
            success: function (response) {
                if (!response || !response.status) {
                    return;
                }

                updateUnreadBadges(response.unread_count || 0);
            }
        });
    };

    if (notificationCenter.length) {
        pollNotificationFeed();
        setInterval(pollNotificationFeed, 8000);
    } else if (unreadBadges.length) {
        pollUnreadCount();
        setInterval(pollUnreadCount, 8000);
    }

    $(document).on('chat:unread-updated', function (_, count) {
        updateUnreadBadges(count);

        if (notificationCenter.length) {
            pollNotificationFeed();
        }
    });

    $('[data-slug-source]').each(function () {
        const source = $(this);
        const target = $(source.data('slug-source'));

        if (!target.length) {
            return;
        }

        let manualSlug = target.val().trim() !== '' && target.val().trim() !== makeSlug(source.val());

        source.on('input', function () {
            if (!manualSlug) {
                target.val(makeSlug(source.val()));
            }
        });

        target.on('input', function () {
            manualSlug = target.val().trim() !== '';
        });

        target.on('blur', function () {
            target.val(makeSlug(target.val()));
        });

        if (!target.val().trim() && source.val().trim()) {
            target.val(makeSlug(source.val()));
        }
    });

    $('[data-translit-source]').each(function () {
        const source = $(this);
        const target = $(source.data('translit-source'));

        if (!target.length) {
            return;
        }

        let manualValue = false;
        let isAutoUpdating = false;

        source.on('input', function () {
            if (!manualValue) {
                isAutoUpdating = true;
                target.val(transliterateToLatin(source.val())).trigger('input');
                isAutoUpdating = false;
            }
        });

        target.on('input', function () {
            if (isAutoUpdating) {
                return;
            }

            manualValue = target.val().trim() !== '';
        });

        if (!target.val().trim() && source.val().trim()) {
            isAutoUpdating = true;
            target.val(transliterateToLatin(source.val())).trigger('input');
            isAutoUpdating = false;
        }
    });

    searchForms.each(function () {
        const form = $(this);
        const input = form.find('[data-search-suggest-input]');
        const results = form.find('[data-search-suggest-results]');
        const suggestUrl = form.data('suggest-url');
        let xhr = null;
        let debounceTimer = null;

        const hideResults = () => {
            results.addClass('d-none').empty();
        };

        const renderItems = (items, emptyText) => {
            if (!items.length) {
                results.html(
                    `<div class="search-suggest-menu bg-body border rounded-4 shadow-sm p-3 text-body-secondary fs-sm">${escapeHtml(emptyText)}</div>`
                ).removeClass('d-none');
                return;
            }

            let html = '<div class="search-suggest-menu bg-body border rounded-4 shadow-sm overflow-hidden">';

            items.forEach((item) => {
                html += `
                    <a class="search-suggest-menu__item d-flex align-items-start justify-content-between gap-3 text-decoration-none text-reset px-3 py-2 border-bottom" href="${escapeHtml(item.url)}">
                        <div class="min-w-0">
                            <div class="fs-xs text-body-tertiary text-uppercase mb-1">${escapeHtml(item.type_label)}</div>
                            <div class="fw-medium text-truncate">${escapeHtml(item.title)}</div>
                        </div>
                        <div class="fs-xs text-body-tertiary text-end flex-shrink-0">${escapeHtml(item.meta)}</div>
                    </a>
                `;
            });

            html += '</div>';
            results.html(html).removeClass('d-none');
        };

        input.on('input', function () {
            const query = $(this).val().trim();

            clearTimeout(debounceTimer);

            if (xhr) {
                xhr.abort();
                xhr = null;
            }

            if (query.length < 2) {
                hideResults();
                return;
            }

            debounceTimer = setTimeout(function () {
                xhr = $.ajax({
                    url: suggestUrl,
                    method: 'GET',
                    dataType: 'json',
                    data: { q: query },
                    success: function (response) {
                        renderItems(response.items || [], response.empty_text || '');
                    },
                    error: function () {
                        hideResults();
                    },
                    complete: function () {
                        xhr = null;
                    }
                });
            }, 250);
        });

        input.on('focus', function () {
            if ($(this).val().trim().length >= 2 && results.children().length) {
                results.removeClass('d-none');
            }
        });

        form.on('submit', function () {
            hideResults();
        });

        $(document).on('click', function (event) {
            if (!form.is(event.target) && form.has(event.target).length === 0) {
                hideResults();
            }
        });
    });

    // Remove from Cart
    $('body').on('click', '.btn-remove', function(e){
        e.preventDefault();
        let btn = $(this);
        let btnText = btn.find('.text');
        let loader = btn.find('.loader');
        let productId = btn.data('id');

        $.ajax({
            url: baseUrl + '/remove-from-cart',
            method: 'POST',
            data: {
                'product_id': productId,
                'needCSRFToken': getCsrfToken(),
            },
            beforeSend: function () {
                // btn.prop('disabled', true);
                $('.btn-remove').prop('disabled', true);
                btnText.addClass('d-none');
                loader.removeClass('d-none');
            },
            success: function (result) {
                toastr.success(result.data);
                $('.product-id-' + productId).find(addToCart).removeClass('btn-secondary').addClass('btn-dark').text('В корзину');
                $('#shoppingCart .offcanvas-body').html(result.mini_cart);
                $('#countCart').text(result.cart_qty);
                console.log(result);
            },
            error: function (request) {
                toastr.error(request.responseText);
                console.log(request);
            },
            complete: function () {
                $('.btn-remove').prop('disabled', false);
                btnText.removeClass('d-none');
                loader.addClass('d-none');
            }
        });
    });

    // Add to Cart
    $(addToCart).on('click', function(e){
        e.preventDefault();
        let btn = $(this);
        let btnText = btn.find('.text');
        let loader = btn.find('.loader');
        let productId = btn.data('id');

        $.ajax({
            url: baseUrl + '/add-to-cart',
            method: 'POST',
            data: {
                'product_id': productId,
                'needCSRFToken': getCsrfToken(),
            },
            beforeSend: function () {
                // btn.prop('disabled', true);
                addToCart.prop('disabled', true);
                btnText.addClass('d-none');
                loader.removeClass('d-none');
            },
            success: function (result) {
                toastr.success(result.data);
                $('.product-id-' + productId).find(addToCart).removeClass('btn-dark').addClass('btn-secondary').text('В корзине');
                $('#shoppingCart .offcanvas-body').html(result.mini_cart);
                $('#countCart').text(result.cart_qty);
                console.log(result);
            },
            error: function (request) {
                toastr.error(request.responseText);
                console.log(request);
            },
            complete: function () {
                setTimeout(function () {
                    // btn.prop('disabled', false);
                    addToCart.prop('disabled', false);
                    btnText.removeClass('d-none');
                    loader.addClass('d-none');
                }, 500);
            },
        });
    });

});

(function () {
    const bootstrapApi = typeof bootstrap !== 'undefined'
        ? bootstrap
        : (window.bootstrap || null);
    const body = document.body;

    const config = {
        timeOut: 5000,
        newestOnTop: false,
    };

    const variants = {
        success: {
            typeClass: 'app-toast--success',
            borderClass: 'border-success',
            iconClass: 'ci-check-circle text-success',
            title: body.dataset.toastSuccessTitle || 'Success',
        },
        error: {
            typeClass: 'app-toast--error',
            borderClass: 'border-danger',
            iconClass: 'ci-alert-circle text-danger',
            title: body.dataset.toastErrorTitle || 'Error',
        },
        info: {
            typeClass: 'app-toast--info',
            borderClass: 'border-info',
            iconClass: 'ci-info text-info',
            title: body.dataset.toastInfoTitle || 'Info',
        },
        warning: {
            typeClass: 'app-toast--warning',
            borderClass: 'border-warning',
            iconClass: 'ci-alert-triangle text-warning',
            title: body.dataset.toastWarningTitle || 'Warning',
        },
    };

    const escapeHtml = (value) => $('<div>').text(value == null ? '' : String(value)).html();

    const getContainer = () => {
        let container = document.querySelector('[data-app-toast-container]');
        if (container) {
            return container;
        }

        container = document.createElement('div');
        container.className = 'app-toast-stack position-fixed bottom-0 end-0 p-3';
        container.setAttribute('data-app-toast-container', '');
        container.setAttribute('aria-live', 'polite');
        container.setAttribute('aria-atomic', 'true');
        document.body.appendChild(container);
        return container;
    };

    const showToast = (type, message, titleOverride) => {
        const variant = variants[type] || variants.info;
        const container = getContainer();
        const toast = document.createElement('div');

        toast.className = `toast ${variant.typeClass} ${variant.borderClass} fade bg-white text-body shadow-sm`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        toast.setAttribute('data-bs-theme', 'light');

        toast.innerHTML = `
            <div class="toast-header bg-white text-body">
                <i class="${variant.iconClass} fs-base me-2"></i>
                <span class="fw-semibold">${escapeHtml(titleOverride || variant.title)}</span>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body me-2 bg-white text-body">
                ${escapeHtml(message)}
            </div>
        `;

        if (config.newestOnTop && container.firstChild) {
            container.insertBefore(toast, container.firstChild);
        } else {
            container.appendChild(toast);
        }

        if (bootstrapApi && bootstrapApi.Toast) {
            const instance = bootstrapApi.Toast.getOrCreateInstance(toast, {
                autohide: true,
                delay: Number(config.timeOut) || 5000,
            });

            toast.addEventListener('hidden.bs.toast', function () {
                toast.remove();
            }, { once: true });

            instance.show();
            return;
        }

        $(toast).addClass('show');
        setTimeout(function () {
            $(toast).removeClass('show');
            setTimeout(function () {
                toast.remove();
            }, 200);
        }, Number(config.timeOut) || 5000);
    };

    const showChatToast = (payload = {}) => {
        const container = getContainer();
        const toast = document.createElement('div');
        const targetHref = String(payload.href || '#');

        toast.className = 'toast app-toast--chat border-primary fade bg-white text-body shadow-sm';
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        toast.setAttribute('data-bs-theme', 'light');

        toast.innerHTML = `
            <div class="toast-header bg-white text-body">
                ${payload.avatar ? `<img src="${escapeHtml(payload.avatar)}" alt="" class="rounded-circle object-fit-cover border me-2" style="width: 36px; height: 36px;">` : ''}
                <i class="ci-chat text-primary fs-base me-2"></i>
                <span class="fw-semibold text-truncate">${escapeHtml(payload.title || variants.info.title)}</span>
                <span class="small text-body-tertiary ms-2">${escapeHtml(payload.time || '')}</span>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body me-2 bg-white text-body">
                ${escapeHtml(payload.message || '')}
            </div>
        `;

        toast.addEventListener('click', function (event) {
            if ($(event.target).closest('.btn-close').length || targetHref === '' || targetHref === '#') {
                return;
            }

            window.location.href = targetHref;
        });

        if (config.newestOnTop && container.firstChild) {
            container.insertBefore(toast, container.firstChild);
        } else {
            container.appendChild(toast);
        }

        if (bootstrapApi && bootstrapApi.Toast) {
            const instance = bootstrapApi.Toast.getOrCreateInstance(toast, {
                autohide: true,
                delay: Number(config.timeOut) || 5000,
            });

            toast.addEventListener('hidden.bs.toast', function () {
                toast.remove();
            }, { once: true });

            instance.show();
            return;
        }

        $(toast).addClass('show');
        setTimeout(function () {
            $(toast).removeClass('show');
            setTimeout(function () {
                toast.remove();
            }, 200);
        }, Number(config.timeOut) || 5000);
    };

    window.toastr = {
        options: config,
        success: (message, title) => showToast('success', message, title),
        error: (message, title) => showToast('error', message, title),
        info: (message, title) => showToast('info', message, title),
        warning: (message, title) => showToast('warning', message, title),
        chat: (payload) => showChatToast(payload),
        remove: () => {
            document.querySelectorAll('[data-app-toast-container] .toast').forEach((toast) => toast.remove());
        },
        clear: () => {
            document.querySelectorAll('[data-app-toast-container] .toast').forEach((toast) => {
                if (bootstrapApi && bootstrapApi.Toast) {
                    bootstrapApi.Toast.getOrCreateInstance(toast).hide();
                    return;
                }

                toast.remove();
            });
        },
    };
})();
