$(function () {
    'use strict';

    const app = $('[data-group-chat-app]').first();
    if (!app.length) return;

    // FIREBALL_CHAT30_GROUPS
    // FIREBALL_CHAT31_GROUP_UI_V2

    const conversationId = Number(app.data('conversation-id')) || 0;
    const messagesUrl = String(app.data('messages-url') || '');
    const sendUrl = String(app.data('send-url') || '');
    const streamUrl = String(app.data('stream-url') || '');
    const box = app.find('[data-group-chat-messages]');
    const form = app.find('[data-group-chat-form]');
    const input = app.find('[data-group-chat-input]');
    const sendButton = form.find('button[type="submit"]');

    // Use the same viewport shell as direct chat, including the on-screen keyboard.
    let viewportFrame = 0;
    let messageViewportFrame = 0;
    let viewportAnchor = null;
    let viewportAnchorReleaseTimer = 0;

    // FIREBALL_CHAT_VIEWPORT_IOS_FIX_20260929
    const readViewportAnchor = () => {
        const element = box[0];
        if (!element) return null;
        const distanceFromBottom = Math.max(0, element.scrollHeight - element.scrollTop - element.clientHeight);
        return {distanceFromBottom, stickToBottom: distanceFromBottom <= 48};
    };
    const restoreViewportAnchor = (anchor) => {
        if (!anchor) return;
        if (messageViewportFrame) cancelAnimationFrame(messageViewportFrame);
        messageViewportFrame = window.requestAnimationFrame(() => {
            messageViewportFrame = 0;
            const element = box[0];
            if (!element) return;
            element.scrollTop = anchor.stickToBottom
                ? element.scrollHeight
                : Math.max(0, element.scrollHeight - element.clientHeight - anchor.distanceFromBottom);
        });
    };
    const beginViewportAnchorSession = () => {
        if (!viewportAnchor) viewportAnchor = readViewportAnchor();
        if (viewportAnchorReleaseTimer) clearTimeout(viewportAnchorReleaseTimer);
        viewportAnchorReleaseTimer = window.setTimeout(() => {
            viewportAnchorReleaseTimer = 0;
            viewportAnchor = null;
        }, 220);
    };

    const syncViewport = () => {
        viewportFrame = 0;
        const active = document.activeElement;
        const focused = Boolean(active && app[0].contains(active)
            && active.matches('input, textarea, [contenteditable="true"]'));
        window.FireballChatViewport.sync(focused);
        restoreViewportAnchor(viewportAnchor);
    };
    const scheduleViewport = () => {
        beginViewportAnchorSession();
        if (!viewportFrame) viewportFrame = requestAnimationFrame(syncViewport);
    };
    window.addEventListener('resize', scheduleViewport, {passive: true});
    window.addEventListener('pageshow', scheduleViewport, {passive: true});
    window.addEventListener('orientationchange', scheduleViewport, {passive: true});
    window.visualViewport?.addEventListener('resize', scheduleViewport, {passive: true});
    window.visualViewport?.addEventListener('scroll', scheduleViewport, {passive: true});
    document.addEventListener('focusin', scheduleViewport);
    document.addEventListener('focusout', scheduleViewport);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) scheduleViewport();
    }, {passive: true});

    let source = null;
    let realtimeConnected = false;
    let requestPending = false;
    let realtimeRefreshPending = false;
    let renderedSignature = '';

    const escapeHtml = (value) => $('<div>').text(value || '').html();

    const showError = (message) => {
        const text = String(message || '').trim();
        if (!text) return;

        if (window.toastr && typeof window.toastr.error === 'function') {
            window.toastr.error(text);
        } else {
            window.alert(text);
        }
    };

    const formatTime = (value) => {
        const parsed = new Date(String(value || '').replace(' ', 'T'));
        if (Number.isNaN(parsed.getTime())) return '';

        return new Intl.DateTimeFormat(
            document.documentElement.lang || undefined,
            {hour: '2-digit', minute: '2-digit'}
        ).format(parsed);
    };

    const signature = (messages) => (Array.isArray(messages) ? messages : [])
        .map((item) => [
            Number(item.id) || 0,
            Number(item.sender_id) || 0,
            item.message || '',
            item.created_at || '',
            item.edited_at || '',
        ].join(':'))
        .join('|');

    // FIREBALL_CHAT_SELECTION_GUARD
    let deferredGroupMessages = null;
    let deferredGroupForceBottom = false;

    const groupSelectionNodeInsideMessages = (node) => {
        if (!node || !box[0]) return false;
        const element = node.nodeType === Node.TEXT_NODE ? node.parentNode : node;
        return Boolean(element && (element === box[0] || box[0].contains(element)));
    };

    const hasGroupMessageTextSelection = () => {
        const selection = window.getSelection ? window.getSelection() : null;
        if (!selection || selection.isCollapsed || selection.rangeCount === 0 || String(selection.toString() || '') === '') return false;
        if (groupSelectionNodeInsideMessages(selection.anchorNode) || groupSelectionNodeInsideMessages(selection.focusNode)) return true;
        for (let index = 0; index < selection.rangeCount; index += 1) {
            if (selection.getRangeAt(index).intersectsNode(box[0])) return true;
        }
        return false;
    };

    const flushDeferredGroupRender = () => {
        if (hasGroupMessageTextSelection()) return;
        if (Array.isArray(deferredGroupMessages)) {
            render(deferredGroupMessages, deferredGroupForceBottom);
        }
        if (realtimeRefreshPending) refreshRealtimeMessages();
    };

    const refreshRealtimeMessages = () => {
        realtimeRefreshPending = true;
        if (document.hidden || requestPending || hasGroupMessageTextSelection()) return;
        realtimeRefreshPending = false;
        loadMessages(false);
    };

    const render = (messages, forceBottom = false) => {
        messages = Array.isArray(messages) ? messages : [];
        if (hasGroupMessageTextSelection()) {
            deferredGroupMessages = messages;
            deferredGroupForceBottom = deferredGroupForceBottom || Boolean(forceBottom);
            return;
        }

        deferredGroupMessages = null;
        deferredGroupForceBottom = false;

        const nextSignature = signature(messages);

        if (nextSignature === renderedSignature && !forceBottom) return;

        const el = box[0];
        const nearBottom = el
            ? (el.scrollHeight - (box.scrollTop() + el.clientHeight)) < 48
            : true;

        if (!messages.length) {
            box.html(`
                <div class="chat-dialog-empty">
                    <span class="chat-dialog-empty__icon" aria-hidden="true"><i class="ci-chat"></i></span>
                    <p class="text-body-secondary mb-0">${escapeHtml(app.data('empty-text') || 'No messages yet.')}</p>
                </div>
            `);
            renderedSignature = nextSignature;
            return;
        }

        box.html(messages.map((item) => {
            const mine = Boolean(item.is_mine);
            const sender = escapeHtml(item.sender_name || '');
            const avatar = escapeHtml(item.sender_avatar || '');
            const text = escapeHtml(item.message || '').replace(/\n/g, '<br>');
            const time = escapeHtml(formatTime(item.created_at));
            const dateTime = escapeHtml(String(item.created_at || '').replace(' ', 'T'));

            return `
                <div class="chat-message-row ${mine ? 'chat-message-row--mine' : 'chat-message-row--theirs'} is-group-start is-group-end" data-message-id="${Number(item.id) || 0}">
                    ${mine ? '' : `<img src="${avatar}" alt="" class="chat-message-avatar rounded-circle border flex-shrink-0">`}
                    <div class="chat-message-stack">
                        ${mine ? '' : `<div class="group-chat-sender">${sender}</div>`}
                        <div class="chat-message-bubble ${mine ? 'chat-message-bubble--mine' : 'chat-message-bubble--theirs'}">
                            <div class="chat-message-text">${text}</div>
                            <div class="chat-message-meta-text">
                                <time datetime="${dateTime}" title="${escapeHtml(item.created_at || '')}">${time}</time>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join(''));

        renderedSignature = nextSignature;

        if (el && (forceBottom || nearBottom)) {
            box.scrollTop(el.scrollHeight);
        }
    };

    const loadMessages = (forceBottom = false) => {
        if (requestPending || !messagesUrl || !conversationId) return;

        requestPending = true;

        $.ajax({
            url: messagesUrl,
            method: 'GET',
            dataType: 'json',
            data: {conversation_id: conversationId},
            success: function (response) {
                if (!response.status) {
                    showError(response.message || app.data('load-error-text'));
                    return;
                }

                render(response.messages || [], forceBottom);
            },
            error: function (request) {
                showError(
                    request.responseJSON && request.responseJSON.message
                        ? request.responseJSON.message
                        : app.data('load-error-text')
                );
            },
            complete: function () {
                requestPending = false;
                if (realtimeRefreshPending) refreshRealtimeMessages();
            }
        });
    };

    const stopRealtime = () => {
        realtimeConnected = false;
        if (source) {
            source.close();
            source = null;
        }
    };

    const startRealtime = () => {
        if (!streamUrl || !conversationId || typeof window.EventSource === 'undefined' || document.hidden) {
            realtimeConnected = false;
            return;
        }

        stopRealtime();

        const url = new URL(streamUrl, window.location.href);
        url.searchParams.set('conversation_id', String(conversationId));

        const current = new window.EventSource(url.toString(), {withCredentials: true});
        source = current;

        current.addEventListener('open', () => {
            if (source === current) realtimeConnected = true;
        });

        current.addEventListener('group-chat', (event) => {
            if (source !== current) return;

            let payload = {};
            try {
                payload = JSON.parse(event.data || '{}');
            } catch (error) {
                return;
            }

            if (Number(payload.conversation_id) === conversationId) {
                refreshRealtimeMessages();
            }
        });

        current.addEventListener('error', () => {
            if (source === current) realtimeConnected = false;
        });
    };

    const resizeInput = () => {
        const el = input[0];
        if (!el) return;

        const resizeAnchor = viewportAnchor || readViewportAnchor();
        el.style.height = 'auto';
        el.style.height = `${Math.max(44, Math.min(el.scrollHeight, 132))}px`;
        el.style.overflowY = el.scrollHeight > 132 ? 'auto' : 'hidden';
        restoreViewportAnchor(resizeAnchor);
    };

    form.on('submit', function (event) {
        event.preventDefault();

        const message = String(input.val() || '').trim();
        if (!message || sendButton.prop('disabled')) return;

        $.ajax({
            url: sendUrl,
            method: 'POST',
            dataType: 'json',
            data: form.serialize(),
            beforeSend: function () {
                sendButton.prop('disabled', true);
            },
            success: function (response) {
                if (!response.status) {
                    showError(response.message || app.data('send-error-text'));
                    return;
                }

                input.val('');
                resizeInput();
                render(response.messages || [], true);
            },
            error: function (request) {
                showError(
                    request.responseJSON && request.responseJSON.message
                        ? request.responseJSON.message
                        : app.data('send-error-text')
                );
            },
            complete: function () {
                sendButton.prop('disabled', false);
                input.trigger('focus');
            }
        });
    });

    input.on('input', resizeInput);

    input.on('keydown', function (event) {
        const original = event.originalEvent || event;

        if (event.key !== 'Enter' || event.shiftKey || original.isComposing) {
            return;
        }

        event.preventDefault();
        form.trigger('submit');
    });

    // FIREBALL_CHAT_SELECTION_GUARD
    document.addEventListener('selectionchange', function () {
        window.setTimeout(flushDeferredGroupRender, 0);
    });

    $(document).on('mouseup touchend keyup', function () {
        window.setTimeout(flushDeferredGroupRender, 0);
    });

    window.addEventListener('pagehide', stopRealtime);

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            startRealtime();
            loadMessages(false);
        } else {
            stopRealtime();
        }
    });

    window.setInterval(function () {
        if (document.visibilityState === 'visible' && !realtimeConnected) {
            loadMessages(false);
        }
    }, 5000);

    syncViewport();
    resizeInput();
    loadMessages(true);
    startRealtime();
});
