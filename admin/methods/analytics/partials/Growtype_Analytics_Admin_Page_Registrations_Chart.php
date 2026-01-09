<?php

/**
 * Analytics Admin Page Registrations Chart Partial
 *
 * Handles the daily registrations chart rendering and data fetching
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/admin/methods/analytics
 */

class Growtype_Analytics_Admin_Page_Registrations_Chart
{
    public function __construct()
    {
        add_action('wp_ajax_get_daily_registrations_chart_data', array($this, 'ajax_get_daily_registrations_chart_data'));
    }

    /**
     * Render the chart section
     */
    public function render()
    {
        ?>
        <div class="analytics-section">
            <div class="analytics-section-header">
                <h2><?php _e('Daily Registrations', 'growtype-analytics'); ?></h2>
                <div class="analytics-chart-controls">
                    <button type="button" class="button registrations-chart-period-btn active" data-period="week"><?php _e('One Week', 'growtype-analytics'); ?></button>
                    <button type="button" class="button registrations-chart-period-btn" data-period="month"><?php _e('One Month', 'growtype-analytics'); ?></button>
                </div>
            </div>
            
            <div class="analytics-chart-container">
                <canvas id="dailyRegistrationsChart"></canvas>
                <div class="registrations-chart-loading-overlay" style="display: none;">
                    <span class="spinner is-active"></span>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX handler to get chart data
     */
    public function ajax_get_daily_registrations_chart_data()
    {
        check_ajax_referer('growtype_analytics_nonce', 'nonce');

        $period = isset($_POST['period']) ? sanitize_text_field($_POST['period']) : 'week';
        $days = ($period === 'month') ? 30 : 7;

        $debug = array();
        
        // Get registration data from PostHog
        $posthog_registrations = $this->get_posthog_registrations_data($days, $debug);

        // Get registration data from WP local DB
        $wp_registrations = $this->get_daily_registrations_data($days);

        // Get unique users data from PostHog
        $posthog_unique_users = $this->get_posthog_unique_users_data($days, $debug);

        // Get unique users data from local DB
        $wp_unique_users = $this->get_unique_users_data($days);

        wp_send_json_success(array(
            'chart_data' => array(
                'labels' => $wp_registrations['labels'], // Use WP labels as base
                'datasets' => array(
                    array(
                        'label' => __('WordPress Registrations', 'growtype-analytics'),
                        'data' => $wp_registrations['values'],
                        'color' => '#f5576c'
                    ),
                    array(
                        'label' => __('PostHog Registrations', 'growtype-analytics'),
                        'data' => $posthog_registrations['values'],
                        'color' => '#e91e63'
                    ),
                    // array(
                    //     'label' => __('WordPress Unique Users', 'growtype-analytics'),
                    //     'data' => $wp_unique_users['values'],
                    //     'color' => '#764ba2'
                    // ),
                    array(
                        'label' => __('PostHog Unique Users', 'growtype-analytics'),
                        'data' => $posthog_unique_users['values'],
                        'color' => '#1d2327'
                    )
                )
            ),
            'debug' => $debug
        ));
    }

    /**
     * Get registration data from PostHog API
     */
    private function get_posthog_registrations_data($days, &$debug)
    {
        $api_key = get_option('growtype_analytics_posthog_details_api_key');
        $project_id = get_option('growtype_analytics_posthog_details_project_id');
        $host = get_option('growtype_analytics_posthog_details_host', 'https://eu.i.posthog.com');

        $debug['config'] = array(
            'api_key_set' => !empty($api_key),
            'project_id'  => $project_id,
            'host'        => $host
        );

        if (empty($api_key) || empty($project_id)) {
            $debug['error'] = 'PostHog API Key or Project ID is missing in settings.';
            return array('labels' => array(), 'values' => array());
        }

        $host = rtrim($host, '/');
        $date_from = '-' . ($days - 1) . 'd';

        // Use the specific event name provided by the user
        $url = add_query_arg(array(
            'insight'   => 'TRENDS',
            'interval'  => 'day',
            'date_from' => $date_from,
            'events'    => json_encode(array(
                array(
                    'id'   => 'growtype_analytics_wp_user_registered',
                    'math' => 'dau'
                )
            ))
        ), $host . '/api/projects/' . $project_id . '/insights/trend/');

        $debug['request_url'] = $url;

        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'timeout' => 15
        ));

        if (is_wp_error($response)) {
            $debug['error'] = 'WP_Error: ' . $response->get_error_message();
            return array('labels' => array(), 'values' => array());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $debug['response_code'] = $status_code;
        $debug['raw_body'] = substr($body, 0, 1000);

        $data = json_decode($body, true);

        if ($status_code !== 200) {
            $debug['error'] = 'API returned non-200 status code.';
            return array('labels' => array(), 'values' => array());
        }

        if (empty($data['result']) || !isset($data['result'][0]['data'])) {
            $debug['error'] = 'PostHog Data structure invalid or empty result.';
            return array('labels' => array(), 'values' => array());
        }

        $result_data = $data['result'][0]['data'];
        $result_labels = $data['result'][0]['labels'];

        $labels = array();
        $values = array();

        foreach ($result_labels as $index => $label) {
            $labels[] = date('M d', strtotime($label));
            $values[] = (int) ($result_data[$index] ?? 0);
        }

        return array(
            'labels' => $labels,
            'values' => $values
        );
    }

    /**
     * Get daily registrations data for the specified number of days
     */
    private function get_daily_registrations_data($days)
    {
        global $wpdb;

        $labels = array();
        $values = array();

        // Generate date range
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $labels[] = date('M d', strtotime($date));
            
            // Count new users registered on that day
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(ID) 
                 FROM $wpdb->users 
                 WHERE DATE(user_registered) = %s",
                $date
            ));
            
            $values[] = (int) ($count ?: 0);
        }

        return array(
            'labels' => $labels,
            'values' => $values
        );
    }

    /**
     * Get unique users data from PostHog API
     */
    private function get_posthog_unique_users_data($days, &$debug)
    {
        $api_key = get_option('growtype_analytics_posthog_details_api_key');
        $project_id = get_option('growtype_analytics_posthog_details_project_id');
        $host = get_option('growtype_analytics_posthog_details_host', 'https://eu.i.posthog.com');

        if (empty($api_key) || empty($project_id)) {
            return array('labels' => array(), 'values' => array());
        }

        $host = rtrim($host, '/');
        $date_from = '-' . ($days - 1) . 'd';

        $url = add_query_arg(array(
            'insight'   => 'TRENDS',
            'interval'  => 'day',
            'date_from' => $date_from,
            'events'    => json_encode(array(
                array(
                    'id'   => '$pageview',
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
            return array('labels' => array(), 'values' => array());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code !== 200 || empty($data['result']) || !isset($data['result'][0]['data'])) {
            return array('labels' => array(), 'values' => array());
        }

        $result_data = $data['result'][0]['data'];
        $result_labels = $data['result'][0]['labels'];

        $labels = array();
        $values = array();

        foreach ($result_labels as $index => $label) {
            $labels[] = date('M d', strtotime($label));
            $values[] = (int) ($result_data[$index] ?? 0);
        }

        return array(
            'labels' => $labels,
            'values' => $values
        );
    }

    /**
     * Get unique users data from local database (fallback)
     */
    private function get_unique_users_data($days)
    {
        global $wpdb;

        $labels = array();
        $values = array();

        // Generate date range
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $labels[] = date('M d', strtotime($date));
            
            // Fallback to counting new users registered on that day
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(ID) 
                 FROM $wpdb->users 
                 WHERE DATE(user_registered) = %s",
                $date
            ));
            
            $values[] = (int) ($count ?: 0);
        }

        return array(
            'labels' => $labels,
            'values' => $values
        );
    }
}
