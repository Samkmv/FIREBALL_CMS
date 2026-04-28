$(function () {
    const page = $('[data-file-manager-page]');
    if (!page.length) {
        return;
    }

    const popupName = 'fireball_file_manager';
    const bootstrapApi = typeof bootstrap !== 'undefined' ? bootstrap : (window.bootstrap || null);
    let searchTimer = null;

    function getBrowser() {
        return $('[data-file-manager-browser]');
    }

    function getModal(selector) {
        if (!bootstrapApi) {
            return null;
        }

        const element = document.querySelector(selector);
        return element ? bootstrapApi.Modal.getOrCreateInstance(element) : null;
    }

    function setLoading(isLoading) {
        getBrowser().toggleClass('is-loading', isLoading);
    }

    function renderFeedback(status, message) {
        const wrap = $('[data-file-manager-feedback-wrap]').first();
        if (!wrap.length) {
            return;
        }

        if (!message) {
            wrap.empty();
            return;
        }

        const variant = status === 'success' ? 'success' : 'danger';
        wrap.html(
            '<div class="alert alert-' + variant + ' border-0 rounded-4 mb-0">' + $('<div>').text(String(message)).html() + '</div>'
        );
    }

    function replaceBrowser(html) {
        getBrowser().html(html);
        refreshSelectionState();
        initTooltips();
    }

    function initTooltips() {
        if (!bootstrapApi || typeof bootstrapApi.Tooltip === 'undefined') {
            return;
        }

        document.querySelectorAll('[data-file-manager-browser] [data-bs-toggle="tooltip"]').forEach(function (element) {
            bootstrapApi.Tooltip.getOrCreateInstance(element);
        });
    }

    function requestManager(url, options) {
        const requestOptions = Object.assign({
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        }, options || {});

        setLoading(true);

        return fetch(url, requestOptions)
            .then(async function (response) {
                const payload = await response.json();
                if (!response.ok) {
                    throw payload;
                }

                return payload;
            })
            .finally(function () {
                setLoading(false);
            });
    }

    function loadUrl(url, pushState) {
        requestManager(url, { method: 'GET' })
            .then(function (payload) {
                replaceBrowser(payload.html || '');
                renderFeedback('', '');

                if (pushState !== false) {
                    window.history.pushState({ url: url }, '', url);
                }
            })
            .catch(function (payload) {
                renderFeedback('error', payload && payload.message ? payload.message : 'Request failed');
            });
    }

    function submitAsyncForm(form, onSuccess) {
        const formData = new FormData(form);

        requestManager(form.action, {
            method: String(form.method || 'POST').toUpperCase(),
            body: formData
        }).then(function (payload) {
            hideVisibleModals();
            replaceBrowser(payload.html || '');
            renderFeedback(payload.status || 'success', payload.message || '');

            if (typeof onSuccess === 'function') {
                onSuccess(payload);
            }
        }).catch(function (payload) {
            if (payload && payload.html) {
                replaceBrowser(payload.html);
            }

            renderFeedback('error', payload && payload.message ? payload.message : 'Request failed');
        });
    }

    function selectedRows() {
        return $('[data-file-manager-select]:checked').closest('[data-file-manager-row]');
    }

    function refreshSelectionState() {
        const rows = $('[data-file-manager-row]');
        const checked = $('[data-file-manager-select]:checked');
        const actionToggle = $('[data-file-manager-action-toggle]');
        const countTarget = $('[data-file-manager-selection-count]');
        const toggleAll = $('[data-file-manager-toggle-all]');

        rows.each(function () {
            const row = $(this);
            const isChecked = row.find('[data-file-manager-select]').is(':checked');
            row.toggleClass('is-selected', isChecked);
            row.find('[data-file-manager-select-type]').prop('disabled', !isChecked);
        });

        countTarget.text(String(checked.length));
        actionToggle.prop('disabled', checked.length === 0);
        toggleAll.prop('checked', rows.length > 0 && checked.length === rows.length);
    }

    function currentMessages() {
        return {
            selectionRequired: String(page.data('fmSelectionRequired') || 'Select at least one item.'),
            renameSingle: String(page.data('fmRenameSingle') || 'Select one item to rename.'),
            openSingle: String(page.data('fmOpenSingle') || 'Select one item to open.'),
            deleteConfirm: String(page.data('fmDeleteConfirm') || 'Delete selected items (:count)?')
        };
    }

    function openRenameModal(row) {
        const modal = getModal('[data-file-rename-modal]');
        if (!modal || !row.length) {
            return;
        }

        const path = String(row.data('path') || '');
        const fullName = String(row.data('name') || '');
        const name = String(row.data('baseName') || '');
        const extension = String(row.data('extension') || '');

        $('[data-file-rename-path-input]').val(path);
        $('[data-file-rename-input]').val(name);
        $('[data-file-rename-current-name]').text(fullName);
        $('[data-file-rename-extension]').text(extension);
        $('[data-file-rename-extension-wrap]').toggleClass('d-none', !extension);
        modal.show();

        window.setTimeout(function () {
            $('[data-file-rename-input]').trigger('focus').trigger('select');
        }, 120);
    }

    function openPreviewModal(url, name) {
        const modal = getModal('[data-file-preview-modal]');
        if (!modal) {
            return;
        }

        $('[data-file-preview-title]').text(name || 'Preview');
        $('[data-file-preview-image]').attr('src', url).attr('alt', name);
        $('[data-file-preview-open]').attr('href', url);
        modal.show();
    }

    function closeTransientModals() {
        ['[data-file-upload-modal]', '[data-file-folder-modal]', '[data-file-rename-modal]'].forEach(function (selector) {
            const modal = getModal(selector);
            if (modal) {
                modal.hide();
            }
        });
    }

    function hideVisibleModals() {
        if (!bootstrapApi) {
            return;
        }

        document.querySelectorAll('.modal.show').forEach(function (element) {
            const instance = bootstrapApi.Modal.getInstance(element);
            if (instance) {
                instance.hide();
            }
        });
    }

    page.on('click', '[data-fm-nav-link]', function (event) {
        const link = $(this);
        const href = String(link.attr('href') || '');
        if (!href) {
            return;
        }

        event.preventDefault();
        loadUrl(href, true);
    });

    page.on('click', '[data-fm-pagination] a', function (event) {
        const href = String($(this).attr('href') || '');
        if (!href) {
            return;
        }

        event.preventDefault();
        loadUrl(href, true);
    });

    page.on('submit', '[data-fm-search-form]', function (event) {
        event.preventDefault();
        const form = event.currentTarget;
        const url = new URL(form.action || window.location.href, window.location.origin);
        url.searchParams.delete('page');
        const formData = new FormData(form);

        formData.forEach(function (value, key) {
            if (String(value) !== '') {
                url.searchParams.set(key, String(value));
            } else {
                url.searchParams.delete(key);
            }
        });

        loadUrl(url.toString(), true);
    });

    page.on('input', '[data-fm-search-form] input[type="search"]', function () {
        const form = $(this).closest('[data-fm-search-form]')[0];
        clearTimeout(searchTimer);
        searchTimer = window.setTimeout(function () {
            $(form).trigger('submit');
        }, 260);
    });

    page.on('submit', '[data-fm-async-form]', function (event) {
        event.preventDefault();
        submitAsyncForm(event.currentTarget, function () {
            closeTransientModals();
        });
    });

    page.on('change', '[data-file-manager-toggle-all]', function () {
        const checked = $(this).is(':checked');
        $('[data-file-manager-select]').prop('checked', checked);
        refreshSelectionState();
    });

    page.on('change', '[data-file-manager-select]', function () {
        refreshSelectionState();
    });

    page.on('click', '[data-file-manager-row]', function (event) {
        const target = $(event.target);
        if (target.closest('a, button, input, label').length) {
            return;
        }

        const checkbox = $(this).find('[data-file-manager-select]');
        checkbox.prop('checked', !checkbox.is(':checked')).trigger('change');
    });

    page.on('dblclick', '[data-file-manager-row]', function (event) {
        const target = $(event.target);
        if (target.closest('a, button, input, label').length) {
            return;
        }

        const row = $(this);
        const type = String(row.data('type') || '');
        const url = String(row.data('openUrl') || '');

        if (!url) {
            return;
        }

        if (type === 'directory') {
            loadUrl(url, true);
            return;
        }

        if (String(row.data('canPreview') || '0') === '1') {
            openPreviewModal(String(row.data('previewUrl') || ''), String(row.data('name') || ''));
            return;
        }

        window.open(url, '_blank', 'noopener');
    });

    page.on('click', '[data-file-manager-open-upload]', function () {
        const modal = getModal('[data-file-upload-modal]');
        if (modal) {
            modal.show();
        }
    });

    page.on('click', '[data-file-manager-open-folder]', function () {
        const modal = getModal('[data-file-folder-modal]');
        if (modal) {
            modal.show();
        }
    });

    page.on('click', '[data-file-manager-action]', function () {
        const action = String($(this).data('fileManagerAction') || '');
        const rows = selectedRows();
        const messages = currentMessages();

        if (!rows.length) {
            renderFeedback('error', messages.selectionRequired);
            return;
        }

        if (action === 'rename') {
            if (rows.length !== 1) {
                renderFeedback('error', messages.renameSingle);
                return;
            }

            openRenameModal(rows.first());
            return;
        }

        if (action === 'open') {
            if (rows.length !== 1) {
                renderFeedback('error', messages.openSingle);
                return;
            }

            const row = rows.first();
            const type = String(row.data('type') || '');
            const url = String(row.data('openUrl') || '');

            if (type === 'directory') {
                loadUrl(url, true);
            } else if (url) {
                window.open(url, '_blank', 'noopener');
            }

            return;
        }

        if (action === 'delete') {
            const form = $('[data-file-manager-bulk-form]')[0];
            if (!form) {
                return;
            }

            const message = messages.deleteConfirm.replace(':count', String(rows.length));
            if (!window.confirm(message)) {
                return;
            }

            $(form).find('[data-file-manager-action-name]').val('delete');
            submitAsyncForm(form);
        }
    });

    page.on('click', '[data-file-preview]', function () {
        openPreviewModal(
            String($(this).data('filePreviewUrl') || ''),
            String($(this).data('filePreviewName') || '')
        );
    });

    page.on('click', '[data-file-select]', function () {
        const button = $(this);
        const field = String(button.data('fileSelectField') || '');
        const value = String(button.data('fileSelectValue') || '');

        if (!field || !window.opener) {
            return;
        }

        window.opener.postMessage({
            type: 'fireball:file:selected',
            field: field,
            value: value
        }, window.location.origin);

        window.close();
    });

    page.on('click', '[data-file-rename-open]', function () {
        openRenameModal($(this).closest('[data-file-manager-row]'));
    });

    $(document).on('hidden.bs.modal', '[data-file-preview-modal]', function () {
        $('[data-file-preview-title]').text('');
        $('[data-file-preview-image]').attr('src', '').attr('alt', '');
        $('[data-file-preview-open]').attr('href', '');
    });

    $(document).on('hidden.bs.modal', '[data-file-rename-modal]', function () {
        $('[data-file-rename-path-input]').val('');
        $('[data-file-rename-input]').val('');
        $('[data-file-rename-current-name]').text('');
        $('[data-file-rename-extension]').text('');
        $('[data-file-rename-extension-wrap]').removeClass('d-none');
    });

    window.addEventListener('message', function (event) {
        if (event.origin !== window.location.origin || !event.data || event.data.type !== 'fireball:file:selected') {
            return;
        }

        const input = document.getElementById(String(event.data.field || ''));
        if (!input) {
            return;
        }

        input.value = String(event.data.value || '');
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));

        const previewImageSelector = input.getAttribute('data-file-preview-image');
        const previewTextSelector = input.getAttribute('data-file-preview-text');
        const previewWrap = document.getElementById(input.id + '_preview_wrap') || document.getElementById(input.id.replace(/_image$/, '_image_preview_wrap'));

        if (previewImageSelector) {
            const image = document.querySelector(previewImageSelector);
            if (image) {
                image.setAttribute('src', String(event.data.value || ''));
            }
        }

        if (previewTextSelector) {
            const text = document.querySelector(previewTextSelector);
            if (text) {
                text.textContent = String(event.data.value || '');
            }
        }

        if (previewWrap) {
            previewWrap.classList.toggle('d-none', !event.data.value);
        }
    });

    window.addEventListener('popstate', function () {
        loadUrl(window.location.href, false);
    });

    refreshSelectionState();
    initTooltips();

    $('[data-file-manager-open]').on('click', function () {
        const button = $(this);
        const inputId = String(button.data('file-manager-input') || '');
        const baseUrl = String(button.data('file-manager-url') || '');
        const directory = String(button.data('file-manager-dir') || '');

        if (!inputId || !baseUrl) {
            return;
        }

        const url = new URL(baseUrl, window.location.origin);
        url.searchParams.set('picker', '1');
        url.searchParams.set('field', inputId);

        if (directory) {
            url.searchParams.set('dir', directory);
        }

        window.open(url.toString(), popupName, 'width=1280,height=860,resizable=yes,scrollbars=yes');
    });
});
