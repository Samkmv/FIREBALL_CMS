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
    const syncOffcanvasState = () => {
        if (document.body) {
            document.body.classList.toggle('has-open-offcanvas', document.querySelector('.offcanvas.show, .offcanvas.showing') !== null);
        }
    };
    const slugMap = {
        'а': 'a', 'б': 'b', 'в': 'v', 'г': 'g', 'д': 'd',
        'е': 'e', 'ё': 'e', 'ж': 'zh', 'з': 'z', 'и': 'i',
        'й': 'y', 'к': 'k', 'л': 'l', 'м': 'm', 'н': 'n',
        'о': 'o', 'п': 'p', 'р': 'r', 'с': 's', 'т': 't',
        'у': 'u', 'ф': 'f', 'х': 'h', 'ц': 'c', 'ч': 'ch',
        'ш': 'sh', 'щ': 'shch', 'ъ': '', 'ы': 'y', 'ь': '',
        'э': 'e', 'ю': 'yu', 'я': 'ya'
    };

    document.addEventListener('show.bs.offcanvas', syncOffcanvasState);
    document.addEventListener('shown.bs.offcanvas', syncOffcanvasState);
    document.addEventListener('hide.bs.offcanvas', syncOffcanvasState);
    document.addEventListener('hidden.bs.offcanvas', syncOffcanvasState);

    const initBootstrapTooltips = (container) => {
        if (!window.bootstrap?.Tooltip || !container) {
            return;
        }

        container.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((element) => {
            window.bootstrap.Tooltip.getOrCreateInstance(element, { trigger: 'hover' });
        });
    };

    const initAdminTableScrollbars = (container) => {
        if (!window.SimpleBar || !container) {
            return;
        }

        const scrollables = container.matches?.('.admin-table-scroll')
            ? [container, ...container.querySelectorAll('.admin-table-scroll')]
            : Array.from(container.querySelectorAll('.admin-table-scroll'));

        scrollables.forEach((element) => {
            const instance = window.SimpleBar.instances?.get(element);
            if (instance) {
                instance.recalculate();
                return;
            }

            element.setAttribute('data-simplebar', '');
            new window.SimpleBar(element, { autoHide: true });
        });
    };

    initAdminTableScrollbars(document);

    const adminPostActionDropdowns = new WeakMap();
    const getAdminPostActionDropdown = (target) => {
        if (!target || !target.closest) {
            return null;
        }

        return target.closest('[data-admin-post-actions-dropdown]');
    };

    const placeAdminPostActionMenu = (dropdown) => {
        const state = adminPostActionDropdowns.get(dropdown);
        if (!state || !state.menu || !state.toggle) {
            return;
        }

        const gap = 6;
        const viewportGap = 12;
        const buttonRect = state.toggle.getBoundingClientRect();
        const menu = state.menu;
        menu.style.position = 'fixed';
        menu.style.left = 'auto';
        menu.style.right = Math.max(viewportGap, window.innerWidth - buttonRect.right) + 'px';
        menu.style.maxHeight = Math.max(160, window.innerHeight - viewportGap * 2) + 'px';
        menu.style.overflowY = 'auto';

        const menuHeight = menu.offsetHeight || 0;
        const belowTop = buttonRect.bottom + gap;
        const aboveTop = buttonRect.top - menuHeight - gap;
        const belowSpace = window.innerHeight - buttonRect.bottom - viewportGap;
        const aboveSpace = buttonRect.top - viewportGap;
        const shouldOpenUp = menuHeight > belowSpace && aboveSpace > belowSpace;
        const top = shouldOpenUp
            ? Math.max(viewportGap, aboveTop)
            : Math.min(belowTop, window.innerHeight - menuHeight - viewportGap);

        menu.style.top = Math.max(viewportGap, top) + 'px';
    };

    document.addEventListener('show.bs.dropdown', (event) => {
        const dropdown = getAdminPostActionDropdown(event.target);
        if (!dropdown) {
            return;
        }

        const menu = dropdown.querySelector('.dropdown-menu');
        const toggle = dropdown.querySelector('[data-bs-toggle="dropdown"]');
        if (!menu || !toggle) {
            return;
        }

        const placeholder = document.createComment('admin-post-actions-menu');
        menu.parentNode.insertBefore(placeholder, menu);

        const update = () => placeAdminPostActionMenu(dropdown);
        adminPostActionDropdowns.set(dropdown, {
            menu,
            toggle,
            placeholder,
            parent: dropdown,
            update
        });

        document.body.appendChild(menu);
        menu.classList.add('admin-post-actions-dropdown__menu--floating');
    });

    document.addEventListener('shown.bs.dropdown', (event) => {
        const dropdown = getAdminPostActionDropdown(event.target);
        if (!dropdown || !adminPostActionDropdowns.has(dropdown)) {
            return;
        }

        placeAdminPostActionMenu(dropdown);
        const state = adminPostActionDropdowns.get(dropdown);
        if (!state) {
            return;
        }

        window.addEventListener('resize', state.update, { passive: true });
        document.addEventListener('scroll', state.update, { passive: true, capture: true });
    });

    document.addEventListener('hide.bs.dropdown', (event) => {
        const dropdown = getAdminPostActionDropdown(event.target);
        const state = dropdown ? adminPostActionDropdowns.get(dropdown) : null;
        if (!dropdown || !state) {
            return;
        }

        window.removeEventListener('resize', state.update);
        document.removeEventListener('scroll', state.update, { capture: true });

        state.menu.classList.remove('admin-post-actions-dropdown__menu--floating');
        state.menu.removeAttribute('style');
        if (state.placeholder.parentNode) {
            state.placeholder.parentNode.insertBefore(state.menu, state.placeholder);
            state.placeholder.parentNode.removeChild(state.placeholder);
        } else {
            state.parent.appendChild(state.menu);
        }

        adminPostActionDropdowns.delete(dropdown);
    });

    document.querySelectorAll('[data-admin-posts-tabs]').forEach((root) => {
        const input = root.querySelector('[data-admin-posts-live-search]');
        const panes = Array.from(root.querySelectorAll('[data-admin-posts-pane]'));
        const form = root.querySelector('[data-admin-posts-live-form]');
        const statusInput = root.querySelector('[data-admin-posts-status-input]');
        const pageInput = root.querySelector('[data-admin-posts-page-input]');
        const sortInput = form ? form.querySelector('input[name="sort"]') : null;
        const directionInput = form ? form.querySelector('input[name="direction"]') : null;
        const endpoint = root.getAttribute('data-admin-posts-url') || window.location.pathname;
        let activeStatus = root.querySelector('[data-admin-posts-tab-button].active')?.getAttribute('data-admin-posts-tab-button') || 'published';
        let activeRequest = null;
        let searchTimer = null;

        if (!input || !panes.length) {
            return;
        }

        const getPane = (status) => root.querySelector('[data-admin-posts-pane="' + status + '"]');
        const setLoading = (isLoading) => {
            root.classList.toggle('admin-posts-tabs--loading', isLoading);
        };

        const buildUrl = (status, page = 1) => {
            const url = new URL(endpoint, window.location.origin);
            const search = input.value.trim();
            const sort = sortInput ? sortInput.value.trim() : '';
            const direction = directionInput ? directionInput.value.trim() : '';

            if (search !== '') {
                url.searchParams.set('search', search);
            }
            url.searchParams.set('status', status);
            url.searchParams.set('page', String(Math.max(1, parseInt(page, 10) || 1)));
            if (sort !== '') {
                url.searchParams.set('sort', sort);
            }
            if (direction !== '') {
                url.searchParams.set('direction', direction);
            }

            return url;
        };

        const applyUrlState = (url) => {
            const status = url.searchParams.get('status') === 'drafts' ? 'drafts' : 'published';
            activeStatus = status;
            input.value = url.searchParams.get('search') || '';

            if (statusInput) {
                statusInput.value = status;
            }
            if (pageInput) {
                pageInput.value = url.searchParams.get('page') || '1';
            }
            if (sortInput) {
                sortInput.value = url.searchParams.get('sort') || sortInput.value || '';
            }
            if (directionInput) {
                directionInput.value = url.searchParams.get('direction') || directionInput.value || '';
            }
        };

        const updateTabCounts = (counts) => {
            if (!counts) {
                return;
            }

            ['published', 'drafts'].forEach((status) => {
                const countNode = root.querySelector('[data-admin-posts-count="' + status + '"]');
                if (countNode) {
                    countNode.textContent = String(counts[status] ?? 0);
                }
            });
        };

        const loadTable = (url, pushState = true) => {
            applyUrlState(url);

            const pane = getPane(activeStatus);
            if (!pane) {
                return Promise.resolve();
            }

            if (activeRequest) {
                activeRequest.abort();
            }

            activeRequest = new AbortController();
            setLoading(true);

            return fetch(url.toString(), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                signal: activeRequest.signal
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Admin table request failed');
                    }

                    return response.json();
                })
                .then((data) => {
                    pane.innerHTML = data.html || '';
                    initAdminTableScrollbars(pane);
                    initBootstrapTooltips(pane);
                    updateTabCounts(data.counts);
                    if (pushState) {
                        window.history.pushState({ adminTableUrl: url.toString() }, '', url.toString());
                    }
                })
                .catch((error) => {
                    if (error.name !== 'AbortError') {
                        console.error(error);
                    }
                })
                .finally(() => {
                    setLoading(false);
                    activeRequest = null;
                });
        };

        root.querySelectorAll('[data-admin-posts-tab-button]').forEach((button) => {
            button.addEventListener('shown.bs.tab', () => {
                const status = button.getAttribute('data-admin-posts-tab-button') === 'drafts' ? 'drafts' : 'published';
                loadTable(buildUrl(status, 1));
            });
        });

        input.addEventListener('input', () => {
            window.clearTimeout(searchTimer);
            searchTimer = window.setTimeout(() => {
                loadTable(buildUrl(activeStatus, 1));
            }, 300);
        });

        form?.addEventListener('submit', (event) => {
            event.preventDefault();
            loadTable(buildUrl(activeStatus, 1));
        });

        root.addEventListener('click', (event) => {
            const link = event.target.closest('[data-admin-posts-pane] .pagination a, [data-admin-posts-pane] thead a');
            if (!link || !link.href) {
                return;
            }

            event.preventDefault();
            const url = new URL(link.href, window.location.origin);
            loadTable(url);
        });

        window.addEventListener('popstate', () => {
            loadTable(new URL(window.location.href), false);
        });
    });

    document.querySelectorAll('[data-admin-live-table]').forEach((root) => {
        let activeRequest = null;
        let searchTimer = null;

        const getInput = () => root.querySelector('[data-admin-live-table-search]');
        const getForm = () => root.querySelector('[data-admin-live-table-form]');
        const setLoading = (isLoading) => {
            root.classList.toggle('admin-live-table--loading', isLoading);
        };

        const buildLiveTableUrl = (page = 1) => {
            const form = getForm();
            const input = getInput();
            const url = new URL(form ? form.action || window.location.href : window.location.href, window.location.origin);
            const formData = form ? new FormData(form) : new FormData();
            const search = input ? input.value.trim() : '';

            url.search = '';
            formData.forEach((value, key) => {
                const normalizedValue = String(value || '').trim();
                if (key === 'q' || key === 'page' || normalizedValue === '') {
                    return;
                }

                url.searchParams.set(key, normalizedValue);
            });
            if (search !== '') {
                url.searchParams.set('search', search);
            }
            url.searchParams.set('page', String(Math.max(1, parseInt(page, 10) || 1)));

            return url;
        };

        const syncFormFromUrl = (url) => {
            const form = getForm();
            const input = getInput();
            if (!form) {
                return;
            }

            form.querySelectorAll('input[type="hidden"]').forEach((field) => {
                field.value = url.searchParams.get(field.name) || field.value || '';
            });
            const pageInput = form.querySelector('[data-admin-live-table-page-input]');
            if (pageInput) {
                pageInput.value = url.searchParams.get('page') || '1';
            }
            if (input) {
                input.value = url.searchParams.get('search') || '';
            }
        };

        const replaceLiveTableHtml = (html) => {
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const nextRoot = doc.querySelector('[data-admin-live-table]');
            if (!nextRoot) {
                throw new Error('Admin live table response is missing table root');
            }

            root.innerHTML = nextRoot.innerHTML;
        };

        const loadLiveTable = (url, pushState = true) => {
            syncFormFromUrl(url);

            if (activeRequest) {
                activeRequest.abort();
            }

            activeRequest = new AbortController();
            setLoading(true);

            return fetch(url.toString(), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'text/html'
                },
                signal: activeRequest.signal
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Admin live table request failed');
                    }

                    return response.text();
                })
                .then((html) => {
                    replaceLiveTableHtml(html);
                    initAdminTableScrollbars(root);
                    initBootstrapTooltips(root);
                    if (pushState) {
                        window.history.pushState({ adminLiveTableUrl: url.toString() }, '', url.toString());
                    }
                })
                .catch((error) => {
                    if (error.name !== 'AbortError') {
                        console.error(error);
                    }
                })
                .finally(() => {
                    setLoading(false);
                    activeRequest = null;
                });
        };

        if (!getInput()) {
            return;
        }

        root.addEventListener('input', (event) => {
            if (!event.target.matches('[data-admin-live-table-search]')) {
                return;
            }

            window.clearTimeout(searchTimer);
            searchTimer = window.setTimeout(() => {
                loadLiveTable(buildLiveTableUrl(1));
            }, 300);
        });

        root.addEventListener('submit', (event) => {
            if (!event.target.matches('[data-admin-live-table-form]')) {
                return;
            }

            event.preventDefault();
            loadLiveTable(buildLiveTableUrl(1));
        });

        root.addEventListener('click', (event) => {
            const link = event.target.closest('.pagination a, thead a');
            if (!link || !link.href || !root.contains(link)) {
                return;
            }

            event.preventDefault();
            loadLiveTable(new URL(link.href, window.location.origin));
        });

        window.addEventListener('popstate', () => {
            loadLiveTable(new URL(window.location.href), false);
        });
    });

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

            let html = '<div class="search-suggest-menu bg-body border rounded-4 shadow-sm">';

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
