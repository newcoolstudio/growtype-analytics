<?php

/**
 * Growtype Analytics REST API Retention Partial
 *
 * Handles REST API routes for user retention and churn metrics.
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/includes/methods/api/partials
 */

class Growtype_Analytics_Rest_Api_Retention
{
    /**
     * Initialize the class and set its properties.
     */
    public function __construct()
    {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Register the routes for Retention.
     */
    public function register_routes()
    {
        register_rest_route('growtype-analytics/v1', '/retention/activity-stats', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_activity_stats'),
                'permission_callback' => array($this, 'get_retention_permissions_check'),
            ),
        ));

        register_rest_route('growtype-analytics/v1', '/retention/cohorts', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_cohorts'),
                'permission_callback' => array($this, 'get_retention_permissions_check'),
                'args'                => array(
                    'interval' => array(
                        'description' => __('Interval for cohorts (week, month).', 'growtype-analytics'),
                        'type'        => 'string',
                        'enum'        => array('week', 'month'),
                        'default'     => 'week',
                    ),
                ),
            ),
        ));

        register_rest_route('growtype-analytics/v1', '/retention/churn-risk', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_churn_risk'),
                'permission_callback' => array($this, 'get_retention_permissions_check'),
                'args'                => array(
                    'days' => array(
                        'description' => __('Inactivity days to consider as churn risk.', 'growtype-analytics'),
                        'type'        => 'integer',
                        'default'     => 14,
                    ),
                    'limit' => array(
                        'type'    => 'integer',
                        'default' => 20,
                    ),
                ),
            ),
        ));
    }

    /**
     * Check if a given request has access to retention data.
     */
    public function get_retention_permissions_check($request)
    {
        return current_user_can('manage_options');
    }

    /**
     * Get DAU, WAU, MAU and Stickiness Ratio.
     */
    public function get_activity_stats($request)
    {
        global $wpdb;

        if (!class_exists('Growtype_Chat_Database')) {
            return new WP_Error('growtype_chat_not_found', __('Growtype Chat plugin is not active.', 'growtype-analytics'), array('status' => 404));
        }

        $messages_table = $wpdb->prefix . Growtype_Chat_Database::MESSAGES_TABLE;

        // Stickiness relies on unique users who sent a message in a given period
        $dau = $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM $messages_table WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)");
        $wau = $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM $messages_table WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $mau = $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM $messages_table WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");

        $stickiness_ratio = ($mau > 0) ? round(($dau / $mau) * 100, 2) : 0;

        return new WP_REST_Response(array(
            'dau'              => (int) $dau,
            'wau'              => (int) $wau,
            'mau'              => (int) $mau,
            'stickiness_ratio' => $stickiness_ratio . '%',
            'notes'            => __('Stickiness Ratio (DAU/MAU) measures how many monthly users return daily.', 'growtype-analytics'),
        ), 200);
    }

    /**
     * Get Cohort Analysis.
     */
    public function get_cohorts($request)
    {
        global $wpdb;

        if (!class_exists('Growtype_Chat_Database')) {
            return new WP_Error('growtype_chat_not_found', __('Growtype Chat plugin is not active.', 'growtype-analytics'), array('status' => 404));
        }

        $interval = $request->get_param('interval') ?: 'week';
        $messages_table = $wpdb->prefix . Growtype_Chat_Database::MESSAGES_TABLE;
        $users_table = $wpdb->users;

        // Simplify cohort: User's registration week vs activity week
        $date_format = ($interval === 'week') ? '%Y-%u' : '%Y-%m';
        
        // 1. Get all registrations per interval
        $cohort_sizes = $wpdb->get_results("
            SELECT DATE_FORMAT(user_registered, '$date_format') as cohort, COUNT(ID) as total_users
            FROM $users_table
            WHERE user_registered >= DATE_SUB(NOW(), INTERVAL 24 " . strtoupper($interval) . ")
            GROUP BY cohort
            ORDER BY cohort ASC
        ", OBJECT_K);

        if (empty($cohort_sizes)) {
            return new WP_REST_Response(array('cohorts' => []), 200);
        }

        // 2. Get activity per cohort and per subsequent interval
        $cohort_data = [];
        foreach ($cohort_sizes as $cohort => $data) {
            $cohort_data[$cohort] = [
                'total_registered' => (int) $data->total_users,
                'retention_counts' => [],
                'retention_pct'    => []
            ];
        }

        // Let's do a cleaner SQL for performance
        $interval_sql = ($interval === 'week') ? "FLOOR(DATEDIFF(m.created_at, u.user_registered) / 7)" : "PERIOD_DIFF(EXTRACT(YEAR_MONTH FROM m.created_at), EXTRACT(YEAR_MONTH FROM u.user_registered))";

        $clean_activity = $wpdb->get_results("
            SELECT 
                DATE_FORMAT(u.user_registered, '$date_format') as cohort,
                $interval_sql as offset,
                COUNT(DISTINCT m.user_id) as active_users
            FROM $messages_table m
            JOIN {$wpdb->prefix}" . Growtype_Chat_Database::USERS_TABLE . " cu ON m.user_id = cu.id
            JOIN $users_table u ON cu.external_id = u.ID
            WHERE u.user_registered >= DATE_SUB(NOW(), INTERVAL 24 " . strtoupper($interval) . ")
            AND cu.type = 'wp_user'
            GROUP BY cohort, offset
            HAVING offset >= 0 AND offset <= 12
            ORDER BY cohort DESC, offset ASC
        ");

        foreach ($clean_activity as $row) {
            if (!isset($cohort_data[$row->cohort])) continue;
            
            $offset = (int) $row->offset;
            $percentage = round(($row->active_users / $cohort_data[$row->cohort]['total_registered']) * 100, 2);
            $cohort_data[$row->cohort]['retention_pct'][$offset] = $percentage . '%';
            $cohort_data[$row->cohort]['retention_counts'][$offset] = (int) $row->active_users;
        }

        return new WP_REST_Response(array(
            'interval' => $interval,
            'cohorts'  => $cohort_data,
        ), 200);
    }

    /**
     * Get high-value users at churn risk.
     */
    public function get_churn_risk($request)
    {
        global $wpdb;

        if (!class_exists('Growtype_Chat_Database')) {
            return new WP_Error('growtype_chat_not_found', __('Growtype Chat plugin is not active.', 'growtype-analytics'), array('status' => 404));
        }

        $days = (int) $request->get_param('days') ?: 14;
        $limit = (int) $request->get_param('limit') ?: 20;

        $messages_table = $wpdb->prefix . Growtype_Chat_Database::MESSAGES_TABLE;
        $chat_users_table = $wpdb->prefix . Growtype_Chat_Database::USERS_TABLE;

        // Find users who spent money (has orders) but haven't sent a message in X days
        // We'll also return their credit balance
        $query = "
            SELECT 
                u.ID as wp_user_id,
                u.user_email,
                MAX(m.created_at) as last_activity,
                meta.meta_value as remaining_credits,
                (SELECT SUM(pm.meta_value) FROM {$wpdb->postmeta} pm 
                 JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
                 WHERE pm.meta_key = '_order_total' AND p.post_status IN ('wc-completed', 'wc-processing') 
                 AND p.ID IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_customer_user' AND meta_value = u.ID)
                ) as total_spent
            FROM $wpdb->users u
            LEFT JOIN $chat_users_table cu ON u.ID = cu.external_id AND cu.type = 'wp_user'
            LEFT JOIN $messages_table m ON cu.id = m.user_id
            LEFT JOIN $wpdb->usermeta meta ON u.ID = meta.user_id AND meta.meta_key = 'growtype_chat_credits'
            GROUP BY u.ID
            HAVING (last_activity < DATE_SUB(NOW(), INTERVAL %d DAY) OR last_activity IS NULL)
            AND total_spent > 0
            ORDER BY total_spent DESC, last_activity DESC
            LIMIT %d
        ";

        $results = $wpdb->get_results($wpdb->prepare($query, $days, $limit));

        foreach ($results as &$user) {
            $user->total_spent = (float) $user->total_spent;
            $user->remaining_credits = (int) $user->remaining_credits;
            $user->days_inactive = $user->last_activity ? floor((time() - strtotime($user->last_activity)) / 86400) : 'Never active';
        }

        return new WP_REST_Response($results, 200);
    }
}
