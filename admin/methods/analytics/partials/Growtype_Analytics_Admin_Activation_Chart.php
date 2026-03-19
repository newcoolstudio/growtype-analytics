<?php

/**
 * Analytics Admin Page Activation Rate Chart Partial
 *
 * Handles the activation rate chart rendering and data fetching
 * Shows % of new users who send ≥3 messages
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/admin/methods/analytics
 */

class Growtype_Analytics_Admin_Activation_Chart
{
    private $controller;

    public function __construct($controller)
    {
        $this->controller = $controller;
        add_action('wp_ajax_get_activation_rate_chart_data', array($this, 'ajax_get_activation_rate_chart_data'));
    }

    /**
     * Render the chart section
     */
    public function render()
    {
        ?>
        <div class="analytics-section">
            <div class="analytics-section-header">
                <h2><?php _e('Activation Rate', 'growtype-analytics'); ?></h2>
                <p class="description"><?php _e('% of new users who send ≥3 messages', 'growtype-analytics'); ?></p>
                <div class="analytics-chart-controls">
                    <button type="button" class="button analytics-chart-period-btn active" data-period="week" data-action="get_activation_rate_chart_data" data-chart="activationRateChart"><?php _e('One Week', 'growtype-analytics'); ?></button>
                    <button type="button" class="button analytics-chart-period-btn" data-period="month" data-action="get_activation_rate_chart_data" data-chart="activationRateChart"><?php _e('One Month', 'growtype-analytics'); ?></button>
                </div>
            </div>
            
            <div class="analytics-chart-container">
                <canvas id="activationRateChart"></canvas>
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
    public function ajax_get_activation_rate_chart_data()
    {
        check_ajax_referer('growtype_analytics_nonce', 'nonce');

        $period = isset($_POST['period']) ? sanitize_text_field($_POST['period']) : 'week';
        $days = ($period === 'month') ? 30 : 7;

        $cache_key = 'growtype_activation_chart_' . $period;
        $data = get_transient($cache_key);

        if (false === $data) {
            $data = $this->get_activation_rate_data($days);
            set_transient($cache_key, $data, GROWTYPE_ANALYTICS_CACHE_TIME);
        }

        wp_send_json_success(array(
            'chart_data' => $data
        ));
    }

    /**
     * Get activation rate data for the specified number of days
     */
    private function get_activation_rate_data($days)
    {
        global $wpdb;

        $labels = array();
        $activation_rates = array();
        $new_users_counts = array();
        $activated_users_counts = array();

        $start_date = date('Y-m-d', strtotime("-" . ($days - 1) . " days"));

        // Use centralized metrics for new users
        $batched_users = $this->controller->metrics->get_batched_user_data($start_date);

        // Batch activated users (>= 3 messages)
        $chat_users_table = $wpdb->prefix . 'growtype_chat_users';
        $chat_messages_table = $wpdb->prefix . 'growtype_chat_messages';
        
        $batched_activated = array();
        if ($this->controller->table_exists($chat_users_table) && 
            $this->controller->table_exists($chat_messages_table)) {
            
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT DATE(u.user_registered) as reg_date, COUNT(DISTINCT activated.user_id) as activated_count
                 FROM $wpdb->users u
                 INNER JOIN $chat_users_table cu ON cu.external_id = u.ID
                 INNER JOIN (
                     SELECT user_id
                     FROM $chat_messages_table
                     GROUP BY user_id
                     HAVING COUNT(*) >= 3
                 ) as activated ON activated.user_id = cu.id
                 WHERE u.user_registered >= %s
                 GROUP BY reg_date",
                $start_date
            ), OBJECT_K);
            
            $batched_activated = $results;
        }

        // Generate date range
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $labels[] = date('M d', strtotime($date));
            
            $new_users = $batched_users[$date] ?? 0;
            $new_users_counts[] = $new_users;

            $activated_row = $batched_activated[$date] ?? null;
            $activated_users = $activated_row ? (int)$activated_row->activated_count : 0;
            $activated_users_counts[] = $activated_users;

            // Calculate activation rate as percentage
            $activation_rate = $new_users > 0 ? round(($activated_users / $new_users) * 100, 2) : 0;
            $activation_rates[] = $activation_rate;
        }

        return array(
            'labels' => $labels,
            'datasets' => array(
                array(
                    'label' => __('Activation Rate (%)', 'growtype-analytics'),
                    'data' => $activation_rates,
                    'color' => '#00c853',
                    'type' => 'line'
                ),
                array(
                    'label' => __('New Users', 'growtype-analytics'),
                    'data' => $new_users_counts,
                    'color' => '#2196f3',
                    'type' => 'bar'
                ),
                array(
                    'label' => __('Activated Users', 'growtype-analytics'),
                    'data' => $activated_users_counts,
                    'color' => '#4caf50',
                    'type' => 'bar'
                )
            )
        );
    }
}
