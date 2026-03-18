<?php

/**
 * Growtype Analytics REST API Characters Partial
 *
 * Handles REST API routes for character-specific performance analytics.
 * NOTE: This partial requires the 'growtype-chat' and 'WooCommerce' plugins to be active.
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/includes/methods/api/partials
 */

class Growtype_Analytics_Rest_Api_Characters
{
    /**
     * Initialize the class and set its properties.
     */
    public function __construct()
    {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Register the routes for characters.
     */
    public function register_routes()
    {
        register_rest_route('growtype-analytics/v1', '/characters/revenue', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_characters_revenue'),
                'permission_callback' => array($this, 'get_characters_permissions_check'),
                'args'                => array(
                    'period'     => array('type' => 'string', 'enum' => array('week', 'month', 'year', 'all')),
                    'start_date' => array('type' => 'string', 'format' => 'date'),
                    'end_date'   => array('type' => 'string', 'format' => 'date'),
                    'limit'      => array('type' => 'integer', 'default' => 10),
                ),
            ),
        ));

        register_rest_route('growtype-analytics/v1', '/characters/conversion-rate', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_characters_conversion_rate'),
                'permission_callback' => array($this, 'get_characters_permissions_check'),
                'args'                => array(
                    'period' => array('type' => 'string', 'enum' => array('week', 'month', 'year', 'all'), 'default' => 'month'),
                    'limit'  => array('type' => 'integer', 'default' => 10),
                ),
            ),
        ));
    }

    /**
     * Check if a given request has access to get character data.
     */
    public function get_characters_permissions_check($request)
    {
        return current_user_can('manage_options');
    }

    /**
     * Get revenue attributed to characters.
     */
    public function get_characters_revenue($request)
    {
        global $wpdb;

        if (!class_exists('WooCommerce') || !class_exists('Growtype_Chat_Database')) {
            return new WP_Error('plugin_not_found', __('Required plugins (WooCommerce/Chat) not active.', 'growtype-analytics'), array('status' => 404));
        }

        $period = $request->get_param('period');
        $start_date = $request->get_param('start_date');
        $end_date = $request->get_param('end_date');
        $limit = $request->get_param('limit') ?: 10;

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
        } else if (empty($start_date)) {
            $start_date = date('Y-m-d', strtotime('-30 days'));
            $end_date = !empty($end_date) ? $end_date : date('Y-m-d');
        }

        // 1. Get completed orders
        $args = array(
            'limit'      => -1,
            'status'     => 'completed',
            'return'     => 'objects',
        );

        if (!empty($start_date)) {
            $args['date_created'] = '>=' . $start_date;
        }
        if (!empty($end_date)) {
            $args['date_created'] = (isset($args['date_created']) ? $args['date_created'] . '...' : '') . '<=' . $end_date;
        }

        $orders = wc_get_orders($args);
        
        if (empty($orders)) {
            return new WP_REST_Response(['characters' => [], 'total_revenue' => 0], 200);
        }

        $messages_table = $wpdb->prefix . Growtype_Chat_Database::MESSAGES_TABLE;
        $session_message_table = $wpdb->prefix . Growtype_Chat_Database::SESSION_MESSAGE_TABLE;
        $user_session_table = $wpdb->prefix . Growtype_Chat_Database::USER_SESSION_TABLE;
        $chat_users_table = $wpdb->prefix . Growtype_Chat_Database::USERS_TABLE;
        $characters_table = $wpdb->prefix . 'characters';

        $revenue_by_char = [];
        $total_attributed_revenue = 0;

        foreach ($orders as $order) {
            $user_id = $order->get_user_id();
            if (empty($user_id)) continue;

            $order_date = $order->get_date_created()->date('Y-m-d H:i:s');
            $order_total = (float) $order->get_total();

            // Find the character associated with the last message before the order
            // We need to find the chat_user_id for this WP user
            $chat_user_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $chat_users_table WHERE external_id = %d AND type = 'wp_user'",
                $user_id
            ));

            if (!$chat_user_id) continue;

            // Find last message and its session
            $char_data = $wpdb->get_row($wpdb->prepare("
                SELECT u.external_id as character_external_id, u.id as chat_bot_user_id, sm.session_id
                FROM $messages_table m
                JOIN $session_message_table sm ON m.id = sm.message_id
                JOIN $user_session_table us ON sm.session_id = us.session_id
                JOIN $chat_users_table u ON us.user_id = u.id
                WHERE m.user_id = %d 
                AND m.created_at <= %s
                AND u.type = 'bot'
                ORDER BY m.created_at DESC 
                LIMIT 1
            ", $chat_user_id, $order_date));

            if ($char_data) {
                $char_id = $char_data->character_external_id;
                if (!isset($revenue_by_char[$char_id])) {
                    $revenue_by_char[$char_id] = [
                        'character_id' => $char_id,
                        'revenue' => 0,
                        'order_count' => 0,
                        'slug' => null,
                        'name' => null
                    ];
                }
                $revenue_by_char[$char_id]['revenue'] += $order_total;
                $revenue_by_char[$char_id]['order_count']++;
                $total_attributed_revenue += $order_total;
            }
        }

        // Resolve slugs and names for the top characters
        if (!empty($revenue_by_char)) {
            uasort($revenue_by_char, function($a, $b) {
                return $b['revenue'] <=> $a['revenue'];
            });

            $top_chars = array_slice($revenue_by_char, 0, $limit, true);
            $char_ids = array_keys($top_chars);

            if (!empty($char_ids)) {
                $placeholders = implode(',', array_fill(0, count($char_ids), '%s'));
                $char_info = $wpdb->get_results($wpdb->prepare(
                    "SELECT external_id, slug, metadata FROM $characters_table WHERE external_id IN ($placeholders)",
                    ...$char_ids
                ));

                foreach ($char_info as $info) {
                    if (isset($top_chars[$info->external_id])) {
                        $top_chars[$info->external_id]['slug'] = $info->slug;
                        
                        $metadata = json_decode($info->metadata, true);
                        if (!empty($metadata) && isset($metadata['details']['character_title'])) {
                            $top_chars[$info->external_id]['name'] = $metadata['details']['character_title'];
                        }
                    }
                }
            }
            
            $revenue_by_char = array_values($top_chars);
        }

        return new WP_REST_Response(array(
            'characters' => $revenue_by_char,
            'total_revenue' => round($total_attributed_revenue, 2),
            'period' => $period,
            'start_date' => $start_date,
            'end_date' => $end_date
        ), 200);
    }

    /**
     * Get conversion rate per character (Buyers / Chatters).
     */
    public function get_characters_conversion_rate($request)
    {
        global $wpdb;

        if (!class_exists('WooCommerce') || !class_exists('Growtype_Chat_Database')) {
            return new WP_Error('plugin_not_found', __('Required plugins (WooCommerce/Chat) not active.', 'growtype-analytics'), array('status' => 404));
        }

        $period = $request->get_param('period') ?: 'month';
        $limit = (int) $request->get_param('limit') ?: 10;

        $messages_table = $wpdb->prefix . Growtype_Chat_Database::MESSAGES_TABLE;
        $chat_users_table = $wpdb->prefix . Growtype_Chat_Database::USERS_TABLE;
        $characters_table = $wpdb->prefix . 'characters';
        $user_session_table = $wpdb->prefix . Growtype_Chat_Database::USER_SESSION_TABLE;

        $date_condition = "AND m.created_at >= DATE_SUB(NOW(), INTERVAL 1 " . strtoupper($period) . ")";
        if ($period === 'all') $date_condition = "";

        // 1. Get unique chatters per character
        // Logic: A chatter is a wp_user in a session where a bot (character) is present
        $chatters_query = "
            SELECT u_bot.external_id as character_id, COUNT(DISTINCT m.user_id) as total_chatters
            FROM $messages_table m
            JOIN {$wpdb->prefix}" . Growtype_Chat_Database::SESSION_MESSAGE_TABLE . " sm ON m.id = sm.message_id
            JOIN $user_session_table us_bot ON sm.session_id = us_bot.session_id
            JOIN $chat_users_table u_bot ON us_bot.user_id = u_bot.id
            WHERE u_bot.type = 'bot'
            $date_condition
            GROUP BY character_id
        ";

        $chatters_results = $wpdb->get_results($chatters_query, OBJECT_K);

        // 2. Get unique buyers per character
        // Logic: Buyers (any status) who chatted with the character before purchase
        $buyers_query = "
            SELECT u_bot.external_id as character_id, COUNT(DISTINCT u_wp.ID) as total_buyers
            FROM $messages_table m
            JOIN {$wpdb->prefix}" . Growtype_Chat_Database::SESSION_MESSAGE_TABLE . " sm ON m.id = sm.message_id
            JOIN $user_session_table us_bot ON sm.session_id = us_bot.session_id
            JOIN $chat_users_table u_bot ON us_bot.user_id = u_bot.id
            JOIN $chat_users_table u_chat ON m.user_id = u_chat.id
            JOIN {$wpdb->users} u_wp ON u_chat.external_id = u_wp.ID
            JOIN {$wpdb->postmeta} pm_order ON u_wp.ID = pm_order.meta_value AND pm_order.meta_key = '_customer_user'
            JOIN {$wpdb->posts} p_order ON pm_order.post_id = p_order.ID AND p_order.post_status IN ('wc-completed', 'wc-processing')
            WHERE u_bot.type = 'bot' AND u_chat.type = 'wp_user'
            AND m.created_at <= p_order.post_date
            $date_condition
            GROUP BY character_id
        ";

        $buyers_results = $wpdb->get_results($buyers_query, OBJECT_K);

        $conversion_data = [];
        foreach ($chatters_results as $char_id => $data) {
            $chatters = (int) $data->total_chatters;
            $buyers = isset($buyers_results[$char_id]) ? (int) $buyers_results[$char_id]->total_buyers : 0;
            
            $rate = ($chatters > 0) ? round(($buyers / $chatters) * 100, 2) : 0;

            $conversion_data[$char_id] = [
                'character_id'    => $char_id,
                'chatters_count'  => $chatters,
                'buyers_count'    => $buyers,
                'conversion_rate' => $rate . '%',
                'name'            => null,
                'slug'            => null
            ];
        }

        // Sort by conversion rate
        uasort($conversion_data, function($a, $b) {
            return (float)$b['conversion_rate'] <=> (float)$a['conversion_rate'];
        });

        $top_chars = array_slice($conversion_data, 0, $limit, true);
        
        // Resolve names/slugs
        if (!empty($top_chars)) {
            $char_ids = array_keys($top_chars);
            $placeholders = implode(',', array_fill(0, count($char_ids), '%s'));
            $char_info = $wpdb->get_results($wpdb->prepare(
                "SELECT external_id, slug, metadata FROM $characters_table WHERE external_id IN ($placeholders)",
                ...$char_ids
            ));

            foreach ($char_info as $info) {
                if (isset($top_chars[$info->external_id])) {
                    $top_chars[$info->external_id]['slug'] = $info->slug;
                    $metadata = json_decode($info->metadata, true);
                    $top_chars[$info->external_id]['name'] = $metadata['details']['character_title'] ?? $info->slug;
                }
            }
        }

        return new WP_REST_Response(array(
            'period'           => $period,
            'characters'       => array_values($top_chars),
            'global_note'      => __('Higher conversion rate characters should be prioritized in marketing.', 'growtype-analytics')
        ), 200);
    }
}
