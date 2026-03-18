<?php

/**
 * Growtype Analytics REST API Economy Partial
 *
 * Handles REST API routes for project economy, credits and monetization.
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/includes/methods/api/partials
 */

class Growtype_Analytics_Rest_Api_Economy
{
    /**
     * Initialize the class and set its properties.
     */
    public function __construct()
    {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Register the routes for Economy.
     */
    public function register_routes()
    {
        register_rest_route('growtype-analytics/v1', '/economy/credits', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_credits_economy'),
                'permission_callback' => array($this, 'get_economy_permissions_check'),
            ),
        ));

        register_rest_route('growtype-analytics/v1', '/economy/repurchase-rate', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_repurchase_rate'),
                'permission_callback' => array($this, 'get_economy_permissions_check'),
                'args'                => array(
                    'period' => array(
                        'description' => __('The period for which to calculate the rate (all, year, etc.).', 'growtype-analytics'),
                        'type'        => 'string',
                        'enum'        => array('week', 'month', 'year', 'all'),
                        'default'     => 'all',
                    ),
                ),
            ),
        ));

        register_rest_route('growtype-analytics/v1', '/economy/burn-rate', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_burn_rate'),
                'permission_callback' => array($this, 'get_economy_permissions_check'),
                'args'                => array(
                    'days' => array(
                        'type'    => 'integer',
                        'default' => 30,
                    ),
                ),
            ),
        ));

        register_rest_route('growtype-analytics/v1', '/economy/arpu', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_arpu'),
                'permission_callback' => array($this, 'get_economy_permissions_check'),
            ),
        ));
    }

    /**
     * Check if a given request has access to economy data.
     */
    public function get_economy_permissions_check($request)
    {
        return current_user_can('manage_options');
    }

    /**
     * Get aggregate statistics about credits in circulation.
     */
    public function get_credits_economy($request)
    {
        global $wpdb;

        // Total credits purchased based on WooCommerce order items
        // We'll calculate this by summing quantity * growtype_chat_credits_amount where product is a credit pack
        $total_purchased = $wpdb->get_var("
            SELECT SUM(CAST(oim_qty.meta_value AS SIGNED) * CAST(pm_credits.meta_value AS SIGNED)) 
            FROM {$wpdb->prefix}woocommerce_order_itemmeta oim_qty
            JOIN {$wpdb->prefix}woocommerce_order_items oi ON oim_qty.order_item_id = oi.order_item_id
            JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim_prod ON oim_qty.order_item_id = oim_prod.order_item_id AND oim_prod.meta_key = '_product_id'
            JOIN {$wpdb->postmeta} pm_credits ON oim_prod.meta_value = pm_credits.post_id AND pm_credits.meta_key = 'growtype_chat_credits_amount'
            JOIN {$wpdb->posts} p ON oi.order_id = p.ID
            WHERE oim_qty.meta_key = '_qty'
            AND p.post_status IN ('wc-completed', 'wc-processing')
        ") ?: 0;

        // Total unspent credits (Deferred Revenue)
        $unspent = $wpdb->get_var($wpdb->prepare("
            SELECT SUM(CAST(meta_value AS SIGNED)) 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = %s", 
            'growtype_chat_credits'
        )) ?: 0;

        // Registered users with balance > 0
        $active_holders = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(user_id) 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = %s AND CAST(meta_value AS SIGNED) > 0", 
            'growtype_chat_credits'
        )) ?: 0;

        return new WP_REST_Response(array(
            'total_credits_purchased_all_time' => (int) $total_purchased,
            'total_credits_unspent_deferred'    => (int) $unspent,
            'users_with_remaining_balance'      => (int) $active_holders,
            'average_balance_per_user'          => round($unspent / (max(1, $active_holders)), 2),
            'liability_reduction_ratio'        => round((($total_purchased - $unspent) / max(1, $total_purchased)) * 100, 2) . '%',
            'notes'                             => __('Deferred revenue is the value of purchased credits not yet used.', 'growtype-analytics'),
        ), 200);
    }

    /**
     * Get re-purchase rate (Recurring Buyers / Total Buyers).
     */
    public function get_repurchase_rate($request)
    {
        global $wpdb;

        if (!class_exists('WooCommerce')) {
            return new WP_Error('woocommerce_not_found', __('WooCommerce plugin is not active.', 'growtype-analytics'), array('status' => 404));
        }

        $period = $request->get_param('period') ?: 'all';
        $where = "p.post_status IN ('wc-completed', 'wc-processing')";
        
        if ($period !== 'all') {
            $where .= " AND p.post_date >= DATE_SUB(NOW(), INTERVAL 1 " . strtoupper($period) . ")";
        }

        // 1. Total unique buyers
        $total_buyers = $wpdb->get_var("
            SELECT COUNT(DISTINCT pm.meta_value)
            FROM {$wpdb->postmeta} pm
            JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = '_customer_user' AND pm.meta_value > 0 AND $where
        ") ?: 0;

        // 2. Buyers with > 1 successful order
        $recurring_buyers = $wpdb->get_var("
            SELECT COUNT(wp_user_id) FROM (
                SELECT pm.meta_value as wp_user_id, COUNT(p.ID) as order_count
                FROM {$wpdb->postmeta} pm
                JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                WHERE pm.meta_key = '_customer_user' AND pm.meta_value > 0 AND $where
                GROUP BY pm.meta_value
                HAVING order_count > 1
            ) as recurrent_count
        ") ?: 0;

        $rate = ($total_buyers > 0) ? round(($recurring_buyers / $total_buyers) * 100, 2) : 0;

        return new WP_REST_Response(array(
            'period'           => $period,
            'total_buyers'     => (int) $total_buyers,
            'recurring_buyers' => (int) $recurring_buyers,
            'repurchase_rate'  => $rate . '%',
            'notes'            => __('Growth is fueled by recurring customers. A rate > 30% is excellent.', 'growtype-analytics'),
        ), 200);
    }

    /**
     * Average Credits Burned per Daily Active User.
     */
    public function get_burn_rate($request)
    {
        global $wpdb;

        if (!class_exists('Growtype_Chat_Database')) {
            return new WP_Error('growtype_chat_not_found', __('Growtype Chat plugin is not active.', 'growtype-analytics'), array('status' => 404));
        }

        $days = (int) $request->get_param('days') ?: 30;
        $messages_table = $wpdb->prefix . Growtype_Chat_Database::MESSAGES_TABLE;

        // Since we don't have a specific credit log, 
        // we'll approximate burn value by message volume / sessions, or if we can track specific "content unlocks."
        // For now, we'll return average messages per paying chatter.
        
        $paying_users_in_chat = $wpdb->get_var("
            SELECT COUNT(DISTINCT cu.id)
            FROM $messages_table m
            JOIN {$wpdb->prefix}" . Growtype_Chat_Database::USERS_TABLE . " cu ON m.user_id = cu.id
            JOIN {$wpdb->postmeta} pm ON cu.external_id = pm.meta_value AND pm.meta_key = '_customer_user'
            WHERE m.created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
        ");

        $total_messages = $wpdb->get_var("
            SELECT COUNT(id) FROM $messages_table WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
        ");

        return new WP_REST_Response(array(
            'period_days'     => $days,
            'total_chatter_volume' => (int) $total_messages,
            'paying_users_active'  => (int) $paying_users_in_chat,
            'avg_daily_workload'   => round($total_messages / max(1, $days), 2) . ' messages/day',
            'notes'                => __('Approximate burn based on activity per active customer.', 'growtype-analytics'),
        ), 200);
    }

    /**
     * Get ARPU (Average Revenue Per User) and LTV estimation.
     */
    public function get_arpu($request)
    {
        global $wpdb;

        // Total revenue
        $total_revenue = $wpdb->get_var("
            SELECT SUM(CAST(meta_value AS DECIMAL(10,2))) 
            FROM {$wpdb->postmeta} pm
            JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = '_order_total' AND p.post_status IN ('wc-completed', 'wc-processing')
        ") ?: 0;

        // Total registered users
        $total_users = $wpdb->get_var("SELECT COUNT(ID) FROM $wpdb->users") ?: 0;

        // Total buyers
        $total_buyers = $wpdb->get_var("
            SELECT COUNT(DISTINCT pm.meta_value)
            FROM {$wpdb->postmeta} pm
            JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = '_customer_user' AND pm.meta_value > 0 
            AND p.post_status IN ('wc-completed', 'wc-processing')
        ") ?: 0;

        return new WP_REST_Response(array(
            'total_revenue'         => (float) $total_revenue,
            'arpu'                  => round($total_revenue / max(1, $total_users), 2),
            'arppu'                 => round($total_revenue / max(1, $total_buyers), 2),
            'notes'                 => array(
                'arpu'  => __('Average Revenue Per User (Total Revenue / Total Registered Users)', 'growtype-analytics'),
                'arppu' => __('Average Revenue Per Paying User (Total Revenue / Total Unique Buyers)', 'growtype-analytics'),
            )
        ), 200);
    }
}
