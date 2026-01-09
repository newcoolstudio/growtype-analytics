<?php

/**
 * Growtype Analytics REST API Documentation Partial
 *
 * Handles REST API routes for API documentation.
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/includes/methods/api/partials
 */

class Growtype_Analytics_Rest_Api_Docs
{
    /**
     * Initialize the class and set its properties.
     */
    public function __construct()
    {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Register the routes for Documentation.
     */
    public function register_routes()
    {
        register_rest_route('growtype-analytics/v1', '/docs', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_docs'),
                'permission_callback' => '__return_true', // Documentation is public
            ),
        ));
    }

    /**
     * Get the API documentation.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_REST_Response
     */
    public function get_docs($request)
    {
        $base_url = rest_url('growtype-analytics/v1');
        
        $docs = array(
            'title'       => __('Growtype Analytics API Documentation', 'growtype-analytics'),
            'description' => __('Universal API for fetching platform analytics data.', 'growtype-analytics'),
            'endpoints'   => array(
                'docs' => array(
                    'url'         => $base_url . '/docs',
                    'method'      => 'GET',
                    'description' => __('Get API documentation.', 'growtype-analytics'),
                    'permissions' => __('Public', 'growtype-analytics'),
                ),
                'users' => array(
                    'url'         => $base_url . '/users',
                    'method'      => 'GET',
                    'description' => __('Get list of registered users for a specific period.', 'growtype-analytics'),
                    'permissions' => __('Manage Options (Admin)', 'growtype-analytics'),
                    'parameters'  => array(
                        'period'     => array('week', 'month', 'year', 'all'),
                        'start_date' => 'YYYY-MM-DD (or use date_from)',
                        'end_date'   => 'YYYY-MM-DD (or use date_to)',
                        'date_from'  => 'YYYY-MM-DD (alias for start_date)',
                        'date_to'    => 'YYYY-MM-DD (alias for end_date)',
                    ),
                    'sample_queries' => array(
                        'last_week'    => $base_url . '/users?period=week',
                        'last_month'   => $base_url . '/users?period=month',
                        'custom_range' => $base_url . '/users?start_date=2023-01-01&end_date=2023-01-31',
                        'custom_range_alt' => $base_url . '/users?date_from=2023-01-01&date_to=2023-01-31',
                    ),
                ),
                'users/daily' => array(
                    'url'         => $base_url . '/users/daily',
                    'method'      => 'GET',
                    'description' => __('Get daily registrations for a specific period.', 'growtype-analytics'),
                    'permissions' => __('Manage Options (Admin)', 'growtype-analytics'),
                    'parameters'  => array(
                        'period'     => array('week', 'month', 'year', 'all'),
                        'start_date' => 'YYYY-MM-DD (or use date_from)',
                        'end_date'   => 'YYYY-MM-DD (or use date_to)',
                        'date_from'  => 'YYYY-MM-DD (alias for start_date)',
                        'date_to'    => 'YYYY-MM-DD (alias for end_date)',
                    ),
                    'sample_queries' => array(
                        'last_week'    => $base_url . '/users/daily?period=week',
                        'last_month'   => $base_url . '/users/daily?period=month',
                        'custom_range' => $base_url . '/users/daily?start_date=2023-01-01&end_date=2023-01-31',
                        'custom_range_alt' => $base_url . '/users/daily?date_from=2023-01-01&date_to=2023-01-31',
                    ),
                ),
            ),
        );

        if (class_exists('Growtype_Chat')) {
            $docs['endpoints']['chat/messages/daily'] = array(
                'url'         => $base_url . '/chat/messages/daily',
                'method'      => 'GET',
                'description' => __('Get daily chat message counts for a specific period.', 'growtype-analytics'),
                'permissions' => __('Manage Options (Admin)', 'growtype-analytics'),
                'parameters'  => array(
                    'period'     => array('week', 'month', 'year', 'all'),
                    'start_date' => 'YYYY-MM-DD (or use date_from)',
                    'end_date'   => 'YYYY-MM-DD (or use date_to)',
                    'date_from'  => 'YYYY-MM-DD (alias for start_date)',
                    'date_to'    => 'YYYY-MM-DD (alias for end_date)',
                    'user_type'  => array('bot', 'wp_user'),
                ),
                'sample_queries' => array(
                    'last_week'    => $base_url . '/chat/messages/daily?period=week',
                    'last_month'   => $base_url . '/chat/messages/daily?period=month',
                    'bots_only'    => $base_url . '/chat/messages/daily?user_type=bot&period=week',
                    'users_only'   => $base_url . '/chat/messages/daily?user_type=wp_user&period=week',
                    'custom_range' => $base_url . '/chat/messages/daily?date_from=2023-01-01&date_to=2023-01-31',
                ),
            );
            $docs['endpoints']['chat/users/daily'] = array(
                'url'         => $base_url . '/chat/users/daily',
                'method'      => 'GET',
                'description' => __('Get daily active users who sent at least X messages.', 'growtype-analytics'),
                'permissions' => __('Manage Options (Admin)', 'growtype-analytics'),
                'parameters'  => array(
                    'min_messages' => __('Minimum number of messages to be considered active (default 1).', 'growtype-analytics'),
                    'period'       => array('week', 'month', 'year', 'all'),
                    'start_date'   => 'YYYY-MM-DD',
                    'end_date'     => 'YYYY-MM-DD',
                    'user_type'    => array('bot', 'wp_user'),
                ),
                'sample_queries' => array(
                    'sent_at_least_1'  => $base_url . '/chat/users/daily?min_messages=1&period=week',
                    'bots_at_least_3'  => $base_url . '/chat/users/daily?min_messages=3&user_type=bot&period=week',
                    'users_at_least_10'=> $base_url . '/chat/users/daily?min_messages=10&user_type=wp_user&period=week',
                ),
            );
            $docs['endpoints']['chat/characters/popular'] = array(
                'url'         => $base_url . '/chat/characters/popular',
                'method'      => 'GET',
                'description' => __('Get daily rankings of the most popular characters based on message count. Returns the top N characters (by total message volume for the period) with their daily message counts.', 'growtype-analytics'),
                'permissions' => __('Manage Options (Admin)', 'growtype-analytics'),
                'parameters'  => array(
                    'limit'      => __('Number of top characters to retrieve (default 3). Common values: 3, 5, 10.', 'growtype-analytics'),
                    'period'     => array('week', 'month', 'year', 'all'),
                    'start_date' => 'YYYY-MM-DD',
                    'end_date'   => 'YYYY-MM-DD',
                ),
                'response_format' => array(
                    'structure' => 'daily_popular_characters: { "YYYY-MM-DD": [ { character_slug, message_count }, ... ] }',
                    'notes'     => 'The endpoint first identifies the top N characters by total popularity, then returns their daily breakdown. Character slugs are resolved from encrypted session metadata and the character database.',
                ),
                'sample_queries' => array(
                    'top_3_last_week'    => $base_url . '/chat/characters/popular?period=week',
                    'top_5_last_month'   => $base_url . '/chat/characters/popular?limit=5&period=month',
                    'top_10_custom_range'=> $base_url . '/chat/characters/popular?limit=10&start_date=2025-12-01&end_date=2025-12-31',
                ),
            );
        }

        if (class_exists('WooCommerce')) {
            $docs['endpoints']['orders'] = array(
                'url'         => $base_url . '/orders',
                'method'      => 'GET',
                'description' => __('Get WooCommerce orders for a specific period and status.', 'growtype-analytics'),
                'permissions' => __('Manage Options (Admin)', 'growtype-analytics'),
                'parameters'  => array(
                    'status'     => array('purchased', 'pending', 'all', 'any', 'completed', 'processing', 'on-hold', 'cancelled', 'refunded', 'failed'),
                    'period'     => array('week', 'month', 'year', 'all'),
                    'start_date' => 'YYYY-MM-DD (or use date_from)',
                    'end_date'   => 'YYYY-MM-DD (or use date_to)',
                    'date_from'  => 'YYYY-MM-DD (alias for start_date)',
                    'date_to'    => 'YYYY-MM-DD (alias for end_date)',
                ),
                'sample_queries' => array(
                    'completed_last_month' => $base_url . '/orders?status=completed&period=month',
                    'pending_orders'       => $base_url . '/orders?status=pending',
                    'annual_report'        => $base_url . '/orders?period=year',
                    'custom_range'         => $base_url . '/orders?date_from=2023-01-01&date_to=2023-01-31',
                ),
            );
            $docs['endpoints']['orders/daily'] = array(
                'url'         => $base_url . '/orders/daily',
                'method'      => 'GET',
                'description' => __('Get daily WooCommerce order counts for a specific period (returns date => count).', 'growtype-analytics'),
                'permissions' => __('Manage Options (Admin)', 'growtype-analytics'),
                'parameters'  => array(
                    'status'     => array('purchased', 'pending', 'all', 'any', 'completed', 'processing', 'on-hold', 'cancelled', 'refunded', 'failed'),
                    'period'     => array('week', 'month', 'year', 'all'),
                    'start_date' => 'YYYY-MM-DD (or use date_from)',
                    'end_date'   => 'YYYY-MM-DD (or use date_to)',
                    'date_from'  => 'YYYY-MM-DD (alias for start_date)',
                    'date_to'    => 'YYYY-MM-DD (alias for end_date)',
                ),
                'sample_queries' => array(
                    'purchased_daily_week' => $base_url . '/orders/daily?period=week&status=purchased',
                    'pending_daily_week'   => $base_url . '/orders/daily?period=week&status=pending',
                    'all_orders_daily'     => $base_url . '/orders/daily?period=week&status=all',
                    'custom_range_daily'   => $base_url . '/orders/daily?date_from=2023-01-01&date_to=2023-01-31',
                ),
            );
            $docs['endpoints']['orders/sales/daily'] = array(
                'url'         => $base_url . '/orders/sales/daily',
                'method'      => 'GET',
                'description' => __('Get daily WooCommerce sales totals for a specific period (returns date => amount).', 'growtype-analytics'),
                'permissions' => __('Manage Options (Admin)', 'growtype-analytics'),
                'parameters'  => array(
                    'status'     => array('purchased', 'pending', 'all', 'any', 'completed', 'processing', 'on-hold', 'cancelled', 'refunded', 'failed'),
                    'period'     => array('week', 'month', 'year', 'all'),
                    'start_date' => 'YYYY-MM-DD (or use date_from)',
                    'end_date'   => 'YYYY-MM-DD (or use date_to)',
                    'date_from'  => 'YYYY-MM-DD (alias for start_date)',
                    'date_to'    => 'YYYY-MM-DD (alias for end_date)',
                ),
                'sample_queries' => array(
                    'sales_daily_week' => $base_url . '/orders/sales/daily?period=week',
                    'sales_daily_month' => $base_url . '/orders/sales/daily?period=month',
                ),
            );
            $docs['endpoints']['orders/distribution'] = array(
                'url'         => $base_url . '/orders/distribution',
                'method'      => 'GET',
                'description' => __('Get distribution of WooCommerce orders by status (returns status => count).', 'growtype-analytics'),
                'permissions' => __('Manage Options (Admin)', 'growtype-analytics'),
                'parameters'  => array(
                    'period'     => array('week', 'month', 'year', 'all'),
                    'start_date' => 'YYYY-MM-DD (or use date_from)',
                    'end_date'   => 'YYYY-MM-DD (or use date_to)',
                    'date_from'  => 'YYYY-MM-DD (alias for start_date)',
                    'date_to'    => 'YYYY-MM-DD (alias for end_date)',
                ),
                'sample_queries' => array(
                    'distribution_week'  => $base_url . '/orders/distribution?period=week',
                    'distribution_month' => $base_url . '/orders/distribution?period=month',
                ),
            );
            $docs['endpoints']['orders/daily-distribution'] = array(
                'url'         => $base_url . '/orders/daily-distribution',
                'method'      => 'GET',
                'description' => __('Get daily distribution of WooCommerce orders by status (returns date => { status => count }).', 'growtype-analytics'),
                'permissions' => __('Manage Options (Admin)', 'growtype-analytics'),
                'parameters'  => array(
                    'period'     => array('week', 'month', 'year', 'all'),
                    'start_date' => 'YYYY-MM-DD (or use date_from)',
                    'end_date'   => 'YYYY-MM-DD (or use date_to)',
                    'date_from'  => 'YYYY-MM-DD (alias for start_date)',
                    'date_to'    => 'YYYY-MM-DD (alias for end_date)',
                ),
                'sample_queries' => array(
                    'daily_distribution_week'  => $base_url . '/orders/daily-distribution?period=week',
                    'daily_distribution_month' => $base_url . '/orders/daily-distribution?period=month',
                ),
            );
            $docs['endpoints']['conversion/daily'] = array(
                'url'         => $base_url . '/conversion/daily',
                'method'      => 'GET',
                'description' => __('Get daily conversion rate (orders / registrations * 100) for a specific period (returns date => rate).', 'growtype-analytics'),
                'permissions' => __('Manage Options (Admin)', 'growtype-analytics'),
                'parameters'  => array(
                    'status'     => array('purchased', 'pending', 'all', 'any', 'completed', 'processing', 'on-hold', 'cancelled', 'refunded', 'failed'),
                    'period'     => array('week', 'month', 'year', 'all'),
                    'start_date' => 'YYYY-MM-DD (or use date_from)',
                    'end_date'   => 'YYYY-MM-DD (or use date_to)',
                    'date_from'  => 'YYYY-MM-DD (alias for start_date)',
                    'date_to'    => 'YYYY-MM-DD (alias for end_date)',
                ),
                'sample_queries' => array(
                    'conversion_week'  => $base_url . '/conversion/daily?period=week',
                    'conversion_month' => $base_url . '/conversion/daily?period=month',
                ),
            );
        }

        if (class_exists('Growtype_Affiliate')) {
            $docs['endpoints']['affiliate/payouts'] = array(
                'url'         => $base_url . '/affiliate/payouts',
                'method'      => 'GET',
                'description' => __('Get total affiliate commissions grouped by referral source.', 'growtype-analytics'),
                'permissions' => __('Manage Options (Admin)', 'growtype-analytics'),
                'response_format' => array(
                    'structure' => 'payouts: [ { source, total_earned, affiliate_name, affiliate_email, affiliate_id, total_user_paid }, ... ]',
                    'notes'     => 'Calculates 40% for subscriptions and 30% for one-time orders by default. Total user paid is the manual adjustment from the user profile.',
                ),
                'sample_queries' => array(
                    'all_payouts' => $base_url . '/affiliate/payouts',
                ),
            );
        }

        return new WP_REST_Response($docs, 200);
    }
}
