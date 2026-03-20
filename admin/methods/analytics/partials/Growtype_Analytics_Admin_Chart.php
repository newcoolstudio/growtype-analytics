<?php

/**
 * Analytics Admin Page Chart Partial
 *
 * Handles the unique users chart rendering and data fetching
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/admin/methods/analytics
 */

class Growtype_Analytics_Admin_Chart
{
    private $controller;

    public function __construct($controller)
    {
        $this->controller = $controller;
        add_action('wp_ajax_get_unique_users_chart_data', array($this, 'ajax_get_unique_users_chart_data'));
    }

    /**
     * Render the chart section
     */
    public function render()
    {
        ?>
        <div class="analytics-section">
            <div class="analytics-section-header">
                <h2><?php _e('Daily Unique Users', 'growtype-analytics'); ?></h2>
                <div class="analytics-chart-controls">
                    <button type="button" class="button analytics-chart-period-btn active" data-period="week" data-action="get_unique_users_chart_data" data-chart="uniqueUsersChart"><?php _e('One Week', 'growtype-analytics'); ?></button>
                    <button type="button" class="button analytics-chart-period-btn" data-period="month" data-action="get_unique_users_chart_data" data-chart="uniqueUsersChart"><?php _e('One Month', 'growtype-analytics'); ?></button>
                </div>
            </div>
            
            <div class="analytics-chart-container">
                <canvas id="uniqueUsersChart"></canvas>
                <div class="analytics-chart-loading-overlay" style="display: none;">
                    <span class="spinner is-active"></span>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX handler to get chart data
     */
    public function ajax_get_unique_users_chart_data()
    {
        check_ajax_referer('growtype_analytics_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized', 403);
            return;
        }

        $period = isset($_POST['period']) ? sanitize_text_field($_POST['period']) : 'week';
        $days = ($period === 'month') ? 30 : 7;

        $cache_key = 'growtype_unique_users_chart_' . $period;
        $data = get_transient($cache_key);

        if (false === $data) {
            $debug = array();
            
            // Try getting data from PostHog first
            $data = $this->controller->posthog->get_unique_users_trend($days);

            // Fallback to local DB if PostHog data is not available
            $source = 'PostHog';
            if (empty($data['labels'])) {
                $source = 'Local Database (Fallback)';
                $data = $this->get_unique_users_data($days);
            }
            
            $data['source'] = $source;
            set_transient($cache_key, $data, GROWTYPE_ANALYTICS_CACHE_TIME);
        }

        wp_send_json_success(array(
            'chart_data' => $data,
            'source'     => $data['source'] ?? 'Unknown'
        ));
    }



    /**
     * Get unique users data for the specified number of days (Local Fallback: New Registrations)
     */
    private function get_unique_users_data($days)
    {
        global $wpdb;

        $labels = array();
        $values = array();
        $start_date = date('Y-m-d', strtotime("-" . ($days - 1) . " days"));

        $batched_users = $this->controller->metrics->get_batched_user_data($start_date);

        // Generate date range
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $labels[] = date('M d', strtotime($date));
            $values[] = $batched_users[$date] ?? 0;
        }

        return array(
            'labels' => $labels,
            'values' => $values
        );
    }
}
