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

class Growtype_Analytics_Admin_Paywall_Chart
{
    private $controller;

    public function __construct($controller)
    {
        $this->controller = $controller;
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
                    <button type="button" class="button analytics-chart-period-btn active" data-period="week" data-action="get_paywall_views_chart_data" data-chart="paywallViewsChart"><?php _e('One Week', 'growtype-analytics'); ?></button>
                    <button type="button" class="button analytics-chart-period-btn" data-period="month" data-action="get_paywall_views_chart_data" data-chart="paywallViewsChart"><?php _e('One Month', 'growtype-analytics'); ?></button>
                </div>
            </div>
            
            <div class="analytics-chart-container">
                <canvas id="paywallViewsChart"></canvas>
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
    public function ajax_get_paywall_views_chart_data()
    {
        check_ajax_referer('growtype_analytics_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized', 403);
            return;
        }

        $period = isset($_POST['period']) ? sanitize_text_field($_POST['period']) : 'week';
        $days = ($period === 'month') ? 30 : 7;

        $cache_key = 'growtype_paywall_chart_' . $period;
        $data = get_transient($cache_key);

        if (false === $data) {
            $data = $this->get_paywall_views_data($days);
            set_transient($cache_key, $data, GROWTYPE_ANALYTICS_CACHE_TIME);
        }

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

        $start_date = date('Y-m-d', strtotime("-" . ($days - 1) . " days"));
        $end_date = date('Y-m-d');

        // Batch fetch all data
        $batched_users = $this->controller->metrics->get_batched_user_data($start_date);
        $batched_wc = $this->fetch_batched_wc_data($start_date);
        $batched_posthog = $this->fetch_batched_posthog_data($start_date, $end_date);

        // Generate date range and map data
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $labels[] = date('M d', strtotime($date));

            // New Users
            $total_users_count = isset($batched_users[$date]) ? (int) $batched_users[$date] : 0;
            $total_users[] = $total_users_count;

            // Paywall Views (PostHog)
            $paywall_views_count = isset($batched_posthog['pageviews'][$date]) ? (int) $batched_posthog['pageviews'][$date] : 0;
            $paywall_viewers[] = $paywall_views_count;

            // Calculate view rate
            $view_rate = $total_users_count > 0 ? round(($paywall_views_count / $total_users_count) * 100, 2) : 0;
            $view_rates[] = $view_rate;

            // PostHog Purchases
            $posthog_purchase_count = isset($batched_posthog['purchases'][$date]) ? (int) $batched_posthog['purchases'][$date] : 0;
            $posthog_purchases[] = $posthog_purchase_count;

            // Calculate PostHog conversion rate
            $posthog_conv_rate = $total_users_count > 0 ? round(($posthog_purchase_count / $total_users_count) * 100, 2) : 0;
            $posthog_conversion_rates[] = $posthog_conv_rate;

            // WooCommerce Purchases
            $wc_purchase_count = isset($batched_wc[$date]) ? (int) $batched_wc[$date] : 0;
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
                    'view_rates' => $view_rates
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



    private function fetch_batched_wc_data($start_date)
    {
        if (!class_exists('WooCommerce')) {
            return array();
        }

        global $wpdb;
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(p.post_date) as order_date, COUNT(DISTINCT p.ID) as count
             FROM {$wpdb->posts} p
             WHERE p.post_type = 'shop_order'
             AND p.post_status IN ('wc-completed', 'wc-processing')
             AND p.post_date >= %s
             GROUP BY order_date",
            $start_date
        ), ARRAY_A);

        $data = array();
        foreach ($results as $row) {
            $data[$row['order_date']] = $row['count'];
        }
        return $data;
    }

    private function fetch_batched_posthog_data($start_date, $end_date)
    {
        $api_key = get_option('growtype_analytics_posthog_details_api_key');
        $project_id = get_option('growtype_analytics_posthog_details_project_id');
        $host = get_option('growtype_analytics_posthog_details_host', 'https://eu.i.posthog.com');

        $data = array('pageviews' => array(), 'purchases' => array());

        if (empty($api_key) || empty($project_id)) {
            return $data;
        }

        $host = rtrim($host, '/');

        $url = add_query_arg(array(
            'insight'   => 'TRENDS',
            'interval'  => 'day',
            'date_from' => $start_date,
            'date_to'   => $end_date,
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
                ),
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
            'timeout' => 30
        ));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return $data;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['result'])) {
            return $data;
        }

        // Map results back to dates
        foreach ($body['result'] as $index => $series) {
            $key = ($index === 0) ? 'pageviews' : 'purchases';
            if (isset($series['data']) && isset($series['labels'])) {
                foreach ($series['labels'] as $i => $label) {
                    // PostHog labels are like "18-Mar-2026" or similar, but the 'days' array is also available
                    if (isset($series['days'][$i])) {
                        $date = $series['days'][$i];
                        $data[$key][$date] = $series['data'][$i];
                    }
                }
            }
        }

        return $data;
    }
}
