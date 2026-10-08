(function () {
    'use strict';

    function ready(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, {once: true});
            return;
        }
        callback();
    }

    function setupPlanNodes(container) {
        var list = container.querySelector('[data-vpn-v2-plan-node-list]');
        var template = container.querySelector('[data-vpn-v2-plan-node-template]');
        var addButton = container.querySelector('[data-vpn-v2-node-add]');
        if (!list || !template || !addButton) {
            return;
        }

        var nextIndex = parseInt(container.getAttribute('data-next-index') || '0', 10);
        var autoLabel = container.getAttribute('data-flow-auto-label') || 'Automatic';
        var noneLabel = container.getAttribute('data-flow-none-label') || 'No Flow';

        function rebuildFlow(row) {
            var inboundSelect = row.querySelector('[data-vpn-v2-node-inbound]');
            var flowSelect = row.querySelector('[data-vpn-v2-node-flow]');
            if (!inboundSelect || !flowSelect) {
                return;
            }

            var selected = inboundSelect.options[inboundSelect.selectedIndex];
            var allowed = [];
            if (selected && selected.getAttribute('data-allowed-flows')) {
                try {
                    allowed = JSON.parse(selected.getAttribute('data-allowed-flows'));
                } catch (error) {
                    allowed = [];
                }
            }

            var current = flowSelect.value || '__auto__';
            flowSelect.replaceChildren();
            flowSelect.add(new Option(autoLabel, '__auto__'));
            allowed.forEach(function (flow) {
                if (typeof flow === 'string' && flow !== '') {
                    flowSelect.add(new Option(flow, flow));
                }
            });
            flowSelect.add(new Option(noneLabel, '__none__'));

            var supported = Array.prototype.some.call(flowSelect.options, function (option) {
                return option.value === current;
            });
            flowSelect.value = supported ? current : '__auto__';
        }

        function filterInbounds(row) {
            var serverSelect = row.querySelector('[data-vpn-v2-node-server]');
            var inboundSelect = row.querySelector('[data-vpn-v2-node-inbound]');
            if (!serverSelect || !inboundSelect) {
                return;
            }

            var serverId = serverSelect.value;
            var current = inboundSelect.value;
            Array.prototype.forEach.call(inboundSelect.options, function (option) {
                if (option.value === '') {
                    option.hidden = false;
                    option.disabled = false;
                    return;
                }

                var matches = serverId !== '' && option.getAttribute('data-server-id') === serverId;
                var eligible = option.getAttribute('data-eligible') === '1';
                option.hidden = !matches;
                option.disabled = !matches || !eligible;
            });

            var currentOption = Array.prototype.find.call(inboundSelect.options, function (option) {
                return option.value === current;
            });
            if (!currentOption || currentOption.disabled || currentOption.hidden) {
                inboundSelect.value = '';
            }
            rebuildFlow(row);
        }

        function setupRow(row) {
            if (row.getAttribute('data-vpn-v2-initialized') === '1') {
                return;
            }
            row.setAttribute('data-vpn-v2-initialized', '1');

            var serverSelect = row.querySelector('[data-vpn-v2-node-server]');
            var inboundSelect = row.querySelector('[data-vpn-v2-node-inbound]');
            var removeButton = row.querySelector('[data-vpn-v2-node-remove]');
            if (serverSelect) {
                serverSelect.addEventListener('change', function () {
                    filterInbounds(row);
                });
            }
            if (inboundSelect) {
                inboundSelect.addEventListener('change', function () {
                    rebuildFlow(row);
                });
            }
            if (removeButton) {
                removeButton.addEventListener('click', function () {
                    row.remove();
                });
            }

            filterInbounds(row);
        }

        Array.prototype.forEach.call(list.querySelectorAll('[data-vpn-v2-plan-node-row]'), setupRow);
        addButton.addEventListener('click', function () {
            var html = template.innerHTML.replaceAll('__INDEX__', String(nextIndex++));
            var wrapper = document.createElement('div');
            wrapper.innerHTML = html.trim();
            var row = wrapper.firstElementChild;
            if (!row) {
                return;
            }
            list.appendChild(row);
            setupRow(row);
        });
    }

    function legacyCopy(value) {
        var textarea = document.createElement('textarea');
        textarea.value = value;
        textarea.readOnly = true;
        textarea.setAttribute('aria-hidden', 'true');
        textarea.style.position = 'fixed';
        textarea.style.top = '0';
        textarea.style.left = '0';
        textarea.style.width = '1px';
        textarea.style.height = '1px';
        textarea.style.opacity = '0';
        textarea.style.fontSize = '16px';
        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();
        textarea.setSelectionRange(0, textarea.value.length);
        var copied = false;
        try {
            copied = document.execCommand('copy');
        } catch (error) {
            copied = false;
        } finally {
            textarea.remove();
        }

        return copied;
    }

    function copyText(value) {
        return new Promise(function (resolve, reject) {
            if (legacyCopy(value)) {
                resolve();
                return;
            }
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(value).then(resolve).catch(reject);
                return;
            }
            reject(new Error('copy_failed'));
        });
    }

    function setupProfileCopy() {
        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-vpn-v2-copy-value]');
            if (!button || button.disabled) {
                return;
            }
            event.preventDefault();
            var value = button.getAttribute('data-vpn-v2-copy-value') || '';
            if (!value) {
                return;
            }
            var label = button.querySelector('[data-vpn-v2-copy-label]');
            var status = button.parentElement ? button.parentElement.querySelector('[data-vpn-v2-copy-status]') : null;
            var original = button.getAttribute('data-vpn-v2-copy-original') || (label ? label.textContent : '');
            button.setAttribute('data-vpn-v2-copy-original', original);
            copyText(value).then(function () {
                var message = button.getAttribute('data-vpn-v2-copy-done') || original;
                if (label) {
                    label.textContent = message;
                }
                if (status) {
                    status.textContent = message;
                }
                window.setTimeout(function () {
                    if (label) {
                        label.textContent = original;
                    }
                }, 1800);
            }).catch(function () {
                var message = button.getAttribute('data-vpn-v2-copy-failed') || '';
                if (status) {
                    status.textContent = message;
                }
                var manual = button.parentElement ? button.parentElement.querySelector('[data-vpn-v2-manual-copy]') : null;
                var input = manual ? manual.querySelector('[data-vpn-v2-copy-input]') : null;
                if (manual) {
                    manual.classList.remove('d-none');
                }
                if (input) {
                    input.focus();
                    input.select();
                    input.setSelectionRange(0, input.value.length);
                }
            });
        });
    }

    function operationAlert(form) {
        return form.closest('main, .container, .container-fluid')?.querySelector('[data-vpn-v2-operation-alert]')
            || document.querySelector('[data-vpn-v2-operation-alert]');
    }

    function showOperationStatus(container, type, message, busy) {
        if (!container) {
            return;
        }
        var alert = document.createElement('div');
        alert.className = 'alert alert-' + type + ' rounded-4 d-flex align-items-start gap-2';
        alert.setAttribute('role', 'status');
        if (busy) {
            var spinner = document.createElement('span');
            spinner.className = 'spinner-border spinner-border-sm flex-shrink-0 mt-1';
            spinner.setAttribute('aria-hidden', 'true');
            alert.append(spinner);
        }
        var label = document.createElement('span');
        label.textContent = message;
        alert.append(label);
        container.replaceChildren(alert);
    }

    function operationMessage(container, attribute, fallback) {
        return container ? (container.getAttribute(attribute) || fallback) : fallback;
    }

    function operationIsWaiting(data) {
        return ['pending', 'queued', 'running'].indexOf(String(data.status || '')) !== -1;
    }

    function operationResult(container, data, fromPoll) {
        var status = String(data.status || '');
        var busy = operationIsWaiting(data);
        var message = String((fromPoll ? data.status_label : data.message) || data.status_label || status);
        if (Number(data.total_count) > 0) {
            message += ' · ' + Number(data.processed_count || 0) + ' / ' + Number(data.total_count);
        }
        if (data.last_error && ['failed', 'retry', 'cancelled'].indexOf(status) !== -1
            && message.indexOf(String(data.last_error)) === -1) {
            message += ' — ' + String(data.last_error);
        }
        showOperationStatus(container,
            status === 'completed' ? 'success' : (status === 'failed' ? 'danger'
                : (['completed_partial', 'cancelled', 'retry'].indexOf(status) !== -1 ? 'warning' : 'info')),
            message, busy);
    }

    function localOperationUrl(url) {
        var target = new URL(url, window.location.href);
        if (target.origin !== window.location.origin) throw new Error('operation_failed');
        return target.href;
    }

    async function pollOperation(url, container) {
        url = localOperationUrl(url);
        var failures = 0;
        for (var attempt = 0; attempt < 150; attempt++) {
            await new Promise(function (resolve) { window.setTimeout(resolve, 2000); });
            var controller = new AbortController();
            var timeout = window.setTimeout(function () { controller.abort(); }, 15000);
            var data;
            try {
                var response = await fetch(url, {credentials: 'same-origin', cache: 'no-store',
                    headers: {'Accept': 'application/json'}, signal: controller.signal});
                if (!response.ok) throw new Error('operation_failed');
                data = await response.json();
                if (!data.status) throw new Error('operation_failed');
                failures = 0;
            } catch (error) {
                if (++failures >= 3) break;
                continue;
            } finally {
                window.clearTimeout(timeout);
            }
            operationResult(container, data, true);
            // A delayed retry is not a completed operation and must not spin indefinitely.
            if (!operationIsWaiting(data)) return data;
        }
        throw new Error(operationMessage(container, 'data-vpn-v2-operation-unknown',
            'The result is unknown. Check Operations before starting it again.'));
    }

    async function refreshOperationsTable(container) {
        var table = document.querySelector('[data-vpn-v2-operations-table]');
        if (!table) return;
        var controller = new AbortController();
        var timeout = window.setTimeout(function () { controller.abort(); }, 15000);
        try {
            // Reuse the authenticated CMS view, preserving its pagination, CSRF and action markup.
            var response = await fetch(window.location.href, {credentials: 'same-origin', cache: 'no-store',
                headers: {'Accept': 'text/html'}, signal: controller.signal});
            if (!response.ok || new URL(response.url).pathname !== window.location.pathname) throw new Error('table_refresh_failed');
            var page = new DOMParser().parseFromString(await response.text(), 'text/html');
            var next = page.querySelector('[data-vpn-v2-operations-table]');
            if (!next) throw new Error('table_refresh_failed');
            table.replaceWith(document.importNode(next, true));
        } catch (error) {
            // Keep the operation result even if the separate read-only table refresh fails.
            var notice = document.createElement('div');
            notice.className = 'alert alert-warning rounded-4';
            notice.textContent = operationMessage(container, 'data-vpn-v2-operation-refresh-failed',
                'The result was received, but the table could not be refreshed. Refresh the page manually.');
            if (container) container.append(notice);
        } finally {
            window.clearTimeout(timeout);
        }
    }

    function setupAsyncOperations() {
        var busy = false;
        document.addEventListener('submit', async function (event) {
            var form = event.target.closest('form[data-vpn-v2-async-operation]');
            if (!form) {
                return;
            }
            var usesAdminConfirmation = form.hasAttribute('data-admin-delete-form');
            if (usesAdminConfirmation && form.dataset.deleteConfirmed !== '1') {
                event.preventDefault();
                return;
            }
            event.preventDefault();
            if (busy) return;
            if (usesAdminConfirmation) {
                var modalElement = document.querySelector('[data-admin-delete-modal]');
                var bootstrapApi = typeof bootstrap !== 'undefined' ? bootstrap : (window.bootstrap || null);
                if (modalElement && bootstrapApi && bootstrapApi.Modal) {
                    bootstrapApi.Modal.getOrCreateInstance(modalElement).hide();
                }
            }
            var button = event.submitter || form.querySelector('button[type="submit"]');
            var container = operationAlert(form);
            var body = new FormData(form);
            var controls = Array.from(document.querySelectorAll('form[data-vpn-v2-async-operation] button, [data-vpn-v2-operations-table] button'))
                .map(function (control) { return {element: control, disabled: control.disabled}; });
            var table = document.querySelector('[data-vpn-v2-operations-table]');
            var originalChildren = button ? Array.from(button.childNodes) : [];
            var originalWidth = button ? button.style.minWidth : '';
            var loading = operationMessage(container, 'data-vpn-v2-operation-loading', 'Processing…');
            busy = true;
            controls.forEach(function (control) { control.element.disabled = true; });
            if (container) container.setAttribute('aria-busy', 'true');
            if (table) table.setAttribute('aria-busy', 'true');
            if (button) {
                button.style.minWidth = button.getBoundingClientRect().width + 'px';
                button.setAttribute('aria-busy', 'true');
                var spinner = document.createElement('span');
                spinner.className = 'spinner-border spinner-border-sm me-2';
                spinner.setAttribute('aria-hidden', 'true');
                var label = document.createElement('span');
                label.textContent = loading;
                button.replaceChildren(spinner, label);
            }
            showOperationStatus(container, 'info', loading, true);
            try {
                var response = await fetch(localOperationUrl(form.action), {method: 'POST', body: body,
                    credentials: 'same-origin', headers: {'Accept': 'application/json'}});
                var data;
                try { data = await response.json(); } catch (error) { throw new Error(response.ok ? 'operation_unknown' : 'operation_failed'); }
                if (!response.ok) throw new Error(data.error || 'operation_failed');
                if (!data.status) throw new Error('operation_unknown');
                operationResult(container, data, false);
                if (data.progress_url && operationIsWaiting(data)) await pollOperation(data.progress_url, container);
            } catch (error) {
                var fallback = operationMessage(
                    container,
                    'data-vpn-v2-operation-failed',
                    'The operation could not be completed.'
                );
                var message = error.message || fallback;
                if (message === 'Failed to fetch' || message === 'operation_failed') {
                    message = message === 'operation_failed' ? fallback : operationMessage(container,
                        'data-vpn-v2-operation-unknown', 'The result is unknown. Check Operations before starting it again.');
                }
                if (message === 'operation_unknown') message = operationMessage(container,
                    'data-vpn-v2-operation-unknown', 'The result is unknown. Check Operations before starting it again.');
                showOperationStatus(container, 'danger', message);
            } finally {
                await refreshOperationsTable(container);
                controls.forEach(function (control) { control.element.disabled = control.disabled; });
                if (button) {
                    button.replaceChildren.apply(button, originalChildren);
                    button.style.minWidth = originalWidth;
                    button.removeAttribute('aria-busy');
                }
                if (container) container.removeAttribute('aria-busy');
                if (table) table.removeAttribute('aria-busy');
                busy = false;
            }
        });
    }

    function setupConnectionOrder(container) {
        var list = container.querySelector('[data-vpn-v2-connection-order-list]');
        if (!list) {
            return;
        }
        var dragged = null;

        function updateButtons() {
            var items = list.querySelectorAll('[data-vpn-v2-connection-order-item]');
            Array.prototype.forEach.call(items, function (item, index) {
                var up = item.querySelector('[data-vpn-v2-order-move="up"]');
                var down = item.querySelector('[data-vpn-v2-order-move="down"]');
                if (up) {
                    up.disabled = index === 0;
                }
                if (down) {
                    down.disabled = index === items.length - 1;
                }
            });
        }

        list.addEventListener('click', function (event) {
            var button = event.target.closest('[data-vpn-v2-order-move]');
            var item = button ? button.closest('[data-vpn-v2-connection-order-item]') : null;
            if (!button || !item) {
                return;
            }
            var direction = button.getAttribute('data-vpn-v2-order-move');
            if (direction === 'up' && item.previousElementSibling) {
                list.insertBefore(item, item.previousElementSibling);
            } else if (direction === 'down' && item.nextElementSibling) {
                list.insertBefore(item.nextElementSibling, item);
            }
            updateButtons();
        });

        list.addEventListener('dragstart', function (event) {
            dragged = event.target.closest('[data-vpn-v2-connection-order-item]');
            if (!dragged) {
                return;
            }
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', 'vpn-v2-connection');
            dragged.classList.add('opacity-50');
        });
        list.addEventListener('dragover', function (event) {
            var target = event.target.closest('[data-vpn-v2-connection-order-item]');
            if (!dragged || !target || target === dragged) {
                return;
            }
            event.preventDefault();
            var bounds = target.getBoundingClientRect();
            list.insertBefore(dragged, event.clientY < bounds.top + bounds.height / 2 ? target : target.nextElementSibling);
            updateButtons();
        });
        list.addEventListener('dragend', function () {
            if (dragged) {
                dragged.classList.remove('opacity-50');
            }
            dragged = null;
            updateButtons();
        });

        updateButtons();
    }

    function formatMetricBytes(value, units) {
        if (value === null || value === undefined || value === '') {
            return '—';
        }
        var bytes = Number(value);
        if (!Number.isFinite(bytes) || bytes < 0) {
            return '—';
        }
        var localized = Array.isArray(units);
        units = units || ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        var index = 0;
        while (bytes >= 1024 && index < units.length - 1) {
            bytes /= 1024;
            index++;
        }
        var precision = index === 0 || bytes >= 100 ? 0 : (bytes >= 10 ? 1 : 2);

        var formatted = bytes.toFixed(localized ? (index === 0 ? 0 : 2) : precision);
        if (localized) formatted = formatted.replace(/(\.[0-9]*?)0+$/, '$1').replace(/\.$/, '');
        return formatted + ' ' + units[index];
    }

    function formatUptime(value, labels) {
        if (value === null || value === undefined || value === '') {
            return '—';
        }
        var seconds = Math.max(0, Number(value));
        if (!Number.isFinite(seconds)) {
            return '—';
        }
        var days = Math.floor(seconds / 86400);
        var hours = Math.floor((seconds % 86400) / 3600);
        var minutes = Math.floor((seconds % 3600) / 60);

        return (days > 0 ? days + ' ' + labels.days + ' ' : '')
            + hours + ' ' + labels.hours + ' ' + minutes + ' ' + labels.minutes;
    }

    function setupServerMetrics(container) {
        var cards = Array.prototype.slice.call(container.querySelectorAll('[data-vpn-v2-server-metric-card]'));
        var refresh = container.querySelector('[data-vpn-v2-refresh-metrics]');
        var loadingLabel = container.getAttribute('data-loading-label') || 'Loading…';
        var errorLabel = container.getAttribute('data-error-label') || 'Metrics unavailable';
        var disabledLabel = container.getAttribute('data-disabled-label') || 'Server disabled';
        var uptimeLabels = {
            days: container.getAttribute('data-days-label') || 'd',
            hours: container.getAttribute('data-hours-label') || 'h',
            minutes: container.getAttribute('data-minutes-label') || 'm'
        };
        var coresLabel = container.getAttribute('data-cores-label') || 'cores';

        function status(card, value) {
            var badge = card.querySelector('[data-vpn-v2-server-status]');
            if (!badge) return;
            var classes = {online: 'text-bg-success', offline: 'text-bg-danger', error: 'text-bg-warning', disabled: 'text-bg-secondary', unchecked: 'text-bg-light border text-body-secondary'};
            var labelKey = value === 'error' ? 'data-error-status-label' : value === 'disabled' ? 'data-disabled-status-label' : 'data-' + value + '-label';
            badge.className = 'badge rounded-pill ' + classes[value];
            badge.textContent = container.getAttribute(labelKey) || value;
        }

        function clear(card) {
            card.querySelectorAll('[data-vpn-v2-metric]').forEach(function (target) {
                if (/-bar$/.test(target.getAttribute('data-vpn-v2-metric') || '')) {
                    target.style.width = '0%';
                    target.className = 'progress-bar';
                    target.parentElement.setAttribute('aria-valuenow', '0');
                } else {
                    target.textContent = '—';
                }
            });
        }

        function node(card, name) {
            return card.querySelector('[data-vpn-v2-metric="' + name + '"]');
        }

        function text(card, name, value) {
            var target = node(card, name);
            if (target) {
                target.textContent = value;
            }
        }

        function usage(card, key, data) {
            data = data || {};
            var percent = Number(data.percent);
            var valid = data.percent !== null && data.percent !== undefined && Number.isFinite(percent);
            var shown = valid ? Math.max(0, Math.min(100, percent)) : 0;
            text(card, key + '-value', valid ? shown.toFixed(1).replace('.0', '') + '%' : '—');
            text(card, key + '-details', data.current != null && data.total != null
                ? formatMetricBytes(data.current) + ' / ' + formatMetricBytes(data.total)
                : '—');
            var bar = node(card, key + '-bar');
            if (bar) {
                bar.style.width = shown + '%';
                bar.className = 'progress-bar' + (shown >= 90 ? ' bg-danger' : (shown >= 75 ? ' bg-warning' : ''));
                bar.parentElement.setAttribute('aria-valuenow', String(Math.round(shown)));
            }
        }

        function render(card, data) {
            var cpu = data.cpu || {};
            usage(card, 'cpu', {percent: cpu.percent, current: null, total: null});
            var cpuDetails = [];
            if (cpu.cores !== null && cpu.cores !== undefined) {
                cpuDetails.push(String(cpu.cores) + ' ' + coresLabel);
            }
            if (cpu.speed_mhz !== null && cpu.speed_mhz !== undefined) {
                cpuDetails.push(String(cpu.speed_mhz) + ' MHz');
            }
            text(card, 'cpu-details', cpuDetails.length ? cpuDetails.join(' · ') : '—');
            usage(card, 'memory', data.memory);
            usage(card, 'swap', data.swap);
            usage(card, 'disk', data.disk);

            var load = data.load || {};
            var loadValues = [load.one, load.five, load.fifteen].map(function (value) {
                return value === null || value === undefined ? '—' : Number(value).toFixed(2);
            });
            text(card, 'load', loadValues.join(' / '));
            text(card, 'uptime', formatUptime(data.uptime_seconds, uptimeLabels));

            var network = data.network || {};
            var sent = network.up !== null && network.up !== undefined ? network.up : network.sent;
            var received = network.down !== null && network.down !== undefined ? network.down : network.received;
            text(card, 'network', '↑ ' + formatMetricBytes(sent) + ' · ↓ ' + formatMetricBytes(received));
            var connections = data.connections || {};
            text(card, 'connections', (connections.tcp === null || connections.tcp === undefined ? '—' : connections.tcp)
                + ' / ' + (connections.udp === null || connections.udp === undefined ? '—' : connections.udp));
            var xray = data.xray || {};
            text(card, 'xray', (xray.state || 'unknown') + (xray.version ? ' · ' + xray.version : ''));

            var state = card.querySelector('[data-vpn-v2-metric-state]');
            if (state) {
                state.className = 'small text-success mb-3';
                state.textContent = data.checked_at || '';
            }
        }

        function load(card) {
            var state = card.querySelector('[data-vpn-v2-metric-state]');
            clear(card);
            if (card.getAttribute('data-enabled') !== '1') {
                status(card, 'disabled');
                if (state) {
                    state.textContent = disabledLabel;
                }
                return Promise.resolve();
            }
            status(card, 'unchecked');
            if (state) {
                state.className = 'small text-body-secondary mb-3';
                state.textContent = loadingLabel;
            }

            var controller = new AbortController();
            var timeout = window.setTimeout(function () { controller.abort(); }, 20000);

            return fetch(card.getAttribute('data-url') || '', {
                method: 'GET',
                credentials: 'same-origin',
                signal: controller.signal,
                headers: {'Accept': 'application/json'}
            }).then(function (response) {
                return response.json().catch(function () {
                    var invalid = new Error(errorLabel);
                    invalid.serverStatus = response.status >= 502 && response.status <= 504 ? 'offline' : 'error';
                    throw invalid;
                }).then(function (data) {
                    if (!response.ok) {
                        var failure = new Error(data && data.error || errorLabel);
                        failure.serverStatus = response.status === 502 || response.status === 503 || response.status === 504 ? 'offline' : 'error';
                        throw failure;
                    }
                    return data;
                });
            }).then(function (data) {
                if (!data || typeof data !== 'object' || !data.cpu || !data.memory || data.error) {
                    var invalid = new Error(errorLabel);
                    invalid.serverStatus = 'error';
                    throw invalid;
                }
                render(card, data);
                status(card, 'online');
            }).catch(function (error) {
                clear(card);
                status(card, error.serverStatus || 'offline');
                if (state) {
                    state.className = 'small text-danger mb-3';
                    state.textContent = error.serverStatus ? error.message || errorLabel : errorLabel;
                }
            }).finally(function () { window.clearTimeout(timeout); });
        }

        function loadAll() {
            if (refresh && refresh.disabled) return;
            if (refresh) {
                refresh.disabled = true;
            }
            Promise.all(cards.map(load)).finally(function () {
                if (refresh) {
                    refresh.disabled = false;
                }
            });
        }

        if (refresh) {
            refresh.addEventListener('click', loadAll);
        }
        loadAll();
    }

    function setupServerRecovery(container) {
        var busy = false;
        var progress = container.querySelector('[data-vpn-recovery-progress]');
        var csrf = container.querySelector('input[type="hidden"]');
        var resumeForms = container.querySelectorAll('[data-vpn-recovery-resume]');

        async function json(url, options) {
            var response = await fetch(url, Object.assign({credentials: 'same-origin', headers: {'Accept': 'application/json'}}, options));
            var data = await response.json();
            if (!response.ok) throw new Error(data.error || container.dataset.failed);
            return data;
        }

        async function run(ids, retry) {
            var succeeded = 0;
            var failures = [];
            var remaining = [];
            var failureMessage = '';
            for (var index = 0; index < ids.length; index++) {
                progress.textContent = (index + 1) + ' / ' + ids.length;
                try {
                    var body = new FormData();
                    if (csrf) body.append(csrf.name, csrf.value);
                    body.append('operation_id', ids[index]);
                    body.append('retry', retry ? '1' : '0');
                    var data = await json(container.dataset.processUrl, {method: 'POST', body: body});
                    // Another worker may already own this subscription; wait for its persisted result.
                    for (var poll = 0; data.status === 'running' && poll < 150; poll++) {
                        await new Promise(function (resolve) { window.setTimeout(resolve, 2000); });
                        data = await json(container.dataset.progressBase + ids[index]);
                    }
                    container.querySelectorAll('[data-vpn-recovery-status="' + Number(data.subscription_id) + '"]').forEach(function (row) {
                        row.textContent = data.status_label || data.status;
                    });
                    container.querySelectorAll('[data-vpn-recovery-error="' + Number(data.subscription_id) + '"]').forEach(function (row) {
                        row.textContent = data.last_error || '';
                    });
                    if (data.status === 'completed') succeeded++;
                    else if (data.status === 'pending' || data.status === 'running') remaining.push(ids[index]);
                    else failures.push(ids[index]);
                } catch (error) {
                    failures.push(ids[index]);
                    failureMessage = error.message || container.dataset.failed;
                }
            }
            progress.textContent = container.dataset.completed.replace('%d', succeeded).replace('%d', ids.length - succeeded);
            if (failureMessage) progress.textContent += ' ' + failureMessage;
            resumeForms.forEach(function (form) {
                var nextIds = form.dataset.retry === '1' ? failures : remaining;
                form.dataset.operationIds = JSON.stringify(nextIds);
                form.hidden = nextIds.length === 0;
                form.querySelector('button').disabled = nextIds.length === 0;
            });
        }

        async function start(form, apply) {
            if (busy) return;
            busy = true;
            var button = form.querySelector('button[type="submit"]');
            button.disabled = true;
            try {
                var ids;
                if (apply) {
                    var data = await json(form.action, {method: 'POST', body: new FormData(form)});
                    ids = data.operation_ids || [];
                    progress.textContent = data.message || '';
                } else ids = JSON.parse(form.dataset.operationIds || '[]');
                await run(ids, !apply && form.dataset.retry === '1');
            } catch (error) {
                progress.textContent = error.message || container.dataset.failed;
            } finally {
                busy = false;
                if (apply) button.disabled = false;
                else button.disabled = JSON.parse(form.dataset.operationIds || '[]').length === 0;
            }
        }

        container.querySelector('[data-vpn-recovery-apply]').addEventListener('submit', function (event) {
            event.preventDefault();
            start(event.currentTarget, true);
        });
        resumeForms.forEach(function (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                start(form, false);
            });
        });
    }

    function setupClientInspection(button) {
        var card = button.closest('[data-vpn-v2-client-card]');
        var section = button.closest('[data-vpn-v2-client-information]');
        var result = card.querySelector('[data-vpn-v2-client-result]');
        var fields = card.querySelector('[data-vpn-v2-client-live]');
        var label = button.querySelector('[data-vpn-v2-client-button-label]');
        var originalLabel = label.textContent;
        button.vpnV2Inspect = async function (batchRequest) {
            if (button.disabled || (section.vpnV2TrafficBusy && batchRequest !== true)) return null;
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            label.textContent = button.dataset.loading;
            result.classList.remove('text-danger');
            result.textContent = button.dataset.loading;
            fields.hidden = true;
            fields.replaceChildren();
            var controller = new AbortController();
            var timeout = window.setTimeout(function () { controller.abort(); }, 35000);
            try {
                var response = await fetch(button.dataset.vpnV2ClientInspect, {
                    credentials: 'same-origin', cache: 'no-store', signal: controller.signal,
                    headers: {'Accept': 'application/json'}
                });
                var data = await response.json();
                if (!response.ok || !Array.isArray(data.fields) || typeof data.checked_at !== 'string'
                    || !/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(data.checked_at)
                    || Number(data.subscription_id) !== Number(section.dataset.subscriptionId)
                    || Number(data.connection_id) !== Number(button.dataset.connectionId)
                    || Number(data.server_id) !== Number(button.dataset.serverId)
                    || !data.traffic || !['upload', 'download', 'total'].every(function (field) {
                        return Number.isSafeInteger(data.traffic[field]) && data.traffic[field] >= 0;
                    })) {
                    throw new Error(button.dataset.failed);
                }
                data.fields.forEach(function (field) {
                    if (typeof field.label !== 'string' || typeof field.value !== 'string') throw new Error(button.dataset.failed);
                    var term = document.createElement('dt');
                    term.className = 'col-sm-5 text-body-secondary small';
                    term.textContent = field.label;
                    var value = document.createElement('dd');
                    value.className = 'col-sm-7 mb-0 text-break small';
                    value.textContent = field.value;
                    fields.append(term, value);
                });
                fields.hidden = false;
                result.textContent = button.dataset.liveLabel + ' · ' + data.checked_at;
                if (section.vpnV2RecordTraffic) section.vpnV2RecordTraffic(button, data);
                return data;
            } catch (error) {
                fields.hidden = true;
                fields.replaceChildren();
                result.classList.add('text-danger');
                result.textContent = button.dataset.failed;
                if (section.vpnV2RecordTraffic) section.vpnV2RecordTraffic(button, null);
                return null;
            } finally {
                window.clearTimeout(timeout);
                button.disabled = false;
                button.removeAttribute('aria-busy');
                label.textContent = originalLabel;
            }
        };
        button.addEventListener('click', function () { button.vpnV2Inspect(false); });
    }

    function setupServerTraffic(button) {
        var section = button.closest('[data-vpn-v2-client-information]');
        var table = section.querySelector('[data-vpn-v2-server-traffic]');
        var units = JSON.parse(table.dataset.units);
        var result = table.querySelector('[data-vpn-v2-server-traffic-result]');
        var allClients = Array.from(section.querySelectorAll('[data-vpn-v2-client-inspect]'));
        var clients = allClients.filter(function (client) { return !client.disabled; });
        var snapshots = JSON.parse(table.dataset.snapshot);
        var readings = {}, failures = {};
        var addBytes = function (total, value) { return Math.min(Number.MAX_SAFE_INTEGER, total + Math.max(0, Number(value))); };

        function renderTraffic() {
            var used = 0, known = false, complete = true, partial = false, failed = false;
            var checkedAt = null, liveCount = 0;
            snapshots.forEach(function (server) {
                var serverClients = allClients.filter(function (client) { return Number(client.dataset.serverId) === server.id; });
                var responses = serverClients.map(function (client) { return readings[client.dataset.connectionId]; }).filter(Boolean);
                var serverFailed = serverClients.some(function (client) { return failures[client.dataset.connectionId]; });
                if (responses.length === server.connections && !serverFailed) {
                    server.used = server.upload = server.download = 0;
                    server.checked_at = null;
                    responses.forEach(function (data) {
                        server.used = addBytes(server.used, data.traffic.total);
                        server.upload = addBytes(server.upload, data.traffic.upload);
                        server.download = addBytes(server.download, data.traffic.download);
                        if (!server.checked_at || data.checked_at < server.checked_at) server.checked_at = data.checked_at;
                    });
                    server.known = server.sample_known = server.live = true;
                    server.partial = server.failed = false;
                    server.missing = 0;
                } else if (responses.length || serverFailed) {
                    // Keep the last full server snapshot. A reachable subtotal
                    // must not replace it or be presented as the complete total.
                    server.partial = true;
                    server.failed = server.failed || serverFailed;
                }
                used = addBytes(used, server.used);
                known = known || server.known;
                complete = complete && server.known && server.missing === 0;
                partial = partial || server.partial;
                failed = failed || server.failed;
                if (server.live) liveCount++;
                if (server.checked_at && (!checkedAt || server.checked_at < checkedAt)) checkedAt = server.checked_at;
                table.querySelectorAll('[data-vpn-v2-traffic-value][data-server-id="' + server.id + '"]').forEach(function (value) {
                    var field = value.dataset.vpnV2TrafficValue;
                    value.textContent = (field === 'used' ? server.known : server.sample_known)
                        ? formatMetricBytes(server[field], units) : '—';
                });
                table.querySelectorAll('[data-vpn-v2-traffic-state][data-server-id="' + server.id + '"]').forEach(function (state) {
                    var source = server.live ? table.dataset.liveLabel : table.dataset.savedLabel;
                    state.textContent = source + (server.checked_at ? ' · ' + server.checked_at : '')
                        + (server.failed ? ' · ' + table.dataset.failedLabel : server.partial ? ' · ' + table.dataset.partialLabel : '');
                    state.classList.toggle('text-warning', server.partial);
                });
            });
            var limit = Number(section.dataset.trafficLimit);
            section.querySelector('[data-vpn-v2-usage-value="used"]').textContent = known ? formatMetricBytes(used, units) : '—';
            section.querySelector('[data-vpn-v2-usage-value="remaining"]').textContent = limit <= 0
                ? section.dataset.unlimitedLabel : complete ? formatMetricBytes(Math.max(0, limit - used), units) : '—';
            var progress = section.querySelector('[data-vpn-v2-usage-progress]');
            var percent = limit > 0 && complete ? Math.min(100, Math.round(used / limit * 1000) / 10) : null;
            progress.hidden = percent === null;
            progress.classList.toggle('d-none', percent === null);
            progress.setAttribute('aria-valuenow', percent === null ? '0' : String(percent));
            var bar = progress.querySelector('.progress-bar');
            bar.style.width = (percent || 0) + '%';
            bar.classList.toggle('bg-danger', percent >= 100);
            bar.classList.toggle('bg-warning', percent >= 80 && percent < 100);
            bar.classList.toggle('bg-primary', percent < 80);
            var source = liveCount === snapshots.length ? table.dataset.liveLabel
                : liveCount > 0 ? table.dataset.mixedLabel : table.dataset.savedLabel;
            section.querySelector('[data-vpn-v2-usage-checked]').textContent = checkedAt
                ? source + ' · ' + checkedAt : section.dataset.notCheckedLabel;
            var warning = section.querySelector('[data-vpn-v2-usage-warning]');
            warning.hidden = !partial;
            warning.textContent = failed ? section.dataset.failedTrafficLabel : section.dataset.partialTrafficLabel;
            result.classList.toggle('text-warning', partial);
            result.textContent = partial ? table.dataset.partialLabel : source;
        }

        section.vpnV2RecordTraffic = function (client, data) {
            var id = client.dataset.connectionId;
            if (data) { readings[id] = data; delete failures[id]; }
            else { delete readings[id]; failures[id] = true; }
            if (!section.vpnV2TrafficBusy) renderTraffic();
        };
        if (!clients.length) { button.disabled = true; return; }
        var originalChildren = Array.from(button.childNodes);
        button.addEventListener('click', async function () {
            if (button.disabled) return;
            // An individual inspection may still be running. Do not launch a
            // duplicate batch or mistake its disabled button for missing data.
            if (clients.some(function (client) { return client.disabled; })) return;
            button.disabled = true;
            section.vpnV2TrafficBusy = true;
            button.setAttribute('aria-busy', 'true');
            var spinner = document.createElement('span');
            spinner.className = 'spinner-border spinner-border-sm';
            spinner.setAttribute('aria-hidden', 'true');
            var label = document.createElement('span');
            label.textContent = button.dataset.loading;
            button.replaceChildren(spinner, label);
            result.textContent = button.dataset.loading;
            result.classList.remove('text-danger', 'text-warning');
            readings = {}; failures = {};
            try {
                // At most two read-only panel inspections at once; never execute a VPN queue here.
                for (var index = 0; index < clients.length; index += 2) {
                    await Promise.all(clients.slice(index, index + 2).map(async function (client) {
                        await client.vpnV2Inspect(true);
                    }));
                }
                renderTraffic();
            } catch (error) {
                result.classList.add('text-danger');
                result.textContent = table.dataset.failedLabel;
            } finally {
                section.vpnV2TrafficBusy = false;
                button.replaceChildren.apply(button, originalChildren);
                button.disabled = false;
                button.removeAttribute('aria-busy');
            }
        });
    }

    ready(function () {
        document.querySelectorAll('[data-vpn-v2-plan-nodes]').forEach(setupPlanNodes);
        document.querySelectorAll('[data-vpn-v2-connection-order]').forEach(setupConnectionOrder);
        setupProfileCopy();
        setupAsyncOperations();
        document.querySelectorAll('[data-vpn-recovery]').forEach(setupServerRecovery);
        document.querySelectorAll('[data-vpn-v2-server-metrics]').forEach(setupServerMetrics);
        document.querySelectorAll('[data-vpn-v2-client-inspect]').forEach(setupClientInspection);
        document.querySelectorAll('[data-vpn-v2-client-traffic-refresh]').forEach(setupServerTraffic);
    });
}());
