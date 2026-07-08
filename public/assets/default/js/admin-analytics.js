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
                cssVar('--chart-accent-4', '#20c997'),
                cssVar('--chart-accent-5', '#0ea5e9'),
                cssVar('--chart-accent-6', '#84cc16'),
                cssVar('--chart-accent-7', '#e11d48'),
                cssVar('--chart-accent-8', '#64748b')
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

    function renderCountries() {
        const target = root.querySelector('[data-analytics-chart="countries"]');
        const data = seriesRows(payload.countries || []);
        renderChart('countries', target, {
            type: 'pie',
            labels: data.labels,
            values: data.values,
            label: i18n.countries || 'Countries'
        });
    }

    function renderChart(key, target, config) {
        if (!target) {
            return;
        }

        if (charts[key] && typeof charts[key].destroy === 'function') {
            charts[key].destroy();
            delete charts[key];
        }

        target.innerHTML = '';

        if (config.type !== 'line' && !hasChartData(config.values)) {
            renderChartMessage(target, i18n.empty || i18n.unavailable || 'No data.');
            return;
        }

        if (window.Chart) {
            charts[key] = renderChartJs(target, config);
            return;
        }

        if (window.ApexCharts) {
            charts[key] = renderApex(target, config);
            return;
        }

        renderChartMessage(target, i18n.unavailable || 'Chart is unavailable.');
    }

    function hasChartData(values) {
        return Array.isArray(values) && values.some((value) => Number(value || 0) > 0);
    }

    function renderChartMessage(target, text) {
        const message = document.createElement('div');
        message.className = 'text-body-secondary py-5 text-center';
        message.textContent = text;
        target.appendChild(message);
    }

    function renderApex(target, config) {
        const theme = palette();
        const isLine = config.type === 'line';
        const isPie = config.type === 'pie';
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
                    type: isPie ? 'pie' : 'donut',
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
        const context = canvas.getContext('2d');
        const isLine = config.type === 'line';
        const isPie = config.type === 'pie';
        let lineFill = colorWithAlpha(theme.line, 0.12);

        if (isLine && context) {
            const gradient = context.createLinearGradient(0, 0, 0, Math.max(target.clientHeight || 320, 240));
            gradient.addColorStop(0, colorWithAlpha(theme.line, 0.34));
            gradient.addColorStop(0.7, colorWithAlpha(theme.line, 0.08));
            gradient.addColorStop(1, colorWithAlpha(theme.line, 0));
            lineFill = gradient;
        }

        const chart = new window.Chart(canvas, {
            type: isLine ? 'line' : (isPie ? 'pie' : 'doughnut'),
            data: {
                labels: config.labels,
                datasets: [{
                    label: config.label,
                    data: config.values,
                    borderColor: isLine ? theme.line : theme.series,
                    backgroundColor: isLine ? lineFill : theme.series,
                    borderWidth: isLine ? 3 : 2,
                    pointRadius: isLine ? 3 : 0,
                    pointHoverRadius: isLine ? 6 : 0,
                    pointBackgroundColor: theme.tooltipBg,
                    pointBorderColor: theme.line,
                    pointBorderWidth: 2,
                    pointHoverBackgroundColor: theme.line,
                    pointHoverBorderColor: theme.tooltipText,
                    tension: 0.38,
                    fill: isLine
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                layout: {
                    padding: { top: 4, right: 8, bottom: 0, left: 4 }
                },
                plugins: {
                    legend: {
                        display: !isLine,
                        position: 'bottom',
                        labels: {
                            color: theme.text,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            padding: 18
                        }
                    },
                    tooltip: {
                        backgroundColor: theme.tooltipBg,
                        titleColor: theme.tooltipText,
                        bodyColor: theme.tooltipText,
                        borderColor: theme.grid,
                        borderWidth: 1,
                        padding: 12,
                        displayColors: !isLine,
                        cornerRadius: 10
                    }
                },
                scales: isLine
                    ? {
                        x: {
                            border: { display: false },
                            grid: {
                                color: colorWithAlpha(theme.text, 0.08),
                                drawTicks: false
                            },
                            ticks: {
                                color: theme.text,
                                padding: 10,
                                maxRotation: 0,
                                autoSkip: true,
                                callback: function (value) {
                                    return formatChartDate(this.getLabelForValue(value));
                                }
                            }
                        },
                        y: {
                            beginAtZero: true,
                            border: { display: false },
                            grid: {
                                color: colorWithAlpha(theme.text, 0.1),
                                drawTicks: false
                            },
                            ticks: {
                                color: theme.text,
                                padding: 10,
                                precision: 0
                            }
                        }
                    }
                    : {}
            }
        });

        return { destroy: () => chart.destroy() };
    }

    function formatChartDate(value) {
        const match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
        return match ? `${match[3]}.${match[2]}` : value;
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
        renderCountries();
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
