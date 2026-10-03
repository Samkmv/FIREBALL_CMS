(() => {
    'use strict';
    if (window.FireballAppViewport) return;

    // Only fullscreen applications opt in. Ordinary public pages keep their
    // document scroll. The application is fixed, not body: nesting fixed
    // chrome inside a fixed body creates a second viewport/clip layer on iOS.
    const owners = new Set();
    let savedStyles = [];
    let touch = null;
    const lockStyles = (element, properties) => {
        Object.entries(properties).forEach(([property, value]) => {
            savedStyles.push([element, property, element.style.getPropertyValue(property), element.style.getPropertyPriority(property)]);
            element.style.setProperty(property, value, 'important');
        });
    };
    const lock = owner => {
        if (owners.has(owner)) return;
        if (!owners.size) {
            lockStyles(document.documentElement, {height: '100%', overflow: 'hidden', 'overscroll-behavior': 'none', 'scroll-behavior': 'auto'});
            lockStyles(document.body, {height: '100%', 'min-height': '0', overflow: 'hidden', 'overscroll-behavior': 'none'});
            document.addEventListener('touchstart', startTouch, {passive: true});
            document.addEventListener('touchmove', moveTouch, {passive: false});
        }
        owners.add(owner);
    };
    const unlock = owner => {
        if (!owners.delete(owner) || owners.size) return;
        savedStyles.forEach(([element, property, value, priority]) => {
            if (value) element.style.setProperty(property, value, priority);
            else element.style.removeProperty(property);
        });
        savedStyles = [];
        touch = null;
        document.removeEventListener('touchstart', startTouch);
        document.removeEventListener('touchmove', moveTouch);
    };
    const startTouch = event => {
        touch = event.touches.length === 1
            ? {x: event.touches[0].clientX, y: event.touches[0].clientY, target: event.target}
            : null;
    };
    const moveTouch = event => {
        if (!touch || event.touches.length !== 1 || !event.cancelable
            || (Number(window.visualViewport?.scale) || 1) > 1.02) return;
        const deltaX = event.touches[0].clientX - touch.x;
        const deltaY = event.touches[0].clientY - touch.y;
        touch.x = event.touches[0].clientX;
        touch.y = event.touches[0].clientY;
        // Preserve horizontal gestures, pinch zoom and text-selection handles.
        if (Math.abs(deltaX) >= Math.abs(deltaY) || window.getSelection()?.isCollapsed === false) return;
        for (let element = touch.target; element && element !== document.body && element !== document.documentElement; element = element.parentElement) {
            if (element.nodeType !== 1) continue;
            const style = window.getComputedStyle(element);
            const maxScroll = element.scrollHeight - element.clientHeight;
            if (/(auto|scroll)/.test(style.overflowY) && maxScroll > 1
                && ((deltaY < 0 && element.scrollTop < maxScroll - 1) || (deltaY > 0 && element.scrollTop > 1))) return;
        }
        // Block only gestures that no inner scroller can consume, not all touch.
        event.preventDefault();
    };

    window.FireballAppViewport = {
        inspect() {
            const geometry = selector => {
                const element = document.querySelector(selector);
                if (!element) return null;
                const rect = element.getBoundingClientRect();
                const style = window.getComputedStyle(element);
                return {top: rect.top, bottom: rect.bottom, height: rect.height, position: style.position, transform: style.transform, visibility: style.visibility, overflow: style.overflow};
            };
            const viewport = window.visualViewport;
            return {
                userAgent: window.navigator?.userAgent,
                layout: {height: window.innerHeight, clientHeight: document.documentElement.clientHeight, scrollY: window.scrollY},
                visual: viewport ? {height: viewport.height, offsetTop: viewport.offsetTop, pageTop: viewport.pageTop, scale: viewport.scale} : null,
                applications: [...owners].map(owner => owner.metrics),
                header: geometry('body > header'),
                headerContent: geometry('body > header > .container'),
                chat: geometry('.chat-page'),
                composer: geometry('.chat-thread__composer'),
                editor: geometry('[data-editor-workspace]')
            };
        },
        create({media = '(max-width: 767.98px)', observe = true, onChange = () => {}} = {}) {
            const query = window.matchMedia(media);
            let referenceHeight = 0;
            let referenceWidth = 0;
            let keyboardWasVisible = false;
            let frame = 0;
            let destroyed = false;
            const subscriptions = [];
            const owner = {};
            let extentProbe = null;
            let diagnosticButton = null;
            if (window.location?.search && new URLSearchParams(window.location.search).get('viewport_debug') === '1') {
                diagnosticButton = document.createElement('button');
                diagnosticButton.type = 'button';
                diagnosticButton.textContent = 'Viewport';
                diagnosticButton.style.cssText = 'position:fixed;right:8px;top:env(safe-area-inset-top,0px);z-index:1055;font:12px monospace;padding:8px;border-radius:8px';
                diagnosticButton.addEventListener('click', () => {
                    // Geometry only: no account, messages or form field values.
                    window.prompt('Viewport diagnostics', JSON.stringify(window.FireballAppViewport.inspect()));
                });
                document.body.appendChild(diagnosticButton);
            }
            const sync = focused => {
                if (destroyed || !document.body) return;
                const root = document.documentElement;
                const mobile = query.matches;
                if (mobile) lock(owner);
                else unlock(owner);
                const viewport = window.visualViewport;
                // Don't reflow the application around a user-controlled pinch zoom.
                if (mobile && viewport && Math.abs((Number(viewport.scale) || 1) - 1) > .02) return;
                const width = Number(window.innerWidth) || root.clientWidth;
                const layoutHeight = Number(window.innerHeight) || root.clientHeight;
                const visualHeight = mobile && Number(viewport?.height) > 0 ? Number(viewport.height) : layoutHeight;
                const standalone = root.classList?.contains('pwa-standalone')
                    || window.navigator?.standalone === true
                    || window.matchMedia('(display-mode: standalone)').matches;
                // Measure the closed extent before detecting the keyboard: on
                // resume/rotation the first observation may already be reduced.
                if (mobile && standalone && !extentProbe && document.createElement) {
                    extentProbe = document.createElement('div');
                    extentProbe.setAttribute('aria-hidden', 'true');
                    extentProbe.style.cssText = 'position:fixed;top:0;left:0;width:0;height:100vh;visibility:hidden;pointer-events:none;contain:strict';
                    document.body.appendChild(extentProbe);
                }
                const nativeHeight = Number(extentProbe?.getBoundingClientRect().height) || layoutHeight;
                const active = document.activeElement;
                const editable = Boolean(focused || (active && (active.isContentEditable || active.matches?.('input, textarea, select'))));
                if (referenceWidth && Math.abs(referenceWidth - width) > 40) {
                    referenceHeight = 0;
                    keyboardWasVisible = false;
                }
                referenceWidth = width;
                if (!referenceHeight || (!editable && !keyboardWasVisible)) referenceHeight = standalone ? nativeHeight : Math.max(layoutHeight, visualHeight);
                else referenceHeight = Math.max(referenceHeight, visualHeight);
                // offsetTop is a coordinate, not usable height. Adding it to
                // height disguises a keyboard when Safari pans the viewport.
                const keyboard = mobile && (editable || keyboardWasVisible)
                    && referenceHeight - visualHeight > Math.max(80, referenceHeight * .12);
                keyboardWasVisible = keyboard;
                let height = visualHeight;
                let top = mobile ? Math.max(0, Number(viewport?.offsetTop) || 0) : 0;
                if (mobile && standalone && !keyboard) {
                    // WebKit 254868: a closed PWA visualViewport can exclude
                    // safe-area while fixed layout covers it. Measure CSS's
                    // native extent instead of adding guessed inset pixels.
                    height = nativeHeight;
                    // offsetTop can remain stale after iOS dismisses keyboard.
                    // Native closed-screen layout must start at its own origin.
                    top = 0;
                }
                owner.metrics = {mobile, standalone: Boolean(standalone), height, top, visualHeight, keyboard};
                onChange(owner.metrics);
            };
            const schedule = () => {
                if (!frame && !destroyed) frame = window.requestAnimationFrame(() => {frame = 0; sync();});
            };
            const listen = (target, event, callback = schedule) => {
                if (!target?.addEventListener) return;
                target.addEventListener(event, callback, {passive: true});
                subscriptions.push(() => target.removeEventListener(event, callback));
            };
            if (observe) {
                ['resize', 'scroll', 'pageshow', 'orientationchange'].forEach(event => listen(window, event));
                ['resize', 'scroll', 'scrollend'].forEach(event => listen(window.visualViewport, event));
                ['focusin', 'focusout'].forEach(event => listen(document, event));
                listen(document, 'visibilitychange', () => {if (!document.hidden) schedule();});
                if (query.addEventListener) listen(query, 'change');
                else if (query.addListener) {
                    query.addListener(schedule);
                    subscriptions.push(() => query.removeListener(schedule));
                }
            }
            return {
                sync,
                destroy() {
                    destroyed = true;
                    window.cancelAnimationFrame(frame);
                    subscriptions.forEach(remove => remove());
                    extentProbe?.remove();
                    diagnosticButton?.remove();
                    unlock(owner);
                }
            };
        }
    };
})();
