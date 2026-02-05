<?php

/**
 * Growtype Analytics REST API Payments Partial
 *
 * Handles REST API routes for Payment-related data.
 * NOTE: This partial requires the 'WooCommerce' plugin to be active.
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/includes/methods/api/partials
 */

class Growtype_Analytics_Rest_Api_Payments
{
    /**
     * Initialize the class and set its properties.
     */
    public function __construct()
    {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Register the routes for Payments.
     */
    public function register_routes()
    {
        register_rest_route('growtype-analytics/v1', '/payments/gateways', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_payment_gateways_distribution'),
                'permission_callback' => array($this, 'get_payments_permissions_check'),
                'args'                => array(
                    'period' => array(
                        'description' => __('The period for which to get the data (week, month, etc.).', 'growtype-analytics'),
                        'type'        => 'string',
                        'enum'        => array('week', 'month', 'year', 'all'),
                        'required'    => false,
                    ),
                    'start_date' => array(
                        'type' => 'string',
                        'format' => 'date',
                        'required' => false,
                    ),
                    'end_date' => array(
                        'type' => 'string',
                        'format' => 'date',
                        'required' => false,
                    ),
                ),
            ),
        ));

        register_rest_route('growtype-analytics/v1', '/payments/failures', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_payment_failures'),
                'permission_callback' => array($this, 'get_payments_permissions_check'),
                'args'                => array(
                    'period' => array(
                        'type' => 'string',
                        'enum' => array('week', 'month', 'year', 'all'),
                        'required' => false,
                    ),
                ),
            ),
        ));

        register_rest_route('growtype-analytics/v1', '/payments/success-rate', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_payment_success_rate'),
                'permission_callback' => array($this, 'get_payments_permissions_check'),
                'args'                => array(
                    'period' => array(
                        'type' => 'string',
                        'enum' => array('week', 'month', 'year', 'all'),
                        'required' => false,
                    ),
                    'start_date' => array('type' => 'string', 'format' => 'date'),
                    'end_date'   => array('type' => 'string', 'format' => 'date'),
                ),
            ),
        ));

        register_rest_route('growtype-analytics/v1', '/payments/abandonment', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_payment_abandonment_rates'),
                'permission_callback' => array($this, 'get_payments_permissions_check'),
                'args'                => array(
                    'period' => array(
                        'type' => 'string',
                        'enum' => array('week', 'month', 'year', 'all'),
                        'required' => false,
                    ),
                    'start_date' => array('type' => 'string', 'format' => 'date'),
                    'end_date'   => array('type' => 'string', 'format' => 'date'),
                ),
            ),
        ));
    }

    /**
     * Check if a given request has access to get payment data.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|bool
     */
    public function get_payments_permissions_check($request)
    {
        return current_user_can('manage_options');
    }

    /**
     * Get distribution of orders by payment gateway.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function get_payment_gateways_distribution($request)
    {
        if (!class_exists('WooCommerce')) {
            return new WP_Error('woocommerce_not_active', __('WooCommerce is not active.', 'growtype-analytics'), array('status' => 404));
        }

        global $wpdb;

        $period = $request->get_param('period');
        $start_date = $request->get_param('start_date');
        $end_date = $request->get_param('end_date');

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
            }
        }

        $query = "
            SELECT pm.meta_value as gateway, COUNT(p.ID) as count, SUM(pm2.meta_value) as revenue
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_payment_method'
            JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_order_total'
            WHERE p.post_type = 'shop_order' AND p.post_status = 'wc-completed'
        ";

        $where = [];
        if (!empty($start_date)) {
            $where[] = $wpdb->prepare("p.post_date >= %s", $start_date . ' 00:00:00');
        }
        if (!empty($end_date)) {
            $where[] = $wpdb->prepare("p.post_date <= %s", $end_date . ' 23:59:59');
        }

        if (!empty($where)) {
            $query .= " AND " . implode(' AND ', $where);
        }

        $query .= " GROUP BY pm.meta_value ORDER BY count DESC";

        $results = $wpdb->get_results($query);

        return new WP_REST_Response(array(
            'gateways' => $results,
            'period'   => $period,
            'start_date' => $start_date,
            'end_date'   => $end_date,
        ), 200);
    }

    /**
     * Get failed payment statistics.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function get_payment_failures($request)
    {
        if (!class_exists('WooCommerce')) {
            return new WP_Error('woocommerce_not_active', __('WooCommerce is not active.', 'growtype-analytics'), array('status' => 404));
        }

        global $wpdb;

        $period = $request->get_param('period');
        $start_date = null;
        $end_date = null;

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
            $start_date = date('Y-m-d', strtotime('-30 days'));
            $end_date = !empty($end_date) ? $end_date : date('Y-m-d');
        }

        $query = "
            SELECT pm.meta_value as gateway, COUNT(p.ID) as count
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_payment_method'
            WHERE p.post_type = 'shop_order' AND p.post_status IN ('wc-failed', 'wc-cancelled')
        ";

        $where = [];
        if (!empty($start_date)) {
            $where[] = $wpdb->prepare("p.post_date >= %s", $start_date . ' 00:00:00');
        }
        if (!empty($end_date)) {
            $where[] = $wpdb->prepare("p.post_date <= %s", $end_date . ' 23:59:59');
        }

        if (!empty($where)) {
            $query .= " AND " . implode(' AND ', $where);
        }

        $query .= " GROUP BY pm.meta_value ORDER BY count DESC";

        $results = $wpdb->get_results($query);

        return new WP_REST_Response(array(
            'failures' => $results,
            'period'   => $period,
            'start_date' => $start_date,
            'end_date' => $end_date,
        ), 200);
    }

    /**
     * Get payment success rate statistics.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function get_payment_success_rate($request)
    {
        if (!class_exists('WooCommerce')) {
            return new WP_Error('woocommerce_not_active', __('WooCommerce is not active.', 'growtype-analytics'), array('status' => 404));
        }

        global $wpdb;

        $period = $request->get_param('period');
        $start_date = $request->get_param('start_date');
        $end_date = $request->get_param('end_date');

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
            $start_date = date('Y-m-d', strtotime('-30 days'));
            $end_date = !empty($end_date) ? $end_date : date('Y-m-d');
        }

        $base_query = "
            SELECT p.post_status, COUNT(p.ID) as count
            FROM {$wpdb->posts} p
            WHERE p.post_type = 'shop_order' 
            AND p.post_status IN ('wc-completed', 'wc-failed', 'wc-cancelled', 'wc-pending')
        ";

        $where = [];
        if (!empty($start_date)) {
            $where[] = $wpdb->prepare("p.post_date >= %s", $start_date . ' 00:00:00');
        }
        if (!empty($end_date)) {
            $where[] = $wpdb->prepare("p.post_date <= %s", $end_date . ' 23:59:59');
        }

        if (!empty($where)) {
            $base_query .= " AND " . implode(' AND ', $where);
        }

        $base_query .= " GROUP BY p.post_status";

        $results = $wpdb->get_results($base_query);

        $stats = [
            'completed' => 0,
            'failed'    => 0,
            'cancelled' => 0,
            'pending'   => 0,
            'total'     => 0,
        ];

        foreach ($results as $row) {
            $status = str_replace('wc-', '', $row->post_status);
            if (isset($stats[$status])) {
                $count = (int) $row->count;
                $stats[$status] = $count;
                $stats['total'] += $count;
            }
        }

        $success_rate = $stats['total'] > 0 ? ($stats['completed'] / $stats['total']) * 100 : 0;

        return new WP_REST_Response(array(
            'success_rate' => round($success_rate, 2),
            'stats'        => $stats,
            'period'       => $period,
            'start_date'   => $start_date,
            'end_date'     => $end_date,
        ), 200);
    }

    /**
     * Get abandonment rates per payment gateway.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function get_payment_abandonment_rates($request)
    {
        if (!class_exists('WooCommerce')) {
            return new WP_Error('woocommerce_not_active', __('WooCommerce is not active.', 'growtype-analytics'), array('status' => 404));
        }

        global $wpdb;

        $period = $request->get_param('period');
        $start_date = $request->get_param('start_date');
        $end_date = $request->get_param('end_date');

        if (!empty($period)) {
            switch ($period) {
                case 'week':
                    $start_date = date('Y-m-d', strtotime('-7 days'));
                    break;
                case 'month':
                    $start_date = date('Y-m-d', strtotime('-30 days'));
                    break;
                case 'year':
                    $start_date = date('Y-m-d', strtotime('-365 days'));
                    break;
            }
            if ($period !== 'all') {
                $end_date = date('Y-m-d');
            }
        } else if (empty($start_date)) {
            $start_date = date('Y-m-d', strtotime('-30 days'));
            $end_date = !empty($end_date) ? $end_date : date('Y-m-d');
        }

        $query = "
            SELECT 
                pm.meta_value as gateway, 
                p.post_status as status, 
                COUNT(p.ID) as count
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_payment_method'
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ('wc-completed', 'wc-failed', 'wc-cancelled', 'wc-pending', 'wc-processing')
        ";

        $where = [];
        if (!empty($start_date)) {
            $where[] = $wpdb->prepare("p.post_date >= %s", $start_date . ' 00:00:00');
        }
        if (!empty($end_date)) {
            $where[] = $wpdb->prepare("p.post_date <= %s", $end_date . ' 23:59:59');
        }

        if (!empty($where)) {
            $query .= " AND " . implode(' AND ', $where);
        }

        $query .= " GROUP BY pm.meta_value, p.post_status";

        $results = $wpdb->get_results($query);

        $gateway_stats = [];

        foreach ($results as $row) {
            $gateway = $row->gateway ?: 'unknown';
            $status = str_replace('wc-', '', $row->status);
            $count = (int) $row->count;

            if (!isset($gateway_stats[$gateway])) {
                $gateway_stats[$gateway] = [
                    'gateway' => $gateway,
                    'completed' => 0,
                    'abandoned' => 0,
                    'total' => 0
                ];
            }

            if ($status === 'completed' || $status === 'processing') {
                $gateway_stats[$gateway]['completed'] += $count;
            } else if ($status === 'pending' || $status === 'failed' || $status === 'cancelled') {
                $gateway_stats[$gateway]['abandoned'] += $count;
            }

            $gateway_stats[$gateway]['total'] += $count;
        }

        // Calculate rates
        foreach ($gateway_stats as &$stats) {
            $stats['abandonment_rate'] = $stats['total'] > 0 ? round(($stats['abandoned'] / $stats['total']) * 100, 2) : 0;
            $stats['success_rate'] = $stats['total'] > 0 ? round(($stats['completed'] / $stats['total']) * 100, 2) : 0;
        }

        return new WP_REST_Response(array(
            'gateways'   => array_values($gateway_stats),
            'period'     => $period,
            'start_date' => $start_date,
            'end_date'   => $end_date,
        ), 200);
    }
}
