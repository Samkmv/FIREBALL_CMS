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
    // FIREBALL_CHAT_VIEWPORT_IOS_FIX_20260929
    // Do not move the entire chat by visualViewport.offsetTop. Safari changes
    // offsetTop while focusing the keyboard, which is exactly what makes the
    // chat jump. Keep the top stable and use offsetTop only to find the visible
    // bottom edge.
    const viewport = window.visualViewport;
    const visualTop = mobile ? Math.max(0, Number(viewport?.offsetTop || 0)) : 0;
    const visualHeight = mobile
        ? Math.max(0, Number(viewport?.height || window.innerHeight))
        : Math.max(0, Number(window.innerHeight));
    const layoutHeight = Math.max(
        Number(window.innerHeight) || 0,
        Number(document.documentElement.clientHeight) || 0
    );
    const visibleBottom = mobile
        ? Math.max(0, Math.min(layoutHeight, visualTop + visualHeight))
        : layoutHeight;

    root.style.setProperty('--chat-mobile-viewport-top', `${headerHeight}px`);
    root.style.setProperty('--chat-mobile-viewport-height', `${Math.max(0, visibleBottom - headerHeight)}px`);
})();
</script>
