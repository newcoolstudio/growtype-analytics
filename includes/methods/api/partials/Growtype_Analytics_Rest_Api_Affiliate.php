<?php

/**
 * Growtype Analytics REST API Affiliate Partial
 *
 * Handles REST API routes for affiliate-related data.
 * NOTE: This partial requires the 'growtype-affiliate' plugin to be active.
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/includes/methods/api/partials
 */

class Growtype_Analytics_Rest_Api_Affiliate
{
    /**
     * Initialize the class and set its properties.
     */
    public function __construct()
    {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Register the routes for the affiliate.
     */
    public function register_routes()
    {
        register_rest_route('growtype-analytics/v1', '/affiliate/payouts', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_affiliates_payouts'),
                'permission_callback' => array($this, 'get_affiliate_permissions_check'),
            ),
        ));
    }

    /**
     * Check if a given request has access to get affiliate data.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|bool
     */
    public function get_affiliate_permissions_check($request)
    {
        return current_user_can('manage_options');
    }

    /**
     * Get total affiliate amounts to pay grouped by source.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function get_affiliates_payouts($request)
    {
        if (!class_exists('Growtype_Affiliate')) {
            return new WP_Error('growtype_affiliate_not_found', __('Growtype Affiliate plugin is not active.', 'growtype-analytics'), array('status' => 404));
        }

        // Ensure necessary admin classes are loaded as they might not be loaded in REST context
        if (!class_exists('Growtype_Affiliate_Admin_Referrals')) {
            require_once GROWTYPE_AFFILIATE_PATH . 'admin/methods/referrals/index.php';
        }
        if (!class_exists('Growtype_Affiliate_Admin_Links')) {
            require_once GROWTYPE_AFFILIATE_PATH . 'admin/methods/links/index.php';
        }
        if (!class_exists('Growtype_Affiliate_Admin_Profile')) {
            require_once GROWTYPE_AFFILIATE_PATH . 'admin/methods/profile/index.php';
        }
        if (!class_exists('Growtype_Affiliate_Admin_Payouts')) {
            require_once GROWTYPE_AFFILIATE_PATH . 'admin/methods/payouts/index.php';
        }

        $affiliates = get_users(array('role' => 'affiliate'));
        $payouts_by_source = array();

        foreach ($affiliates as $affiliate) {
            $user_id = $affiliate->ID;
            
            // Get already paid amount (per user)
            $already_paid = (float)get_user_meta(
                $user_id, 
                Growtype_Affiliate_Admin_Profile::ALREADY_PAID_COMMISSIONS_AMOUNT_META_KEY, 
                true
            );
            
            // Get all referred orders for this affiliate
            $details = Growtype_Affiliate_Admin_Referrals::get_affiliate_orders_details($user_id);
            $order_ids = $details['order_ids'];

            $recurring_percent = 0.4;
            $onetime_percent = 0.3;
            
            if (class_exists('Growtype_Affiliate_Referrals')) {
                $recurring_percent = Growtype_Affiliate_Referrals::RECURRING_PAYMENT_PERCENT;
                $onetime_percent = Growtype_Affiliate_Referrals::ONETIME_PAYMENT_PERCENT;
            }

            foreach ($order_ids as $order_id) {
                $order = wc_get_order($order_id);
                if (!$order || !$order->is_paid()) {
                    continue;
                }

                $is_subscription = false;
                if (class_exists('Growtype_Wc_Subscription')) {
                    $is_subscription = Growtype_Wc_Subscription::is_subscription_order($order_id);
                }
                
                $percent = $is_subscription ? $recurring_percent : $onetime_percent;
                $commission = $order->get_total() * $percent;

                // Identify source
                $ref_garef = get_post_meta($order_id, '_wc_order_attribution_garef', true);
                $ref_utm = get_post_meta($order_id, '_wc_order_attribution_utm_source', true);
                $ref_old = get_post_meta($order_id, 'growtype_affiliate_ref', true);
                $source = $ref_garef ?: $ref_utm ?: $ref_old ?: 'organic';

                if (!isset($payouts_by_source[$source])) {
                    $payouts_by_source[$source] = array(
                        'source'           => $source,
                        'total_earned'     => 0,
                        'affiliate_name'   => $affiliate->display_name,
                        'affiliate_email'  => $affiliate->user_email,
                        'affiliate_id'     => $user_id,
                        'total_user_paid'  => $already_paid
                    );
                }

                $payouts_by_source[$source]['total_earned'] += $commission;
            }
        }

        $results = array();
        foreach ($payouts_by_source as $source => $data) {
            $data['total_earned'] = round($data['total_earned'], 2);
            $results[] = $data;
        }

        return new WP_REST_Response(array(
            'payouts' => $results,
        ), 200);
    }
}
