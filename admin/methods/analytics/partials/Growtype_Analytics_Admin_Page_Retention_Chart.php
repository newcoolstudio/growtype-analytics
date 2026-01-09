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

class Growtype_Analytics_Admin_Page_Retention_Chart
{
    public function __construct()
    {
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
                    <button type="button" class="button retention-chart-period-btn active" data-period="week"><?php _e('One Week', 'growtype-analytics'); ?></button>
                    <button type="button" class="button retention-chart-period-btn" data-period="month"><?php _e('One Month', 'growtype-analytics'); ?></button>
                </div>
            </div>
            
            <div class="analytics-chart-container">
                <canvas id="userRetentionChart"></canvas>
                <div class="retention-chart-loading-overlay" style="display: none;">
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

        $data = $this->get_user_retention_data($days);

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

        // Generate date range
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $labels[] = date('M d', strtotime($date));
            
            // Count new users registered on that day
            $new_users_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(ID) 
                 FROM $wpdb->users 
                 WHERE DATE(user_registered) = %s",
                $date
            ));

            $new_users_count = (int) ($new_users_count ?: 0);
            $new_users[] = $new_users_count;

            // Count how many of those users returned (had activity after registration day)
            $returned_count = 0;
            
            if ($new_users_count > 0) {
                // Get user IDs registered on this date
                $user_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT ID 
                     FROM $wpdb->users 
                     WHERE DATE(user_registered) = %s",
                    $date
                ));

                if (!empty($user_ids)) {
                    $user_ids_string = implode(',', array_map('intval', $user_ids));
                    
                    // Check for return activity via chat messages sent after registration day
                    $chat_messages_table = $wpdb->prefix . 'growtype_chat_messages';
                    $chat_users_table = $wpdb->prefix . 'growtype_chat_users';
                    
                    $chat_messages_exists = $wpdb->get_var("SHOW TABLES LIKE '$chat_messages_table'") == $chat_messages_table;
                    $chat_users_exists = $wpdb->get_var("SHOW TABLES LIKE '$chat_users_table'") == $chat_users_table;
                    
                    if ($chat_messages_exists && $chat_users_exists) {
                        // Count users who sent messages after their registration date
                        $returned_count = $wpdb->get_var(
                            "SELECT COUNT(DISTINCT cu.external_id)
                             FROM $chat_users_table cu
                             INNER JOIN $chat_messages_table cm ON cu.id = cm.user_id
                             WHERE cu.external_id IN ($user_ids_string)
                             AND DATE(cm.created_at) > %s",
                            $date
                        );
                    }
                }
            }

            $returned_count = (int) ($returned_count ?: 0);
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
