<?php

/**
 * Posthog Service
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/admin/methods/analytics/services
 */

class Growtype_Analytics_Admin_Posthog_Service
{
    private $controller;

    public function __construct($controller)
    {
        $this->controller = $controller;
        add_action('wp_ajax_growtype_analytics_get_demographics', array($this, 'ajax_get_demographics'));
    }

    public function ajax_get_demographics()
    {
        if (session_id()) {
            session_write_close();
        }

        check_ajax_referer('growtype-analytics', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $date_from = isset($_POST['date_from']) ? sanitize_text_field(wp_unslash($_POST['date_from'])) : date('Y-m-d', strtotime('-30 days'));
        $date_to = isset($_POST['date_to']) ? sanitize_text_field(wp_unslash($_POST['date_to'])) : date('Y-m-d');
        $days = max(1, (int)((strtotime($date_to) - strtotime($date_from)) / 86400));

        $countries = $this->get_demographics_breakdown('$geoip_country_name', 'event', $days);
        $devices = $this->get_demographics_breakdown('$device_type', 'event', $days);
        $os_data = $this->get_demographics_breakdown('$os', 'event', $days);
        $genders = $this->get_demographics_breakdown('gender', 'person', $days);
        $ages = $this->get_demographics_breakdown('age', 'person', $days);

        $paged = isset($_POST['paged']) ? max(1, (int)$_POST['paged']) : 1;
        $per_page = Growtype_Analytics_Admin_Table_Renderer::DEFAULT_PER_PAGE;
        $offset = ($paged - 1) * $per_page;

        $format = function($data) {
            return $this->format_demographics_for_table($data);
        };

        $prep = function($raw, $headers, $type = '') use ($format, $offset, $per_page, $paged, $days) {
            if (empty($raw) && $type === 'gender') $raw = $this->get_db_gender_distribution($days);
            if (empty($raw) && $type === 'age') $raw = $this->get_db_age_distribution($days);
            
            $formatted_all = $format($raw);
            $total = count($formatted_all);
            $paged_data = array_slice($formatted_all, $offset, $per_page);
            return $this->format_markup_table($headers, $paged_data, $total, $per_page, $paged);
        };

        wp_send_json_success(array(
            'countries' => $prep($countries, array('Country', 'Active Users')),
            'genders' => $prep($genders, array('Gender', 'Active Users'), 'gender'),
            'ages' => $prep($ages, array('Age', 'Active Users'), 'age'),
            'devices' => $prep($devices, array('Device', 'Active Users')),
            'os' => $prep($os_data, array('OS', 'Active Users')),
            'has_genders' => !empty($genders),
            'has_ages' => !empty($ages)
        ));
    }

    private function format_demographics_for_table($raw_data)
    {
        if (empty($raw_data)) return array();
        
        $formatted = array();
        foreach (array_slice($raw_data, 0, 15) as $row) {
            if (empty($row['label'])) continue;
            
            $raw_label = (string)$row['label'];
            if ($raw_label === '$$_posthog_breakdown_other_$$') {
                $label = __('Other', 'growtype-analytics');
                $is_other = true;
            } else {
                $label = ucwords(strtolower($raw_label));
                $is_other = false;
            }

            if ($label == 'Unknown' || $label == 'Null' || $label == 'None' || $raw_label == '$$_posthog_breakdown_null_$$') continue;

            $formatted[] = array(
                'label' => $label,
                'users' => $this->controller->format_number($row['count']),
                'count' => (int)$row['count'],
                'is_other' => $is_other
            );
        }

        // Move "Other" to the end
        usort($formatted, function ($a, $b) {
            if ($a['is_other']) return 1;
            if ($b['is_other']) return -1;
            return $b['count'] <=> $a['count'];
        });

        // Map down to only the columns we want to display as columns in the table renderer
        return array_map(function($item) {
            return array(
                'label' => $item['label'],
                'users' => $item['users']
            );
        }, $formatted);
    }

    private function format_markup_table($headers, $rows, $total = null, $per_page = 50, $paged = 1)
    {
        ob_start();
        $this->controller->table_renderer->render($headers, $rows, $total, $per_page, $paged);
        return ob_get_clean();
    }

    /**
     * Get total unique users from PostHog for a specific period
     */
    public function get_total_unique_users($date_from = '', $date_to = '')
    {
        $api_key = get_option('growtype_analytics_posthog_details_api_key');
        $project_id = get_option('growtype_analytics_posthog_details_project_id');
        $host = get_option('growtype_analytics_posthog_details_host', 'https://eu.i.posthog.com');

        if (empty($api_key) || empty($project_id)) {
            return 0;
        }

        if (empty($date_from)) $date_from = date('Y-m-d', strtotime('-30 days'));
        if (empty($date_to)) $date_to = date('Y-m-d');

        $transient_key = 'gt_posthog_total_uu_' . md5($date_from . $date_to);
        $cached = get_transient($transient_key);
        if ($cached !== false) return (int)$cached;

        $host = rtrim($host, '/');
        $url = add_query_arg(array(
            'insight' => 'TRENDS',
            'date_from' => $date_from,
            'date_to' => $date_to,
            'events' => json_encode(array(
                array(
                    'id' => '$pageview',
                    'math' => 'dau' // DAU with Totals gives overall unique users in period
                )
            )),
            'display' => 'BoldNumber'
        ), $host . '/api/projects/' . $project_id . '/insights/trend/');

        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'timeout' => 15
        ));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return 0;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $total = 0;
        if (!empty($body['result'])) {
            // Find the aggregation value
            $total = (int)($body['result'][0]['aggregated_value'] ?? 0);
        }

        set_transient($transient_key, $total, HOUR_IN_SECONDS);

        return $total;
    }

    /**
     * Get unique users trend data
     */
    public function get_unique_users_trend($days)
    {
        $api_key = get_option('growtype_analytics_posthog_details_api_key');
        $project_id = get_option('growtype_analytics_posthog_details_project_id');
        $host = get_option('growtype_analytics_posthog_details_host', 'https://eu.i.posthog.com');

        if (empty($api_key) || empty($project_id)) {
            return array('labels' => array(), 'values' => array());
        }

        $transient_key = 'growtype_analytics_posthog_dau_' . $days;
        $cached = get_transient($transient_key);
        if ($cached !== false) {
            return $cached;
        }

        $host = rtrim($host, '/');
        $date_from = '-' . ($days - 1) . 'd';

        $url = add_query_arg(array(
            'insight' => 'TRENDS',
            'interval' => 'day',
            'date_from' => $date_from,
            'events' => json_encode(array(
                    array(
                    'id' => '$pageview',
                    'math' => 'dau'
                    )
            ))
        ), $host . '/api/projects/' . $project_id . '/insights/trend/');

        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'timeout' => 15
        ));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return array('labels' => array(), 'values' => array());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['result']) || !isset($body['result'][0]['data'])) {
            return array('labels' => array(), 'values' => array());
        }

        $result_data = $body['result'][0]['data'];
        $result_labels = $body['result'][0]['labels'];

        $labels = array();
        $values = array();

        foreach ($result_labels as $index => $label) {
            $labels[] = date('M d', strtotime($label));
            $values[] = (int)($result_data[$index] ?? 0);
        }

        $result = array(
            'labels' => $labels,
            'values' => $values
        );

        set_transient($transient_key, $result, HOUR_IN_SECONDS);

        return $result;
    }

    /**
     * Get pageview data
     */
    public function get_pageview_data($days)
    {
        $api_key = get_option('growtype_analytics_posthog_details_api_key');
        $project_id = get_option('growtype_analytics_posthog_details_project_id');
        $host = get_option('growtype_analytics_posthog_details_host', 'https://eu.i.posthog.com');

        if (empty($api_key) || empty($project_id)) {
            return array('labels' => array(), 'values' => array());
        }

        $transient_key = 'growtype_analytics_posthog_pageviews_' . $days;
        $cached = get_transient($transient_key);
        if ($cached !== false) {
            return $cached;
        }

        $host = rtrim($host, '/');
        $date_from = '-' . ($days - 1) . 'd';

        $url = add_query_arg(array(
            'insight' => 'TRENDS',
            'interval' => 'day',
            'date_from' => $date_from,
            'events' => json_encode(array(
                array('id' => '$pageview')
            ))
        ), $host . '/api/projects/' . $project_id . '/insights/trend/');

        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'timeout' => 15
        ));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return array('labels' => array(), 'values' => array());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['result']) || !isset($body['result'][0]['data'])) {
            return array('labels' => array(), 'values' => array());
        }

        $result = array(
            'labels' => $body['result'][0]['labels'],
            'values' => array_map('intval', $body['result'][0]['data']),
        );

        set_transient($transient_key, $result, HOUR_IN_SECONDS);

        return $result;
    }

    public function is_enabled()
    {
        $api_key = get_option('growtype_analytics_posthog_details_api_key');
        $project_id = get_option('growtype_analytics_posthog_details_project_id');
        return !empty($api_key) && !empty($project_id);
    }

    /**
     * Get unique users growth (WoW)
     */
    public function get_uu_growth($days = 7)
    {
        $date_from_current = date('Y-m-d', strtotime('-' . $days . ' days'));
        $date_to_current = date('Y-m-d');

        $date_from_prev = date('Y-m-d', strtotime('-' . ($days * 2) . ' days'));
        $date_to_prev = date('Y-m-d', strtotime('-' . ($days + 1) . ' days'));

        $current = $this->get_total_unique_users($date_from_current, $date_to_current);
        $previous = $this->get_total_unique_users($date_from_prev, $date_to_prev);

        $growth = 0;
        if ($previous > 0) {
            $growth = (($current - $previous) / $previous) * 100;
        } elseif ($current > 0) {
            $growth = 100;
        }

        return array(
            'current' => $current,
            'previous' => $previous,
            'growth' => round($growth, 1)
        );
    }

    /**
     * Get aggregated demographic breakdown from PostHog
     * 
     * @param string $property Property name (e.g., '$geoip_country_name', 'gender')
     * @param string $type Breakdown type ('event' or 'person')
     * @param int $days Number of days to look back
     * @return array Array of associative arrays with 'label' and 'count'
     */
    public function get_demographics_breakdown($property, $type = 'event', $days = 30)
    {
        $api_key = get_option('growtype_analytics_posthog_details_api_key');
        $project_id = get_option('growtype_analytics_posthog_details_project_id');
        $host = get_option('growtype_analytics_posthog_details_host', 'https://eu.i.posthog.com');

        if (empty($api_key) || empty($project_id)) {
            return array();
        }

        $transient_key = 'gt_ph_demo_' . md5($property . '_' . $type . '_' . $days);
        $cached = get_transient($transient_key);
        if ($cached !== false) {
            return $cached;
        }

        $host = rtrim($host, '/');
        $date_from = '-' . ($days - 1) . 'd';

        $url = add_query_arg(array(
            'insight' => 'TRENDS',
            'date_from' => $date_from,
            'events' => json_encode(array(
                array('id' => '$pageview', 'math' => 'dau') // Count unique users per breakdown
            )),
            'breakdown' => $property,
            'breakdown_type' => $type
        ), $host . '/api/projects/' . $project_id . '/insights/trend/');

        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'timeout' => 15
        ));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return array();
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $results = array();

        if (!empty($body['result'])) {
            foreach ($body['result'] as $group) {
                // PostHog returns an array of data points per day for each group.
                // We sum them or take the aggregated_value if available.
                $count = 0;
                if (isset($group['aggregated_value'])) {
                    $count = (int)$group['aggregated_value'];
                } elseif (!empty($group['data'])) {
                    $count = array_sum($group['data']);
                }

                $label = !empty($group['breakdown_value']) ? $group['breakdown_value'] : 'Unknown';
                // Ignore '$none' or 'null' literally returned by API if property is strictly empty
                if ($label === '$none' || $label === 'null' || $label === 'None' || $label === '$$_posthog_breakdown_null_$$') {
                    $label = 'Unknown';
                }

                $results[$label] = ($results[$label] ?? 0) + $count;
            }
        }

        // Convert to indexed array and sort by count descending
        $formatted_results = array();
        foreach ($results as $label => $count) {
            if ($count > 0) {
                $formatted_results[] = array(
                    'label' => $label,
                    'count' => $count
                );
            }
        }

        usort($formatted_results, function ($a, $b) {
            return $b['count'] <=> $a['count'];
        });

        set_transient($transient_key, $formatted_results, HOUR_IN_SECONDS);

        return $formatted_results;
    }
    public function render_posthog_insights($date_from = '', $date_to = '')
    {
        if (!$this->is_enabled()) {
            return;
        }

        $total_uu = $this->get_total_unique_users($date_from, $date_to);
        $uu_wow = $this->get_uu_growth(7);
        $growth_sign = $uu_wow['growth'] > 0 ? '+' : '';
        
        $pinned = get_option('growtype_analytics_pinned_kpis', []);
        $is_pinned_uu = in_array('posthog_unique_users', $pinned);
        $is_pinned_growth = in_array('posthog_uu_growth_wow', $pinned);

        ?>
        <div class="analytics-section">
            <h2><?php _e('PostHog Insights', 'growtype-analytics'); ?></h2>
            <p class="description"><?php _e('Traffic metrics directly from PostHog.', 'growtype-analytics'); ?></p>

            <div class="analytics-scale-snapshot-grid">
                <?php
                $this->controller->decision_renderer->render_snapshot_card(
                    __('Total Unique Users', 'growtype-analytics'),
                    number_format_i18n($total_uu),
                    sprintf(__('Unique visitors between %s and %s', 'growtype-analytics'), $date_from, $date_to),
                    null,
                    __('Total number of unique users who performed at least one pageview during the selected period, according to PostHog.', 'growtype-analytics'),
                    'posthog_unique_users',
                    $is_pinned_uu
                );

                $this->controller->decision_renderer->render_snapshot_card(
                    __('UU Growth (WoW)', 'growtype-analytics'),
                    $growth_sign . $uu_wow['growth'] . '%',
                    sprintf(__('%d vs %d users (Goal: 10%%)', 'growtype-analytics'), $uu_wow['current'], $uu_wow['previous']),
                    $uu_wow['growth'] >= 10,
                    __('Weekly Unique User growth. Compares the last 7 days to the previous 7 days.', 'growtype-analytics'),
                    'posthog_uu_growth_wow',
                    $is_pinned_growth
                );
                ?>
            </div>
        </div>
        <?php
    }
    /**
     * Add posthog metrics to KPI meta map for pinning
     */
    public function add_to_kpi_meta_map($map, $metrics, $pd)
    {
        if (!$this->is_enabled()) {
            return $map;
        }

        $map['posthog_unique_users'] = array(
            'title' => __('Total Unique Users', 'growtype-analytics'),
            'value' => number_format_i18n($this->get_total_unique_users($metrics['date_from'] ?? '', $metrics['date_to'] ?? '')),
            'desc' => sprintf(__('Unique visitors (%sd)', 'growtype-analytics'), $pd),
            'tooltip' => __('Total number of unique users who performed at least one pageview during the selected period, according to PostHog.', 'growtype-analytics')
        );

        $uu_wow = $this->get_uu_growth(7);
        $growth_sign = $uu_wow['growth'] > 0 ? '+' : '';
        $map['posthog_uu_growth_wow'] = array(
            'title' => __('UU Growth (WoW)', 'growtype-analytics'),
            'value' => $growth_sign . $uu_wow['growth'] . '%',
            'desc' => sprintf(__('%d vs %d users', 'growtype-analytics'), $uu_wow['current'], $uu_wow['previous']),
            'is_good' => $uu_wow['growth'] >= 10,
            'tooltip' => __('Growth of unique users in the last 7 days compared to the previous 7 days. Goal: 10%+', 'growtype-analytics')
        );

        return $map;
    }
    /**
     * Get registration data from PostHog API
     */
    public function get_registrations_data($days, &$debug)
    {
        if (!$this->is_enabled()) {
            return array('labels' => array(), 'values' => array());
        }

        $api_key = get_option('growtype_analytics_posthog_details_api_key');
        $project_id = get_option('growtype_analytics_posthog_details_project_id');
        $host = get_option('growtype_analytics_posthog_details_host', 'https://eu.i.posthog.com');

        $host = rtrim($host, '/');
        $date_from = '-' . ($days - 1) . 'd';

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

        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'timeout' => 15
        ));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return array('labels' => array(), 'values' => array());
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($data['result']) || !isset($data['result'][0]['data'])) {
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
        return array('labels' => $labels, 'values' => $values);
    }

    public function get_event_counts_by_property($event_name, $property_name, $days = 30)
    {
        if (!$this->is_enabled()) {
            return array();
        }

        $api_key = get_option('growtype_analytics_posthog_details_api_key');
        $project_id = get_option('growtype_analytics_posthog_details_project_id');
        $host = get_option('growtype_analytics_posthog_details_host', 'https://eu.i.posthog.com');

        $transient_key = 'gt_ph_event_prop_' . md5($event_name . $property_name . $days);
        $cached = get_transient($transient_key);
        if ($cached !== false) return $cached;

        $host = rtrim($host, '/');
        $date_from = '-' . ($days - 1) . 'd';

        $url = add_query_arg(array(
            'insight' => 'TRENDS',
            'date_from' => $date_from,
            'events' => json_encode(array(
                array('id' => $event_name)
            )),
            'breakdown' => $property_name,
            'breakdown_type' => 'event'
        ), $host . '/api/projects/' . $project_id . '/insights/trend/');

        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'timeout' => 20
        ));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return array();
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $result = array();

        if (!empty($body['result'])) {
            foreach ($body['result'] as $item) {
                $property_value = $item['breakdown_value'] ?? 'unknown';
                $count = (int)($item['aggregated_value'] ?? 0);
                $result[$property_value] = $count;
            }
        }

        set_transient($transient_key, $result, HOUR_IN_SECONDS);

        return $result;
    }

    /**
     * Fallback: Get gender distribution from WordPress database
     */
    public function get_db_gender_distribution($days = 30)
    {
        global $wpdb;
        $timestamp = time() - ($days * DAY_IN_SECONDS);

        $query = "
            SELECT m1.meta_value as label, COUNT(DISTINCT m1.user_id) as count
            FROM {$wpdb->usermeta} m1
            LEFT JOIN {$wpdb->usermeta} m2 ON m1.user_id = m2.user_id AND m2.meta_key = 'wc_last_active'
            WHERE m1.meta_key = 'gender' AND m1.meta_value != ''
            AND (m2.meta_value IS NULL OR CAST(m2.meta_value AS UNSIGNED) >= %d)
            GROUP BY m1.meta_value
            ORDER BY count DESC
        ";

        return $wpdb->get_results($wpdb->prepare($query, $timestamp), ARRAY_A);
    }

    /**
     * Fallback: Get age distribution from WordPress database
     */
    public function get_db_age_distribution($days = 30)
    {
        global $wpdb;
        $timestamp = time() - ($days * DAY_IN_SECONDS);

        $query = "
            SELECT m1.meta_value as dob, m1.user_id
            FROM {$wpdb->usermeta} m1
            LEFT JOIN {$wpdb->usermeta} m2 ON m1.user_id = m2.user_id AND m2.meta_key = 'wc_last_active'
            WHERE m1.meta_key = 'date_of_birth' AND m1.meta_value != ''
            AND (m2.meta_value IS NULL OR CAST(m2.meta_value AS UNSIGNED) >= %d)
        ";

        $users = $wpdb->get_results($wpdb->prepare($query, $timestamp), ARRAY_A);
        if (empty($users)) return array();

        $age_groups = array(
            'Under 18' => 0,
            '18-24' => 0,
            '25-34' => 0,
            '35-44' => 0,
            '45-54' => 0,
            '55+' => 0,
        );

        foreach ($users as $user) {
            $dob = strtotime($user['dob']);
            if (!$dob) continue;
            
            $age = floor((time() - $dob) / 31556926); // approximate year
            
            if ($age < 18) $age_groups['Under 18']++;
            elseif ($age <= 24) $age_groups['18-24']++;
            elseif ($age <= 34) $age_groups['25-34']++;
            elseif ($age <= 44) $age_groups['35-44']++;
            elseif ($age <= 54) $age_groups['45-54']++;
            else $age_groups['55+']++;
        }

        $formatted = array();
        foreach ($age_groups as $group => $count) {
            if ($count > 0) {
                $formatted[] = array(
                    'label' => $group,
                    'count' => $count
                );
            }
        }

        return $formatted;
    }
}
