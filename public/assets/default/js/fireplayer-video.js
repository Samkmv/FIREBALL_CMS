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
            let lastTapAt = 0;
            let lastTapSide = '';

            const seekFromPoint = function (clientX) {
                if (!player.options.gestures || !Number.isFinite(player.media.duration)) {
                    return;
                }
                const box = stage.getBoundingClientRect();
                const relative = box.width > 0 ? (clientX - box.left) / box.width : 0.5;
                if (relative < 0.38) {
                    player.media.currentTime = Math.max(0, player.media.currentTime - 10);
                } else if (relative > 0.62) {
                    player.media.currentTime = Math.min(player.media.duration, player.media.currentTime + 10);
                } else {
                    player.toggle();
                }
            };

            const onDoubleClick = function (event) {
                if (event.target.closest('button, input, select')) {
                    return;
                }
                event.preventDefault();
                seekFromPoint(event.clientX);
            };

            const onTouchEnd = function (event) {
                if (!player.options.gestures || event.target.closest('button, input, select')) {
                    return;
                }
                const touch = event.changedTouches && event.changedTouches[0];
                if (!touch) {
                    return;
                }
                const box = stage.getBoundingClientRect();
                const side = touch.clientX < box.left + (box.width * 0.38) ? 'left'
                    : (touch.clientX > box.left + (box.width * 0.62) ? 'right' : 'center');
                const now = Date.now();
                if (now - lastTapAt < 320 && side === lastTapSide) {
                    event.preventDefault();
                    seekFromPoint(touch.clientX);
                    lastTapAt = 0;
                    lastTapSide = '';
                    return;
                }
                lastTapAt = now;
                lastTapSide = side;
            };

            stage.addEventListener('dblclick', onDoubleClick);
            stage.addEventListener('touchend', onTouchEnd, { passive: false });
            player.root.classList.add('fireplayer--video-ready');

            return function () {
                stage.removeEventListener('dblclick', onDoubleClick);
                stage.removeEventListener('touchend', onTouchEnd, { passive: false });
                player.root.classList.remove('fireplayer--video-ready');
            };
        }
    });
})(window);
