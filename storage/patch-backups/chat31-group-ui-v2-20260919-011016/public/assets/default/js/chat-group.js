$(function () {
    'use strict';

    const app = $('[data-group-chat-app]').first();
    if (!app.length) return;

    const conversationId = Number(app.data('conversation-id')) || 0;
    const messagesUrl = String(app.data('messages-url') || '');
    const sendUrl = String(app.data('send-url') || '');
    const streamUrl = String(app.data('stream-url') || '');
    const box = app.find('[data-group-chat-messages]');
    const form = app.find('[data-group-chat-form]');
    const input = app.find('[data-group-chat-input]');
    const submitButton = form.find('button[type="submit"]');

    let eventSource = null;
    let realtimeConnected = false;
    let requestPending = false;
    let renderedSignature = '';

    const escapeHtml = (value) => $('<div>').text(value || '').html();

    const showError = (message) => {
        const text = String(message || '').trim();
        if (!text) return;

        if (window.toastr && typeof window.toastr.error === 'function') {
            window.toastr.error(text);
            return;
        }

        window.alert(text);
    };

    const parseDate = (value) => {
        const normalized = String(value || '').replace(' ', 'T');
        const parsed = new Date(normalized);
        return Number.isNaN(parsed.getTime()) ? null : parsed;
    };

    const formatTime = (value) => {
        const parsed = parseDate(value);
        if (!parsed) {
            const match = String(value || '').match(/(\d{2}):(\d{2})/);
            return match ? `${match[1]}:${match[2]}` : '';
        }

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

    const render = (messages, forceBottom = false) => {
        messages = Array.isArray(messages) ? messages : [];
        const nextSignature = signature(messages);
        if (nextSignature === renderedSignature && !forceBottom) return;

        const nearBottom = box[0]
            ? (box[0].scrollHeight - (box.scrollTop() + box[0].clientHeight)) < 40
            : true;

        if (!messages.length) {
            box.html(`
                <div class="text-center text-body-secondary py-5">
                    ${escapeHtml(app.data('empty-text') || 'No messages yet.')}
                </div>
            `);
            renderedSignature = nextSignature;
            return;
        }

        box.html(messages.map((item) => {
            const mine = Boolean(item.is_mine);
            const senderName = escapeHtml(item.sender_name || '');
            const avatar = escapeHtml(item.sender_avatar || '');
            const message = escapeHtml(item.message || '').replace(/\n/g, '<br>');
            const time = escapeHtml(formatTime(item.created_at));

            return `
                <div class="group-chat-row ${mine ? 'is-mine' : ''}">
                    ${mine ? '' : `
                        <img
                            src="${avatar}"
                            alt=""
                            class="group-chat-avatar rounded-circle border"
                        >
                    `}
                    <div class="group-chat-message">
                        ${mine ? '' : `<div class="group-chat-sender">${senderName}</div>`}
                        <div class="group-chat-bubble">
                            <div>${message}</div>
                            <time class="group-chat-time">${time}</time>
                        </div>
                    </div>
                </div>
            `;
        }).join(''));

        renderedSignature = nextSignature;

        if (forceBottom || nearBottom) {
            box.scrollTop(box[0].scrollHeight);
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
                const message = request.responseJSON && request.responseJSON.message
                    ? request.responseJSON.message
                    : app.data('load-error-text');
                showError(message);
            },
            complete: function () {
                requestPending = false;
            }
        });
    };

    const stopRealtime = () => {
        realtimeConnected = false;
        if (eventSource) {
            eventSource.close();
            eventSource = null;
        }
    };

    const startRealtime = () => {
        if (
            !streamUrl
            || !conversationId
            || typeof window.EventSource === 'undefined'
            || document.hidden
        ) {
            realtimeConnected = false;
            return;
        }

        stopRealtime();

        const url = new URL(streamUrl, window.location.href);
        url.searchParams.set('conversation_id', String(conversationId));

        const source = new window.EventSource(url.toString(), {
            withCredentials: true
        });

        eventSource = source;

        source.addEventListener('open', () => {
            if (eventSource === source) {
                realtimeConnected = true;
            }
        });

        source.addEventListener('group-chat', (event) => {
            if (eventSource !== source) return;

            let payload = {};
            try {
                payload = JSON.parse(event.data || '{}');
            } catch (error) {
                return;
            }

            if (Number(payload.conversation_id) !== conversationId) return;

            loadMessages(true);
        });

        source.addEventListener('error', () => {
            if (eventSource === source) {
                realtimeConnected = false;
            }
        });
    };

    const resizeInput = () => {
        const element = input[0];
        if (!element) return;

        element.style.height = 'auto';
        element.style.height = `${Math.min(element.scrollHeight, 132)}px`;
        element.style.overflowY = element.scrollHeight > 132 ? 'auto' : 'hidden';
    };

    form.on('submit', function (event) {
        event.preventDefault();

        const message = String(input.val() || '').trim();
        if (!message || submitButton.prop('disabled')) return;

        $.ajax({
            url: sendUrl,
            method: 'POST',
            dataType: 'json',
            data: form.serialize(),
            beforeSend: function () {
                submitButton.prop('disabled', true);
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
                const message = request.responseJSON && request.responseJSON.message
                    ? request.responseJSON.message
                    : app.data('send-error-text');
                showError(message);
            },
            complete: function () {
                submitButton.prop('disabled', false);
                input.trigger('focus');
            }
        });
    });

    input.on('input', resizeInput);

    input.on('keydown', function (event) {
        const originalEvent = event.originalEvent || event;
        if (event.key !== 'Enter' || event.shiftKey || originalEvent.isComposing) {
            return;
        }

        event.preventDefault();
        form.trigger('submit');
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

    resizeInput();
    loadMessages(true);
    startRealtime();
});
