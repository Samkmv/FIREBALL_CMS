(function () {
    const timers = Array.from(document.querySelectorAll('[data-toy-rental-timer]'));
    if (timers.length === 0) {
        return;
    }

    const settings = window.toyRentalSettings || {};
    const notified = new Set();

    const format = (seconds) => {
        const sign = seconds < 0 ? '+' : '';
        const value = Math.abs(seconds);
        const minutes = Math.floor(value / 60);
        const rest = value % 60;

        return `${sign}${String(minutes).padStart(2, '0')}:${String(rest).padStart(2, '0')}`;
    };

    const playSound = () => {
        if (settings.soundEnabled === false) {
            return;
        }

        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) {
                return;
            }
            const context = new AudioContext();
            const oscillator = context.createOscillator();
            const gain = context.createGain();
            oscillator.type = 'sine';
            oscillator.frequency.value = 880;
            gain.gain.value = 0.055;
            oscillator.connect(gain);
            gain.connect(context.destination);
            oscillator.start();
            setTimeout(() => {
                oscillator.stop();
                context.close();
            }, 180);
        } catch (error) {
            // Browsers can block audio until user interaction.
        }
    };

    const notify = (card, label) => {
        const rideId = card?.dataset?.rideId || label;
        if (notified.has(rideId)) {
            return;
        }
        notified.add(rideId);

        if (settings.toastEnabled !== false && window.toastr) {
            if (typeof window.toastr.rental === 'function') {
                window.toastr.rental({
                    title: 'Время поездки закончилось',
                    message: label,
                    href: settings.dashboardUrl || '/admin/toy-rental',
                });
            } else if (typeof window.toastr.error === 'function') {
                window.toastr.error(label, 'Время поездки закончилось');
            }
        }

        playSound();
    };

    const tick = () => {
        const now = Date.now();
        timers.forEach((timer) => {
            const end = Date.parse(timer.dataset.end || '');
            if (!Number.isFinite(end)) {
                return;
            }

            const seconds = Math.ceil((end - now) / 1000);
            const card = timer.closest('[data-toy-rental-card]');
            const label = card?.dataset?.carLabel || 'машинка';
            timer.textContent = format(seconds);

            if (seconds < 0) {
                timer.classList.add('text-danger');
                card?.classList.add('is-overdue', 'toy-rental-alert-pulse');
                const badge = card?.querySelector('[data-toy-rental-status]');
                if (badge) {
                    badge.textContent = 'Просрочена';
                    badge.className = 'badge rounded-pill text-bg-danger';
                }
                notify(card, label);
            }
        });
    };

    tick();
    window.setInterval(tick, 1000);

    if (settings.autoRefreshSeconds > 0) {
        window.setTimeout(() => window.location.reload(), settings.autoRefreshSeconds * 1000);
    }
})();
