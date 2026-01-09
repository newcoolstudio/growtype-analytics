<?php

/**
 * Analytics Admin Page Paywall Views Chart Partial
 *
 * Handles the paywall views chart rendering and data fetching
 * Shows how many users actually see pricing
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/admin/methods/analytics
 */

class Growtype_Analytics_Admin_Page_Paywall_Chart
{
    public function __construct()
    {
        add_action('wp_ajax_get_paywall_views_chart_data', array($this, 'ajax_get_paywall_views_chart_data'));
    }

    /**
     * Render the chart section
     */
    public function render()
    {
        ?>
        <div class="analytics-section">
            <div class="analytics-section-header">
                <h2><?php _e('Paywall Views', 'growtype-analytics'); ?></h2>
                <p class="description"><?php _e('How many users actually see pricing', 'growtype-analytics'); ?></p>
                <div class="analytics-chart-controls">
                    <button type="button" class="button paywall-chart-period-btn active" data-period="week"><?php _e('One Week', 'growtype-analytics'); ?></button>
                    <button type="button" class="button paywall-chart-period-btn" data-period="month"><?php _e('One Month', 'growtype-analytics'); ?></button>
                </div>
            </div>
            
            <div class="analytics-chart-container">
                <canvas id="paywallViewsChart"></canvas>
                <div class="paywall-chart-loading-overlay" style="display: none;">
                    <span class="spinner is-active"></span>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX handler to get chart data
     */
    public function ajax_get_paywall_views_chart_data()
    {
        check_ajax_referer('growtype_analytics_nonce', 'nonce');

        $period = isset($_POST['period']) ? sanitize_text_field($_POST['period']) : 'week';
        $days = ($period === 'month') ? 30 : 7;

        $data = $this->get_paywall_views_data($days);

        wp_send_json_success(array(
            'chart_data' => $data
        ));
    }

    /**
     * Get paywall views data for the specified number of days
     */
    private function get_paywall_views_data($days)
    {
        global $wpdb;

        $labels = array();
        $total_users = array();
        $paywall_viewers = array();
        $view_rates = array();
        $posthog_purchases = array();
        $wc_purchases = array();
        $posthog_conversion_rates = array();
        $wc_conversion_rates = array();

        // Generate date range
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $labels[] = date('M d', strtotime($date));
            
            // Count new users registered on that day
            $total_users_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(ID) 
                 FROM $wpdb->users 
                 WHERE DATE(user_registered) = %s",
                $date
            ));

            $total_users_count = (int) ($total_users_count ?: 0);
            $total_users[] = $total_users_count;

            // Count users who viewed the /plans/ page on that day from PostHog
            $paywall_views_count = $this->get_posthog_plans_pageviews($date);

            $paywall_views_count = (int) ($paywall_views_count ?: 0);
            $paywall_viewers[] = $paywall_views_count;

            // Calculate view rate as percentage
            $view_rate = $total_users_count > 0 ? round(($paywall_views_count / $total_users_count) * 100, 2) : 0;
            $view_rates[] = $view_rate;

            // Get PostHog purchase events
            $posthog_purchase_count = $this->get_posthog_purchases($date);
            $posthog_purchases[] = $posthog_purchase_count;
            
            // Calculate PostHog conversion rate
            $posthog_conv_rate = $total_users_count > 0 ? round(($posthog_purchase_count / $total_users_count) * 100, 2) : 0;
            $posthog_conversion_rates[] = $posthog_conv_rate;

            // Get WooCommerce purchases
            $wc_purchase_count = $this->get_woocommerce_purchases($date);
            $wc_purchases[] = $wc_purchase_count;
            
            // Calculate WooCommerce conversion rate
            $wc_conv_rate = $total_users_count > 0 ? round(($wc_purchase_count / $total_users_count) * 100, 2) : 0;
            $wc_conversion_rates[] = $wc_conv_rate;
        }

        return array(
            'labels' => $labels,
            'datasets' => array(
                array(
                    'label' => __('Paywall Viewers', 'growtype-analytics'),
                    'data' => $paywall_viewers,
                    'color' => '#4caf50',
                    'type' => 'line',
                    'view_rates' => $view_rates  // Include view rates for tooltip
                ),
                array(
                    'label' => __('PostHog Purchases', 'growtype-analytics'),
                    'data' => $posthog_purchases,
                    'color' => '#9c27b0',
                    'type' => 'line',
                    'view_rates' => $posthog_conversion_rates
                ),
                array(
                    'label' => __('WooCommerce Purchases', 'growtype-analytics'),
                    'data' => $wc_purchases,
                    'color' => '#ff5722',
                    'type' => 'line',
                    'view_rates' => $wc_conversion_rates
                ),
                array(
                    'label' => __('New Users', 'growtype-analytics'),
                    'data' => $total_users,
                    'color' => '#2196f3',
                    'type' => 'line'
                )
            )
        );
    }

    /**
     * Get PostHog pageview count for /plans/ URL on a specific date
     */
    private function get_posthog_plans_pageviews($date)
    {
        $api_key = get_option('growtype_analytics_posthog_details_api_key');
        $project_id = get_option('growtype_analytics_posthog_details_project_id');
        $host = get_option('growtype_analytics_posthog_details_host', 'https://eu.i.posthog.com');

        if (empty($api_key) || empty($project_id)) {
            return 0;
        }

        $host = rtrim($host, '/');
        
        // Query for pageviews on the specific date with /plans/ in the URL
        $url = add_query_arg(array(
            'insight'   => 'TRENDS',
            'interval'  => 'day',
            'date_from' => $date,
            'date_to'   => $date,
            'events'    => json_encode(array(
                array(
                    'id'   => '$pageview',
                    'math' => 'dau',
                    'properties' => array(
                        array(
                            'key' => '$current_url',
                            'value' => '/plans/',
                            'operator' => 'icontains',
                            'type' => 'event'
                        )
                    )
                )
            ))
        ), $host . '/api/projects/' . $project_id . '/insights/trend/');

        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'timeout' => 15
        ));

        if (is_wp_error($response)) {
            return 0;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code !== 200 || empty($data['result']) || !isset($data['result'][0]['data'])) {
            return 0;
        }

        $result_data = $data['result'][0]['data'];
        
        // Return the count for the specific date (should be first/only element)
        return isset($result_data[0]) ? (int) $result_data[0] : 0;
    }

    /**
     * Get PostHog purchase events count for a specific date
     */
    private function get_posthog_purchases($date)
    {
        $api_key = get_option('growtype_analytics_posthog_details_api_key');
        $project_id = get_option('growtype_analytics_posthog_details_project_id');
        $host = get_option('growtype_analytics_posthog_details_host', 'https://eu.i.posthog.com');

        if (empty($api_key) || empty($project_id)) {
            return 0;
        }

        $host = rtrim($host, '/');
        
        // Query for purchase events on the specific date
        $url = add_query_arg(array(
            'insight'   => 'TRENDS',
            'interval'  => 'day',
            'date_from' => $date,
            'date_to'   => $date,
            'events'    => json_encode(array(
                array(
                    'id'   => 'growtype_analytics_growtype_wc_purchase',
                    'math' => 'dau'
                )
            ))
        ), $host . '/api/projects/' . $project_id . '/insights/trend/');

        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'timeout' => 15
        ));

        if (is_wp_error($response)) {
            return 0;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code !== 200 || empty($data['result']) || !isset($data['result'][0]['data'])) {
            return 0;
        }

        $result_data = $data['result'][0]['data'];
        
        return isset($result_data[0]) ? (int) $result_data[0] : 0;
    }

    /**
     * Get WooCommerce purchase count for a specific date
     */
    private function get_woocommerce_purchases($date)
    {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return 0;
        }

        global $wpdb;

        // Count completed orders on that date
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID)
             FROM {$wpdb->posts} p
             WHERE p.post_type = 'shop_order'
             AND p.post_status IN ('wc-completed', 'wc-processing')
             AND DATE(p.post_date) = %s",
            $date
        ));

        return (int) ($count ?: 0);
    }
}
