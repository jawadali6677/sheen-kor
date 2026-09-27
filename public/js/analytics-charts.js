(function (window, document) {
    'use strict';

    var charts = [];

    function wideScreen() {
        return window.matchMedia('(min-width: 1024px)').matches;
    }

    function renderChart(elementId, options) {
        var el = document.querySelector(elementId);

        if (!el || typeof window.ApexCharts === 'undefined') {
            return;
        }

        var chart = new window.ApexCharts(el, options);
        charts.push(chart);
        chart.render();
    }

    function destroyCharts() {
        charts.forEach(function (chart) {
            chart.destroy();
        });
        charts = [];
    }

    function categoryAxis(categories) {
        var axis = { categories: categories };

        if (wideScreen()) {
            axis.labels = {
                rotate: 0,
                rotateAlways: false,
                hideOverlappingLabels: true,
            };
        }

        return axis;
    }

    function frame(type, mobileHeight, desktopHeight, extra) {
        var chart = {
            type: type,
            height: wideScreen() ? desktopHeight : mobileHeight,
            width: '100%',
            redrawOnParentResize: true,
            redrawOnWindowResize: true,
        };

        if (extra) {
            Object.keys(extra).forEach(function (key) {
                chart[key] = extra[key];
            });
        }

        return chart;
    }

    function boot() {
        var payload = window.analyticsReport;

        if (!payload) {
            return;
        }

        destroyCharts();

        var labels = payload.labels || [];

        renderChart('#activity-chart', {
            chart: frame('area', 320, 380, { toolbar: { show: false } }),
            stroke: { curve: 'smooth', width: 2 },
            dataLabels: { enabled: false },
            colors: ['#2563eb', '#059669', '#dc2626'],
            series: [
                { name: 'New users', data: payload.activity.users },
                { name: 'Stories', data: payload.activity.stories },
                { name: 'Alerts', data: payload.activity.alerts }
            ],
            xaxis: categoryAxis(labels),
            legend: { position: 'top' }
        });

        renderChart('#workflow-chart', {
            chart: frame('bar', 320, 380, { stacked: true, toolbar: { show: false } }),
            colors: ['#0ea5e9', '#16a34a'],
            series: [
                { name: 'Taken / in progress', data: payload.workflow.claimed },
                { name: 'Fixed', data: payload.workflow.fixed }
            ],
            xaxis: categoryAxis(labels),
            legend: { position: 'top' }
        });

        renderChart('#alert-status-chart', {
            chart: frame('donut', 300, 320),
            labels: Object.keys(payload.alerts_by_status),
            series: Object.values(payload.alerts_by_status),
            colors: ['#f59e0b', '#3b82f6', '#22c55e'],
            legend: { position: 'bottom' }
        });

        renderChart('#alert-severity-chart', {
            chart: frame('bar', 300, 320, { toolbar: { show: false } }),
            plotOptions: { bar: { horizontal: true, borderRadius: 6 } },
            colors: ['#f97316'],
            series: [{ name: 'Alerts', data: Object.values(payload.alerts_by_severity) }],
            xaxis: { categories: Object.keys(payload.alerts_by_severity) }
        });

        renderChart('#story-status-chart', {
            chart: frame('donut', 300, 320),
            labels: Object.keys(payload.stories_by_status),
            series: Object.values(payload.stories_by_status),
            colors: ['#10b981', '#64748b'],
            legend: { position: 'bottom' }
        });

        renderChart('#user-role-chart', {
            chart: frame('donut', 300, 320),
            labels: Object.keys(payload.users_by_role),
            series: Object.values(payload.users_by_role),
            colors: ['#6366f1', '#f59e0b', '#ef4444'],
            legend: { position: 'bottom' }
        });

        renderChart('#user-status-chart', {
            chart: frame('donut', 300, 320),
            labels: Object.keys(payload.users_by_status),
            series: Object.values(payload.users_by_status),
            colors: ['#16a34a', '#6b7280'],
            legend: { position: 'bottom' }
        });
    }

    document.addEventListener('DOMContentLoaded', boot);

    if (typeof window.matchMedia('(min-width: 1024px)').addEventListener === 'function') {
        window.matchMedia('(min-width: 1024px)').addEventListener('change', boot);
    }
})(window, document);
