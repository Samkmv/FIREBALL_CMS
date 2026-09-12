(function (window) {
    'use strict';

    if (!window.FirePlayer) {
        return;
    }

    window.FirePlayer.use({
        name: 'video',
        test: function (player, info) {
            return info.media === 'video';
        },
        setup: function (player) {
            const stage = player.elements.stage;
            const media = player.media;
            const menu = player.elements.settingsMenu;
            const document = player.root.ownerDocument;
            const russian = (document.documentElement.lang || '').toLowerCase().startsWith('ru');
            const zoom = document.createElement('div');
            zoom.className = 'fireplayer__zoom';
            zoom.innerHTML = '<label class="fireplayer__zoom-label"><span class="fireplayer__settings-label">' + (russian ? 'Масштаб' : 'Zoom') + '</span>' +
                '<span data-fp-zoom-value>1×</span><input class="fireplayer__range" data-fp-zoom-range type="range" min="1" max="3" step="0.05" value="1" aria-label="' + (russian ? 'Масштаб видео' : 'Video zoom') + '"></label>' +
                '<button type="button" class="fireplayer__zoom-reset" data-fp-zoom-reset>' + (russian ? 'Сбросить масштаб' : 'Reset zoom') + '</button>';
            menu.appendChild(zoom);
            const range = zoom.querySelector('[data-fp-zoom-range]');
            const value = zoom.querySelector('[data-fp-zoom-value]');
            const reset = zoom.querySelector('[data-fp-zoom-reset]');
            const pointers = new Map();
            const removers = [];
            const previousTransform = media.style.transform;
            const previousTouchAction = stage.style.touchAction;
            const previousMaxHeight = menu.style.maxHeight;
            let scale = 1;
            let x = 0;
            let y = 0;
            let gesture = null;
            let dragged = false;
            let suppressClickUntil = 0;
            let clickTimer = null;
            let lastTapAt = 0;
            let lastTapSide = '';

            const listen = function (element, name, callback, options) {
                element.addEventListener(name, callback, options);
                removers.push(function () { element.removeEventListener(name, callback, options); });
            };
            const interactive = function (event) {
                return event.target instanceof window.Element && event.target.closest('button, input, select, textarea, a, [data-fp-controls]');
            };
            const applyZoom = function () {
                const maxX = stage.clientWidth * (scale - 1) / 2;
                const maxY = stage.clientHeight * (scale - 1) / 2;
                x = Math.max(-maxX, Math.min(maxX, x));
                y = Math.max(-maxY, Math.min(maxY, y));
                media.style.transform = scale === 1 ? previousTransform : 'translate(' + x + 'px, ' + y + 'px) scale(' + scale + ')';
                player.root.classList.toggle('fireplayer--zoomed', scale > 1);
                range.value = String(scale);
                range.style.setProperty('--fireplayer-progress', ((scale - 1) / 2 * 100) + '%');
                value.textContent = Number(scale.toFixed(2)) + '×';
                reset.disabled = scale === 1;
                menu.style.maxHeight = Math.max(80, stage.clientHeight - player.elements.controls.offsetHeight - 12) + 'px';
            };
            const setScale = function (next) {
                if (!Number.isFinite(next)) { return; }
                scale = Math.max(1, Math.min(3, next));
                applyZoom();
            };
            const localPoint = function (point) {
                const box = stage.getBoundingClientRect();
                return { x: point.clientX - box.left - box.width / 2, y: point.clientY - box.top - box.height / 2 };
            };
            const pinchPoints = function () {
                const active = Array.from(pointers.values());
                const first = active[0];
                const second = active[1];
                return {
                    distance: Math.max(1, Math.hypot(first.clientX - second.clientX, first.clientY - second.clientY)),
                    center: localPoint({ clientX: (first.clientX + second.clientX) / 2, clientY: (first.clientY + second.clientY) / 2 })
                };
            };
            const beginGesture = function () {
                if (pointers.size >= 2) {
                    const pinch = pinchPoints();
                    gesture = { pinch: true, distance: pinch.distance, center: pinch.center, scale: scale, x: x, y: y };
                    dragged = true;
                } else if (pointers.size === 1) {
                    const point = Array.from(pointers.values())[0];
                    gesture = { pinch: false, clientX: point.clientX, clientY: point.clientY, x: x, y: y };
                } else {
                    gesture = null;
                    player.root.classList.remove('fireplayer--panning');
                }
            };

            const seekFromPoint = function (clientX) {
                if (!player.options.gestures) { return; }
                const box = stage.getBoundingClientRect();
                const relative = box.width > 0 ? (clientX - box.left) / box.width : 0.5;
                if (relative >= 0.38 && relative <= 0.62) {
                    player.toggle().catch(function () {});
                    return;
                }
                let start = 0;
                let end = media.duration;
                if (media.seekable.length) {
                    let index = media.seekable.length - 1;
                    for (let i = 0; i < media.seekable.length; i += 1) {
                        if (media.currentTime >= media.seekable.start(i) && media.currentTime <= media.seekable.end(i)) { index = i; break; }
                    }
                    start = media.seekable.start(index);
                    end = media.seekable.end(index);
                }
                if (!Number.isFinite(end) || end <= start) { return; }
                try { media.currentTime = Math.max(start, Math.min(end, media.currentTime + (relative < 0.38 ? -10 : 10))); } catch (error) { /* A live seek window can expire between events. */ }
            };

            const registerTap = function (event) {
                const box = stage.getBoundingClientRect();
                const side = event.clientX < box.left + (box.width * 0.38) ? 'left'
                    : (event.clientX > box.left + (box.width * 0.62) ? 'right' : 'center');
                const now = Date.now();
                if (now - lastTapAt < 320 && side === lastTapSide) {
                    seekFromPoint(event.clientX);
                    lastTapAt = 0;
                    lastTapSide = '';
                    return;
                }
                lastTapAt = now;
                lastTapSide = side;
            };

            listen(range, 'input', function () { setScale(Number(range.value)); });
            listen(reset, 'click', function () { setScale(1); });
            listen(stage, 'click', function (event) {
                if (interactive(event) || Date.now() < suppressClickUntil || event.pointerType === 'touch') { return; }
                if (clickTimer !== null) { window.clearTimeout(clickTimer); }
                clickTimer = window.setTimeout(function () {
                    clickTimer = null;
                    if (!player._destroyed) { player.toggle().catch(function () {}); }
                }, 240);
            });
            listen(stage, 'dblclick', function (event) {
                if (interactive(event) || Date.now() < suppressClickUntil) { return; }
                event.preventDefault();
                if (clickTimer !== null) { window.clearTimeout(clickTimer); clickTimer = null; }
                player.fullscreen().catch(function (error) { player._emit('controlerror', { error: error }); });
            });
            listen(stage, 'pointerdown', function (event) {
                if (!player.options.gestures || interactive(event) || (event.pointerType === 'mouse' && event.button !== 0)) { return; }
                if (!pointers.size) { dragged = false; }
                pointers.set(event.pointerId, { clientX: event.clientX, clientY: event.clientY, startX: event.clientX, startY: event.clientY, startedAt: Date.now() });
                if (event.pointerType === 'touch') { suppressClickUntil = Date.now() + 700; }
                if (event.pointerType !== 'touch') {
                    try { stage.setPointerCapture(event.pointerId); } catch (error) { /* Capture is optional. */ }
                }
                beginGesture();
            });
            listen(stage, 'pointermove', function (event) {
                const pointer = pointers.get(event.pointerId);
                if (!pointer || !gesture) { return; }
                pointer.clientX = event.clientX;
                pointer.clientY = event.clientY;
                // A finger on the video must still scroll/zoom the page. Only
                // stationary touch taps belong to the player; mouse drag pans it.
                if (event.pointerType === 'touch') {
                    if (Math.hypot(event.clientX - pointer.startX, event.clientY - pointer.startY) > 5) { dragged = true; }
                    return;
                }
                if (gesture.pinch && pointers.size >= 2) {
                    const pinch = pinchPoints();
                    scale = Math.max(1, Math.min(3, gesture.scale * pinch.distance / gesture.distance));
                    x = pinch.center.x - (gesture.center.x - gesture.x) * scale / gesture.scale;
                    y = pinch.center.y - (gesture.center.y - gesture.y) * scale / gesture.scale;
                } else {
                    const dx = event.clientX - gesture.clientX;
                    const dy = event.clientY - gesture.clientY;
                    if (Math.hypot(dx, dy) > 5) { dragged = true; }
                    if (scale <= 1) { return; }
                    x = gesture.x + dx;
                    y = gesture.y + dy;
                }
                if (dragged) {
                    event.preventDefault();
                    player.root.classList.add('fireplayer--panning');
                    applyZoom();
                }
            }, { passive: false });
            const finishPointer = function (event) {
                const pointer = pointers.get(event.pointerId);
                if (!pointer) { return; }
                if (event.type !== 'pointerup') {
                    dragged = true;
                    lastTapAt = 0;
                    lastTapSide = '';
                }
                if (dragged || event.pointerType === 'touch') { suppressClickUntil = Date.now() + 700; }
                if (event.type === 'pointerup' && !dragged && pointers.size === 1 && event.pointerType === 'touch' && Date.now() - pointer.startedAt < 500) {
                    registerTap(event);
                } else if (dragged) {
                    lastTapAt = 0;
                }
                pointers.delete(event.pointerId);
                try { stage.releasePointerCapture(event.pointerId); } catch (error) { /* The browser may already have released capture. */ }
                beginGesture();
            };
            listen(stage, 'pointerup', finishPointer);
            listen(stage, 'pointercancel', finishPointer);
            listen(stage, 'lostpointercapture', finishPointer);
            listen(window, 'resize', applyZoom);
            const resizeObserver = typeof window.ResizeObserver === 'function' ? new window.ResizeObserver(applyZoom) : null;
            if (resizeObserver) { resizeObserver.observe(stage); }
            if (player.options.gestures) { stage.style.touchAction = 'pan-y pinch-zoom'; }
            player.root.classList.add('fireplayer--video-ready');
            applyZoom();

            return function () {
                removers.forEach(function (remove) { remove(); });
                if (clickTimer !== null) { window.clearTimeout(clickTimer); }
                if (resizeObserver) { resizeObserver.disconnect(); }
                pointers.forEach(function (_, id) {
                    try { stage.releasePointerCapture(id); } catch (error) { /* Capture may already be released. */ }
                });
                pointers.clear();
                zoom.remove();
                media.style.transform = previousTransform;
                stage.style.touchAction = previousTouchAction;
                menu.style.maxHeight = previousMaxHeight;
                player.root.classList.remove('fireplayer--video-ready', 'fireplayer--zoomed', 'fireplayer--panning');
            };
        }
    });
})(window);
