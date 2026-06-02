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

    const charts = {};
    const colors = ['#212529', '#0d6efd', '#198754', '#ffc107', '#dc3545', '#6f42c1', '#20c997'];

    function seriesRows(rows) {
        rows = Array.isArray(rows) ? rows : [];
        return {
            labels: rows.map((row) => String(row.label || 'Unknown')),
            values: rows.map((row) => Number(row.total || 0))
        };
    }

    function renderTraffic(range) {
        const target = root.querySelector('[data-analytics-chart="traffic"]');
        const data = payload.traffic && payload.traffic[String(range)] ? payload.traffic[String(range)] : { labels: [], values: [] };
        renderChart('traffic', target, {
            type: 'line',
            labels: data.labels || [],
            values: data.values || [],
            label: 'Визиты'
        });
    }

    function renderSources() {
        const target = root.querySelector('[data-analytics-chart="sources"]');
        const data = seriesRows(payload.sources || []);
        renderChart('sources', target, {
            type: 'donut',
            labels: data.labels,
            values: data.values,
            label: 'Источники'
        });
    }

    function renderDevices() {
        const target = root.querySelector('[data-analytics-chart="devices"]');
        const data = seriesRows(payload.devices || []);
        renderChart('devices', target, {
            type: 'donut',
            labels: data.labels,
            values: data.values,
            label: 'Устройства'
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

        target.innerHTML = '<div class="text-body-secondary py-5 text-center">График недоступен.</div>';
    }

    function renderApex(target, config) {
        const options = config.type === 'line'
            ? {
                chart: { type: 'area', height: 320, toolbar: { show: false } },
                series: [{ name: config.label, data: config.values }],
                xaxis: { categories: config.labels },
                colors: [colors[0]],
                stroke: { curve: 'smooth', width: 3 },
                fill: { opacity: 0.18 },
                dataLabels: { enabled: false },
                grid: { borderColor: 'rgba(108,117,125,.18)' }
            }
            : {
                chart: { type: 'donut', height: 320 },
                series: config.values,
                labels: config.labels,
                colors: colors,
                legend: { position: 'bottom' },
                dataLabels: { enabled: true }
            };

        const chart = new window.ApexCharts(target, options);
        chart.render();
        return chart;
    }

    function renderChartJs(target, config) {
        const canvas = document.createElement('canvas');
        target.appendChild(canvas);

        const chart = new window.Chart(canvas, {
            type: config.type === 'line' ? 'line' : 'doughnut',
            data: {
                labels: config.labels,
                datasets: [{
                    label: config.label,
                    data: config.values,
                    borderColor: colors[0],
                    backgroundColor: config.type === 'line' ? 'rgba(33,37,41,.14)' : colors,
                    tension: 0.35,
                    fill: config.type === 'line'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: config.type === 'line' ? 'top' : 'bottom' } },
                scales: config.type === 'line' ? { y: { beginAtZero: true, ticks: { precision: 0 } } } : {}
            }
        });

        return { destroy: () => chart.destroy() };
    }

    root.querySelectorAll('[data-analytics-range]').forEach((button) => {
        button.addEventListener('click', () => {
            root.querySelectorAll('[data-analytics-range]').forEach((item) => item.classList.remove('active'));
            button.classList.add('active');
            renderTraffic(button.getAttribute('data-analytics-range') || '7');
        });
    });

    renderTraffic('7');
    renderSources();
    renderDevices();
})();
