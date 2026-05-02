$(function () {
    const chatApp = $('[data-chat-app]');
    if (!chatApp.length) {
        return;
    }

    const fetchUrl = chatApp.data('fetch-url');
    const sendUrl = chatApp.data('send-url');
    const getContactButtons = () => chatApp.find('[data-chat-contact]');
    const contactGroupLists = chatApp.find('[data-chat-contact-group-list]');
    const contactGroups = chatApp.find('[data-chat-contact-group]');
    const contactSearchInput = chatApp.find('[data-chat-contact-search]');
    const contactSearchEmpty = chatApp.find('[data-chat-search-empty]');
    const messagesBox = chatApp.find('[data-chat-messages]');
    const currentName = chatApp.find('[data-chat-current-name]');
    const currentAvatar = chatApp.find('[data-chat-current-avatar]');
    const currentStatus = chatApp.find('[data-chat-current-status]');
    const form = chatApp.find('[data-chat-form]');
    const userIdInput = form.find('[data-chat-user-id]');
    const messageInput = form.find('input[name="message"]');
    const attachmentInput = form.find('[data-chat-attachment]');
    const attachmentName = form.find('[data-chat-attachment-name]');
    const sendButton = form.find('button[type="submit"]');
    const previewModalElement = $('[data-chat-preview-modal]').first();
    const previewModalTitle = previewModalElement.find('[data-chat-preview-modal-title]');
    const previewModalBody = previewModalElement.find('[data-chat-preview-modal-body]');
    const previewModalOpen = previewModalElement.find('[data-chat-preview-modal-open]');
    const previewModalDownload = previewModalElement.find('[data-chat-preview-modal-download]');
    const bootstrapApi = typeof bootstrap !== 'undefined'
        ? bootstrap
        : (window.bootstrap || null);
    const previewModal = previewModalElement.length && bootstrapApi
        ? new bootstrapApi.Modal(previewModalElement[0])
        : null;
    const maxFileSize = 200 * 1024 * 1024;
    const originalTitle = document.title;
    const latestIncomingByContact = {};
    const lastUnreadCounts = {};
    const currentUserAvatar = String(chatApp.data('current-user-avatar') || '');
    const previewUnavailableText = String(chatApp.data('preview-unavailable-text') || 'Preview unavailable.');
    const previewLoadingText = String(chatApp.data('preview-loading-text') || 'Loading...');
    let renderedConversationSignature = '';
    let unreadCountsReady = false;
    let previewRequestId = 0;

    const escapeHtml = (text) => $('<div>').text(text || '').html();
    const activeContactId = () => Number(userIdInput.val()) || 0;
    const activeContactAvatar = () => {
        const activeContact = getContactButtons().filter('.active').first();
        return String(activeContact.data('user-avatar') || currentAvatar.attr('src') || '');
    };
    const getStatusText = (isOnline) => (
        isOnline ? String(chatApp.data('online-text') || 'Online') : String(chatApp.data('offline-text') || 'Offline')
    );
    const getPreviewText = (value) => String(value || chatApp.data('empty-text') || '');
    const getGroupKey = (value) => String(value || 'clients');

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
                        class="btn p-0 border-0 bg-transparent d-block"
                        data-chat-attachment-preview
                        data-preview-url="${url}"
                        data-preview-name="${name}"
                        data-preview-kind="${previewKind}"
                        data-preview-type="${type}"
                        data-preview-extension="${extension}"
                        style="cursor: zoom-in;"
                    >
                        <img class="rounded-3 border" src="${url}" alt="${name}" style="max-width: 240px; max-height: 180px; object-fit: cover;">
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

    const updateDocumentTitle = (totalUnread) => {
        const count = Number(totalUnread) || 0;
        document.title = count > 0 ? `(${count}) ${originalTitle}` : originalTitle;
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
                    String(button.text() || '')
                ].join(' ').toLowerCase();
                const isVisible = query === '' || haystack.includes(query);

                button.toggleClass('d-none', !isVisible);
                if (isVisible) {
                    groupVisible += 1;
                    visibleContacts += 1;
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

            if (unreadCountsReady && contactId !== activeContactId() && count > Number(lastUnreadCounts[contactId] || 0)) {
                toastr.info(`${chatApp.data('new-message-text') || 'New message'}: ${contact.data('user-name')}`);
            }

            lastUnreadCounts[contactId] = count;
        });

        unreadCountsReady = true;
        updateDocumentTitle(totalUnread);
    };

    const renderPresenceBadge = (isOnline) => `
        <span class="rounded-circle d-inline-block flex-shrink-0 ${isOnline ? 'bg-success' : 'bg-secondary'}" style="width: 8px; height: 8px;"></span>
        <span>${escapeHtml(getStatusText(isOnline))}</span>
    `;

    const updateCurrentContactPresence = (isOnline) => {
        if (!currentStatus.length) {
            return;
        }

        currentStatus
            .toggleClass('text-success', isOnline)
            .toggleClass('text-body-secondary', !isOnline)
            .html(renderPresenceBadge(isOnline));
    };

    const updateContactsPresence = (contactItems) => {
        if (!Array.isArray(contactItems)) {
            return;
        }

        const orderedButtonsByGroup = {};

        contactItems.forEach((item) => {
            const contactId = Number(item.id) || 0;
            if (!contactId) {
                return;
            }

            const button = getContactButtons().filter(`[data-user-id="${contactId}"]`).first();
            if (!button.length) {
                return;
            }

            const groupKey = getGroupKey(item.chat_group || button.data('contact-group'));
            if (!orderedButtonsByGroup[groupKey]) {
                orderedButtonsByGroup[groupKey] = [];
            }

            button.attr('data-contact-group', groupKey);
            orderedButtonsByGroup[groupKey].push(button);

            const isOnline = Number(item.is_online) === 1 || item.is_online === true;
            button.data('user-online', isOnline ? 1 : 0);
            button.data('user-last-seen', String(item.last_seen_at || ''));
            button.data('last-message-preview', String(item.last_message_preview || ''));

            const status = button.find(`[data-chat-contact-status="${contactId}"]`);
            status
                .toggleClass('text-success', isOnline)
                .toggleClass('text-body-secondary', !isOnline)
                .html(renderPresenceBadge(isOnline));

            const preview = button.find(`[data-chat-contact-preview="${contactId}"]`);
            preview.text(getPreviewText(item.last_message_preview));

            if (contactId === activeContactId()) {
                updateCurrentContactPresence(isOnline);
            }
        });

        contactGroupLists.each(function () {
            const list = $(this);
            const groupKey = getGroupKey(list.data('chat-contact-group-list'));
            const buttons = orderedButtonsByGroup[groupKey] || [];
            buttons.forEach((button) => {
                list.append(button);
            });
        });

        applyContactFilter();
    };

    const notifyIncomingMessages = (messages, currentUserId) => {
        const contactId = activeContactId();
        let latestIncoming = 0;
        let latestMessage = null;

        messages.forEach((item) => {
            if (Number(item.sender_id) !== Number(currentUserId) && Number(item.id) > latestIncoming) {
                latestIncoming = Number(item.id);
                latestMessage = item;
            }
        });

        if (latestIncoming > 0 && latestIncomingByContact[contactId] && latestIncoming > latestIncomingByContact[contactId]) {
            const preview = latestMessage && latestMessage.message
                ? latestMessage.message
                : (chatApp.data('attachment-label') || 'Attachment');
            toastr.info(`${chatApp.data('new-message-text') || 'New message'}: ${preview}`);
        }

        if (latestIncoming > 0) {
            latestIncomingByContact[contactId] = latestIncoming;
        }
    };

    const renderMessages = (messages, currentUserId, options = {}) => {
        const box = messagesBox[0];
        const force = Boolean(options.force);
        const signature = `${activeContactId()}::${buildMessagesSignature(messages)}`;
        const shouldStickToBottom = !box || Boolean(options.stickToBottom) || isNearBottom(box);
        const previousScrollTop = box ? box.scrollTop : 0;

        if (!force && signature === renderedConversationSignature) {
            return;
        }

        if (!messages.length) {
            messagesBox.html(`<p class="text-body-secondary mb-0">${escapeHtml(chatApp.data('empty-text') || 'No messages yet.')}</p>`);
            renderedConversationSignature = signature;
            return;
        }

        let html = '';
        messages.forEach((item) => {
            const mine = Number(item.sender_id) === Number(currentUserId);
            const checks = mine
                ? `<span class="ms-2">${Number(item.is_read) === 1 ? '✓✓' : '✓'}</span>`
                : '';
            const avatar = escapeHtml(mine ? currentUserAvatar : activeContactAvatar());
            const messageText = item.message
                ? `<div class="chat-message-text small lh-sm">${escapeHtml(item.message)}</div>`
                : '';
            const attachment = renderAttachment(item.attachment);

            html += `
                <div class="d-flex ${mine ? 'justify-content-end' : 'justify-content-start'} align-items-end gap-2 mb-2">
                    ${mine ? '' : `<img src="${avatar}" alt="" class="rounded-circle border flex-shrink-0" style="width: 32px; height: 32px; object-fit: cover;">`}
                    <div class="chat-message-bubble rounded-3 px-3 py-2 ${mine ? 'chat-message-bubble--mine' : 'chat-message-bubble--theirs'}">
                        ${messageText}
                        ${attachment}
                        <div class="small mt-1 chat-message-meta-text d-flex align-items-center justify-content-end">
                            <span>${escapeHtml(item.created_at)}</span>
                            ${checks}
                        </div>
                    </div>
                    ${mine ? `<img src="${avatar}" alt="" class="rounded-circle border flex-shrink-0" style="width: 32px; height: 32px; object-fit: cover;">` : ''}
                </div>
            `;
        });

        messagesBox.html(html);
        renderedConversationSignature = signature;

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
        const requestId = ++previewRequestId;

        previewModalTitle.text(name || chatApp.data('preview-text') || 'Preview');
        previewModalOpen.attr('href', url);
        previewModalDownload.attr('href', url).attr('download', name || 'file');
        previewModalBody.html(`<p class="text-body-secondary mb-0">${escapeHtml(previewLoadingText)}</p>`);

        if (previewKind === 'image') {
            previewModalBody.html(`
                <img
                    src="${escapeHtml(url)}"
                    alt="${escapeHtml(name)}"
                    class="img-fluid rounded-4"
                    style="max-height: 75vh; width: auto;"
                >
            `);
            return;
        }

        if (previewKind === 'audio') {
            previewModalBody.html(`
                <audio class="w-100" controls preload="metadata" src="${escapeHtml(url)}"></audio>
            `);
            return;
        }

        if (previewKind === 'video') {
            previewModalBody.html(`
                <video class="w-100 rounded-4" controls preload="metadata" src="${escapeHtml(url)}" style="max-height: 75vh;"></video>
            `);
            return;
        }

        if (previewKind === 'pdf') {
            previewModalBody.html(`
                <iframe class="w-100 rounded-4 border-0 bg-white" src="${escapeHtml(url)}" style="min-height: 75vh;"></iframe>
            `);
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
                    if (requestId !== previewRequestId) {
                        return;
                    }

                    previewModalBody.html(`
                        <pre class="w-100 mb-0 p-3 rounded-4 border bg-body overflow-auto text-start" style="max-height: 75vh; white-space: pre-wrap;">${escapeHtml(text)}</pre>
                    `);
                })
                .catch(() => {
                    if (requestId !== previewRequestId) {
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

        const attachment = {
            url: String(trigger.data('preview-url') || ''),
            name: String(trigger.data('preview-name') || ''),
            kind: String(trigger.data('preview-kind') || ''),
            type: String(trigger.data('preview-type') || ''),
            extension: String(trigger.data('preview-extension') || '')
        };

        previewModal.show();
        renderPreviewContent(attachment);
    };

    const loadMessages = (options = {}) => {
        const userId = userIdInput.val();
        if (!userId) {
            return;
        }

        $.ajax({
            url: fetchUrl,
            method: 'GET',
            dataType: 'json',
            data: { user_id: userId },
            success: function (response) {
                if (!response.status) {
                    return;
                }

                notifyIncomingMessages(response.messages || [], response.current_user_id || 0);
                renderMessages(response.messages || [], response.current_user_id || 0, options);
                updateContactUnreadBadges(response.contact_unread_counts || {}, response.unread_count || 0);
                updateContactsPresence(response.contacts || []);
                $(document).trigger('chat:unread-updated', [response.unread_count || 0]);
            }
        });
    };

    const setActiveContact = (button) => {
        getContactButtons().removeClass('active');
        button.addClass('active');

        userIdInput.val(button.data('user-id'));
        currentName.text(button.data('user-name'));
        currentAvatar.attr('src', button.data('user-avatar')).attr('alt', button.data('user-name'));
        updateCurrentContactPresence(Number(button.data('user-online')) === 1);
        messageInput.val('');
        attachmentInput.val('');
        attachmentName.addClass('d-none').text('');
        renderedConversationSignature = '';
        loadMessages({ force: true, stickToBottom: true });
    };

    chatApp.on('click', '[data-chat-contact]', function () {
        setActiveContact($(this));
    });

    messagesBox.on('click', '[data-chat-attachment-preview]', function () {
        openAttachmentPreview($(this));
    });

    previewModalElement.on('hidden.bs.modal', function () {
        previewRequestId += 1;
        previewModalTitle.text(chatApp.data('preview-text') || 'Preview');
        previewModalBody.html(`<p class="text-body-secondary mb-0">${escapeHtml(previewLoadingText)}</p>`);
        previewModalOpen.attr('href', '');
        previewModalDownload.attr('href', '').removeAttr('download');
    });

    form.on('submit', function (event) {
        event.preventDefault();

        const file = attachmentInput[0] && attachmentInput[0].files.length ? attachmentInput[0].files[0] : null;
        if (file && file.size > maxFileSize) {
            toastr.error(chatApp.data('file-too-large-text') || 'File is too large.');
            return;
        }

        const payload = new FormData(form[0]);

        $.ajax({
            url: sendUrl,
            method: 'POST',
            dataType: 'json',
            data: payload,
            processData: false,
            contentType: false,
            beforeSend: function () {
                sendButton.prop('disabled', true);
            },
            success: function (response) {
                if (!response.status) {
                    toastr.error(response.message || 'Message could not be sent.');
                    return;
                }

                messageInput.val('');
                attachmentInput.val('');
                attachmentName.addClass('d-none').text('');
                renderMessages(response.messages || [], response.current_user_id || 0, { force: true, stickToBottom: true });
                updateContactUnreadBadges(response.contact_unread_counts || {}, response.unread_count || 0);
                updateContactsPresence(response.contacts || []);
                $(document).trigger('chat:unread-updated', [response.unread_count || 0]);
            },
            error: function (request) {
                const message = request.responseJSON && request.responseJSON.message
                    ? request.responseJSON.message
                    : 'Message send error.';
                toastr.error(message);
            },
            complete: function () {
                sendButton.prop('disabled', false);
            }
        });
    });

    attachmentInput.on('change', function () {
        const file = this.files.length ? this.files[0] : null;
        if (!file) {
            attachmentName.addClass('d-none').text('');
            return;
        }

        if (file.size > maxFileSize) {
            toastr.error(chatApp.data('file-too-large-text') || 'File is too large.');
            attachmentInput.val('');
            attachmentName.addClass('d-none').text('');
            return;
        }

        attachmentName.removeClass('d-none').text(`${file.name} (${formatBytes(file.size)})`);
    });

    contactSearchInput.on('input', applyContactFilter);

    loadMessages();
    applyContactFilter();
    setInterval(loadMessages, 4000);
});
