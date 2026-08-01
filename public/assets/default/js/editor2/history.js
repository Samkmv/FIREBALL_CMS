(function (window) {
    'use strict';

    const api = window.FireballEditor2 = window.FireballEditor2 || {};

    function clone(value) {
        if (typeof structuredClone === 'function') {
            return structuredClone(value);
        }
        return JSON.parse(JSON.stringify(value));
    }

    class EditorHistory {
        constructor(initialState, options) {
            const settings = options || {};
            this.limit = Math.max(10, Number(settings.limit || 100));
            this.coalesceMs = Math.max(0, Number(settings.coalesceMs || 650));
            this.entries = [{ state: clone(initialState), label: 'initial', time: Date.now() }];
            this.index = 0;
        }

        push(state, label, force) {
            const now = Date.now();
            const entry = this.entries[this.index];
            const shouldCoalesce = !force
                && entry
                && entry.label === label
                && now - entry.time <= this.coalesceMs;

            if (shouldCoalesce) {
                this.entries[this.index] = { state: clone(state), label: label, time: now };
                return;
            }

            this.entries = this.entries.slice(0, this.index + 1);
            this.entries.push({ state: clone(state), label: label || 'change', time: now });
            if (this.entries.length > this.limit) {
                this.entries.shift();
            }
            this.index = this.entries.length - 1;
        }

        canUndo() {
            return this.index > 0;
        }

        canRedo() {
            return this.index < this.entries.length - 1;
        }

        undo() {
            if (!this.canUndo()) {
                return null;
            }
            this.index -= 1;
            return clone(this.entries[this.index].state);
        }

        redo() {
            if (!this.canRedo()) {
                return null;
            }
            this.index += 1;
            return clone(this.entries[this.index].state);
        }

        replaceCurrent(state) {
            const current = this.entries[this.index] || {};
            this.entries[this.index] = {
                state: clone(state),
                label: current.label || 'change',
                time: Date.now()
            };
        }
    }

    api.EditorHistory = EditorHistory;
    api.clone = clone;
})(window);
