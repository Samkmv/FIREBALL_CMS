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
    const height = window.visualViewport?.height || window.innerHeight;
    root.style.setProperty('--chat-mobile-viewport-top', `${headerHeight}px`);
    root.style.setProperty('--chat-mobile-viewport-height', `${Math.max(0, height - headerHeight)}px`);
})();
</script>
