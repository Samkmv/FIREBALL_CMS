(() => {
    const form = document.querySelector('[data-seasonal-settings]');
    if (!form) return;

    const mode = form.elements.seasonal_mode;
    const start = form.elements.seasonal_start;
    const end = form.elements.seasonal_end;
    const motion = form.elements.seasonal_snow_motion;
    const device = form.querySelector('#seasonal-device');
    const theme = form.querySelector('#seasonal-theme');
    const enabled = form.querySelector('#seasonal-preview-enabled');
    const stage = form.querySelector('[data-seasonal-stage]');
    const viewport = stage.querySelector('[data-seasonal-viewport]');
    const frame = viewport.querySelector('iframe');

    function resizePreview() {
        const [width, height] = device.value === 'mobile' ? [390, 844] : [1440, 900];
        const scale = Math.min(stage.clientWidth / width, 1);
        viewport.style.width = `${width * scale}px`;
        viewport.style.height = `${height * scale}px`;
        frame.style.width = `${width}px`;
        frame.style.height = `${height}px`;
        frame.style.transform = `scale(${scale})`;
    }

    function updateEffects() {
        const page = frame.contentDocument;
        if (!page?.body?.hasAttribute('data-seasonal-preview')) return;

        page.documentElement.setAttribute('data-bs-theme', theme.value);
        const themeIcon = page.querySelector('.theme-icon-active i');
        if (themeIcon) themeIcon.className = theme.value === 'dark' ? 'ci-moon' : 'ci-sun';
        page.body.toggleAttribute('data-winter-city', enabled.checked);
        page.body.toggleAttribute('data-winter-snow-motion', enabled.checked && motion.checked);
    }

    function updateDates() {
        start.required = end.required = mode.value === 'scheduled';
    }

    theme.value = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';
    device.addEventListener('change', resizePreview);
    mode.addEventListener('change', updateDates);
    theme.addEventListener('change', updateEffects);
    enabled.addEventListener('change', updateEffects);
    motion.addEventListener('change', updateEffects);
    frame.addEventListener('load', updateEffects);
    new ResizeObserver(resizePreview).observe(stage);
    resizePreview();
    updateDates();
    updateEffects();
})();
