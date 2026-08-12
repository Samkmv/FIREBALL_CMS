(function () {
    'use strict';

    function syncPlanVisibility(scope) {
        (scope || document).querySelectorAll('[data-editor2]').forEach(function (root) {
            const select = root.querySelector('[data-editor-setting="data.subscriptionAccessMode"]');
            const checkbox = root.querySelector('[data-editor-video-plan]');
            const plans = root.querySelector('[data-editor-video-plans]')
                || (checkbox ? checkbox.closest('.fb-editor2-inspector-field') : null);
            if (!select || !plans) {
                return;
            }
            plans.setAttribute('data-editor-video-plans', '');
            plans.hidden = select.value !== 'plans';
        });
    }

    document.addEventListener('change', function (event) {
        const target = event.target;
        if (!(target instanceof HTMLInputElement) || !target.hasAttribute('data-editor-video-plan')) {
            window.requestAnimationFrame(function () {
                syncPlanVisibility(document);
            });
            return;
        }

        const root = target.closest('[data-editor2]');
        const editor = root && root.__fireballEditor2;
        const block = editor && typeof editor.activeBlock === 'function' ? editor.activeBlock() : null;
        if (!block || !block.data) {
            return;
        }

        const planId = Number(target.value);
        const values = new Set((block.data.subscriptionPlanIds || []).map(Number));
        target.checked ? values.add(planId) : values.delete(planId);
        block.data.subscriptionPlanIds = Array.from(values);
        event.stopPropagation();
        editor.commit('video-access', true, true);
    }, true);

    const observer = new MutationObserver(function () {
        syncPlanVisibility(document);
    });

    function start() {
        syncPlanVisibility(document);
        if (document.body) {
            observer.observe(document.body, { childList: true, subtree: true });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }
})();
