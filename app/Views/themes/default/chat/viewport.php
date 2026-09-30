<?php // Establish the chat shell before its markup is painted, without waiting for jQuery. ?>
<script>
(() => {
    const root = document.documentElement;
    const mobile = window.matchMedia('(max-width: 767.98px)').matches;
    const standalone = root.classList.contains('pwa-standalone')
        || window.matchMedia('(display-mode: standalone)').matches
        || navigator.standalone === true;
    [root, document.body].forEach(element => {
        element.classList.add('chat-viewport-fullscreen');
        element.classList.toggle('chat-mobile-fullscreen', mobile);
        element.classList.toggle('chat-pwa-fullscreen', standalone);
    });
    const header = standalone && !mobile ? null : document.querySelector('body > header');
    const headerHeight = header ? header.getBoundingClientRect().height : 0;
    // FIREBALL_CHAT_VIEWPORT_IOS_FIX_V2_20260930
    // This script runs before the composer can have keyboard focus. On iOS,
    // visualViewport may already be shorter than the fixed/layout viewport
    // because of browser chrome. Using that shorter value here creates the
    // empty strip below the composer. Start from the full layout viewport;
    // chat.js switches to visualViewport only while the keyboard is visible.
    const layoutHeight = Math.max(
        Number(window.innerHeight) || 0,
        Number(document.documentElement.clientHeight) || 0
    );

    root.style.setProperty('--chat-mobile-viewport-top', `${headerHeight}px`);
    root.style.setProperty('--chat-mobile-viewport-height', `${Math.max(0, layoutHeight - headerHeight)}px`);
})();
</script>
