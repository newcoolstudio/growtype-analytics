/**
 * Analytics Admin Page JavaScript
 */

(function ($) {
    'use strict';

    $(document).ready(function () {
        // Initialize analytics page
        GrowtypeAnalyticsPage.init();
    });

    const GrowtypeAnalyticsPage = {
        uniqueUsersChart: null,
        dailyRegistrationsChart: null,
        activationRateChart: null,
        paywallViewsChart: null,
        userRetentionChart: null,

        /**
         * Initialize the analytics page
         */
        init: function () {
            this.initUniqueUsersChart();
            this.initDailyRegistrationsChart();
            this.initActivationRateChart();
            this.initPaywallViewsChart();
            this.initUserRetentionChart();
            this.bindEvents();
        },

        /**
         * Initialize the Unique Users Chart
         */
        initUniqueUsersChart: function () {
            const ctx = document.getElementById('uniqueUsersChart');
            if (!ctx) return;

            this.uniqueUsersChart = this.createChart(ctx, 'Unique Users', '#764ba2', 'rgba(118, 75, 162, 0.1)');
            this.loadChartData('get_unique_users_chart_data', 'week', this.uniqueUsersChart, $('.chart-loading-overlay'));
        },

        /**
         * Initialize the Daily Registrations Chart
         */
        initDailyRegistrationsChart: function () {
            const ctx = document.getElementById('dailyRegistrationsChart');
            if (!ctx) return;

            this.dailyRegistrationsChart = this.createChart(ctx, 'Registrations', '#f5576c', 'rgba(245, 87, 108, 0.1)', true);
            this.loadChartData('get_daily_registrations_chart_data', 'week', this.dailyRegistrationsChart, $('.registrations-chart-loading-overlay'));
        },

        /**
         * Initialize the Activation Rate Chart
         */
        initActivationRateChart: function () {
            const ctx = document.getElementById('activationRateChart');
            if (!ctx) return;

            this.activationRateChart = this.createMixedChart(ctx);
            this.loadChartData('get_activation_rate_chart_data', 'week', this.activationRateChart, $('.activation-chart-loading-overlay'));
        },

        /**
         * Initialize the Paywall Views Chart
         */
        initPaywallViewsChart: function () {
            const ctx = document.getElementById('paywallViewsChart');
            if (!ctx) return;

            this.paywallViewsChart = this.createMixedChart(ctx);
            this.loadChartData('get_paywall_views_chart_data', 'week', this.paywallViewsChart, $('.paywall-chart-loading-overlay'));
        },

        /**
         * Initialize the User Retention Chart
         */
        initUserRetentionChart: function () {
            const ctx = document.getElementById('userRetentionChart');
            if (!ctx) return;

            this.userRetentionChart = this.createMixedChart(ctx);
            this.loadChartData('get_user_retention_chart_data', 'week', this.userRetentionChart, $('.retention-chart-loading-overlay'));
        },

        /**
         * Helper to create a Chart.js instance
         */
        createChart: function (ctx, label, color, bgColor, showLegend = false) {
            return new Chart(ctx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: label,
                        data: [],
                        borderColor: color,
                        backgroundColor: bgColor,
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: color,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: showLegend,
                            position: 'top',
                            align: 'end',
                            labels: {
                                usePointStyle: true,
                                boxWidth: 8,
                                font: {
                                    size: 11
                                }
                            }
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            padding: 12,
                            backgroundColor: 'rgba(29, 35, 39, 0.9)',
                            titleFont: {
                                size: 14,
                                weight: 'bold'
                            },
                            bodyFont: {
                                size: 13
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                borderDash: [5, 5],
                                color: '#e0e0e0'
                            },
                            ticks: {
                                precision: 0
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        },

        /**
         * Helper to create a Mixed Chart (for Activation Rate)
         */
        createMixedChart: function (ctx) {
            return new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: [],
                    datasets: []
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            align: 'end',
                            labels: {
                                usePointStyle: true,
                                boxWidth: 8,
                                font: {
                                    size: 11
                                }
                            }
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            padding: 12,
                            backgroundColor: 'rgba(29, 35, 39, 0.9)',
                            titleFont: {
                                size: 14,
                                weight: 'bold'
                            },
                            bodyFont: {
                                size: 13
                            },
                            callbacks: {
                                label: function (context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed.y !== null) {
                                        if (context.dataset.label.includes('%')) {
                                            label += context.parsed.y + '%';
                                        } else {
                                            label += context.parsed.y;

                                            // If this is Paywall Viewers and we have view_rates, show the percentage
                                            if (context.dataset.view_rates && context.dataset.view_rates[context.dataIndex] !== undefined) {
                                                label += ' (' + context.dataset.view_rates[context.dataIndex] + '%)';
                                            }
                                        }
                                    }
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                borderDash: [5, 5],
                                color: '#e0e0e0'
                            },
                            ticks: {
                                precision: 0
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            beginAtZero: true,
                            max: 100,
                            grid: {
                                drawOnChartArea: false
                            },
                            ticks: {
                                callback: function (value) {
                                    return value + '%';
                                }
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        },

        /**
         * Bind event handlers
         */
        bindEvents: function () {
            const self = this;

            // Unique Users Chart Period Buttons
            $('.chart-period-btn').on('click', function () {
                const $btn = $(this);
                if ($btn.hasClass('active')) return;

                $('.chart-period-btn').removeClass('active');
                $btn.addClass('active');

                const period = $btn.data('period');
                self.loadChartData('get_unique_users_chart_data', period, self.uniqueUsersChart, $('.chart-loading-overlay'));
            });

            // Registrations Chart Period Buttons
            $('.registrations-chart-period-btn').on('click', function () {
                const $btn = $(this);
                if ($btn.hasClass('active')) return;

                $('.registrations-chart-period-btn').removeClass('active');
                $btn.addClass('active');

                const period = $btn.data('period');
                self.loadChartData('get_daily_registrations_chart_data', period, self.dailyRegistrationsChart, $('.registrations-chart-loading-overlay'));
            });

            // Activation Rate Chart Period Buttons
            $('.activation-chart-period-btn').on('click', function () {
                const $btn = $(this);
                if ($btn.hasClass('active')) return;

                $('.activation-chart-period-btn').removeClass('active');
                $btn.addClass('active');

                const period = $btn.data('period');
                self.loadChartData('get_activation_rate_chart_data', period, self.activationRateChart, $('.activation-chart-loading-overlay'));
            });

            // Paywall Views Chart Period Buttons
            $('.paywall-chart-period-btn').on('click', function () {
                const $btn = $(this);
                if ($btn.hasClass('active')) return;

                $('.paywall-chart-period-btn').removeClass('active');
                $btn.addClass('active');

                const period = $btn.data('period');
                self.loadChartData('get_paywall_views_chart_data', period, self.paywallViewsChart, $('.paywall-chart-loading-overlay'));
            });

            // User Retention Chart Period Buttons
            $('.retention-chart-period-btn').on('click', function () {
                const $btn = $(this);
                if ($btn.hasClass('active')) return;

                $('.retention-chart-period-btn').removeClass('active');
                $btn.addClass('active');

                const period = $btn.data('period');
                self.loadChartData('get_user_retention_chart_data', period, self.userRetentionChart, $('.retention-chart-loading-overlay'));
            });
        },

        /**
         * Helper to convert hex color to RGB
         */
        hexToRgb: function (hex) {
            const shorthandRegex = /^#?([a-f\d])([a-f\d])([a-f\d])$/i;
            hex = hex.replace(shorthandRegex, function (m, r, g, b) {
                return r + r + g + g + b + b;
            });
            const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
            return result ? `${parseInt(result[1], 16)}, ${parseInt(result[2], 16)}, ${parseInt(result[3], 16)}` : null;
        },

        /**
         * Load chart data via AJAX
         */
        loadChartData: function (action, period, chartInstance, $loader) {
            if (!chartInstance) return;

            $loader.show();

            $.ajax({
                url: growtypeAnalytics.ajaxUrl,
                type: 'POST',
                data: {
                    action: action,
                    nonce: growtypeAnalytics.nonce,
                    period: period
                },
                success: function (response) {
                    if (response.success) {
                        const data = response.data;

                        if (data.chart_data) {
                            chartInstance.data.labels = data.chart_data.labels;

                            // Handle multiple datasets if present
                            if (data.chart_data.datasets) {
                                chartInstance.data.datasets = data.chart_data.datasets.map((ds, index) => {
                                    const isPercentage = ds.label && ds.label.includes('%');
                                    const chartType = ds.type || 'line';

                                    return {
                                        label: ds.label,
                                        data: ds.data,
                                        type: chartType,
                                        borderColor: ds.color,
                                        backgroundColor: chartType === 'bar'
                                            ? 'rgba(' + GrowtypeAnalyticsPage.hexToRgb(ds.color) + ', 0.6)'
                                            : 'rgba(' + GrowtypeAnalyticsPage.hexToRgb(ds.color) + ', 0.1)',
                                        borderWidth: chartType === 'line' ? 3 : 1,
                                        tension: 0.4,
                                        fill: chartType === 'line',
                                        pointBackgroundColor: ds.color,
                                        pointRadius: chartType === 'line' ? 4 : 0,
                                        pointHoverRadius: chartType === 'line' ? 6 : 0,
                                        yAxisID: isPercentage ? 'y1' : 'y',
                                        view_rates: ds.view_rates || null  // Preserve view_rates for tooltip
                                    };
                                });
                            } else {
                                chartInstance.data.datasets[0].data = data.chart_data.values;
                            }

                            chartInstance.update();
                        }

                        // Debug logging for PostHog if available
                        if (action === 'get_unique_users_chart_data') {
                            console.log('Unique Users Source:', data.source);
                            if (data.debug) {
                                console.log('PostHog Debug Info:', data.debug);
                            }
                        }
                    }
                    $loader.hide();
                },
                error: function (xhr, status, error) {
                    $loader.hide();
                    console.error('Failed to load ' + action + ' data:', error);
                }
            });
        },

        /**
         * Refresh analytics data
         */
        refreshData: function () {
            location.reload();
        }
    };

    // Expose to global scope if needed
    window.GrowtypeAnalyticsPage = GrowtypeAnalyticsPage;

})(jQuery);
