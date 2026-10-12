(() => {
    'use strict';
    const root = document.getElementById('widget-editor');
    if (!root) return;
    const content = root.querySelector('[data-widget-content]');
    const status = root.querySelector('[data-widget-status]');
    const deleteForm = root.querySelector('[data-widget-delete-form]');
    let busy = false;
    let dragging = null;
    let dragOrigin = null;
    let touchPointer = null;
    let dropTarget = null;
    // Keep the marker outside the lists so it never becomes part of the saved layout.
    const dropIndicator = document.createElement('div');
    dropIndicator.className = 'widget-drop-indicator';
    dropIndicator.hidden = true;
    dropIndicator.setAttribute('aria-hidden', 'true');
    root.append(dropIndicator);
    const clearDropIndicator = () => { dropIndicator.hidden = true; dropTarget = null; };
    // Release the CMS select components before replacing their forms through AJAX.
    const selects = new Map();
    const syncAddButtons = () => content.querySelectorAll('[data-widget-add]').forEach(form => {
        form.querySelector('[type="submit"]').disabled = !form.elements.type.value;
    });
    const enhanceSelects = () => {
        if (typeof window.Choices !== 'function') return;
        content.querySelectorAll('select[data-widget-select]').forEach(select => {
            if (selects.has(select)) return;
            const label = select.getAttribute('aria-label') || select.labels?.[0]?.textContent.trim();
            const choices = new window.Choices(select, {
                allowHTML: false, searchEnabled: false, removeItemButton: false,
                shouldSort: false, itemSelectText: '', position: 'auto',
                classNames: { containerInner: 'form-select' },
            });
            if (label) select.closest('.choices')?.setAttribute('aria-label', label);
            selects.set(select, choices);
        });
        syncAddButtons();
    };
    const setSelectValue = (select, value) => {
        const choices = selects.get(select);
        if (choices) choices.setChoiceByValue(value); else select.value = value;
    };

    const notice = (message, error = false) => {
        status.className = `alert ${error ? 'alert-danger' : 'alert-success'}`;
        status.textContent = message;
        window.toastr?.[error ? 'error' : 'success']?.(message);
    };
    const readForm = form => {
        const settings = {};
        form.querySelectorAll('[data-setting]').forEach(input => {
            settings[input.dataset.setting] = input.type === 'checkbox' ? input.checked : input.type === 'number' ? Number(input.value) : input.value;
        });
        form.querySelectorAll('[data-category-order]').forEach(list => {
            settings[list.dataset.categoryOrder] = [...list.children].map(item => item.dataset.categorySlug);
        });
        form.querySelectorAll('[data-category-hidden]').forEach(list => {
            settings[list.dataset.categoryHidden] = [...list.querySelectorAll('input:checked')].map(input => input.value);
        });
        const visibility = {};
        form.querySelectorAll('[data-widget-page]').forEach(input => { visibility[input.dataset.widgetPage] = input.checked; });
        return { settings, enabled: form.querySelector('[data-widget-enabled]').checked, visibility };
    };
    const drafts = () => [...content.querySelectorAll('[data-widget-id]')].map(card => ({
        id: card.dataset.widgetId,
        open: Boolean(card.querySelector('details')?.open),
        value: card.querySelector('form') ? readForm(card.querySelector('form')) : null,
    }));
    const replaceContent = (html, savedId) => {
        const previous = drafts();
        selects.forEach(choices => choices.destroy());
        selects.clear();
        content.innerHTML = html; // Only server-rendered, escaped CMS forms are inserted.
        content.querySelectorAll('[data-widget-id]').forEach(card => {
            const old = previous.find(item => item.id === card.dataset.widgetId);
            const details = card.querySelector('details');
            if (details) details.open = old?.open ?? true;
            if (!old?.value || String(savedId) === card.dataset.widgetId) return;
            const form = card.querySelector('form');
            if (!form) return;
            form.querySelectorAll('[data-setting]').forEach(input => {
                const value = old.value.settings[input.dataset.setting];
                if (value === undefined) return;
                if (input.type === 'checkbox') input.checked = value; else input.value = value;
            });
            form.querySelectorAll('[data-category-order]').forEach(list => {
                const order = old.value.settings[list.dataset.categoryOrder] || [];
                order.forEach(slug => {
                    const row = [...list.children].find(item => item.dataset.categorySlug === slug);
                    if (row) list.append(row);
                });
            });
            form.querySelectorAll('[data-category-hidden]').forEach(list => {
                list.querySelectorAll('input').forEach(input => { input.checked = (old.value.settings[list.dataset.categoryHidden] || []).includes(input.value); });
            });
            form.querySelector('[data-widget-enabled]').checked = old.value.enabled;
            form.querySelectorAll('[data-widget-page]').forEach(input => {
                if (old.value.visibility[input.dataset.widgetPage] !== undefined) input.checked = old.value.visibility[input.dataset.widgetPage];
            });
        });
        enhanceSelects();
    };
    const request = async payload => {
        if (busy) return false;
        busy = true;
        root.classList.add('widget-busy');
        root.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(root.dataset.saveUrl, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': root.querySelector('[name="needCSRFToken"]').value },
                body: JSON.stringify({ ...payload, revision: Number(root.dataset.revision) }),
            });
            const result = await response.json();
            if (!response.ok || result.status !== 'ok') throw new Error(result.message || root.dataset.error);
            root.dataset.revision = String(result.revision);
            if (payload.action !== 'reorder') replaceContent(result.html, payload.action === 'update' ? payload.id : null);
            notice(result.message);
            return true;
        } catch (error) {
            notice(error instanceof SyntaxError ? root.dataset.error : error.message || root.dataset.error, true);
            return false;
        } finally {
            busy = false;
            root.classList.remove('widget-busy');
            root.removeAttribute('aria-busy');
        }
    };
    const origin = () => [...content.querySelectorAll('[data-widget-area]')].map(zone => ({ zone, children: [...zone.children] }));
    const rollback = previous => previous.forEach(({ zone, children }) => {
        children.forEach(card => {
            zone.append(card);
            const select = card.querySelector('[data-widget-move]');
            if (select) setSelectValue(select, zone.dataset.widgetArea);
        });
    });
    const layout = () => [...content.querySelectorAll('[data-widget-area]')].flatMap(zone => [...zone.children].map(card => ({ id: Number(card.dataset.widgetId), area: zone.dataset.widgetArea })));
    const saveOrder = async previous => {
        if (!await request({ action: 'reorder', layout: layout() })) rollback(previous);
    };
    const canDrop = (card, zone) => {
        const allowed = JSON.parse(card.dataset.allowedAreas);
        return allowed.includes(zone.dataset.widgetArea) || (allowed.length === 0 && card.parentElement === zone);
    };
    const previewDrop = (card, zone, y) => {
        if (!canDrop(card, zone)) { clearDropIndicator(); return; }
        const next = [...zone.children].find(item => item !== card && y < item.getBoundingClientRect().top + item.getBoundingClientRect().height / 2);
        const rootRect = root.getBoundingClientRect();
        const zoneRect = zone.getBoundingClientRect();
        const gap = parseFloat(window.getComputedStyle(zone).rowGap) || 0;
        const last = zone.children[zone.children.length - 1];
        const top = next ? next.getBoundingClientRect().top - (next.previousElementSibling ? gap / 2 : 0)
            : last ? last.getBoundingClientRect().bottom + gap / 2 : zoneRect.top;
        dropIndicator.style.top = `${top - rootRect.top}px`;
        dropIndicator.style.left = `${zoneRect.left - rootRect.left}px`;
        dropIndicator.style.width = `${zoneRect.width}px`;
        dropIndicator.hidden = false;
        dropTarget = { zone, next: next || null };
    };
    const finishDrag = async save => {
        if (!dragging) return;
        const card = dragging;
        card.classList.remove('widget-dragging');
        const previous = dragOrigin;
        const target = dropTarget;
        dragging = null;
        dragOrigin = null;
        touchPointer = null;
        clearDropIndicator();
        content.querySelectorAll('.widget-drop-active').forEach(zone => zone.classList.remove('widget-drop-active'));
        if (save && target && canDrop(card, target.zone)) {
            const before = JSON.stringify(layout());
            target.zone.insertBefore(card, target.next);
            const select = card.querySelector('[data-widget-move]');
            if (select) setSelectValue(select, target.zone.dataset.widgetArea);
            if (JSON.stringify(layout()) !== before) await saveOrder(previous);
        }
    };
    root.addEventListener('submit', event => {
        const form = event.target;
        if (form === deleteForm) {
            event.preventDefault();
            // The shared CMS modal marks the second submission as confirmed.
            if (!busy && form.dataset.deleteConfirmed === '1') {
                request({ action: 'delete', id: Number(form.dataset.widgetDeleteId) });
                const bootstrapApi = typeof bootstrap !== 'undefined' ? bootstrap : window.bootstrap;
                const modalElement = document.querySelector('[data-admin-delete-modal]');
                if (modalElement) bootstrapApi?.Modal.getInstance(modalElement)?.hide();
            }
            return;
        }
        if (!form.matches('[data-widget-add], [data-widget-settings]')) return;
        event.preventDefault();
        if (busy) return;
        if (form.matches('[data-widget-add]') && !form.elements.type.value) {
            form.querySelector('.choices')?.focus();
            return;
        }
        if (!form.reportValidity()) return;
        if (form.matches('[data-widget-add]')) request({ action: 'add', area: form.elements.area.value, type: form.elements.type.value });
        else request({ action: 'update', id: Number(form.closest('[data-widget-id]').dataset.widgetId), ...readForm(form) });
    });
    root.addEventListener('click', event => {
        if (busy) return;
        const button = event.target.closest('button');
        if (!button) return;
        const category = button.closest('[data-category-slug]');
        if (category) {
            if (button.hasAttribute('data-category-up') && category.previousElementSibling) category.parentElement.insertBefore(category, category.previousElementSibling);
            if (button.hasAttribute('data-category-down') && category.nextElementSibling) category.parentElement.insertBefore(category.nextElementSibling, category);
            return;
        }
        const card = button.closest('[data-widget-id]');
        if (!card) return;
        if (button.hasAttribute('data-widget-delete')) {
            if (!deleteForm) return;
            deleteForm.dataset.widgetDeleteId = card.dataset.widgetId;
            deleteForm.dataset.deleteItem = card.querySelector('.widget-instance-title > .fw-semibold')?.textContent.trim() || '';
            deleteForm.requestSubmit();
            return;
        }
        const previous = origin();
        if (button.hasAttribute('data-widget-up') && card.previousElementSibling) {
            card.parentElement.insertBefore(card, card.previousElementSibling); saveOrder(previous);
        }
        if (button.hasAttribute('data-widget-down') && card.nextElementSibling) {
            card.parentElement.insertBefore(card.nextElementSibling, card); saveOrder(previous);
        }
    });
    root.addEventListener('change', event => {
        if (event.target.matches('[data-widget-add] [name="type"]')) syncAddButtons();
        if (!event.target.matches('[data-widget-move]') || busy) return;
        const card = event.target.closest('[data-widget-id]');
        const zone = [...content.querySelectorAll('[data-widget-area]')].find(item => item.dataset.widgetArea === event.target.value);
        if (!zone || !canDrop(card, zone)) return;
        const previous = origin();
        zone.append(card);
        saveOrder(previous);
    });
    root.addEventListener('dragstart', event => {
        if (busy || !event.target.closest('[data-widget-handle]')) { event.preventDefault(); return; }
        dragging = event.target.closest('[data-widget-id]');
        dragOrigin = origin();
        dragging.classList.add('widget-dragging');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', dragging.dataset.widgetId);
    });
    document.addEventListener('dragover', event => {
        if (!dragging) return;
        const zone = event.target.closest('[data-widget-area]');
        if (!zone || !content.contains(zone) || !canDrop(dragging, zone)) { clearDropIndicator(); return; }
        event.preventDefault();
        previewDrop(dragging, zone, event.clientY);
    });
    root.addEventListener('drop', event => {
        if (!dragging) return;
        event.preventDefault();
        finishDrag(!dropIndicator.hidden);
    });
    root.addEventListener('dragend', () => finishDrag(false));
    root.addEventListener('pointerdown', event => {
        const handle = event.target.closest('[data-widget-handle]');
        if (!handle || busy || event.pointerType !== 'touch') return;
        event.preventDefault();
        dragging = handle.closest('[data-widget-id]');
        dragOrigin = origin();
        touchPointer = event.pointerId;
        root.setPointerCapture(event.pointerId);
        dragging.classList.add('widget-dragging');
    });
    root.addEventListener('pointermove', event => {
        if (event.pointerId !== touchPointer || !dragging) return;
        const zone = document.elementFromPoint(event.clientX, event.clientY)?.closest('[data-widget-area]');
        if (zone && content.contains(zone)) previewDrop(dragging, zone, event.clientY);
        else clearDropIndicator();
    });
    root.addEventListener('pointerup', event => { if (event.pointerId === touchPointer) finishDrag(!dropIndicator.hidden); });
    root.addEventListener('pointercancel', event => { if (event.pointerId === touchPointer) finishDrag(false); });
    root.addEventListener('lostpointercapture', event => { if (event.pointerId === touchPointer) finishDrag(false); });
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', enhanceSelects, { once: true });
    else enhanceSelects();
})();
