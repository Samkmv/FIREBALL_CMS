(function () {
    'use strict';

    const bodyDataset = document.body ? document.body.dataset : {};
    const scrollStorageKey = 'fireball.adminDataTable.scrollOnLoad';

    const escapeHtml = (text) => {
        const element = document.createElement('div');
        element.textContent = String(text ?? '');
        return element.innerHTML;
    };

    const tableRoots = () => Array.from(document.querySelectorAll('[data-ajax-table]'));

    const findScrollTarget = (target) => {
        if (!target) {
            return document.querySelector('[data-admin-table], .admin-table-card, [data-file-manager-shell]');
        }

        return target.closest?.('[data-admin-table], .admin-table-card, [data-file-manager-shell]') || target;
    };

    const scrollToTable = (target, behavior = 'smooth') => {
        const element = findScrollTarget(target);
        if (!element || typeof element.getBoundingClientRect !== 'function') {
            return;
        }

        const viewportGap = window.matchMedia('(max-width: 767.98px)').matches ? 12 : 18;
        const top = Math.max(0, window.scrollY + element.getBoundingClientRect().top - viewportGap);
        window.scrollTo({ top, behavior });
    };

    const rememberPaginationScroll = () => {
        try {
            window.sessionStorage.setItem(scrollStorageKey, String(Date.now()));
        } catch (error) {
            // Storage can be disabled in private contexts; pagination still works.
        }
    };

    const consumePaginationScroll = () => {
        try {
            const storedAt = parseInt(window.sessionStorage.getItem(scrollStorageKey) || '0', 10);
            window.sessionStorage.removeItem(scrollStorageKey);
            return storedAt > 0 && Date.now() - storedAt < 10000;
        } catch (error) {
            return false;
        }
    };

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

    const createAjaxController = () => {
        let activeRequest = null;
        let searchTimer = null;

        if (tableRoots().length === 0) {
            return { load: () => Promise.resolve() };
        }

        const setLoading = (isLoading) => {
            tableRoots().forEach((root) => {
                root.classList.toggle('admin-table--loading', isLoading);
                root.setAttribute('aria-busy', isLoading ? 'true' : 'false');
            });
        };

        const buildFormUrl = (form, page = 1) => {
            const url = new URL(form.action || window.location.href, window.location.origin);
            const formData = new FormData(form);
            url.search = '';
            formData.forEach((value, key) => {
                const normalizedValue = String(value || '').trim();
                if (key === 'page' || normalizedValue === '') {
                    return;
                }
                url.searchParams.set(key === 'q' ? 'search' : key, normalizedValue);
            });
            url.searchParams.set('page', String(Math.max(1, parseInt(page, 10) || 1)));
            return url;
        };

        const syncForms = (url) => {
            document.querySelectorAll('[data-admin-table-form]').forEach((form) => {
                Array.from(form.elements).forEach((field) => {
                    if (!field.name || field.type === 'submit') {
                        return;
                    }
                    const queryName = field.name === 'q' ? 'search' : field.name;
                    if (field.type === 'checkbox' || field.type === 'radio') {
                        field.checked = url.searchParams.getAll(queryName).includes(field.value);
                        return;
                    }
                    if (url.searchParams.has(queryName) || field.type === 'hidden') {
                        field.value = url.searchParams.get(queryName) || (field.name === 'page' ? '1' : '');
                    }
                });
            });
        };

        const setPostsStatus = (root, status) => {
            root.querySelectorAll('[data-admin-posts-tab-button]').forEach((button) => {
                const active = button.getAttribute('data-admin-posts-tab-button') === status;
                button.classList.toggle('active', active);
                button.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            root.querySelectorAll('[data-admin-posts-pane]').forEach((pane) => {
                const active = pane.getAttribute('data-admin-posts-pane') === status;
                pane.classList.toggle('show', active);
                pane.classList.toggle('active', active);
            });
        };

        const applyJsonTable = (root, data, url) => {
            const status = url.searchParams.get('status') === 'drafts' ? 'drafts' : 'published';
            const pane = root.querySelector('[data-admin-posts-pane="' + status + '"]');
            if (!pane) {
                throw new Error('Admin Ajax table response is missing the active pane');
            }
            setPostsStatus(root, status);
            pane.innerHTML = data.html || '';
            ['published', 'drafts'].forEach((key) => {
                const count = root.querySelector('[data-admin-posts-count="' + key + '"]');
                if (count && data.counts) {
                    count.textContent = String(data.counts[key] ?? 0);
                }
            });
        };

        const applyHtmlTables = (html) => {
            const doc = new DOMParser().parseFromString(html, 'text/html');
            let replaced = 0;
            tableRoots().forEach((root) => {
                const key = root.getAttribute('data-ajax-table') || '';
                const nextRoot = Array.from(doc.querySelectorAll('[data-ajax-table]')).find((candidate) => {
                    return (candidate.getAttribute('data-ajax-table') || '') === key;
                });
                if (!nextRoot) {
                    return;
                }
                root.innerHTML = nextRoot.innerHTML;
                replaced += 1;
            });
            if (replaced === 0) {
                throw new Error('Admin Ajax table response is missing table roots');
            }
        };

        const showError = () => {
            tableRoots().forEach((root) => {
                let state = root.querySelector('[data-admin-table-error]');
                if (!state) {
                    state = document.createElement('div');
                    state.className = 'admin-table-state admin-table-state--error';
                    state.setAttribute('data-admin-table-error', '');
                    const title = bodyDataset.adminTableErrorTitle || 'Failed to load data';
                    const text = bodyDataset.adminTableErrorText || 'Try refreshing the page';
                    const retry = bodyDataset.adminTableRetryLabel || 'Retry';
                    state.innerHTML = '<strong>' + escapeHtml(title) + '</strong><span>' + escapeHtml(text) + '</span><button class="btn btn-sm btn-outline-danger rounded-pill" type="button" data-admin-table-retry>' + escapeHtml(retry) + '</button>';
                    root.appendChild(state);
                }
            });
        };

        const load = (url, options = {}) => {
            const roots = tableRoots();
            if (roots.length === 0) {
                return Promise.resolve();
            }
            if (activeRequest) {
                activeRequest.abort();
            }
            const request = new AbortController();
            activeRequest = request;
            const jsonRoot = roots.find((root) => root.getAttribute('data-ajax-table-format') === 'json');
            setLoading(true);
            syncForms(url);

            return fetch(url.toString(), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': jsonRoot ? 'application/json' : 'text/html'
                },
                signal: request.signal
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Admin table request failed');
                    }
                    return jsonRoot ? response.json() : response.text();
                })
                .then((payload) => {
                    if (jsonRoot) {
                        applyJsonTable(jsonRoot, payload, url);
                    } else {
                        applyHtmlTables(payload);
                    }
                    tableRoots().forEach((root) => {
                        root.querySelector('[data-admin-table-error]')?.remove();
                        initAdminTableScrollbars(root);
                        initBootstrapTooltips(root);
                    });
                    const scrollTarget = options.scrollTarget || jsonRoot || tableRoots()[0];
                    if (options.scroll === true && scrollTarget) {
                        window.requestAnimationFrame(() => scrollToTable(scrollTarget));
                    }
                    if (options.pushState !== false) {
                        window.history.pushState({ adminTableUrl: url.toString() }, '', url.toString());
                    }
                })
                .catch((error) => {
                    if (error.name === 'AbortError') {
                        return;
                    }
                    console.error(error);
                    showError();
                    window.location.href = url.toString();
                })
                .finally(() => {
                    if (activeRequest === request) {
                        setLoading(false);
                        activeRequest = null;
                    }
                });
        };

        document.addEventListener('click', (event) => {
            const retry = event.target.closest('[data-admin-table-retry]');
            if (retry) {
                load(new URL(window.location.href), { pushState: false });
                return;
            }
            const link = event.target.closest('[data-ajax-table] .pagination a, [data-ajax-table] thead a');
            if (!link || !link.href || link.getAttribute('href') === '#') {
                return;
            }
            event.preventDefault();
            const isPagination = Boolean(link.closest('[data-datatable-pagination], .admin-pagination-wrap, [data-fm-pagination]'));
            load(new URL(link.href, window.location.origin), {
                scroll: isPagination,
                scrollTarget: link.closest('[data-ajax-table]')
            });
        });

        document.addEventListener('submit', (event) => {
            const form = event.target.closest('[data-admin-table-form]');
            if (!form) {
                return;
            }
            event.preventDefault();
            load(buildFormUrl(form, 1));
        });

        document.addEventListener('input', (event) => {
            const input = event.target.closest('[data-admin-table-search]');
            const form = input?.closest('[data-admin-table-form]');
            if (!input || !form) {
                return;
            }
            window.clearTimeout(searchTimer);
            searchTimer = window.setTimeout(() => load(buildFormUrl(form, 1)), 300);
        });

        document.addEventListener('shown.bs.tab', (event) => {
            const button = event.target.closest('[data-admin-posts-tab-button]');
            const root = button?.closest('[data-ajax-table-format="json"]');
            if (!button || !root) {
                return;
            }
            const form = root.querySelector('[data-admin-table-form]');
            const status = button.getAttribute('data-admin-posts-tab-button') === 'drafts' ? 'drafts' : 'published';
            if (form?.elements.status) {
                form.elements.status.value = status;
            }
            load(buildFormUrl(form, 1), { scrollTarget: root });
        });

        window.addEventListener('popstate', () => {
            load(new URL(window.location.href), { pushState: false, scroll: false });
        });

        return { load };
    };

    document.addEventListener('click', (event) => {
        const link = event.target.closest('[data-admin-shell] [data-datatable-pagination] a, [data-file-manager-shell] [data-datatable-pagination] a, [data-file-manager-shell] [data-fm-pagination] a');
        if (!link || !link.href || link.getAttribute('href') === '#' || link.closest('[data-ajax-table]')) {
            return;
        }
        rememberPaginationScroll();
    });

    if (consumePaginationScroll()) {
        window.requestAnimationFrame(() => scrollToTable(null, 'auto'));
    }

    const ajaxController = createAjaxController();

    window.FireballDataTable = {
        load: ajaxController.load,
        refresh: ajaxController.load,
        scrollToTable
    };
})();
