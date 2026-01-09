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

class Growtype_Analytics_Admin_Page_Activation_Chart
{
    public function __construct()
    {
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
                    <button type="button" class="button activation-chart-period-btn active" data-period="week"><?php _e('One Week', 'growtype-analytics'); ?></button>
                    <button type="button" class="button activation-chart-period-btn" data-period="month"><?php _e('One Month', 'growtype-analytics'); ?></button>
                </div>
            </div>
            
            <div class="analytics-chart-container">
                <canvas id="activationRateChart"></canvas>
                <div class="activation-chart-loading-overlay" style="display: none;">
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

        $data = $this->get_activation_rate_data($days);

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

        // Generate date range
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $labels[] = date('M d', strtotime($date));
            
            // Count new users registered on that day
            $new_users = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(ID) 
                 FROM $wpdb->users 
                 WHERE DATE(user_registered) = %s",
                $date
            ));

            $new_users = (int) ($new_users ?: 0);
            $new_users_counts[] = $new_users;

            // Count how many of those users sent >= 3 messages
            // Need to join: wp_users -> wp_growtype_chat_users (via external_id) -> wp_growtype_chat_messages
            $activated_users = 0;
            
            if ($new_users > 0) {
                // Get user IDs registered on this date
                $user_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT ID 
                     FROM $wpdb->users 
                     WHERE DATE(user_registered) = %s",
                    $date
                ));


                if (!empty($user_ids)) {
                    $user_ids_string = implode(',', array_map('intval', $user_ids));
                    
                    // Check if tables exist
                    $chat_users_table = $wpdb->prefix . 'growtype_chat_users';
                    $chat_messages_table = $wpdb->prefix . 'growtype_chat_messages';
                    
                    $chat_users_exists = $wpdb->get_var("SHOW TABLES LIKE '$chat_users_table'") == $chat_users_table;
                    $chat_messages_exists = $wpdb->get_var("SHOW TABLES LIKE '$chat_messages_table'") == $chat_messages_table;
                    
                    if ($chat_users_exists && $chat_messages_exists) {
                        // First, get chat user IDs for these WordPress users
                        $chat_user_ids = $wpdb->get_col(
                            "SELECT id 
                             FROM $chat_users_table 
                             WHERE external_id IN ($user_ids_string)"
                        );
                        
                        if (!empty($chat_user_ids)) {
                            $chat_user_ids_string = implode(',', array_map('intval', $chat_user_ids));
                            
                            // Count how many of these chat users sent >= 3 messages
                            // Note: wp_growtype_chat_messages contains user messages only
                            $query = "
                                SELECT COUNT(*) as activated_count
                                FROM (
                                    SELECT user_id
                                    FROM $chat_messages_table
                                    WHERE user_id IN ($chat_user_ids_string)
                                    GROUP BY user_id
                                    HAVING COUNT(*) >= 3
                                ) as activated_users
                            ";
                            
                            $result = $wpdb->get_var($query);
                            
                            if ($result !== null) {
                                $activated_users = (int) $result;
                            }
                        }
                    }
                }
            }

            $activated_users = (int) ($activated_users ?: 0);
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
