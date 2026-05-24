$(function () {
    const chatApp = $('[data-chat-app]');
    if (!chatApp.length) {
        return;
    }

    const fetchUrl = String(chatApp.data('fetch-url') || '');
    const sendUrl = String(chatApp.data('send-url') || '');
    const deleteUrl = String(chatApp.data('delete-url') || '');
    const clearUrl = String(chatApp.data('clear-url') || '');
    const auditUrl = String(chatApp.data('audit-url') || '');
    const bootstrapApi = typeof bootstrap !== 'undefined'
        ? bootstrap
        : (window.bootstrap || null);
    const currentUserAvatar = String(chatApp.data('current-user-avatar') || '');
    const previewUnavailableText = String(chatApp.data('preview-unavailable-text') || 'Preview unavailable.');
    const previewLoadingText = String(chatApp.data('preview-loading-text') || 'Loading...');
    const maxFileSize = 200 * 1024 * 1024;
    const blockedExtensions = ['exe', 'bat', 'cmd', 'sh', 'apk', 'js'];
    const allowedExtensions = String(chatApp.find('[data-chat-attachment]').attr('accept') || '')
        .split(',')
        .map((item) => item.trim().replace(/^\./, '').toLowerCase())
        .filter(Boolean);

    const getContactButtons = () => chatApp.find('[data-chat-contact]');
    const contactGroupLists = chatApp.find('[data-chat-contact-group-list]');
    const contactGroups = chatApp.find('[data-chat-contact-group]');
    const contactSearchInput = chatApp.find('[data-chat-contact-search]');
    const contactSearchEmpty = chatApp.find('[data-chat-search-empty]');
    const messagesBox = chatApp.find('[data-chat-messages]');
    const currentName = chatApp.find('[data-chat-current-name]');
    const currentAvatar = chatApp.find('[data-chat-current-avatar]');
    const currentStatus = chatApp.find('[data-chat-current-status]');
    const messageSearchInput = chatApp.find('[data-chat-message-search]');
    const messageSearchResults = chatApp.find('[data-chat-message-search-results]');
    const form = chatApp.find('[data-chat-form]');
    const userIdInput = form.find('[data-chat-user-id]');
    const messageInput = form.find('input[name="message"]');
    const attachmentInput = form.find('[data-chat-attachment]');
    const siteFileInput = form.find('[data-chat-site-file-input]');
    const cameraInput = form.find('[data-chat-camera-input]');
    const galleryInput = form.find('[data-chat-gallery-input]');
    const sendButton = form.find('button[type="submit"]');
    const dropzone = form.find('[data-chat-dropzone]');
    const dropzoneOverlay = form.find('[data-chat-dropzone-overlay]');
    const pendingAttachment = form.find('[data-chat-pending-attachment]');
    const pendingList = form.find('[data-chat-pending-list]');
    const pendingMeta = form.find('[data-chat-pending-meta]');
    const progressWrap = form.find('[data-chat-upload-progress-wrap]');
    const progressBar = form.find('[data-chat-upload-progress]');
    const offcanvasElement = $('#accountSidebar');
    const previewModalElement = $('[data-chat-preview-modal]').first();
    const previewModalTitle = previewModalElement.find('[data-chat-preview-modal-title]');
    const previewModalBody = previewModalElement.find('[data-chat-preview-modal-body]');
    const previewModalOpen = previewModalElement.find('[data-chat-preview-modal-open]');
    const previewModalDownload = previewModalElement.find('[data-chat-preview-modal-download]');
    const auditModalElement = $('[data-chat-audit-modal]').first();
    const auditList = auditModalElement.find('[data-chat-audit-list]');
    const confirmModalElement = $('[data-chat-confirm-modal]').first();
    const confirmModalMessage = confirmModalElement.find('[data-chat-confirm-message]');
    const confirmModalSubmit = confirmModalElement.find('[data-chat-confirm-submit]');
    const selectionToggleButton = chatApp.find('[data-chat-selection-toggle]');
    const selectionCancelButton = chatApp.find('[data-chat-selection-cancel]');
    const deleteSelectedButton = chatApp.find('[data-chat-delete-selected]');

    const offcanvas = offcanvasElement.length && bootstrapApi && bootstrapApi.Offcanvas
        ? bootstrapApi.Offcanvas.getOrCreateInstance(offcanvasElement[0])
        : null;
    const previewModal = previewModalElement.length && bootstrapApi && bootstrapApi.Modal
        ? bootstrapApi.Modal.getOrCreateInstance(previewModalElement[0])
        : null;
    const auditModal = auditModalElement.length && bootstrapApi && bootstrapApi.Modal
        ? bootstrapApi.Modal.getOrCreateInstance(auditModalElement[0])
        : null;
    const confirmModal = confirmModalElement.length && bootstrapApi && bootstrapApi.Modal
        ? bootstrapApi.Modal.getOrCreateInstance(confirmModalElement[0])
        : null;

    const state = {
        messages: [],
        renderedSignature: '',
        previewRequestId: 0,
        pendingFiles: [],
        pendingSiteAttachments: [],
        pendingObjectUrls: [],
        dragDepth: 0,
        selectionMode: false,
        selectedIds: new Set(),
        confirmAction: null,
        canModerate: String(chatApp.data('can-moderate')) === '1',
        canBulkDelete: String(chatApp.data('can-bulk-delete')) === '1',
        canClearChat: String(chatApp.data('can-clear-chat')) === '1',
        canViewAudit: String(chatApp.data('can-view-audit')) === '1',
    };

    const escapeHtml = (text) => $('<div>').text(text || '').html();
    const escapeRegExp = (text) => String(text || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const activeContactId = () => Number(userIdInput.val()) || 0;
    const getStatusText = (isOnline) => (
        isOnline ? String(chatApp.data('online-text') || 'Online') : String(chatApp.data('offline-text') || 'Offline')
    );
    const getPreviewText = (value) => String(value || chatApp.data('empty-text') || '');
    const getGroupKey = (value) => String(value || 'clients');
    const currentSearchQuery = () => String(messageSearchInput.val() || '').trim();
    const canUseShowPicker = (input) => input && typeof input.showPicker === 'function';

    const openNativeFilePicker = (input) => {
        if (!input || input.disabled) {
            return;
        }

        input.value = '';

        if (canUseShowPicker(input)) {
            try {
                input.showPicker();
                return;
            } catch (error) {
                // Fall through to click() when showPicker is unavailable for the current browser state.
            }
        }

        input.click();
    };

    const formatBytes = (bytes) => {
        const size = Number(bytes) || 0;
        if (size >= 1024 * 1024) {
            return `${(size / 1024 / 1024).toFixed(1)} MB`;
        }
        if (size >= 1024) {
            return `${Math.ceil(size / 1024)} KB`;
        }
        return `${size} B`;
    };

    const highlightText = (value, query) => {
        const text = String(value || '');
        const normalizedQuery = String(query || '').trim();
        if (normalizedQuery === '') {
            return escapeHtml(text);
        }

        const pattern = new RegExp(escapeRegExp(normalizedQuery), 'ig');
        let lastIndex = 0;
        let output = '';
        let match = null;

        while ((match = pattern.exec(text)) !== null) {
            output += escapeHtml(text.slice(lastIndex, match.index));
            output += `<mark class="chat-message-highlight">${escapeHtml(match[0])}</mark>`;
            lastIndex = match.index + match[0].length;
        }

        output += escapeHtml(text.slice(lastIndex));
        return output;
    };

    const buildMessagesSignature = (messages) => messages.map((item) => [
        Number(item.id) || 0,
        Number(item.sender_id) || 0,
        Number(item.is_read) || 0,
        item.created_at || '',
        item.message || '',
        item.attachment && item.attachment.url ? item.attachment.url : ''
    ].join(':')).join('|');

    const isNearBottom = (element, threshold = 24) => (
        (element.scrollHeight - (element.scrollTop + element.clientHeight)) <= threshold
    );

    const renderPresenceBadge = (isOnline) => `
        <span class="rounded-circle d-inline-block flex-shrink-0 ${isOnline ? 'bg-success' : 'bg-secondary'}" style="width: 8px; height: 8px;"></span>
        <span>${escapeHtml(getStatusText(isOnline))}</span>
    `;

    const updateCurrentContactPresence = (isOnline) => {
        currentStatus
            .toggleClass('text-success', isOnline)
            .toggleClass('text-body-secondary', !isOnline)
            .html(renderPresenceBadge(isOnline));
    };

    const getAttachmentIcon = (kind, previewKind) => {
        if (previewKind === 'image') {
            return 'ci-image';
        }
        if (previewKind === 'audio') {
            return 'ci-music';
        }
        if (previewKind === 'video') {
            return 'ci-video';
        }
        if (previewKind === 'pdf' || kind === 'document') {
            return 'ci-file-text';
        }
        if (kind === 'archive') {
            return 'ci-archive';
        }
        return 'ci-file';
    };

    const getPendingFileKind = (file) => {
        const type = String(file.type || '');
        const extension = String((file.name || '').split('.').pop() || '').toLowerCase();

        if (type.startsWith('image/')) {
            return 'image';
        }
        if (type.startsWith('video/')) {
            return 'video';
        }
        if (type.startsWith('audio/')) {
            return 'audio';
        }
        if (extension === 'pdf') {
            return 'pdf';
        }
        if (['zip', 'rar', '7z'].includes(extension)) {
            return 'archive';
        }
        return 'file';
    };

    const normalizeSiteAttachmentPath = (value) => {
        const inputValue = String(value || '').trim();
        if (inputValue === '') {
            return '';
        }

        let path = inputValue;
        try {
            const parsed = new URL(inputValue, window.location.origin);
            path = String(parsed.pathname || '');
        } catch (error) {
        }

        path = path.replace(/\\/g, '/').replace(/\/{2,}/g, '/');
        if (path.startsWith('/uploads/')) {
            path = path.slice(1);
        }

        if (!path.startsWith('uploads/') || path.includes('..')) {
            return '';
        }

        return path;
    };

    const buildSiteAttachmentUrl = (path) => {
        if (path === '') {
            return '';
        }

        return path.startsWith('/') ? path : `/${path}`;
    };

    const getSiteAttachmentKind = (path) => {
        const extension = String(path.split('.').pop() || '').toLowerCase();
        if (['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'].includes(extension)) {
            return 'image';
        }
        if (['mp4', 'webm', 'mov', 'avi', 'mkv', 'mpeg', 'mpg'].includes(extension)) {
            return 'video';
        }
        if (['mp3', 'wav', 'ogg', 'm4a', 'flac', 'aac'].includes(extension)) {
            return 'audio';
        }
        if (extension === 'pdf') {
            return 'pdf';
        }
        if (['zip', 'rar', '7z'].includes(extension)) {
            return 'archive';
        }

        return 'file';
    };

    const isExtensionAllowed = (extension, kind = 'file') => {
        if (blockedExtensions.includes(extension)) {
            return false;
        }

        if (!allowedExtensions.length) {
            return true;
        }

        if (allowedExtensions.includes(extension)) {
            return true;
        }

        if (kind === 'image' && allowedExtensions.includes('image/*')) {
            return true;
        }
        if (kind === 'video' && allowedExtensions.includes('video/*')) {
            return true;
        }
        if (kind === 'audio' && allowedExtensions.includes('audio/*')) {
            return true;
        }

        return false;
    };

    const resetPendingPreviewUrls = () => {
        state.pendingObjectUrls.forEach((url) => {
            if (url) {
                URL.revokeObjectURL(url);
            }
        });
        state.pendingObjectUrls = [];
    };

    const clearPendingAttachment = () => {
        resetPendingPreviewUrls();
        state.pendingFiles = [];
        state.pendingSiteAttachments = [];
        attachmentInput.val('');
        siteFileInput.val('');
        cameraInput.val('');
        galleryInput.val('');
        pendingAttachment.addClass('d-none');
        pendingList.empty();
        pendingMeta.text('');
        progressWrap.addClass('d-none');
        progressBar.css('width', '0%');
    };

    const renderPendingAttachment = () => {
        const totalItems = state.pendingFiles.length + state.pendingSiteAttachments.length;
        if (!totalItems) {
            clearPendingAttachment();
            return;
        }

        resetPendingPreviewUrls();
        let totalSize = 0;
        let html = '';

        state.pendingFiles.forEach((file) => {
            totalSize += Number(file.size) || 0;
            const kind = getPendingFileKind(file);
            const previewUrl = (kind === 'image' || kind === 'video') ? URL.createObjectURL(file) : '';
            if (previewUrl) {
                state.pendingObjectUrls.push(previewUrl);
            }

            let previewHtml = `<i class="${getAttachmentIcon(kind, kind)} fs-3 text-body-secondary"></i>`;
            if (kind === 'image') {
                previewHtml = `<img src="${escapeHtml(previewUrl)}" alt="${escapeHtml(file.name)}">`;
            } else if (kind === 'video') {
                previewHtml = `<video src="${escapeHtml(previewUrl)}" muted></video>`;
            }

            html += `
                <div class="chat-pending-attachment__item">
                    <div class="chat-pending-attachment__preview">${previewHtml}</div>
                    <div class="min-w-0 flex-grow-1">
                        <div class="fw-medium text-truncate">${escapeHtml(file.name)}</div>
                        <div class="small text-body-secondary mt-1">${escapeHtml(formatBytes(file.size))}</div>
                    </div>
                </div>
            `;
        });

        state.pendingSiteAttachments.forEach((item) => {
            const kind = getSiteAttachmentKind(item.path);
            const previewUrl = buildSiteAttachmentUrl(item.path);

            let previewHtml = `<i class="${getAttachmentIcon(kind, kind)} fs-3 text-body-secondary"></i>`;
            if (kind === 'image') {
                previewHtml = `<img src="${escapeHtml(previewUrl)}" alt="${escapeHtml(item.name)}">`;
            } else if (kind === 'video') {
                previewHtml = `<video src="${escapeHtml(previewUrl)}" muted></video>`;
            }

            html += `
                <div class="chat-pending-attachment__item">
                    <div class="chat-pending-attachment__preview">${previewHtml}</div>
                    <div class="min-w-0 flex-grow-1">
                        <div class="fw-medium text-truncate">${escapeHtml(item.name)}</div>
                        <div class="small text-body-secondary mt-1">${escapeHtml(item.path)}</div>
                    </div>
                </div>
            `;
        });

        pendingList.html(html);
        const metaParts = [`${totalItems}`, `${chatApp.data('attachment-ready-text') || ''}`];
        if (totalSize > 0) {
            metaParts.splice(1, 0, formatBytes(totalSize));
        }
        pendingMeta.text(metaParts.filter(Boolean).join(' • '));
        pendingAttachment.removeClass('d-none');
        progressWrap.addClass('d-none');
        progressBar.css('width', '0%');
    };

    const validatePendingFile = (file) => {
        if (!file) {
            return false;
        }

        const extension = String((file.name || '').split('.').pop() || '').toLowerCase();
        if (file.size > maxFileSize) {
            toastr.error(chatApp.data('file-too-large-text') || 'File is too large.');
            return false;
        }

        const kind = getPendingFileKind(file);
        if (!isExtensionAllowed(extension, kind)) {
            toastr.error(chatApp.data('file-type-error-text') || 'Invalid file type.');
            return false;
        }

        return true;
    };

    const setPendingFiles = (files) => {
        const preparedFiles = Array.from(files || []).filter(Boolean).filter(validatePendingFile);
        if (!preparedFiles.length) {
            return;
        }

        state.pendingFiles = state.pendingFiles.concat(preparedFiles);
        renderPendingAttachment();
    };

    const addPendingSiteAttachment = (value) => {
        const path = normalizeSiteAttachmentPath(value);
        if (path === '') {
            toastr.error(chatApp.data('file-type-error-text') || 'Invalid file type.');
            return;
        }

        const extension = String(path.split('.').pop() || '').toLowerCase();
        const kind = getSiteAttachmentKind(path);
        if (!isExtensionAllowed(extension, kind)) {
            toastr.error(chatApp.data('file-type-error-text') || 'Invalid file type.');
            return;
        }

        if (state.pendingSiteAttachments.some((item) => item.path === path)) {
            siteFileInput.val('');
            return;
        }

        state.pendingSiteAttachments.push({
            path: path,
            name: path.split('/').pop() || path,
        });
        siteFileInput.val('');
        renderPendingAttachment();
    };

    const applyContactFilter = () => {
        const query = String(contactSearchInput.val() || '').trim().toLowerCase();
        let visibleContacts = 0;

        contactGroups.each(function () {
            const group = $(this);
            let groupVisible = 0;

            group.find('[data-chat-contact]').each(function () {
                const button = $(this);
                const haystack = [
                    String(button.data('user-name') || ''),
                    String(button.data('last-message-preview') || ''),
                    String(button.data('user-role-label') || ''),
                    String(button.data('contact-group-label') || ''),
                ].join(' ').toLowerCase();
                const isVisible = query === '' || haystack.includes(query);

                button.toggleClass('d-none', !isVisible);
                if (isVisible) {
                    visibleContacts += 1;
                    groupVisible += 1;
                }
            });

            group.toggleClass('d-none', groupVisible === 0);
        });

        contactSearchEmpty.toggleClass('d-none', query === '' || visibleContacts > 0);
    };

    const updateContactUnreadBadges = (counts, totalUnread) => {
        counts = counts || {};

        getContactButtons().each(function () {
            const contact = $(this);
            const contactId = Number(contact.data('user-id'));
            const count = Number(counts[contactId] || 0);
            const badge = contact.find(`[data-chat-contact-unread="${contactId}"]`);

            badge.text(count);
            badge.toggleClass('d-none', count <= 0);
        });

        $(document).trigger('chat:unread-updated', [Number(totalUnread) || 0]);
    };

    const updateContactsPresence = (contactItems) => {
        if (!Array.isArray(contactItems)) {
            return;
        }

        contactItems.forEach((item) => {
            const contactId = Number(item.id) || 0;
            if (!contactId) {
                return;
            }

            const buttons = getContactButtons().filter(`[data-user-id="${contactId}"]`);
            if (!buttons.length) {
                return;
            }

            const isOnline = Number(item.is_online) === 1 || item.is_online === true;
            const groupKey = getGroupKey(item.chat_group || buttons.first().data('contact-group'));

            buttons.each(function () {
                const button = $(this);
                button.attr('data-contact-group', groupKey);
                button.data('user-online', isOnline ? 1 : 0);
                button.data('user-last-seen', String(item.last_seen_at || ''));
                button.data('last-message-preview', String(item.last_message_preview || ''));

                const status = button.find(`[data-chat-contact-status="${contactId}"]`);
                status
                    .toggleClass('text-success', isOnline)
                    .toggleClass('text-body-secondary', !isOnline)
                    .html(renderPresenceBadge(isOnline));

                button.find(`[data-chat-contact-preview="${contactId}"]`).text(getPreviewText(item.last_message_preview));
            });

            if (contactId === activeContactId()) {
                updateCurrentContactPresence(isOnline);
            }
        });

        contactGroupLists.each(function () {
            const list = $(this);
            const groupKey = getGroupKey(list.data('chat-contact-group-list'));
            contactItems.forEach((item) => {
                if (getGroupKey(item.chat_group) !== groupKey) {
                    return;
                }

                const localButton = list.find(`[data-user-id="${Number(item.id) || 0}"]`).first();
                if (localButton.length) {
                    list.append(localButton);
                }
            });
        });

        applyContactFilter();
    };

    const renderAttachment = (attachment) => {
        if (!attachment || !attachment.url) {
            return '';
        }

        const url = escapeHtml(attachment.url);
        const name = escapeHtml(attachment.name || chatApp.data('attachment-label') || 'Attachment');
        const type = escapeHtml(attachment.type || '');
        const extension = escapeHtml(attachment.extension || '');
        const kind = escapeHtml(attachment.kind || 'file');
        const previewKind = escapeHtml(attachment.preview_kind || '');
        const size = attachment.size ? ` <span class="chat-message-meta-text">(${formatBytes(attachment.size)})</span>` : '';
        const icon = getAttachmentIcon(attachment.kind || 'file', attachment.preview_kind || '');

        if (attachment.is_image) {
            return `
                <div class="mt-2">
                    <button
                        type="button"
                        class="btn p-0 border-0 bg-transparent chat-message-image-trigger"
                        data-chat-attachment-preview
                        data-preview-url="${url}"
                        data-preview-name="${name}"
                        data-preview-kind="${previewKind}"
                        data-preview-type="${type}"
                        data-preview-extension="${extension}"
                        style="cursor: zoom-in;"
                    >
                        <img class="chat-message-image rounded-3" src="${url}" alt="${name}">
                    </button>
                </div>
            `;
        }

        if (attachment.is_previewable) {
            return `
                <button
                    type="button"
                    class="btn btn-outline-secondary d-inline-flex align-items-center gap-2 mt-2 text-start"
                    data-chat-attachment-preview
                    data-preview-url="${url}"
                    data-preview-name="${name}"
                    data-preview-kind="${previewKind}"
                    data-preview-type="${type}"
                    data-preview-extension="${extension}"
                >
                    <i class="${icon}"></i>
                    <span>${name}${size}</span>
                </button>
            `;
        }

        return `
            <a class="d-inline-flex align-items-center gap-2 mt-2 chat-message-attachment" href="${url}" target="_blank" rel="noopener noreferrer" download>
                <i class="${icon}"></i>
                <span>${name}${size}</span>
            </a>
        `;
    };

    const filterMessages = () => {
        const query = currentSearchQuery().toLowerCase();
        if (query === '') {
            return state.messages.slice();
        }

        return state.messages.filter((item) => {
            const attachmentName = item.attachment && item.attachment.name ? item.attachment.name : '';
            const haystack = [item.message || '', attachmentName].join(' ').toLowerCase();
            return haystack.includes(query);
        });
    };

    const syncSelectionControls = () => {
        const selectedCount = state.selectedIds.size;
        const label = String(chatApp.data('selection-count-text') || 'Selected: :count').replace(':count', selectedCount);

        selectionCancelButton.toggleClass('d-none', !state.selectionMode);
        deleteSelectedButton
            .toggleClass('d-none', !state.selectionMode)
            .prop('disabled', selectedCount === 0)
            .text(selectedCount > 0 ? label : (deleteSelectedButton.data('default-text') || deleteSelectedButton.text()));
    };

    if (deleteSelectedButton.length) {
        deleteSelectedButton.attr('data-default-text', deleteSelectedButton.text());
    }

    const renderMessages = (messages, currentUserId, options = {}) => {
        const box = messagesBox[0];
        const force = Boolean(options.force);
        const filteredMessages = filterMessages();
        const signature = `${activeContactId()}::${buildMessagesSignature(filteredMessages)}::${state.selectionMode ? 1 : 0}::${Array.from(state.selectedIds).sort().join(',')}::${currentSearchQuery()}`;
        const shouldStickToBottom = !box || Boolean(options.stickToBottom) || isNearBottom(box);
        const previousScrollTop = box ? box.scrollTop : 0;
        const query = currentSearchQuery();

        const resultText = query
            ? String(chatApp.data('message-search-results-text') || 'Found: :count').replace(':count', filteredMessages.length)
            : '';
        messageSearchResults.text(resultText).toggleClass('d-none', query === '');

        if (!force && signature === state.renderedSignature) {
            return;
        }

        if (!filteredMessages.length) {
            const emptyText = query
                ? (chatApp.data('message-search-empty-text') || chatApp.data('empty-text') || 'No messages found.')
                : (chatApp.data('empty-text') || 'No messages yet.');
            messagesBox.html(`<p class="text-body-secondary mb-0">${escapeHtml(emptyText)}</p>`);
            state.renderedSignature = signature;
            return;
        }

        let html = '';
        filteredMessages.forEach((item) => {
            const mine = Number(item.sender_id) === Number(currentUserId);
            const checks = mine ? `<span class="ms-2">${Number(item.is_read) === 1 ? '✓✓' : '✓'}</span>` : '';
            const avatar = escapeHtml(mine ? currentUserAvatar : (getContactButtons().filter('.active').first().data('user-avatar') || currentAvatar.attr('src') || ''));
            const messageText = item.message
                ? `<div class="chat-message-text small lh-sm">${highlightText(item.message, query)}</div>`
                : '';
            const attachment = renderAttachment(item.attachment);
            const isSelected = state.selectedIds.has(Number(item.id));
            const canShowActions = state.canModerate && !state.selectionMode;
            const canShowCheckbox = state.selectionMode && state.canBulkDelete;

            html += `
                <div class="chat-message-row ${mine ? 'chat-message-row--mine' : 'chat-message-row--theirs'} ${isSelected ? 'is-selected' : ''}" data-chat-message-row data-message-id="${Number(item.id) || 0}">
                    ${mine ? '' : `<img src="${avatar}" alt="" class="chat-message-avatar rounded-circle border flex-shrink-0">`}
                    ${canShowCheckbox ? `<input type="checkbox" class="form-check-input chat-message-select" data-chat-message-select value="${Number(item.id) || 0}" ${isSelected ? 'checked' : ''}>` : ''}
                    <div class="chat-message-bubble rounded-3 px-3 py-2 ${mine ? 'chat-message-bubble--mine' : 'chat-message-bubble--theirs'}">
                        ${canShowActions ? `
                            <div class="chat-message-actions">
                                <button type="button" class="btn btn-sm btn-outline-danger rounded-pill" data-chat-delete-message="${Number(item.id) || 0}">
                                    ${escapeHtml(chatApp.data('action-delete-text') || 'Delete')}
                                </button>
                            </div>
                        ` : ''}
                        ${messageText}
                        ${attachment}
                        <div class="small mt-1 chat-message-meta-text d-flex align-items-center justify-content-end">
                            <span>${escapeHtml(item.created_at)}</span>
                            ${checks}
                        </div>
                    </div>
                    ${mine ? `<img src="${avatar}" alt="" class="chat-message-avatar rounded-circle border flex-shrink-0">` : ''}
                </div>
            `;
        });

        messagesBox.html(html);
        state.renderedSignature = signature;

        if (!box) {
            return;
        }

        if (shouldStickToBottom) {
            messagesBox.scrollTop(box.scrollHeight);
            return;
        }

        messagesBox.scrollTop(previousScrollTop);
    };

    const setPreviewModalFallback = (text) => {
        previewModalBody.html(`
            <div class="text-center text-body-secondary py-5">
                <i class="ci-file fs-1 d-block mb-3"></i>
                <p class="mb-0">${escapeHtml(text)}</p>
            </div>
        `);
    };

    const renderPreviewContent = (attachment) => {
        const previewKind = String(attachment.kind || '');
        const url = String(attachment.url || '');
        const name = String(attachment.name || '');
        const requestId = ++state.previewRequestId;

        previewModalTitle.text(name || chatApp.data('preview-text') || 'Preview');
        previewModalOpen.attr('href', url);
        previewModalDownload.attr('href', url).attr('download', name || 'file');
        previewModalBody.html(`<p class="text-body-secondary mb-0">${escapeHtml(previewLoadingText)}</p>`);

        if (previewKind === 'image') {
            previewModalBody.html(`
                <img src="${escapeHtml(url)}" alt="${escapeHtml(name)}" class="chat-preview-modal__image">
            `);
            return;
        }

        if (previewKind === 'audio') {
            previewModalBody.html(`<audio class="chat-preview-modal__audio" controls preload="metadata" src="${escapeHtml(url)}"></audio>`);
            return;
        }

        if (previewKind === 'video') {
            previewModalBody.html(`<video class="chat-preview-modal__video" controls preload="metadata" src="${escapeHtml(url)}"></video>`);
            return;
        }

        if (previewKind === 'pdf') {
            previewModalBody.html(`<iframe class="chat-preview-modal__frame" src="${escapeHtml(url)}"></iframe>`);
            return;
        }

        if (previewKind === 'text') {
            fetch(url)
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Request failed');
                    }
                    return response.text();
                })
                .then((text) => {
                    if (requestId !== state.previewRequestId) {
                        return;
                    }

                    previewModalBody.html(`
                        <pre class="chat-preview-modal__text">${escapeHtml(text)}</pre>
                    `);
                })
                .catch(() => {
                    if (requestId !== state.previewRequestId) {
                        return;
                    }

                    setPreviewModalFallback(previewUnavailableText);
                });
            return;
        }

        setPreviewModalFallback(previewUnavailableText);
    };

    const openAttachmentPreview = (trigger) => {
        if (!previewModal) {
            return;
        }

        previewModal.show();
        renderPreviewContent({
            url: String(trigger.data('preview-url') || ''),
            name: String(trigger.data('preview-name') || ''),
            kind: String(trigger.data('preview-kind') || ''),
            type: String(trigger.data('preview-type') || ''),
            extension: String(trigger.data('preview-extension') || '')
        });
    };

    const applyPayload = (response, options = {}) => {
        if (!response || !response.status) {
            return;
        }

        state.messages = Array.isArray(response.messages) ? response.messages : [];
        if (response.permissions) {
            state.canModerate = Boolean(response.permissions.can_moderate);
            state.canBulkDelete = Boolean(response.permissions.can_bulk_delete);
            state.canClearChat = Boolean(response.permissions.can_clear_chat);
            state.canViewAudit = Boolean(response.permissions.can_view_audit);
        }

        renderMessages(state.messages, response.current_user_id || 0, options);
        updateContactUnreadBadges(response.contact_unread_counts || {}, response.unread_count || 0);
        updateContactsPresence(response.contacts || []);
        window.__chatAppState = {
            activeContactId: activeContactId(),
            currentUserId: Number(response.current_user_id) || 0,
        };

        if (response.contact) {
            updateCurrentContactPresence(Boolean(response.contact.is_online));
        }
    };

    const loadMessages = (options = {}) => {
        if (!activeContactId()) {
            return;
        }

        $.ajax({
            url: fetchUrl,
            method: 'GET',
            dataType: 'json',
            data: { user_id: activeContactId() },
            success: function (response) {
                applyPayload(response, options);
            }
        });
    };

    const setActiveContact = (button) => {
        const contactId = Number(button.data('user-id')) || 0;
        getContactButtons().removeClass('active');
        getContactButtons().filter(`[data-user-id="${contactId}"]`).addClass('active');

        userIdInput.val(contactId);
        currentName.text(button.data('user-name'));
        currentAvatar.attr('src', button.data('user-avatar')).attr('alt', button.data('user-name'));
        updateCurrentContactPresence(Number(button.data('user-online')) === 1);
        state.renderedSignature = '';
        state.selectionMode = false;
        state.selectedIds.clear();
        syncSelectionControls();
        messageInput.val('');
        messageSearchInput.val('');
        clearPendingAttachment();
        window.__chatAppState = { activeContactId: activeContactId() };
        $(document).trigger('chat:active-contact-changed', [activeContactId()]);

        if (offcanvas) {
            offcanvas.hide();
        }

        loadMessages({ force: true, stickToBottom: true });
    };

    const sendMessage = () => {
        const text = String(messageInput.val() || '').trim();
        if (text === '' && !state.pendingFiles.length && !state.pendingSiteAttachments.length) {
            toastr.error(chatApp.data('message-required-text') || 'Message is required.');
            return;
        }

        const payload = new FormData(form[0]);
        payload.delete('attachment[]');
        payload.delete('attachment');
        payload.delete('site_attachment_paths[]');
        payload.delete('site_attachment_paths');
        state.pendingFiles.forEach((file) => {
            payload.append('attachment[]', file, file.name);
        });
        state.pendingSiteAttachments.forEach((item) => {
            payload.append('site_attachment_paths[]', item.path);
        });

        $.ajax({
            url: sendUrl,
            method: 'POST',
            dataType: 'json',
            data: payload,
            processData: false,
            contentType: false,
            xhr: function () {
                const xhr = $.ajaxSettings.xhr();
                if (xhr.upload) {
                    xhr.upload.addEventListener('progress', function (event) {
                        if (!event.lengthComputable || !state.pendingFiles.length) {
                            return;
                        }

                        const progress = Math.max(0, Math.min(100, Math.round((event.loaded / event.total) * 100)));
                        progressWrap.removeClass('d-none');
                        progressBar.css('width', `${progress}%`);
                    });
                }

                return xhr;
            },
            beforeSend: function () {
                sendButton.prop('disabled', true);
                progressWrap.toggleClass('d-none', !state.pendingFiles.length);
            },
            success: function (response) {
                if (!response.status) {
                    toastr.error(response.message || 'Message could not be sent.');
                    return;
                }

                messageInput.val('');
                clearPendingAttachment();
                applyPayload(response, { force: true, stickToBottom: true });
            },
            error: function (request) {
                const message = request.responseJSON && request.responseJSON.message
                    ? request.responseJSON.message
                    : 'Message send error.';
                toastr.error(message);
            },
            complete: function () {
                sendButton.prop('disabled', false);
                progressWrap.addClass('d-none');
                progressBar.css('width', '0%');
            }
        });
    };

    const runDeleteMessages = (messageIds) => {
        if (!messageIds.length) {
            return;
        }

        $.ajax({
            url: deleteUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                needCSRFToken: form.find('input[name="needCSRFToken"]').val(),
                user_id: activeContactId(),
                message_ids: messageIds,
            },
            success: function (response) {
                if (!response.status) {
                    toastr.error(response.message || '');
                    return;
                }

                state.selectedIds.clear();
                applyPayload(response, { force: true });
                syncSelectionControls();
                toastr.success(response.message || chatApp.data('delete-message-text') || '');
            },
            error: function (request) {
                toastr.error(request.responseJSON && request.responseJSON.message ? request.responseJSON.message : '');
            }
        });
    };

    const runClearConversation = () => {
        $.ajax({
            url: clearUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                needCSRFToken: form.find('input[name="needCSRFToken"]').val(),
                user_id: activeContactId(),
            },
            success: function (response) {
                if (!response.status) {
                    toastr.error(response.message || '');
                    return;
                }

                state.selectedIds.clear();
                state.selectionMode = false;
                syncSelectionControls();
                applyPayload(response, { force: true });
                toastr.success(response.message || chatApp.data('clear-chat-text') || '');
            },
            error: function (request) {
                toastr.error(request.responseJSON && request.responseJSON.message ? request.responseJSON.message : '');
            }
        });
    };

    const showConfirm = (message, callback) => {
        if (!confirmModal) {
            callback();
            return;
        }

        state.confirmAction = callback;
        confirmModalMessage.text(message);
        confirmModal.show();
    };

    const renderAuditItems = (items) => {
        if (!Array.isArray(items) || !items.length) {
            auditList.html(`<p class="text-body-secondary mb-0">${escapeHtml(chatApp.data('audit-empty-text') || '')}</p>`);
            return;
        }

        const actionLabels = {
            delete_message: String(chatApp.data('audit-delete-text') || ''),
            bulk_delete: String(chatApp.data('audit-bulk-delete-text') || ''),
            clear_conversation: String(chatApp.data('audit-clear-text') || ''),
        };

        let html = '<div class="d-flex flex-column gap-3">';
        items.forEach((item) => {
            const details = item.details || {};
            const actionLabel = actionLabels[item.action] || item.action;
            const metaRows = [];

            if (details.reason) {
                metaRows.push(`<div><span class="text-body-secondary">${escapeHtml(chatApp.data('audit-reason-text') || '')}:</span> ${escapeHtml(details.reason)}</div>`);
            }
            if (details.attachment && details.attachment.name) {
                metaRows.push(`<div><span class="text-body-secondary">${escapeHtml(chatApp.data('audit-attachment-text') || '')}:</span> ${escapeHtml(details.attachment.name)}</div>`);
            }
            if (item.ip_address) {
                metaRows.push(`<div><span class="text-body-secondary">${escapeHtml(chatApp.data('audit-ip-text') || '')}:</span> ${escapeHtml(item.ip_address)}</div>`);
            }
            if (item.user_agent) {
                metaRows.push(`<div><span class="text-body-secondary">${escapeHtml(chatApp.data('audit-device-text') || '')}:</span> ${escapeHtml(item.user_agent)}</div>`);
            }

            html += `
                <div class="border rounded-4 p-3">
                    <div class="d-flex align-items-start justify-content-between gap-3 mb-2">
                        <div>
                            <div class="fw-semibold">${escapeHtml(actionLabel)}</div>
                            <div class="small text-body-secondary">${escapeHtml(item.actor_name || '')} • ${escapeHtml(item.created_at || '')}</div>
                        </div>
                        <span class="badge rounded-pill text-body-emphasis bg-body-tertiary">${escapeHtml(item.actor_role || '')}</span>
                    </div>
                    ${metaRows.length ? `<div class="small d-flex flex-column gap-1">${metaRows.join('')}</div>` : ''}
                </div>
            `;
        });
        html += '</div>';

        auditList.html(html);
    };

    const openAudit = () => {
        if (!auditModal) {
            return;
        }

        auditModal.show();
        auditList.html(`<p class="text-body-secondary mb-0">${escapeHtml(chatApp.data('preview-loading-text') || '')}</p>`);

        $.ajax({
            url: auditUrl,
            method: 'GET',
            dataType: 'json',
            data: { user_id: activeContactId() },
            success: function (response) {
                if (!response.status) {
                    toastr.error(response.message || '');
                    return;
                }

                renderAuditItems(response.items || []);
            },
            error: function (request) {
                toastr.error(request.responseJSON && request.responseJSON.message ? request.responseJSON.message : '');
            }
        });
    };

    chatApp.on('click', '[data-chat-contact]', function () {
        setActiveContact($(this));
    });

    chatApp.on('click', '[data-chat-delete-message]', function () {
        const messageId = Number($(this).data('chat-delete-message')) || 0;
        if (!messageId) {
            return;
        }

        showConfirm(chatApp.data('confirm-delete-message-text') || '', function () {
            runDeleteMessages([messageId]);
        });
    });

    chatApp.on('change', '[data-chat-message-select]', function () {
        const messageId = Number($(this).val()) || 0;
        if (!messageId) {
            return;
        }

        if (this.checked) {
            state.selectedIds.add(messageId);
        } else {
            state.selectedIds.delete(messageId);
        }

        state.renderedSignature = '';
        syncSelectionControls();
        renderMessages(state.messages, Number(window.__chatAppState && window.__chatAppState.currentUserId || 0), { force: true });
    });

    chatApp.on('click', '[data-chat-attachment-preview]', function () {
        openAttachmentPreview($(this));
    });

    chatApp.find('[data-chat-pick-camera]').on('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        if (this.disabled || !cameraInput.length) {
            return;
        }

        openNativeFilePicker(cameraInput[0]);
    });

    chatApp.find('[data-chat-pick-gallery]').on('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        if (this.disabled || !galleryInput.length) {
            return;
        }

        openNativeFilePicker(galleryInput[0]);
    });

    attachmentInput.on('change', function () {
        setPendingFiles(this.files || []);
    });

    siteFileInput.on('change', function () {
        addPendingSiteAttachment($(this).val());
    });

    cameraInput.on('change', function () {
        setPendingFiles(this.files || []);
    });

    galleryInput.on('change', function () {
        setPendingFiles(this.files || []);
    });

    form.find('[data-chat-remove-attachment]').on('click', function () {
        clearPendingAttachment();
    });

    dropzone.on('dragenter dragover', function (event) {
        event.preventDefault();
        event.stopPropagation();
        state.dragDepth += 1;
        dropzoneOverlay.removeClass('d-none');
    });

    dropzone.on('dragleave', function (event) {
        event.preventDefault();
        event.stopPropagation();
        state.dragDepth = Math.max(0, state.dragDepth - 1);
        if (state.dragDepth === 0) {
            dropzoneOverlay.addClass('d-none');
        }
    });

    dropzone.on('drop', function (event) {
        event.preventDefault();
        event.stopPropagation();
        state.dragDepth = 0;
        dropzoneOverlay.addClass('d-none');

        const files = event.originalEvent && event.originalEvent.dataTransfer
            ? event.originalEvent.dataTransfer.files
            : null;
        if (files && files.length) {
            setPendingFiles(files);
        }
    });

    form.on('submit', function (event) {
        event.preventDefault();
        sendMessage();
    });

    contactSearchInput.on('input', function () {
        const value = $(this).val();
        contactSearchInput.val(value);
        applyContactFilter();
    });

    messageSearchInput.on('input', function () {
        state.renderedSignature = '';
        renderMessages(state.messages, Number(window.__chatAppState && window.__chatAppState.currentUserId || 0), { force: true });
    });

    previewModalElement.on('hidden.bs.modal', function () {
        state.previewRequestId += 1;
        previewModalTitle.text(chatApp.data('preview-text') || 'Preview');
        previewModalBody.html(`<p class="text-body-secondary mb-0">${escapeHtml(previewLoadingText)}</p>`);
        previewModalOpen.attr('href', '');
        previewModalDownload.attr('href', '').removeAttr('download');
    });

    confirmModalSubmit.on('click', function () {
        if (typeof state.confirmAction === 'function') {
            const action = state.confirmAction;
            state.confirmAction = null;
            confirmModal.hide();
            action();
        }
    });

    confirmModalElement.on('hidden.bs.modal', function () {
        state.confirmAction = null;
    });

    selectionToggleButton.on('click', function () {
        state.selectionMode = true;
        state.selectedIds.clear();
        state.renderedSignature = '';
        syncSelectionControls();
        renderMessages(state.messages, Number(window.__chatAppState && window.__chatAppState.currentUserId || 0), { force: true });
    });

    selectionCancelButton.on('click', function () {
        state.selectionMode = false;
        state.selectedIds.clear();
        state.renderedSignature = '';
        syncSelectionControls();
        renderMessages(state.messages, Number(window.__chatAppState && window.__chatAppState.currentUserId || 0), { force: true });
    });

    deleteSelectedButton.on('click', function () {
        const messageIds = Array.from(state.selectedIds);
        if (!messageIds.length) {
            return;
        }

        showConfirm(chatApp.data('confirm-delete-messages-text') || '', function () {
            runDeleteMessages(messageIds);
        });
    });

    chatApp.find('[data-chat-clear-conversation]').on('click', function () {
        showConfirm(chatApp.data('confirm-clear-chat-text') || '', function () {
            runClearConversation();
        });
    });

    chatApp.find('[data-chat-open-audit]').on('click', function () {
        openAudit();
    });

    window.__chatAppState = { activeContactId: activeContactId(), currentUserId: 0 };
    $(document).trigger('chat:active-contact-changed', [activeContactId()]);

    loadMessages({ stickToBottom: true });
    applyContactFilter();
    syncSelectionControls();
    setInterval(function () {
        loadMessages();
    }, 4000);
});
