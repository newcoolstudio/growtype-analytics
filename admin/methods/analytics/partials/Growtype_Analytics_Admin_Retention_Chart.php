<?php

/**
 * Analytics Admin Page User Retention Chart Partial
 *
 * Handles the user retention chart rendering and data fetching
 * Shows % of users who returned after registration
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/admin/methods/analytics
 */

class Growtype_Analytics_Admin_Retention_Chart
{
    private $controller;

    public function __construct($controller)
    {
        $this->controller = $controller;
        add_action('wp_ajax_get_user_retention_chart_data', array($this, 'ajax_get_user_retention_chart_data'));
    }

    /**
     * Render the chart section
     */
    public function render()
    {
        ?>
        <div class="analytics-section">
            <div class="analytics-section-header">
                <h2><?php _e('User Retention', 'growtype-analytics'); ?></h2>
                <p class="description"><?php _e('% of users who returned after registration', 'growtype-analytics'); ?></p>
                <div class="analytics-chart-controls">
                    <button type="button" class="button analytics-chart-period-btn active" data-period="week" data-action="get_user_retention_chart_data" data-chart="userRetentionChart"><?php _e('One Week', 'growtype-analytics'); ?></button>
                    <button type="button" class="button analytics-chart-period-btn" data-period="month" data-action="get_user_retention_chart_data" data-chart="userRetentionChart"><?php _e('One Month', 'growtype-analytics'); ?></button>
                </div>
            </div>
            
            <div class="analytics-chart-container">
                <canvas id="userRetentionChart"></canvas>
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
    public function ajax_get_user_retention_chart_data()
    {
        check_ajax_referer('growtype_analytics_nonce', 'nonce');

        $period = isset($_POST['period']) ? sanitize_text_field($_POST['period']) : 'week';
        $days = ($period === 'month') ? 30 : 7;

        $cache_key = 'growtype_retention_chart_' . $period;
        $data = get_transient($cache_key);

        if (false === $data) {
            $data = $this->get_user_retention_data($days);
            set_transient($cache_key, $data, GROWTYPE_ANALYTICS_CACHE_TIME);
        }

        wp_send_json_success(array(
            'chart_data' => $data
        ));
    }

    /**
     * Get user retention data for the specified number of days
     */
    private function get_user_retention_data($days)
    {
        global $wpdb;

        $labels = array();
        $new_users = array();
        $returned_users = array();
        $retention_rates = array();

        $start_date = date('Y-m-d', strtotime("-" . ($days - 1) . " days"));

        // Use centralized metrics for new users
        $batched_users = $this->controller->metrics->get_batched_user_data($start_date);

        // Batch returned users
        $chat_messages_table = $wpdb->prefix . 'growtype_chat_messages';
        $chat_users_table = $wpdb->prefix . 'growtype_chat_users';
        
        $batched_returned = array();
        if ($this->controller->table_exists($chat_messages_table) && 
            $this->controller->table_exists($chat_users_table)) {
            
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT DATE(u.user_registered) as reg_date, COUNT(DISTINCT cu.external_id) as returned_count
                 FROM $wpdb->users u
                 INNER JOIN $chat_users_table cu ON cu.external_id = u.ID
                 INNER JOIN $chat_messages_table cm ON cu.id = cm.user_id
                 WHERE u.user_registered >= %s
                 AND DATE(cm.created_at) > DATE(u.user_registered)
                 GROUP BY reg_date",
                $start_date
            ), OBJECT_K);
            
            $batched_returned = $results;
        }

        // Generate date range
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $labels[] = date('M d', strtotime($date));
            
            $new_users_count = $batched_users[$date] ?? 0;
            $new_users[] = $new_users_count;

            $returned_row = $batched_returned[$date] ?? null;
            $returned_count = $returned_row ? (int)$returned_row->returned_count : 0;
            $returned_users[] = $returned_count;

            // Calculate retention rate as percentage
            $retention_rate = $new_users_count > 0 ? round(($returned_count / $new_users_count) * 100, 2) : 0;
            $retention_rates[] = $retention_rate;
        }

        return array(
            'labels' => $labels,
            'datasets' => array(
                array(
                    'label' => __('Retention Rate (%)', 'growtype-analytics'),
                    'data' => $retention_rates,
                    'color' => '#00bcd4',
                    'type' => 'line'
                ),
                array(
                    'label' => __('Returned Users', 'growtype-analytics'),
                    'data' => $returned_users,
                    'color' => '#4caf50',
                    'type' => 'line',
                    'view_rates' => $retention_rates
                ),
                array(
                    'label' => __('New Users', 'growtype-analytics'),
                    'data' => $new_users,
                    'color' => '#2196f3',
                    'type' => 'line'
                )
            )
        );
    }
}
