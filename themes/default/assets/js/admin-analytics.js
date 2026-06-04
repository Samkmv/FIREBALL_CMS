(function () {
    const root = document.querySelector('[data-admin-analytics]');
    if (!root) {
        return;
    }

    let payload = {};
    try {
        payload = JSON.parse(root.getAttribute('data-admin-analytics-payload') || '{}');
    } catch (error) {
        payload = {};
    }

    let i18n = {};
    try {
        i18n = JSON.parse(root.getAttribute('data-admin-analytics-i18n') || '{}');
    } catch (error) {
        i18n = {};
    }

    const charts = {};
    let activeRange = '7';

    function cssVar(name, fallback) {
        const value = getComputedStyle(root).getPropertyValue(name).trim();
        return value || fallback;
    }

    function palette() {
        return {
            line: cssVar('--chart-line', '#2563eb'),
            grid: cssVar('--chart-grid', 'rgba(108, 114, 127, .22)'),
            text: cssVar('--chart-text', '#4e5562'),
            tooltipBg: cssVar('--chart-tooltip-bg', '#ffffff'),
            tooltipText: cssVar('--chart-tooltip-text', '#181d25'),
            series: [
                cssVar('--chart-primary', '#2563eb'),
                cssVar('--chart-secondary', '#0f766e'),
                cssVar('--chart-accent-1', '#f59e0b'),
                cssVar('--chart-accent-2', '#dc3545'),
                cssVar('--chart-accent-3', '#6f42c1'),
                cssVar('--chart-accent-4', '#20c997')
            ]
        };
    }

    function seriesRows(rows) {
        rows = Array.isArray(rows) ? rows : [];
        return {
            labels: rows.map((row) => String(row.label || i18n.unknown || 'Not determined')),
            values: rows.map((row) => Number(row.total || 0))
        };
    }

    function renderTraffic(range) {
        activeRange = String(range || '7');
        const target = root.querySelector('[data-analytics-chart="traffic"]');
        const data = payload.traffic && payload.traffic[activeRange] ? payload.traffic[activeRange] : { labels: [], values: [] };
        renderChart('traffic', target, {
            type: 'line',
            labels: data.labels || [],
            values: data.values || [],
            label: i18n.visits || 'Visits'
        });
    }

    function renderSources() {
        const target = root.querySelector('[data-analytics-chart="sources"]');
        const data = seriesRows(payload.sources || []);
        renderChart('sources', target, {
            type: 'donut',
            labels: data.labels,
            values: data.values,
            label: i18n.sources || 'Sources'
        });
    }

    function renderDevices() {
        const target = root.querySelector('[data-analytics-chart="devices"]');
        const data = seriesRows(payload.devices || []);
        renderChart('devices', target, {
            type: 'donut',
            labels: data.labels,
            values: data.values,
            label: i18n.devices || 'Devices'
        });
    }

    function renderChart(key, target, config) {
        if (!target) {
            return;
        }

        if (charts[key] && typeof charts[key].destroy === 'function') {
            charts[key].destroy();
        }

        target.innerHTML = '';

        if (window.ApexCharts) {
            charts[key] = renderApex(target, config);
            return;
        }

        if (window.Chart) {
            charts[key] = renderChartJs(target, config);
            return;
        }

        const message = document.createElement('div');
        message.className = 'text-body-secondary py-5 text-center';
        message.textContent = i18n.unavailable || 'Chart is unavailable.';
        target.appendChild(message);
    }

    function renderApex(target, config) {
        const theme = palette();
        const isLine = config.type === 'line';
        const options = isLine
            ? {
                chart: {
                    type: 'area',
                    height: 320,
                    toolbar: { show: false },
                    foreColor: theme.text
                },
                series: [{ name: config.label, data: config.values }],
                xaxis: {
                    categories: config.labels,
                    labels: { style: { colors: theme.text } },
                    axisBorder: { color: theme.grid },
                    axisTicks: { color: theme.grid }
                },
                yaxis: {
                    labels: { style: { colors: theme.text } }
                },
                colors: [theme.line],
                stroke: { curve: 'smooth', width: 3, colors: [theme.line] },
                fill: { type: 'solid', opacity: 0.18, colors: [theme.line] },
                markers: { colors: [theme.line], strokeColors: theme.tooltipBg },
                dataLabels: { enabled: false },
                grid: { borderColor: theme.grid },
                tooltip: { theme: document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light' }
            }
            : {
                chart: {
                    type: 'donut',
                    height: 320,
                    foreColor: theme.text
                },
                series: config.values,
                labels: config.labels,
                colors: theme.series,
                legend: {
                    position: 'bottom',
                    labels: { colors: theme.text }
                },
                dataLabels: {
                    enabled: true,
                    style: { colors: [theme.tooltipText] },
                    dropShadow: { enabled: false }
                },
                stroke: { colors: [theme.tooltipBg] },
                tooltip: { theme: document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light' }
            };

        const chart = new window.ApexCharts(target, options);
        chart.render();
        return chart;
    }

    function renderChartJs(target, config) {
        const theme = palette();
        const canvas = document.createElement('canvas');
        target.appendChild(canvas);

        const chart = new window.Chart(canvas, {
            type: config.type === 'line' ? 'line' : 'doughnut',
            data: {
                labels: config.labels,
                datasets: [{
                    label: config.label,
                    data: config.values,
                    borderColor: theme.line,
                    backgroundColor: config.type === 'line' ? colorWithAlpha(theme.line, 0.14) : theme.series,
                    pointBackgroundColor: theme.line,
                    pointBorderColor: theme.tooltipBg,
                    tension: 0.35,
                    fill: config.type === 'line'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: config.type === 'line' ? 'top' : 'bottom',
                        labels: { color: theme.text }
                    },
                    tooltip: {
                        backgroundColor: theme.tooltipBg,
                        titleColor: theme.tooltipText,
                        bodyColor: theme.tooltipText,
                        borderColor: theme.grid,
                        borderWidth: 1
                    }
                },
                scales: config.type === 'line'
                    ? {
                        x: {
                            grid: { color: theme.grid },
                            ticks: { color: theme.text }
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: theme.grid },
                            ticks: { color: theme.text, precision: 0 }
                        }
                    }
                    : {}
            }
        });

        return { destroy: () => chart.destroy() };
    }

    function colorWithAlpha(color, alpha) {
        if (color.startsWith('#') && (color.length === 7 || color.length === 4)) {
            const hex = color.length === 4
                ? color.slice(1).split('').map((item) => item + item).join('')
                : color.slice(1);
            const value = parseInt(hex, 16);
            const red = (value >> 16) & 255;
            const green = (value >> 8) & 255;
            const blue = value & 255;
            return `rgba(${red}, ${green}, ${blue}, ${alpha})`;
        }

        return color;
    }

    function renderAll() {
        renderTraffic(activeRange);
        renderSources();
        renderDevices();
    }

    root.querySelectorAll('[data-analytics-range]').forEach((button) => {
        button.addEventListener('click', () => {
            root.querySelectorAll('[data-analytics-range]').forEach((item) => item.classList.remove('active'));
            button.classList.add('active');
            renderTraffic(button.getAttribute('data-analytics-range') || '7');
        });
    });

    new MutationObserver(renderAll).observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-bs-theme']
    });

    renderAll();
})();
