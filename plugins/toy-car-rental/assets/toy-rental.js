(function () {
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

    const markFixedOverdue = (card) => {
        const rideId = card?.dataset?.rideId || '';
        if (rideId !== '' && !notified.has(rideId)) {
            notified.add(rideId);
            playSound();
        }

        card?.classList.add('is-overdue', 'toy-rental-alert-pulse');
        const badge = card?.querySelector('[data-toy-rental-status]');
        if (badge) {
            badge.textContent = 'Просрочена';
            badge.className = 'badge rounded-pill text-bg-danger';
        }
    };

    const modalRideState = (modal, now = Date.now()) => {
        const start = Date.parse(modal.dataset.start || '');
        if (!Number.isFinite(start)) {
            return null;
        }

        const price = Number.parseFloat(modal.dataset.pricePerMinute || '0') || 0;
        const elapsedSeconds = Math.max(0, Math.floor((now - start) / 1000));
        const elapsedMinutes = Math.max(1, Math.ceil(elapsedSeconds / 60));

        return {
            elapsedMinutes,
            amount: elapsedMinutes * price,
        };
    };

    const updateCompletionModals = (forceFinalAmount = false) => {
        const now = Date.now();
        document.querySelectorAll('[data-toy-rental-complete-modal]').forEach((modal) => {
            const state = modalRideState(modal, now);
            if (!state) {
                return;
            }

            const currency = modal.dataset.currency || settings.currency || '';
            const formattedAmount = state.amount.toFixed(2);
            const durationField = modal.querySelector('[data-toy-rental-modal-duration]');
            const calculatedField = modal.querySelector('[data-toy-rental-modal-calculated]');
            const finalAmountField = modal.querySelector('[data-toy-rental-final-amount]');

            if (durationField) {
                durationField.value = `${state.elapsedMinutes} мин`;
            }
            if (calculatedField) {
                calculatedField.value = `${formattedAmount}${currency ? ` ${currency}` : ''}`;
            }
            if (finalAmountField && (forceFinalAmount || finalAmountField.dataset.userEdited !== 'true')) {
                finalAmountField.value = formattedAmount;
            }
        });
    };

    const tickTimers = () => {
        const now = Date.now();
        document.querySelectorAll('[data-toy-rental-timer]').forEach((timer) => {
            const billingType = timer.dataset.billingType || 'fixed';
            const card = timer.closest('[data-toy-rental-card]');

            if (billingType === 'metered') {
                const start = Date.parse(timer.dataset.start || '');
                if (!Number.isFinite(start)) {
                    return;
                }

                const elapsedSeconds = Math.max(0, Math.floor((now - start) / 1000));
                timer.textContent = format(elapsedSeconds);

                const price = Number.parseFloat(timer.dataset.pricePerMinute || '0') || 0;
                const costTarget = card?.querySelector('[data-toy-rental-live-cost]');
                if (costTarget) {
                    const elapsedMinutes = Math.max(1, Math.ceil(elapsedSeconds / 60));
                    costTarget.textContent = (elapsedMinutes * price).toFixed(2);
                }

                const estimate = Number.parseInt(timer.dataset.estimatedMinutes || '0', 10);
                if (estimate > 0 && elapsedSeconds > estimate * 60) {
                    card?.classList.add('is-metered-warning');
                    card?.querySelector('[data-toy-rental-estimate-warning]')?.classList.remove('d-none');
                }
                return;
            }

            const end = Date.parse(timer.dataset.end || '');
            if (!Number.isFinite(end)) {
                return;
            }

            const seconds = Math.ceil((end - now) / 1000);
            timer.textContent = format(seconds);

            if (seconds < 0) {
                timer.classList.add('text-danger');
                markFixedOverdue(card);
            }
        });

        updateCompletionModals(false);
    };

    document.querySelectorAll('[data-toy-rental-start-form]').forEach((form) => {
        const type = form.querySelector('[data-toy-rental-billing-type]');
        const fixedFields = form.querySelector('[data-toy-rental-fixed-fields]');
        const meteredFields = form.querySelector('[data-toy-rental-metered-fields]');

        const sync = () => {
            const isMetered = type?.value === 'metered';
            fixedFields?.classList.toggle('d-none', isMetered);
            meteredFields?.classList.toggle('d-none', !isMetered);
            fixedFields?.querySelectorAll('input, select, textarea').forEach((field) => {
                field.disabled = isMetered;
            });
            meteredFields?.querySelectorAll('input, select, textarea').forEach((field) => {
                field.disabled = !isMetered;
            });
        };

        type?.addEventListener('change', sync);
        sync();
    });

    document.querySelectorAll('[data-toy-rental-complete-modal]').forEach((modal) => {
        const finalAmountField = modal.querySelector('[data-toy-rental-final-amount]');

        finalAmountField?.addEventListener('input', () => {
            finalAmountField.dataset.userEdited = 'true';
        });

        modal.addEventListener('show.bs.modal', () => {
            if (finalAmountField) {
                finalAmountField.dataset.userEdited = 'false';
            }
            updateCompletionModals(true);
        });
    });

    if (document.querySelector('[data-toy-rental-timer]')) {
        tickTimers();
        window.setInterval(tickTimers, 1000);
    } else if (document.querySelector('[data-toy-rental-complete-modal]')) {
        updateCompletionModals(false);
        window.setInterval(() => updateCompletionModals(false), 1000);
    }

    if (settings.autoRefreshSeconds > 0) {
        window.setTimeout(() => window.location.reload(), settings.autoRefreshSeconds * 1000);
    }
})();
