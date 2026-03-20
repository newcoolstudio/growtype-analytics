/**
 * Analytics Admin Page JavaScript
 */

(function ($) {
    'use strict';

    $(document).ready(function () {
        // Initialize analytics page shell features (sorting, etc)
        GrowtypeAnalyticsPage.init();
    });

    $(document).on('growtype_analytics_section_loaded', function(e, sectionName, $section) {
        // Re-init chart components when a section is loaded via AJAX
        if (sectionName === 'analytics_snapshot' || sectionName === 'extra_sections') {
            GrowtypeAnalyticsPage.initCharts();
        }
        
        // Setup drag-and-drop sortable grids
        if (sectionName === 'execution_kpis' || sectionName === 'custom_kpis') {
            GrowtypeAnalyticsPage.initSortableKPIs();
        }
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
            this.initSortableKPIs();
            this.bindEvents();
            this.initCharts();
        },

        /**
         * Initialize all charts
         */
        initCharts: function() {
            if (typeof Chart === 'undefined') {
                console.warn('Growtype Analytics: Chart.js not loaded yet.');
                return;
            }
            this.initUniqueUsersChart();
            this.initDailyRegistrationsChart();
            this.initActivationRateChart();
            this.initPaywallViewsChart();
            this.initUserRetentionChart();
        },

        /**
         * Initialize Sortable for pinned KPIs
         */
        initSortableKPIs: function() {
            const self = this;
            const $kpiGrid = $('h2:contains("Execution KPIs")').closest('.analytics-section').find('.analytics-scale-snapshot-grid');
            
            if (!$kpiGrid.length) return;
            
            // Check if already initialized to avoid duplicate binding
            if ($kpiGrid.hasClass('ui-sortable')) {
                $kpiGrid.sortable('refresh');
                return;
            }

            $kpiGrid.sortable({
                items: '.analytics-snapshot-card',
                cursor: 'grabbing',
                opacity: 0.8,
                update: function(event, ui) {
                    const order = [];
                    $kpiGrid.find('.analytics-snapshot-card').each(function() {
                        const id = $(this).data('kpi-id');
                        if (id) order.push(id);
                    });
                    self.savePinnedKPIOrder(order);
                }
            });
        },

        /**
         * Save the order of pinned KPIs
         */
        savePinnedKPIOrder: function(order) {
            $.ajax({
                url: growtype_analytics_vars.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'growtype_analytics_save_pinned_kpi_order',
                    nonce: growtype_analytics_vars.nonce,
                    order: order
                },
                success: function(response) {
                    if (response.success) {
                        // Optional: show a small toast
                    }
                }
            });
        },

        /**
         * Initialize the Unique Users Chart
         */
        initUniqueUsersChart: function () {
            const ctx = document.getElementById('uniqueUsersChart');
            if (!ctx || this.uniqueUsersChart) return;

            this.uniqueUsersChart = this.createChart(ctx, 'Unique Users', '#764ba2', 'rgba(118, 75, 162, 0.1)');
            this.loadChartData('get_unique_users_chart_data', 'week', this.uniqueUsersChart, $('#uniqueUsersChart').closest('.analytics-section').find('.analytics-chart-loading-overlay'));
        },

        /**
         * Initialize the Daily Registrations Chart
         */
        initDailyRegistrationsChart: function () {
            const ctx = document.getElementById('dailyRegistrationsChart');
            if (!ctx || this.dailyRegistrationsChart) return;

            this.dailyRegistrationsChart = this.createChart(ctx, 'Registrations', '#f5576c', 'rgba(245, 87, 108, 0.1)', true);
            this.loadChartData('get_daily_registrations_chart_data', 'week', this.dailyRegistrationsChart, $('#dailyRegistrationsChart').closest('.analytics-section').find('.analytics-chart-loading-overlay'));
        },

        /**
         * Initialize the Activation Rate Chart
         */
        initActivationRateChart: function () {
            const ctx = document.getElementById('activationRateChart');
            if (!ctx || this.activationRateChart) return;

            this.activationRateChart = this.createMixedChart(ctx);
            this.loadChartData('get_activation_rate_chart_data', 'week', this.activationRateChart, $('#activationRateChart').closest('.analytics-section').find('.analytics-chart-loading-overlay'));
        },

        /**
         * Initialize the Paywall Views Chart
         */
        initPaywallViewsChart: function () {
            const ctx = document.getElementById('paywallViewsChart');
            if (!ctx || this.paywallViewsChart) return;

            this.paywallViewsChart = this.createMixedChart(ctx);
            this.loadChartData('get_paywall_views_chart_data', 'week', this.paywallViewsChart, $('#paywallViewsChart').closest('.analytics-section').find('.analytics-chart-loading-overlay'));
        },

        /**
         * Initialize the User Retention Chart
         */
        initUserRetentionChart: function () {
            const ctx = document.getElementById('userRetentionChart');
            if (!ctx || this.userRetentionChart) return;

            this.userRetentionChart = this.createMixedChart(ctx);
            this.loadChartData('get_user_retention_chart_data', 'week', this.userRetentionChart, $('#userRetentionChart').closest('.analytics-section').find('.analytics-chart-loading-overlay'));
        },

        /**
         * Helper to create a Chart.js instance
         */
        createChart: function (ctx, label, color, bgColor, showLegend = false) {
            let existingChart = Chart.getChart(ctx.id || ctx);
            if (existingChart) existingChart.destroy();

            // Extract the 2D context directly to bypass chart.js internal canvas lookups
            // which can throw listener errors if the element isn't fully painted.
            let chartCtx = ctx.getContext ? ctx.getContext('2d') : ctx;
            if (!chartCtx) return null;

            return new Chart(chartCtx, {
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
            let existingChart = Chart.getChart(ctx.id || ctx);
            if (existingChart) existingChart.destroy();

            let chartCtx = ctx.getContext ? ctx.getContext('2d') : ctx;
            if (!chartCtx) return null;

            return new Chart(chartCtx, {
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

            // Handle all chart period button clicks with a single listener via delegation for AJAX content
            $(document).off('click', '.analytics-chart-period-btn').on('click', '.analytics-chart-period-btn', function () {
                const $btn = $(this);
                if ($btn.hasClass('active')) return;

                const $container = $btn.closest('.analytics-section');
                $container.find('.analytics-chart-period-btn').removeClass('active');
                $btn.addClass('active');

                const action = $btn.data('action');
                const period = $btn.data('period');
                const chartName = $btn.data('chart');
                const chartInstance = self[chartName];
                const $loader = $container.find('.analytics-chart-loading-overlay');

                if (chartInstance && action) {
                    self.loadChartData(action, period, chartInstance, $loader);
                }
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
                url: growtype_analytics_vars.ajaxUrl,
                type: 'POST',
                data: {
                    action: action,
                    nonce: growtype_analytics_vars.nonce,
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

                        // PostHog data source information (preserved in response but hidden from console)
                        if (action === 'get_unique_users_chart_data') {
                            this.posthogSource = data.source;
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

/**
 * Global helpers for card menus
 */
function toggleAnalyticsCardDropdown(el) {
    const dropdown = el.nextElementSibling;
    const isShowing = dropdown.classList.contains('show');
    
    // Close others
    document.querySelectorAll('.analytics-card-dropdown').forEach(d => d.classList.remove('show'));
    
    if (!isShowing) {
        dropdown.classList.add('show');
        
        // Close on click outside
        const closeDropdown = (e) => {
            if (!el.contains(e.target)) {
                dropdown.classList.remove('show');
                document.removeEventListener('click', closeDropdown);
            }
        };
        setTimeout(() => document.addEventListener('click', closeDropdown), 0);
    }
}

function togglePinnedKPI(id, el) {
    const $ = jQuery;
    const $allCards = $(`.analytics-snapshot-card[data-kpi-id="${id}"]`);
    const $clickedCard = $(el).closest('.analytics-snapshot-card');
    const $btn = $(el); // The dropdown item
    
    $btn.css('opacity', '0.5').css('pointer-events', 'none');
    
    $.ajax({
        url: growtype_analytics_vars.ajaxUrl,
        type: 'POST',
        data: {
            action: 'growtype_analytics_toggle_pinned_kpi',
            nonce: growtype_analytics_vars.nonce,
            kpi_id: id
        },
        success: function(response) {
            if (response.success) {
                const nowPinned = response.data.is_pinned;
                
                // Update instances appropriately
                $allCards.each(function() {
                    const $c = $(this);
                    const $item = $c.find('.analytics-card-dropdown-item');
                    const $sectionHeader = $c.closest('.analytics-section').find('h2');
                    const isInExecution = $sectionHeader.length && $sectionHeader.text().includes('Execution KPIs');
                    
                    if (isInExecution) {
                        if (nowPinned) {
                            $item.html('<span class="dashicons dashicons-star-filled" style="font-size: 14px; width: 14px; height: 14px; margin-right: 5px;"></span>Unpin from Execution KPIs');
                        } else {
                            // Instant removal for all instances in Execution section when unpinned
                            $c.fadeOut(300, function() { $(this).remove(); });
                        }
                    } else {
                        // Overview cards always show "Pin" (but we allow the star to show pinning status)
                        $item.html('<span class="dashicons dashicons-star-empty" style="font-size: 14px; width: 14px; height: 14px; margin-right: 5px;"></span>Pin to Execution KPIs');
                    }

                    // Handle star indicator for all cards
                    if (nowPinned) {
                        if ($c.find('.analytics-snapshot-card__pinned-indicator').length === 0) {
                            $c.prepend('<div class="analytics-snapshot-card__pinned-indicator" title="Pinned to Execution KPIs"><span class="dashicons dashicons-star-filled"></span></div>');
                        }
                    } else {
                        $c.find('.analytics-snapshot-card__pinned-indicator').remove();
                    }
                });

                // If pinning FROM Overview, add a clone to Execution KPIs if it doesn't exist
                const $sectionHeaderClicked = $clickedCard.closest('.analytics-section').find('h2');
                const isClickedFromExecution = $sectionHeaderClicked.length && $sectionHeaderClicked.text().includes('Execution KPIs');

                if (!isClickedFromExecution && nowPinned) {
                    const $kpiGrid = $('h2:contains("Execution KPIs")').closest('.analytics-section').find('.analytics-scale-snapshot-grid');
                    if ($kpiGrid.length) {
                        if ($kpiGrid.find(`[data-kpi-id="${id}"]`).length === 0) {
                            const $clone = $clickedCard.clone();
                            // Update icons & dropdown items in the clone correctly
                            $clone.find('.dashicons-star-empty').removeClass('dashicons-star-empty').addClass('dashicons-star-filled');
                            $clone.find('.analytics-card-dropdown-item').html('<span class="dashicons dashicons-star-filled" style="font-size: 14px; width: 14px; height: 14px; margin-right: 5px;"></span>Unpin from Execution KPIs');
                            
                            // Re-bind the click since it was cloned with original onclick
                            $clone.hide().appendTo($kpiGrid).fadeIn(300);
                        }
                    }
                }
            }
            $btn.css('opacity', '1').css('pointer-events', 'auto');
        },
        error: function() {
            $btn.css('opacity', '1').css('pointer-events', 'auto');
        }
    });

    // Close dropdown
    $('.analytics-card-dropdown').removeClass('show');
}
