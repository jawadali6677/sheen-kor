(function (window, document) {
    'use strict';

    function renderChart(elementId, options) {
        var el = document.querySelector(elementId);

        if (!el || typeof window.ApexCharts === 'undefined') {
            return;
        }

        var chart = new window.ApexCharts(el, options);
        chart.render();
    }

    function boot() {
        var payload = window.monetizationReport;

        if (!payload) {
            return;
        }

        var labels = payload.labels || [];

        renderChart('#revenue-chart', {
            chart: { type: 'area', height: 320, toolbar: { show: false } },
            stroke: { curve: 'smooth', width: 2 },
            dataLabels: { enabled: false },
            colors: ['#059669', '#2563eb'],
            series: [
                { name: 'Paid revenue', data: payload.series.revenue },
                { name: 'Paid orders', data: payload.series.paid_orders }
            ],
            xaxis: { categories: labels },
            legend: { position: 'top' }
        });

        renderChart('#ads-series-chart', {
            chart: { type: 'bar', height: 320, toolbar: { show: false } },
            colors: ['#0ea5e9', '#f59e0b'],
            series: [
                { name: 'Impressions', data: payload.series.impressions },
                { name: 'Clicks', data: payload.series.clicks }
            ],
            xaxis: { categories: labels },
            legend: { position: 'top' }
        });

        renderChart('#revenue-type-chart', {
            chart: { type: 'donut', height: 300 },
            labels: Object.keys(payload.revenue_by_type),
            series: Object.values(payload.revenue_by_type),
            colors: ['#16a34a', '#2563eb', '#f59e0b'],
            legend: { position: 'bottom' }
        });

        renderChart('#orders-status-chart', {
            chart: { type: 'donut', height: 300 },
            labels: Object.keys(payload.orders_by_status),
            series: Object.values(payload.orders_by_status),
            colors: ['#f59e0b', '#16a34a', '#ef4444', '#6b7280', '#8b5cf6'],
            legend: { position: 'bottom' }
        });

        renderChart('#ads-mix-chart', {
            chart: { type: 'donut', height: 300 },
            labels: Object.keys(payload.ads_in_range),
            series: Object.values(payload.ads_in_range),
            colors: ['#0ea5e9', '#f59e0b'],
            legend: { position: 'bottom' }
        });
    }

    document.addEventListener('DOMContentLoaded', boot);
})(window, document);
