<?php

/**
 * Growtype Analytics REST API WooCommerce Partial
 *
 * Handles REST API routes for WooCommerce-related data.
 * NOTE: This partial requires the 'WooCommerce' plugin to be active.
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/includes/methods/api/partials
 */

class Growtype_Analytics_Rest_Api_Orders
{
    /**
     * Initialize the class and set its properties.
     */
    public function __construct()
    {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Register the routes for WooCommerce.
     */
    public function register_routes()
    {
        register_rest_route('growtype-analytics/v1', '/orders', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_orders'),
                'permission_callback' => array($this, 'get_orders_permissions_check'),
                'args'                => array(
                    'status' => array(
                        'description' => __('The order status to filter by (purchased, pending, all, completed, etc.).', 'growtype-analytics'),
                        'type'        => 'string',
                        'default'     => 'any',
                        'required'    => false,
                    ),
                    'period' => array(
                        'description' => __('The period for which to get the orders (week, month, etc.).', 'growtype-analytics'),
                        'type'        => 'string',
                        'enum'        => array('week', 'month', 'year', 'all'),
                        'required'    => false,
                    ),
                    'start_date' => array(
                        'description' => __('The start date for the orders (YYYY-MM-DD).', 'growtype-analytics'),
                        'type'        => 'string',
                        'format'      => 'date',
                        'required'    => false,
                    ),
                    'end_date' => array(
                        'description' => __('The end date for the orders (YYYY-MM-DD).', 'growtype-analytics'),
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

        register_rest_route('growtype-analytics/v1', '/orders/daily', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_orders_daily'),
                'permission_callback' => array($this, 'get_orders_permissions_check'),
                'args'                => array(
                    'status' => array(
                        'description' => __('The order status to filter by (purchased, pending, all, completed, etc.).', 'growtype-analytics'),
                        'type'        => 'string',
                        'default'     => 'completed',
                        'required'    => false,
                    ),
                    'period' => array(
                        'description' => __('The period for which to get the orders (week, month, etc.).', 'growtype-analytics'),
                        'type'        => 'string',
                        'enum'        => array('week', 'month', 'year', 'all'),
                        'required'    => false,
                    ),
                    'start_date' => array(
                        'description' => __('The start date for the orders (YYYY-MM-DD).', 'growtype-analytics'),
                        'type'        => 'string',
                        'format'      => 'date',
                        'required'    => false,
                    ),
                    'end_date' => array(
                        'description' => __('The end date for the orders (YYYY-MM-DD).', 'growtype-analytics'),
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

        register_rest_route('growtype-analytics/v1', '/orders/distribution', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_orders_distribution'),
                'permission_callback' => array($this, 'get_orders_permissions_check'),
                'args'                => array(
                    'period' => array(
                        'description' => __('The period for which to get the orders (week, month, etc.).', 'growtype-analytics'),
                        'type'        => 'string',
                        'enum'        => array('week', 'month', 'year', 'all'),
                        'required'    => false,
                    ),
                    'start_date' => array(
                        'description' => __('The start date for the orders (YYYY-MM-DD).', 'growtype-analytics'),
                        'type'        => 'string',
                        'format'      => 'date',
                        'required'    => false,
                    ),
                    'end_date' => array(
                        'description' => __('The end date for the orders (YYYY-MM-DD).', 'growtype-analytics'),
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

        register_rest_route('growtype-analytics/v1', '/orders/daily-distribution', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_orders_daily_distribution'),
                'permission_callback' => array($this, 'get_orders_permissions_check'),
                'args'                => array(
                    'period' => array(
                        'description' => __('The period for which to get the orders (week, month, etc.).', 'growtype-analytics'),
                        'type'        => 'string',
                        'enum'        => array('week', 'month', 'year', 'all'),
                        'required'    => false,
                    ),
                    'start_date' => array(
                        'description' => __('The start date for the orders (YYYY-MM-DD).', 'growtype-analytics'),
                        'type'        => 'string',
                        'format'      => 'date',
                        'required'    => false,
                    ),
                    'end_date' => array(
                        'description' => __('The end date for the orders (YYYY-MM-DD).', 'growtype-analytics'),
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

        register_rest_route('growtype-analytics/v1', '/orders/sales/daily', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_sales_daily'),
                'permission_callback' => array($this, 'get_orders_permissions_check'),
                'args'                => array(
                    'status' => array(
                        'description' => __('The order status to filter by (purchased, pending, all, completed, etc.).', 'growtype-analytics'),
                        'type'        => 'string',
                        'default'     => 'completed',
                        'required'    => false,
                    ),
                    'period' => array(
                        'description' => __('The period for which to get the sales (week, month, etc.).', 'growtype-analytics'),
                        'type'        => 'string',
                        'enum'        => array('week', 'month', 'year', 'all'),
                        'required'    => false,
                    ),
                    'start_date' => array(
                        'description' => __('The start date for the sales (YYYY-MM-DD).', 'growtype-analytics'),
                        'type'        => 'string',
                        'format'      => 'date',
                        'required'    => false,
                    ),
                    'end_date' => array(
                        'description' => __('The end date for the sales (YYYY-MM-DD).', 'growtype-analytics'),
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

        register_rest_route('growtype-analytics/v1', '/conversion/daily', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_conversion_daily'),
                'permission_callback' => array($this, 'get_orders_permissions_check'),
                'args'                => array(
                    'status' => array(
                        'description' => __('The order status to filter by (purchased, pending, all, completed, etc.).', 'growtype-analytics'),
                        'type'        => 'string',
                        'default'     => 'completed',
                        'required'    => false,
                    ),
                    'period' => array(
                        'description' => __('The period for which to get the conversion rate (week, month, etc.).', 'growtype-analytics'),
                        'type'        => 'string',
                        'enum'        => array('week', 'month', 'year', 'all'),
                        'required'    => false,
                    ),
                    'start_date' => array(
                        'description' => __('The start date (YYYY-MM-DD).', 'growtype-analytics'),
                        'type'        => 'string',
                        'format'      => 'date',
                        'required'    => false,
                    ),
                    'end_date' => array(
                        'description' => __('The end date (YYYY-MM-DD).', 'growtype-analytics'),
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
     * Check if a given request has access to get order data.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|bool
     */
    public function get_orders_permissions_check($request)
    {
        return current_user_can('manage_options');
    }

    /**
     * Get the registered orders for a specific period and status.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function get_orders($request)
    {
        if (!class_exists('WooCommerce')) {
            return new WP_Error('woocommerce_not_active', __('WooCommerce is not active.', 'growtype-analytics'), array('status' => 404));
        }

        $status = $request->get_param('status');

        // Map status aliases
        if ($status === 'purchased') {
            $status = 'completed';
        } elseif ($status === 'all') {
            $status = 'any';
        }

        $period = $request->get_param('period');
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

        $args = array(
            'limit' => -1,
            'status' => $status,
            'return' => 'objects',
        );

        if (!empty($start_date)) {
            $args['date_created'] = '>=' . $start_date;
        }

        if (!empty($end_date)) {
            if (isset($args['date_created'])) {
                $args['date_created'] .= '...<=' . $end_date;
            } else {
                $args['date_created'] = '<=' . $end_date;
            }
        }

        $orders_objects = wc_get_orders($args);
        $orders = array();

        foreach ($orders_objects as $order) {
            $orders[] = array(
                'id'            => $order->get_id(),
                'status'        => $order->get_status(),
                'total'         => $order->get_total(),
                'currency'      => $order->get_currency(),
                'date_created'  => $order->get_date_created()->date('Y-m-d H:i:s'),
                'user_id'       => $order->get_user_id(),
                'customer_email'=> $order->get_billing_email(),
            );
        }

        return new WP_REST_Response(array(
            'orders'     => $orders,
            'total_count' => count($orders),
            'status'     => $status,
            'period'     => $period,
            'start_date' => $start_date,
            'end_date'   => $end_date,
        ), 200);
    }

    /**
     * Get the daily registered orders for a specific period and status.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function get_orders_daily($request)
    {
        if (!class_exists('WooCommerce')) {
            return new WP_Error('woocommerce_not_active', __('WooCommerce is not active.', 'growtype-analytics'), array('status' => 404));
        }

        $status = $request->get_param('status');

        // Map status aliases
        if ($status === 'purchased') {
            $status = 'completed';
        } elseif ($status === 'all') {
            $status = 'any';
        }

        $period = $request->get_param('period');
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

        global $wpdb;
        $status_filter = ($status !== 'any') ? $wpdb->prepare("AND post_status = %s", 'wc-' . $status) : "";
        $where = [];

        if (!empty($start_date)) {
            $where[] = $wpdb->prepare("DATE(post_date) >= %s", $start_date);
        }
        if (!empty($end_date)) {
            $where[] = $wpdb->prepare("DATE(post_date) <= %s", $end_date);
        }

        $query = "SELECT DATE(post_date) as order_date, COUNT(ID) as order_count FROM {$wpdb->posts} WHERE post_type = 'shop_order' $status_filter";
        if (!empty($where)) {
            $query .= " AND " . implode(' AND ', $where);
        }
        $query .= " GROUP BY DATE(post_date) ORDER BY order_date ASC";

        $db_results = $wpdb->get_results($query);
        $mapped_data = [];
        foreach ($db_results as $row) {
            $mapped_data[$row->order_date] = (int) $row->order_count;
        }

        $daily_orders = array();

        if (!empty($start_date) && !empty($end_date)) {
            $current = strtotime($start_date);
            $last = strtotime($end_date);

            while ($current <= $last) {
                $date = date('Y-m-d', $current);
                $daily_orders[$date] = isset($mapped_data[$date]) ? $mapped_data[$date] : 0;
                $current = strtotime("+1 day", $current);
            }
        } else {
            $daily_orders = $mapped_data;
        }

        return new WP_REST_Response(array(
            'daily_orders' => $daily_orders,
            'status'       => $status,
            'period'       => $period,
            'start_date'   => $start_date,
            'end_date'     => $end_date,
        ), 200);
    }

    /**
     * Get daily sales totals for a specific period and status.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function get_sales_daily($request)
    {
        if (!class_exists('WooCommerce')) {
            return new WP_Error('woocommerce_not_active', __('WooCommerce is not active.', 'growtype-analytics'), array('status' => 404));
        }

        $status = $request->get_param('status');

        // Map status aliases
        if ($status === 'purchased') {
            $status = 'completed';
        } elseif ($status === 'all') {
            $status = 'any';
        }

        $period = $request->get_param('period');
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

        global $wpdb;
        $status_filter = ($status !== 'any') ? $wpdb->prepare("AND p.post_status = %s", 'wc-' . $status) : "";
        $where = [];

        if (!empty($start_date)) {
            $where[] = $wpdb->prepare("DATE(p.post_date) >= %s", $start_date);
        }
        if (!empty($end_date)) {
            $where[] = $wpdb->prepare("DATE(p.post_date) <= %s", $end_date);
        }

        $query = "
            SELECT DATE(p.post_date) as order_date, SUM(pm.meta_value) as sales_total 
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_order_total'
            WHERE p.post_type = 'shop_order' $status_filter
        ";
        
        if (!empty($where)) {
            $query .= " AND " . implode(' AND ', $where);
        }
        $query .= " GROUP BY DATE(p.post_date) ORDER BY order_date ASC";

        $db_results = $wpdb->get_results($query);
        $mapped_data = [];
        foreach ($db_results as $row) {
            $mapped_data[$row->order_date] = round((float) $row->sales_total, 2);
        }

        $daily_sales = array();

        if (!empty($start_date) && !empty($end_date)) {
            $current = strtotime($start_date);
            $last = strtotime($end_date);

            while ($current <= $last) {
                $date = date('Y-m-d', $current);
                $daily_sales[$date] = isset($mapped_data[$date]) ? $mapped_data[$date] : 0;
                $current = strtotime("+1 day", $current);
            }
        } else {
            $daily_sales = $mapped_data;
        }

        return new WP_REST_Response(array(
            'daily_sales' => $daily_sales,
            'status'      => $status,
            'period'      => $period,
            'start_date'  => $start_date,
            'end_date'    => $end_date,
        ), 200);
    }

    /**
     * Get the distribution of orders by status for a specific period.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function get_orders_distribution($request)
    {
        if (!class_exists('WooCommerce')) {
            return new WP_Error('woocommerce_not_active', __('WooCommerce is not active.', 'growtype-analytics'), array('status' => 404));
        }

        $period = $request->get_param('period');
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

        global $wpdb;

        $query = "SELECT post_status, COUNT(ID) as order_count 
                  FROM {$wpdb->posts} 
                  WHERE post_type = 'shop_order'";
        
        $where_values = array();

        if (!empty($start_date)) {
            $query .= " AND post_date >= %s";
            $where_values[] = $start_date . ' 00:00:00';
        }

        if (!empty($end_date)) {
            $query .= " AND post_date <= %s";
            $where_values[] = $end_date . ' 23:59:59';
        }

        $query .= " GROUP BY post_status";

        if (!empty($where_values)) {
            $results = $wpdb->get_results($wpdb->prepare($query, $where_values));
        } else {
            $results = $wpdb->get_results($query);
        }

        $distribution = array();
        
        // Get all possible WC statuses to ensure we have a clean list
        $wc_statuses = function_exists('wc_get_order_statuses') ? wc_get_order_statuses() : array();
        
        foreach ($results as $row) {
            $status = str_replace('wc-', '', $row->post_status);
            
            // Check if it's a valid WC status or a standard WP status used by WC
            if (isset($wc_statuses['wc-' . $status]) || $status === 'trash') {
                $distribution[$status] = (int) $row->order_count;
            }
        }

        return new WP_REST_Response(array(
            'distribution' => $distribution,
            'period'       => $period,
            'start_date'   => $start_date,
            'end_date'     => $end_date,
        ), 200);
    }

    /**
     * Get the daily distribution of orders by status for a specific period.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function get_orders_daily_distribution($request)
    {
        if (!class_exists('WooCommerce')) {
            return new WP_Error('woocommerce_not_active', __('WooCommerce is not active.', 'growtype-analytics'), array('status' => 404));
        }

        $period = $request->get_param('period');
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
            // Default to last 7 days
            $start_date = date('Y-m-d', strtotime('-7 days'));
            $end_date = !empty($end_date) ? $end_date : date('Y-m-d');
        }

        global $wpdb;

        $query = "SELECT DATE(post_date) as order_date, post_status, COUNT(ID) as order_count 
                  FROM {$wpdb->posts} 
                  WHERE post_type = 'shop_order'";
        
        $where_values = array();

        if (!empty($start_date)) {
            $query .= " AND post_date >= %s";
            $where_values[] = $start_date . ' 00:00:00';
        }

        if (!empty($end_date)) {
            $query .= " AND post_date <= %s";
            $where_values[] = $end_date . ' 23:59:59';
        }

        $query .= " GROUP BY order_date, post_status ORDER BY order_date ASC";

        if (!empty($where_values)) {
            $results = $wpdb->get_results($wpdb->prepare($query, $where_values));
        } else {
            $results = $wpdb->get_results($query);
        }

        $daily_distribution = array();
        $wc_statuses = function_exists('wc_get_order_statuses') ? wc_get_order_statuses() : array();

        foreach ($results as $row) {
            $date = $row->order_date;
            $status = str_replace('wc-', '', $row->post_status);
            
            if (isset($wc_statuses['wc-' . $status]) || $status === 'trash') {
                if (!isset($daily_distribution[$date])) {
                    $daily_distribution[$date] = array();
                }
                $daily_distribution[$date][$status] = (int) $row->order_count;
            }
        }

        // Fill in missing days with empty arrays if not "all" period
        if ($period !== 'all' && !empty($start_date) && !empty($end_date)) {
            $current = strtotime($start_date);
            $last = strtotime($end_date);
            while ($current <= $last) {
                $date_key = date('Y-m-d', $current);
                if (!isset($daily_distribution[$date_key])) {
                    $daily_distribution[$date_key] = array();
                }
                $current = strtotime("+1 day", $current);
            }
            ksort($daily_distribution);
        }

        return new WP_REST_Response(array(
            'daily_distribution' => $daily_distribution,
            'period'             => $period,
            'start_date'         => $start_date,
            'end_date'           => $end_date,
        ), 200);
    }

    /**
     * Get the daily conversion rate (orders / registrations) for a specific period.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function get_conversion_daily($request)
    {
        global $wpdb;

        if (!class_exists('WooCommerce')) {
            return new WP_Error('woocommerce_not_active', __('WooCommerce is not active.', 'growtype-analytics'), array('status' => 404));
        }

        $status = $request->get_param('status');

        // Map status aliases
        if ($status === 'purchased') {
            $status = 'completed';
        } elseif ($status === 'all') {
            $status = 'any';
        }

        $period = $request->get_param('period');
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
            $start_date = date('Y-m-d', strtotime('-7 days'));
            $end_date = !empty($end_date) ? $end_date : date('Y-m-d');
        }

        // 1. Get daily orders
        $status_filter = ($status !== 'any') ? $wpdb->prepare("AND post_status = %s", 'wc-' . $status) : "";
        $where_orders = [];
        if (!empty($start_date)) {
            $where_orders[] = $wpdb->prepare("DATE(post_date) >= %s", $start_date);
        }
        if (!empty($end_date)) {
            $where_orders[] = $wpdb->prepare("DATE(post_date) <= %s", $end_date);
        }

        $orders_query = "SELECT DATE(post_date) as order_date, COUNT(ID) as order_count FROM {$wpdb->posts} WHERE post_type = 'shop_order' $status_filter";
        if (!empty($where_orders)) {
            $orders_query .= " AND " . implode(' AND ', $where_orders);
        }
        $orders_query .= " GROUP BY DATE(post_date)";
        $orders_results = $wpdb->get_results($orders_query);

        $mapped_orders = [];
        foreach ($orders_results as $row) {
            $mapped_orders[$row->order_date] = (int) $row->order_count;
        }

        // 2. Get daily registrations
        $where_users = [];
        if (!empty($start_date)) {
            $where_users[] = $wpdb->prepare("DATE(user_registered) >= %s", $start_date);
        }
        if (!empty($end_date)) {
            $where_users[] = $wpdb->prepare("DATE(user_registered) <= %s", $end_date);
        }

        $users_query = "SELECT DATE(user_registered) as registration_date, COUNT(ID) as registration_count FROM $wpdb->users";
        if (!empty($where_users)) {
            $users_query .= " WHERE " . implode(' AND ', $where_users);
        }
        $users_query .= " GROUP BY DATE(user_registered)";
        $users_results = $wpdb->get_results($users_query);

        $mapped_users = [];
        foreach ($users_results as $row) {
            $mapped_users[$row->registration_date] = (int) $row->registration_count;
        }

        // 3. Combine and calculate conversion rate
        $daily_conversion = array();
        if (!empty($start_date) && !empty($end_date)) {
            $current = strtotime($start_date);
            $last = strtotime($end_date);

            while ($current <= $last) {
                $date = date('Y-m-d', $current);
                $orders = $mapped_orders[$date] ?? 0;
                $registrations = $mapped_users[$date] ?? 0;

                // conversion rate = (orders / registrations) * 100
                $conversion_rate = ($registrations > 0) ? round(($orders / $registrations) * 100, 2) : 0;
                
                $daily_conversion[$date] = $conversion_rate;
                
                $current = strtotime("+1 day", $current);
            }
        }

        return new WP_REST_Response(array(
            'daily_conversion' => $daily_conversion,
            'status'           => $status,
            'period'           => $period,
            'start_date'       => $start_date,
            'end_date'         => $end_date,
        ), 200);
    }
}
