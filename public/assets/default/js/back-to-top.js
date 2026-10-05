(() => {
    const button = document.querySelector('[data-back-to-top]');
    if (!button) return;

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let updatePending = false;
    let visible = false;

    const update = () => {
        updatePending = false;
        const top = Math.max(0, window.scrollY);
        const scrollRange = Math.max(0, document.documentElement.scrollHeight - window.innerHeight);
        const progress = scrollRange > 0 ? Math.min(1, top / scrollRange) : 0;
        const shouldShow = top > Math.min(320, window.innerHeight * .5);

        button.style.setProperty('--back-top-progress', String((1 - progress) * 100));
        if (visible !== shouldShow) {
            visible = shouldShow;
            button.classList.toggle('is-visible', visible);
            button.setAttribute('aria-hidden', String(!visible));
            button.tabIndex = visible ? 0 : -1;
        }
    };

    const scheduleUpdate = () => {
        if (updatePending) return;
        updatePending = true;
        window.requestAnimationFrame(update);
    };

    button.addEventListener('click', () => {
        window.scrollTo({
            top: 0,
            left: window.scrollX,
            behavior: reducedMotion.matches ? 'instant' : 'smooth'
        });
    });

    button.hidden = false;
    scheduleUpdate();
    window.addEventListener('scroll', scheduleUpdate, { passive: true });
    window.addEventListener('resize', scheduleUpdate, { passive: true });
    window.addEventListener('pageshow', scheduleUpdate);
})();
