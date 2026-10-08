(() => {
    'use strict';
    if (window.FireballChatViewport) return;
    let controller;
    window.FireballChatViewport = {
        captureMessageAnchor(box) {
            if (!box) return null;
            const distance = Math.max(0, box.scrollHeight - box.scrollTop - box.clientHeight);
            // Keep the line being read at the same position; only the latest
            // messages should follow the bottom as the keyboard animates.
            return {scrollTop: box.scrollTop, stickToBottom: distance <= 48};
        },
        restoreMessageAnchor(box, anchor) {
            if (box && anchor) box.scrollTop = anchor.stickToBottom ? box.scrollHeight : anchor.scrollTop;
        },
        keepComposerInputVisible(input) {
            if (!input || document.activeElement !== input) return;
            const composer = input.closest('.chat-thread__composer');
            const row = input.closest('.chat-composer__row');
            if (!composer || !row || composer.scrollHeight <= composer.clientHeight) return;
            const bounds = composer.getBoundingClientRect();
            const field = row.getBoundingClientRect();
            const style = window.getComputedStyle(composer);
            const top = bounds.top + (Number.parseFloat(style.paddingTop) || 0);
            const bottom = bounds.bottom - (Number.parseFloat(style.paddingBottom) || 0);
            // Scroll this panel only: scrollIntoView would pan the iOS document.
            if (field.bottom > bottom) composer.scrollTop += field.bottom - bottom;
            else if (field.top < top) composer.scrollTop -= top - field.top;
        },
        keepModalInputVisible() {
            const input = document.activeElement;
            if (!input || !(input.isContentEditable || input.matches?.('input, textarea, select'))) return;
            const body = input.closest('.fb-cms-modal .modal-body');
            if (!body || body.scrollHeight <= body.clientHeight) return;
            const bounds = body.getBoundingClientRect();
            const field = input.getBoundingClientRect();
            const style = window.getComputedStyle(body);
            const top = bounds.top + (Number.parseFloat(style.paddingTop) || 0);
            const bottom = bounds.bottom - (Number.parseFloat(style.paddingBottom) || 0);
            if (field.bottom > bottom) body.scrollTop += field.bottom - bottom;
            else if (field.top < top) body.scrollTop -= top - field.top;
        },
        sync(editableFocused = false) {
            controller ||= window.FireballAppViewport.create({
                // A landscape phone/iPad still has a software keyboard above 768px.
                media: '(max-width: 767.98px), (hover: none), (pointer: coarse)',
                observe: false, // Chat owns message anchoring around each resize.
                onChange({mobile, height, top, keyboard}) {
                    const root = document.documentElement;
                    const standalone = root.classList.contains('pwa-standalone')
                        || window.matchMedia('(display-mode: standalone)').matches
                        || window.navigator.standalone === true;
                    [root, document.body].forEach(element => {
                        element.classList.add('chat-viewport-fullscreen');
                        element.classList.toggle('chat-mobile-fullscreen', mobile);
                        element.classList.toggle('chat-pwa-fullscreen', standalone);
                        element.classList.toggle('chat-keyboard-visible', keyboard);
                    });
                    const header = standalone && !mobile ? null : document.querySelector('body > header');
                    const headerHeight = header ? Math.max(0, Number(header.getBoundingClientRect().height) || 0) : 0;
                    root.classList.toggle('chat-compact-viewport', mobile && height - headerHeight < 400);
                    root.style.setProperty('--fb-modal-viewport-top', `${top}px`);
                    root.style.setProperty('--fb-modal-viewport-height', `${height}px`);
                    root.style.setProperty('--chat-visual-viewport-top', `${top}px`);
                    root.style.setProperty('--chat-mobile-viewport-top', `${top + headerHeight}px`);
                    root.style.setProperty('--chat-mobile-viewport-height', `${Math.max(0, height - headerHeight)}px`);
                    window.FireballChatViewport.keepModalInputVisible();
                }
            });
            controller.sync(editableFocused);
        }
    };
})();
