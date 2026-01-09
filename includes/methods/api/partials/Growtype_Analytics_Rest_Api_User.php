<?php

/**
 * Growtype Analytics REST API User Partial
 *
 * Handles REST API routes for user-related data.
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/includes/methods/api/partials
 */

class Growtype_Analytics_Rest_Api_User
{
    /**
     * Initialize the class and set its properties.
     */
    public function __construct()
    {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Register the routes for the users.
     */
    public function register_routes()
    {
        register_rest_route('growtype-analytics/v1', '/users', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_users'),
                'permission_callback' => array($this, 'get_users_permissions_check'),
                'args'                => array(
                    'period' => array(
                        'description' => __('The period for which to get the users (week, month, etc.).', 'growtype-analytics'),
                        'type'        => 'string',
                        'enum'        => array('week', 'month', 'year', 'all'),
                        'required'    => false,
                    ),
                    'start_date' => array(
                        'description' => __('The start date for the users (YYYY-MM-DD).', 'growtype-analytics'),
                        'type'        => 'string',
                        'format'      => 'date',
                        'required'    => false,
                    ),
                    'end_date' => array(
                        'description' => __('The end date for the users (YYYY-MM-DD).', 'growtype-analytics'),
                        'type'        => 'string',
                        'format'      => 'date',
                        'required'    => false,
                    ),
                    'date_from' => array(
                        'description' => __('Alias for start_date (YYYY-MM-DD).', 'growtype-analytics'),
                        'type'        => 'string',
                        'format'      => 'date',
                        'required'    => false,
                    ),
                    'date_to' => array(
                        'description' => __('Alias for end_date (YYYY-MM-DD).', 'growtype-analytics'),
                        'type'        => 'string',
                        'format'      => 'date',
                        'required'    => false,
                    ),
                ),
            ),
        ));

        register_rest_route('growtype-analytics/v1', '/users/daily', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_users_daily'),
                'permission_callback' => array($this, 'get_users_permissions_check'),
                'args'                => array(
                    'period' => array(
                        'description' => __('The period for which to get the daily users (week, month, etc.).', 'growtype-analytics'),
                        'type'        => 'string',
                        'enum'        => array('week', 'month', 'year', 'all'),
                        'required'    => false,
                    ),
                    'start_date' => array(
                        'description' => __('The start date for the users (YYYY-MM-DD).', 'growtype-analytics'),
                        'type'        => 'string',
                        'format'      => 'date',
                        'required'    => false,
                    ),
                    'end_date' => array(
                        'description' => __('The end date for the users (YYYY-MM-DD).', 'growtype-analytics'),
                        'type'        => 'string',
                        'format'      => 'date',
                        'required'    => false,
                    ),
                    'date_from' => array(
                        'description' => __('Alias for start_date (YYYY-MM-DD).', 'growtype-analytics'),
                        'type'        => 'string',
                        'format'      => 'date',
                        'required'    => false,
                    ),
                    'date_to' => array(
                        'description' => __('Alias for end_date (YYYY-MM-DD).', 'growtype-analytics'),
                        'type'        => 'string',
                        'format'      => 'date',
                        'required'    => false,
                    ),
                ),
            ),
        ));
    }

    /**
     * Check if a given request has access to get user data.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|bool
     */
    public function get_users_permissions_check($request)
    {
        return current_user_can('manage_options');
    }

    /**
     * Get the registered users for a specific period.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function get_users($request)
    {
        global $wpdb;

        $period = $request->get_param('period');
        // Support both date_from/date_to and start_date/end_date
        $start_date = $request->get_param('date_from') ?: $request->get_param('start_date');
        $end_date = $request->get_param('date_to') ?: $request->get_param('end_date');

        if (!empty($period)) {
            switch ($period) {
                case 'week':
                    $start_date = date('Y-m-d', strtotime('-7 days'));
                    $end_date = date('Y-m-d');
                    break;
                case 'month':
                    $start_date = date('Y-m-d', strtotime('-30 days'));
                    $end_date = date('Y-m-d');
                    break;
                case 'year':
                    $start_date = date('Y-m-d', strtotime('-365 days'));
                    $end_date = date('Y-m-d');
                    break;
                case 'all':
                    $start_date = null;
                    $end_date = null;
                    break;
            }
        }

        $query = "SELECT ID, user_login, user_email, user_registered, display_name FROM $wpdb->users";
        $where = array();

        if (!empty($start_date)) {
            $where[] = $wpdb->prepare("DATE(user_registered) >= %s", $start_date);
        }

        if (!empty($end_date)) {
            $where[] = $wpdb->prepare("DATE(user_registered) <= %s", $end_date);
        }

        if (!empty($where)) {
            $query .= " WHERE " . implode(' AND ', $where);
        }

        $query .= " ORDER BY user_registered DESC";

        $users = $wpdb->get_results($query);

        return new WP_REST_Response(array(
            'users'      => $users,
            'period'     => $period,
            'start_date' => $start_date,
            'end_date'   => $end_date,
        ), 200);
    }

    /**
     * Get the daily registered users for a specific period.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function get_users_daily($request)
    {
        global $wpdb;

        $period = $request->get_param('period');
        // Support both date_from/date_to and start_date/end_date
        $start_date = $request->get_param('date_from') ?: $request->get_param('start_date');
        $end_date = $request->get_param('date_to') ?: $request->get_param('end_date');

        if (!empty($period)) {
            switch ($period) {
                case 'week':
                    $start_date = date('Y-m-d', strtotime('-7 days'));
                    $end_date = date('Y-m-d');
                    break;
                case 'month':
                    $start_date = date('Y-m-d', strtotime('-30 days'));
                    $end_date = date('Y-m-d');
                    break;
                case 'year':
                    $start_date = date('Y-m-d', strtotime('-365 days'));
                    $end_date = date('Y-m-d');
                    break;
                case 'all':
                    $start_date = null;
                    $end_date = null;
                    break;
            }
        } else if (empty($start_date)) {
            // Default to last 7 days if no period or start_date is provided
            $start_date = date('Y-m-d', strtotime('-7 days'));
            $end_date = !empty($end_date) ? $end_date : date('Y-m-d');
        }

        $results = [];
        $where = [];

        if (!empty($start_date)) {
            $where[] = $wpdb->prepare("DATE(user_registered) >= %s", $start_date);
        }
        if (!empty($end_date)) {
            $where[] = $wpdb->prepare("DATE(user_registered) <= %s", $end_date);
        }

        $query = "SELECT DATE(user_registered) as registration_date, COUNT(ID) as registration_count FROM $wpdb->users";
        if (!empty($where)) {
            $query .= " WHERE " . implode(' AND ', $where);
        }
        $query .= " GROUP BY DATE(user_registered) ORDER BY registration_date ASC";

        $db_results = $wpdb->get_results($query);
        $mapped_data = [];
        foreach ($db_results as $row) {
            $mapped_data[$row->registration_date] = (int) $row->registration_count;
        }

        $daily_registrations = array();

        if (!empty($start_date) && !empty($end_date)) {
            $current = strtotime($start_date);
            $last = strtotime($end_date);

            while ($current <= $last) {
                $date = date('Y-m-d', $current);
                $daily_registrations[$date] = isset($mapped_data[$date]) ? $mapped_data[$date] : 0;
                $current = strtotime("+1 day", $current);
            }
        } else {
            $daily_registrations = $mapped_data;
        }

        return new WP_REST_Response(array(
            'daily_registrations' => $daily_registrations,
            'period'              => $period,
            'start_date'          => $start_date,
            'end_date'            => $end_date,
        ), 200);
    }
}
