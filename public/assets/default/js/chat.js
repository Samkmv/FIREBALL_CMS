$(function () {
    const chatApp = $('[data-chat-app]');
    if (!chatApp.length) {
        return;
    }

    const fetchUrl = chatApp.data('fetch-url');
    const sendUrl = chatApp.data('send-url');
    const contacts = chatApp.find('[data-chat-contact]');
    const contactGroupLists = chatApp.find('[data-chat-contact-group-list]');
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
    const imageModalElement = chatApp.find('[data-chat-image-modal]');
    const imageModalTitle = imageModalElement.find('[data-chat-image-modal-title]');
    const imageModalImage = imageModalElement.find('[data-chat-image-modal-image]');
    const imageModalDownload = imageModalElement.find('[data-chat-image-modal-download]');
    const bootstrapApi = typeof bootstrap !== 'undefined'
        ? bootstrap
        : (window.bootstrap || null);
    const imageModal = imageModalElement.length && bootstrapApi
        ? new bootstrapApi.Modal(imageModalElement[0])
        : null;
    const maxFileSize = 200 * 1024 * 1024;
    const originalTitle = document.title;
    const latestIncomingByContact = {};
    const lastUnreadCounts = {};
    const currentUserAvatar = String(chatApp.data('current-user-avatar') || '');
    let renderedConversationSignature = '';
    let unreadCountsReady = false;

    const escapeHtml = (text) => $('<div>').text(text || '').html();
    const activeContactId = () => Number(userIdInput.val()) || 0;
    const activeContactAvatar = () => {
        const activeContact = contacts.filter('.active').first();
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

    const renderAttachment = (attachment, mine) => {
        if (!attachment || !attachment.url) {
            return '';
        }

        const url = escapeHtml(attachment.url);
        const name = escapeHtml(attachment.name || chatApp.data('attachment-label') || 'Attachment');
        const size = attachment.size ? ` <span class="chat-message-meta-text">(${formatBytes(attachment.size)})</span>` : '';

        if (attachment.is_image) {
            return `
                <div class="mt-2">
                    <button
                        type="button"
                        class="btn p-0 border-0 bg-transparent d-block"
                        data-chat-image-preview
                        data-image-url="${url}"
                        data-image-name="${name}"
                        aria-label="${name}"
                        style="cursor: zoom-in;"
                    >
                        <img class="rounded-3 border" src="${url}" alt="${name}" style="max-width: 240px; max-height: 180px; object-fit: cover;">
                    </button>
                </div>
            `;
        }

        return `
            <a class="d-flex align-items-center gap-2 mt-2 chat-message-attachment" href="${url}" target="_blank" rel="noopener noreferrer" download>
                <i class="ci-file"></i>
                <span>${name}${size}</span>
            </a>
        `;
    };

    const updateDocumentTitle = (totalUnread) => {
        const count = Number(totalUnread) || 0;
        document.title = count > 0 ? `(${count}) ${originalTitle}` : originalTitle;
    };

    const updateContactUnreadBadges = (counts, totalUnread) => {
        counts = counts || {};

        contacts.each(function () {
            const contact = $(this);
            const contactId = Number(contact.data('user-id'));
            const count = Number(counts[contactId] || 0);
            const badge = contact.find(`[data-chat-contact-unread="${contactId}"]`);

            badge.text(count);
            badge.toggleClass('d-none', count <= 0);

            if (unreadCountsReady && contactId !== activeContactId() && count > Number(lastUnreadCounts[contactId] || 0)) {
                toastr.info(`${chatApp.data('new-message-text') || 'Новое сообщение'}: ${contact.data('user-name')}`);
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

            const button = contacts.filter(`[data-user-id="${contactId}"]`).first();
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
                : (chatApp.data('attachment-label') || 'Вложение');
            toastr.info(`${chatApp.data('new-message-text') || 'Новое сообщение'}: ${preview}`);
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
            messagesBox.html(`<p class="text-body-secondary mb-0">${escapeHtml(chatApp.data('empty-text') || 'Сообщений пока нет.')}</p>`);
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
            const attachment = renderAttachment(item.attachment, mine);

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
        contacts.removeClass('active');
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

    contacts.on('click', function () {
        setActiveContact($(this));
    });

    messagesBox.on('click', '[data-chat-image-preview]', function () {
        if (!imageModal) {
            return;
        }

        const trigger = $(this);
        const imageUrl = String(trigger.data('image-url') || '');
        const imageName = String(trigger.data('image-name') || '');
        const modalTitle = String(chatApp.data('image-modal-title') || 'Image');

        imageModalTitle.text(modalTitle);
        imageModalImage.attr('src', imageUrl).attr('alt', modalTitle);
        imageModalDownload.attr('href', imageUrl).attr('download', imageName || 'image');
        imageModal.show();
    });

    imageModalElement.on('hidden.bs.modal', function () {
        imageModalImage.attr('src', '').attr('alt', '');
        imageModalDownload.attr('href', '');
    });

    form.on('submit', function (event) {
        event.preventDefault();

        const file = attachmentInput[0] && attachmentInput[0].files.length ? attachmentInput[0].files[0] : null;
        if (file && file.size > maxFileSize) {
            toastr.error(chatApp.data('file-too-large-text') || 'Файл слишком большой.');
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
                    toastr.error(response.message || 'Не удалось отправить сообщение.');
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
                    : 'Ошибка отправки сообщения.';
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
            toastr.error(chatApp.data('file-too-large-text') || 'Файл слишком большой.');
            attachmentInput.val('');
            attachmentName.addClass('d-none').text('');
            return;
        }

        attachmentName.removeClass('d-none').text(`${file.name} (${formatBytes(file.size)})`);
    });

    loadMessages();
    setInterval(loadMessages, 4000);
});
