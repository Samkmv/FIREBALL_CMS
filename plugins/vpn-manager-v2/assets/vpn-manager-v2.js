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

    function showOperationStatus(container, type, message) {
        if (!container) {
            return;
        }
        var alert = document.createElement('div');
        alert.className = 'alert alert-' + type + ' rounded-4';
        alert.setAttribute('role', 'status');
        alert.textContent = message;
        container.replaceChildren(alert);
    }

    function operationMessage(container, attribute, fallback) {
        return container ? (container.getAttribute(attribute) || fallback) : fallback;
    }

    function pollOperation(url, container, attempts) {
        if (!url || attempts <= 0) {
            if (url && attempts <= 0) {
                showOperationStatus(
                    container,
                    'danger',
                    operationMessage(container, 'data-vpn-v2-operation-status-failed', 'Operation status is unavailable.')
                );
            }
            return;
        }
        window.setTimeout(function () {
            fetch(url, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {'Accept': 'application/json'}
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error(operationMessage(
                        container,
                        'data-vpn-v2-operation-status-failed',
                        'Operation status is unavailable.'
                    ));
                }
                return response.json();
            }).then(function (data) {
                var status = String(data.status || 'pending');
                var statusLabel = String(data.status_label || status);
                var progress = Number(data.processed_count || 0) + ' / ' + Number(data.total_count || 0);
                showOperationStatus(
                    container,
                    status === 'completed' ? 'success'
                        : (status === 'completed_partial' ? 'warning' : (status === 'failed' ? 'danger' : 'info')),
                    statusLabel + ' · ' + progress
                );
                if (['completed', 'completed_partial', 'failed', 'cancelled'].indexOf(status) === -1) {
                    pollOperation(url, container, attempts - 1);
                }
            }).catch(function (error) {
                if (attempts <= 1) {
                    showOperationStatus(
                        container,
                        'danger',
                        error.message || operationMessage(
                            container,
                            'data-vpn-v2-operation-status-failed',
                            'Operation status is unavailable.'
                        )
                    );
                    return;
                }
                pollOperation(url, container, attempts - 1);
            });
        }, 2000);
    }

    function setupAsyncOperations() {
        document.addEventListener('submit', function (event) {
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
            if (usesAdminConfirmation) {
                var modalElement = document.querySelector('[data-admin-delete-modal]');
                var bootstrapApi = typeof bootstrap !== 'undefined' ? bootstrap : (window.bootstrap || null);
                if (modalElement && bootstrapApi && bootstrapApi.Modal) {
                    bootstrapApi.Modal.getOrCreateInstance(modalElement).hide();
                }
            }
            var button = form.querySelector('button[type="submit"]');
            var container = operationAlert(form);
            if (button) {
                button.disabled = true;
            }
            fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin',
                headers: {'Accept': 'application/json'}
            }).then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok) {
                        throw new Error(data.error || operationMessage(
                            container,
                            'data-vpn-v2-operation-failed',
                            'The operation could not be completed.'
                        ));
                    }
                    return data;
                });
            }).then(function (data) {
                showOperationStatus(container, 'info', data.message || data.status || 'queued');
                if (data.progress_url) {
                    pollOperation(data.progress_url, container, 150);
                }
            }).catch(function (error) {
                var fallback = operationMessage(
                    container,
                    'data-vpn-v2-operation-failed',
                    'The operation could not be completed.'
                );
                var message = error.message || fallback;
                if (message === 'Failed to fetch' || message === 'operation_failed') {
                    message = fallback;
                }
                showOperationStatus(container, 'danger', message);
            }).finally(function () {
                if (button) {
                    button.disabled = false;
                }
            });
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

    function formatMetricBytes(value) {
        if (value === null || value === undefined || value === '') {
            return '—';
        }
        var bytes = Number(value);
        if (!Number.isFinite(bytes) || bytes < 0) {
            return '—';
        }
        var units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        var index = 0;
        while (bytes >= 1024 && index < units.length - 1) {
            bytes /= 1024;
            index++;
        }
        var precision = index === 0 || bytes >= 100 ? 0 : (bytes >= 10 ? 1 : 2);

        return bytes.toFixed(precision) + ' ' + units[index];
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
            var valid = Number.isFinite(percent);
            var shown = valid ? Math.max(0, Math.min(100, percent)) : 0;
            text(card, key + '-value', valid ? shown.toFixed(1).replace('.0', '') + '%' : '—');
            text(card, key + '-details', data.current !== null && data.total !== null
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
            if (card.getAttribute('data-enabled') !== '1') {
                if (state) {
                    state.textContent = disabledLabel;
                }
                return Promise.resolve();
            }
            if (state) {
                state.className = 'small text-body-secondary mb-3';
                state.textContent = loadingLabel;
            }

            return fetch(card.getAttribute('data-url') || '', {
                method: 'GET',
                credentials: 'same-origin',
                headers: {'Accept': 'application/json'}
            }).then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok) {
                        throw new Error(data.error || errorLabel);
                    }
                    return data;
                });
            }).then(function (data) {
                render(card, data);
            }).catch(function (error) {
                if (state) {
                    state.className = 'small text-danger mb-3';
                    state.textContent = error.message || errorLabel;
                }
            });
        }

        function loadAll() {
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

    ready(function () {
        document.querySelectorAll('[data-vpn-v2-plan-nodes]').forEach(setupPlanNodes);
        document.querySelectorAll('[data-vpn-v2-connection-order]').forEach(setupConnectionOrder);
        setupProfileCopy();
        setupAsyncOperations();
        document.querySelectorAll('[data-vpn-v2-server-metrics]').forEach(setupServerMetrics);
    });
}());
