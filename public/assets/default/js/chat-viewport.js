(() => {
    'use strict';
    if (window.FireballChatViewport) return;
    let controller;
    window.FireballChatViewport = {
        sync(editableFocused = false) {
            controller ||= window.FireballAppViewport.create({
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
                    root.style.setProperty('--chat-visual-viewport-top', `${top}px`);
                    root.style.setProperty('--chat-mobile-viewport-top', `${top + headerHeight}px`);
                    root.style.setProperty('--chat-mobile-viewport-height', `${Math.max(0, height - headerHeight)}px`);
                }
            });
            controller.sync(editableFocused);
        }
    };
})();
