<?php

/**
 * Growtype Analytics REST API Chat Partial
 *
 * Handles REST API routes for chat-related data.
 * NOTE: This partial requires the 'growtype-chat' plugin to be active.
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/includes/methods/api/partials
 */

class Growtype_Analytics_Rest_Api_Chat
{
    /**
     * Initialize the class and set its properties.
     */
    public function __construct()
    {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Register the routes for the chat.
     */
    public function register_routes()
    {
        register_rest_route('growtype-analytics/v1', '/chat/characters/popular', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_popular_characters'),
                'permission_callback' => array($this, 'get_chat_permissions_check'),
                'args'                => array(
                    'period' => array(
                        'description' => __('The period for which to get the popular characters (week, month, etc.).', 'growtype-analytics'),
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
                    'limit' => array(
                        'description' => __('Limit the number of results.', 'growtype-analytics'),
                        'type'        => 'integer',
                        'default'     => 3,
                        'required'    => false,
                    ),
                ),
            ),
        ));

        register_rest_route('growtype-analytics/v1', '/chat/messages/daily', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_messages_daily'),
                'permission_callback' => array($this, 'get_chat_permissions_check'),
                'args'                => array(
                    'period' => array(
                        'description' => __('The period for which to get the daily messages (week, month, etc.).', 'growtype-analytics'),
                        'type'        => 'string',
                        'enum'        => array('week', 'month', 'year', 'all'),
                        'required'    => false,
                    ),
                    'start_date' => array(
                        'description' => __('The start date for the messages (YYYY-MM-DD).', 'growtype-analytics'),
                        'type'        => 'string',
                        'format'      => 'date',
                        'required'    => false,
                    ),
                    'end_date' => array(
                        'description' => __('The end date for the messages (YYYY-MM-DD).', 'growtype-analytics'),
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
                    'user_type' => array(
                        'description' => __('Filter by user type (bot, wp_user).', 'growtype-analytics'),
                        'type'        => 'string',
                        'enum'        => array('bot', 'wp_user'),
                        'required'    => false,
                    ),
                ),
            ),
        ));

        register_rest_route('growtype-analytics/v1', '/chat/users/daily', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_active_users_daily'),
                'permission_callback' => array($this, 'get_chat_permissions_check'),
                'args'                => array(
                    'min_messages' => array(
                        'description' => __('Min messages per day.', 'growtype-analytics'),
                        'type'        => 'integer',
                        'default'     => 1,
                    ),
                    'period' => array(
                        'type' => 'string',
                        'enum' => array('week', 'month', 'year', 'all'),
                    ),
                    'start_date' => array('type' => 'string', 'format' => 'date'),
                    'end_date'   => array('type' => 'string', 'format' => 'date'),
                    'date_from'  => array('type' => 'string', 'format' => 'date'),
                    'date_to'    => array('type' => 'string', 'format' => 'date'),
                    'user_type' => array(
                        'description' => __('Filter by user type (bot, wp_user).', 'growtype-analytics'),
                        'type'        => 'string',
                        'enum'        => array('bot', 'wp_user'),
                        'required'    => false,
                    ),
                ),
            ),
        ));

        register_rest_route('growtype-analytics/v1', '/chat/conversations/buyers', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_conversations_buyers'),
                'permission_callback' => array($this, 'get_chat_permissions_check'),
                'args'                => array(
                    'limit' => array(
                        'description' => __('Limit the number of users.', 'growtype-analytics'),
                        'type'        => 'integer',
                        'default'     => 10,
                        'required'    => false,
                    ),
                    'messages_limit' => array(
                        'description' => __('Limit the number of messages per conversation.', 'growtype-analytics'),
                        'type'        => 'integer',
                        'default'     => 20,
                        'required'    => false,
                    ),
                ),
            ),
        ));

        register_rest_route('growtype-analytics/v1', '/chat/conversations/non-buyers', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_conversations_non_buyers'),
                'permission_callback' => array($this, 'get_chat_permissions_check'),
                'args'                => array(
                    'limit' => array(
                        'description' => __('Limit the number of users.', 'growtype-analytics'),
                        'type'        => 'integer',
                        'default'     => 10,
                        'required'    => false,
                    ),
                    'messages_limit' => array(
                        'description' => __('Limit the number of messages per conversation.', 'growtype-analytics'),
                        'type'        => 'integer',
                        'default'     => 20,
                        'required'    => false,
                    ),
                ),
            ),
        ));

        register_rest_route('growtype-analytics/v1', '/chat/conversations/newest', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_conversations_newest'),
                'permission_callback' => array($this, 'get_chat_permissions_check'),
                'args'                => array(
                    'limit' => array(
                        'description' => __('Limit the number of users (up to 100).', 'growtype-analytics'),
                        'type'        => 'integer',
                        'default'     => 10,
                        'required'    => false,
                    ),
                    'messages_limit' => array(
                        'description' => __('Limit the number of messages per conversation.', 'growtype-analytics'),
                        'type'        => 'integer',
                        'default'     => 20,
                        'required'    => false,
                    ),
                ),
            ),
        ));
    }

    /**
     * Check if a given request has access to get chat data.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|bool
     */
    public function get_chat_permissions_check($request)
    {
        return current_user_can('manage_options');
    }

    /**
     * Get the daily created messages for a specific period.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function get_messages_daily($request)
    {
        global $wpdb;

        if (!class_exists('Growtype_Chat_Database')) {
            return new WP_Error('growtype_chat_not_found', __('Growtype Chat plugin is not active.', 'growtype-analytics'), array('status' => 404));
        }

        $messages_table = $wpdb->prefix . Growtype_Chat_Database::MESSAGES_TABLE;
        $users_table = $wpdb->prefix . Growtype_Chat_Database::USERS_TABLE;

        $period = $request->get_param('period');
        $start_date = $request->get_param('date_from') ?: $request->get_param('start_date');
        $end_date = $request->get_param('date_to') ?: $request->get_param('end_date');
        $user_type = $request->get_param('user_type');

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

        $results = [];
        $where = [];

        if (!empty($start_date)) {
            $where[] = $wpdb->prepare("DATE(m.created_at) >= %s", $start_date);
        }
        if (!empty($end_date)) {
            $where[] = $wpdb->prepare("DATE(m.created_at) <= %s", $end_date);
        }
        if (!empty($user_type)) {
            $where[] = $wpdb->prepare("u.type = %s", $user_type);
        }

        $query = "SELECT DATE(m.created_at) as creation_date, COUNT(m.id) as message_count 
                  FROM $messages_table m 
                  JOIN $users_table u ON m.user_id = u.id";

        if (!empty($where)) {
            $query .= " WHERE " . implode(' AND ', $where);
        }
        $query .= " GROUP BY DATE(m.created_at) ORDER BY creation_date ASC";

        $db_results = $wpdb->get_results($query);
        $mapped_data = [];
        foreach ($db_results as $row) {
            $mapped_data[$row->creation_date] = (int) $row->message_count;
        }

        $daily_messages = array();

        if (!empty($start_date) && !empty($end_date)) {
            $current = strtotime($start_date);
            $last = strtotime($end_date);

            while ($current <= $last) {
                $date = date('Y-m-d', $current);
                $daily_messages[$date] = isset($mapped_data[$date]) ? $mapped_data[$date] : 0;
                $current = strtotime("+1 day", $current);
            }
        } else {
            $daily_messages = $mapped_data;
        }

        return new WP_REST_Response(array(
            'daily_messages' => $daily_messages,
            'period'         => $period,
            'start_date'     => $start_date,
            'end_date'       => $end_date,
        ), 200);
    }

    /**
     * Get the daily active users (sent X messages) for a specific period.
     */
    public function get_active_users_daily($request)
    {
        global $wpdb;

        if (!class_exists('Growtype_Chat_Database')) {
            return new WP_Error('growtype_chat_not_found', __('Growtype Chat plugin is not active.', 'growtype-analytics'), array('status' => 404));
        }

        $messages_table = $wpdb->prefix . Growtype_Chat_Database::MESSAGES_TABLE;
        $users_table = $wpdb->prefix . Growtype_Chat_Database::USERS_TABLE;

        $period = $request->get_param('period');
        $start_date = $request->get_param('date_from') ?: $request->get_param('start_date');
        $end_date = $request->get_param('date_to') ?: $request->get_param('end_date');
        $min_messages = (int) $request->get_param('min_messages') ?: 1;
        $user_type = $request->get_param('user_type');

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

        $where = [];
        if (!empty($start_date)) {
            $where[] = $wpdb->prepare("DATE(m.created_at) >= %s", $start_date);
        }
        if (!empty($end_date)) {
            $where[] = $wpdb->prepare("DATE(m.created_at) <= %s", $end_date);
        }
        if (!empty($user_type)) {
            $where[] = $wpdb->prepare("u.type = %s", $user_type);
        }

        $where_clause = !empty($where) ? " WHERE " . implode(' AND ', $where) : "";

        $query = "
            SELECT creation_date, COUNT(user_id) as active_users_count
            FROM (
                SELECT m.user_id, DATE(m.created_at) as creation_date, COUNT(m.id) as msg_count
                FROM $messages_table m
                JOIN $users_table u ON m.user_id = u.id
                $where_clause
                GROUP BY m.user_id, creation_date
            ) as user_activity
            WHERE msg_count >= %d
            GROUP BY creation_date
            ORDER BY creation_date ASC
        ";

        $db_results = $wpdb->get_results($wpdb->prepare($query, $min_messages));
        $mapped_data = [];
        foreach ($db_results as $row) {
            $mapped_data[$row->creation_date] = (int) $row->active_users_count;
        }

        $daily_users = array();
        if (!empty($start_date) && !empty($end_date)) {
            $current = strtotime($start_date);
            $last = strtotime($end_date);
            while ($current <= $last) {
                $date = date('Y-m-d', $current);
                $daily_users[$date] = isset($mapped_data[$date]) ? $mapped_data[$date] : 0;
                $current = strtotime("+1 day", $current);
            }
        } else {
            $daily_users = $mapped_data;
        }

        return new WP_REST_Response(array(
            'daily_users'  => $daily_users,
            'min_messages' => $min_messages,
            'period'       => $period,
            'start_date'   => $start_date,
            'end_date'     => $end_date,
        ), 200);
    }
    public function get_popular_characters($request)
    {
        global $wpdb;

        if (!class_exists('Growtype_Chat_Database')) {
            return new WP_Error('growtype_chat_not_found', __('Growtype Chat plugin is not active.', 'growtype-analytics'), array('status' => 404));
        }

        $messages_table = $wpdb->prefix . Growtype_Chat_Database::MESSAGES_TABLE;
        $session_message_table = $wpdb->prefix . Growtype_Chat_Database::SESSION_MESSAGE_TABLE;
        $user_session_table = $wpdb->prefix . Growtype_Chat_Database::USER_SESSION_TABLE;
        $users_table = $wpdb->prefix . Growtype_Chat_Database::USERS_TABLE;

        $period = $request->get_param('period');
        $start_date = $request->get_param('start_date');
        $end_date = $request->get_param('end_date');
        $limit = $request->get_param('limit') ?: 3;

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

        $where = [];
        if (!empty($start_date)) {
            $where[] = $wpdb->prepare("m.created_at >= %s", $start_date . ' 00:00:00');
        }
        if (!empty($end_date)) {
            $where[] = $wpdb->prepare("m.created_at <= %s", $end_date . ' 23:59:59');
        }

        $where[] = $wpdb->prepare("u.type = %s", 'bot');
        
        $where_clause = !empty($where) ? " WHERE " . implode(' AND ', $where) : "";

        /**
         * SQL Aggregation: Get counts grouped by day and user.
         */
        $query = "
            SELECT 
                DATE(m.created_at) as day,
                us.user_id as user_id,
                u.external_id as character_id, 
                MAX(sm.session_id) as sample_session_id, 
                COUNT(m.id) as message_count
            FROM $messages_table m
            JOIN $session_message_table sm ON m.id = sm.message_id
            JOIN $user_session_table us ON sm.session_id = us.session_id
            JOIN $users_table u ON us.user_id = u.id
            $where_clause
            GROUP BY day, us.user_id
            ORDER BY day DESC, message_count DESC
        ";

        $results = $wpdb->get_results($query);
        
        if (empty($results)) {
            return new WP_REST_Response(['daily_popular_characters' => []], 200);
        }

        /**
         * 1. Determine Top N Characters overall for this period based on Total 메시지 count.
         */
        $overall_popularity = [];
        foreach ($results as $result) {
            $user_id = $result->user_id;
            if (!isset($overall_popularity[$user_id])) {
                $overall_popularity[$user_id] = [
                    'count' => 0,
                    'sample_session_id' => $result->sample_session_id,
                    'character_id' => $result->character_id
                ];
            }
            $overall_popularity[$user_id]['count'] += (int) $result->message_count;
        }

        uasort($overall_popularity, function($a, $b) {
            return $b['count'] <=> $a['count'];
        });

        $top_chars_info = array_slice($overall_popularity, 0, $limit, true);
        $top_user_ids = array_keys($top_chars_info);

        /**
         * 2. Batch resolve slugs ONLY for the top N characters.
         */
        $sample_session_ids = array_column($top_chars_info, 'sample_session_id');
        $top_external_ids = array_column($top_chars_info, 'character_id');
        $resolved_slugs = [];

        // 2a. Resolve from session meta (handles encrypted slugs)
        if (class_exists('Growtype_Chat_Session') && !empty($sample_session_ids)) {
            $placeholders = implode(',', array_fill(0, count($sample_session_ids), '%d'));
            $meta_table = $wpdb->prefix . Growtype_Chat_Database::SESSION_META_TABLE;
            $meta_results = $wpdb->get_results($wpdb->prepare(
                "SELECT session_id, meta_value FROM $meta_table WHERE meta_key = 'slug' AND session_id IN ($placeholders)",
                ...$sample_session_ids
            ));

            foreach ($meta_results as $meta) {
                if (class_exists('Growtype_Chat_Message')) {
                    $decrypted = Growtype_Chat_Message::decode_content($meta->meta_value);
                    if (!empty($decrypted) && is_string($decrypted)) {
                        $resolved_slugs['session_' . $meta->session_id] = $decrypted;
                    }
                }
            }
        }

        // 2b. Resolve from character database (handles numeric external_id fallbacks)
        $external_id_to_slug = [];
        if (!empty($top_external_ids)) {
            $placeholders = implode(',', array_fill(0, count($top_external_ids), '%s'));
            $characters_table = $wpdb->prefix . 'characters';
            $char_results = $wpdb->get_results($wpdb->prepare(
                "SELECT external_id, slug FROM $characters_table WHERE external_id IN ($placeholders)",
                ...$top_external_ids
            ));
            foreach ($char_results as $char) {
                $external_id_to_slug[$char->external_id] = $char->slug;
            }
        }

        /**
         * 3. Group by day and filter for ONLY those top N characters.
         * We also aggregate by slug here in case multiple user IDs map to the same character.
         */
        $daily_data = [];
        $unique_slug_map = [];

        foreach ($results as $result) {
            if (!isset($top_chars_info[$result->user_id])) {
                continue;
            }

            $day = $result->day;
            $user_id = $result->user_id;
            $character_id = $result->character_id;
            $sample_session_id = $result->sample_session_id;

            /**
             * Final Slug Resolution:
             * 1. Check decrypted session meta (most specific)
             * 2. Check character database mapping (reliable for external_id)
             * 3. Fallback to raw character_id
             */
            $resolved_slug = $character_id;
            if (isset($resolved_slugs['session_' . $sample_session_id]) && !is_numeric($resolved_slugs['session_' . $sample_session_id])) {
                $resolved_slug = $resolved_slugs['session_' . $sample_session_id];
            } elseif (isset($external_id_to_slug[$character_id])) {
                $resolved_slug = $external_id_to_slug[$character_id];
            }

            if (!isset($daily_data[$day])) {
                $daily_data[$day] = [];
            }

            // Aggregate by slug within the same day
            if (!isset($unique_slug_map[$day][$resolved_slug])) {
                $unique_slug_map[$day][$resolved_slug] = count($daily_data[$day]);
                $daily_data[$day][] = [
                    'character_slug' => $resolved_slug,
                    'message_count'  => 0,
                ];
            }

            $index = $unique_slug_map[$day][$resolved_slug];
            $daily_data[$day][$index]['message_count'] += (int) $result->message_count;
        }

        // Final sort per day just in case
        foreach ($daily_data as &$day_data) {
            usort($day_data, function($a, $b) {
                return $b['message_count'] <=> $a['message_count'];
            });
        }

        return new WP_REST_Response([
            'daily_popular_characters' => $daily_data,
            'period'                  => $period,
            'start_date'              => $start_date,
            'end_date'                => $end_date,
        ], 200);
    }

    /**
     * Get conversations of users who bought credits.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function get_conversations_buyers($request)
    {
        global $wpdb;

        if (!class_exists('Growtype_Chat_Database')) {
            return new WP_Error('growtype_chat_not_found', __('Growtype Chat plugin is not active.', 'growtype-analytics'), array('status' => 404));
        }

        $limit = $request->get_param('limit') ?: 10;
        $messages_limit = $request->get_param('messages_limit') ?: 20;

        // 1. Get WP User IDs of buyers (those with completed orders), their total spend, and sum of credits purchased
        // Exclude @talkiemate.com users and sort by newest registration
        $buyer_data = $wpdb->get_results("
            SELECT DISTINCT pm.meta_value as wp_user_id, 
                   SUM(CAST(o.meta_value AS DECIMAL(10,2))) as total_spent, 
                   MAX(p.post_date) as last_order_date,
                   u.user_registered
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            INNER JOIN {$wpdb->postmeta} o ON p.ID = o.post_id AND o.meta_key = '_order_total'
            INNER JOIN {$wpdb->users} u ON pm.meta_value = u.ID
            WHERE pm.meta_key = '_customer_user' 
            AND p.post_type = 'shop_order' 
            AND p.post_status IN ('wc-completed', 'wc-processing')
            AND pm.meta_value > 0
            AND u.user_email NOT LIKE '%@talkiemate.com'
            GROUP BY pm.meta_value
            ORDER BY u.user_registered DESC
        ", ARRAY_A);

        $buyer_wp_user_ids = array_column($buyer_data, 'wp_user_id');
        $buyer_metrics = [];
        
        if (!empty($buyer_wp_user_ids)) {
            foreach ($buyer_data as $b) {
                // For each buyer, we also need to sum up 'growtype_chat_credits_amount' from their order items
                // This is slightly complex to do in one SQL query without a lot of joins, so we'll do it per user or in a batch if needed
                // But for now, let's estimate or fetch from order items
                $total_credits = 0;
                $orders = $wpdb->get_col($wpdb->prepare("
                    SELECT post_id FROM {$wpdb->postmeta} 
                    WHERE meta_key = '_customer_user' AND meta_value = %s
                ", $b['wp_user_id']));
                
                if (!empty($orders)) {
                    $order_placeholders = implode(',', array_fill(0, count($orders), '%d'));
                    // Sum up credits from order items meta
                    $total_credits = $wpdb->get_var($wpdb->prepare("
                        SELECT SUM(meta_value) 
                        FROM {$wpdb->prefix}woocommerce_order_itemmeta oim
                        JOIN {$wpdb->prefix}woocommerce_order_items oi ON oim.order_item_id = oi.order_item_id
                        WHERE oi.order_id IN ($order_placeholders) AND oim.meta_key = '_qty'
                        AND EXISTS (
                            SELECT 1 FROM {$wpdb->postmeta} pm2 
                            WHERE pm2.post_id = (SELECT meta_value FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE order_item_id = oi.order_item_id AND meta_key = '_product_id')
                            AND pm2.meta_key = 'growtype_chat_credits_amount'
                        )
                    ", ...$orders));
                    
                    // Actually, let's just use a more direct way: sum(qty * growtype_chat_credits_amount)
                    // This is getting complex for SQL, let's do a PHP loop for the limit-restricted users instead to keep it performant
                }

                $buyer_metrics[$b['wp_user_id']] = [
                    'total_spent'              => (float) $b['total_spent'],
                    'last_order_date'          => $b['last_order_date'],
                    'total_credits_purchased'  => 0, // Will populate in the main loop for the specific users we return
                ];
            }
        }

        // 2. Map to Chat User IDs
        $buyer_chat_user_ids = [];
        $chat_to_wp_map = [];
        if (!empty($buyer_wp_user_ids)) {
            $placeholders = implode(',', array_fill(0, count($buyer_wp_user_ids), '%d'));
            // Use FIELD() to maintain the order of $buyer_wp_user_ids (latest orders first)
            $order_by_field = "FIELD(external_id, " . implode(',', array_map('intval', $buyer_wp_user_ids)) . ")";
            $chat_users = $wpdb->get_results($wpdb->prepare(
                "SELECT id, external_id FROM {$wpdb->prefix}" . Growtype_Chat_Database::USERS_TABLE . " WHERE external_id IN ($placeholders) AND type = 'wp_user' ORDER BY $order_by_field",
                ...$buyer_wp_user_ids
            ), ARRAY_A);
            
            foreach ($chat_users as $cu) {
                $buyer_chat_user_ids[] = (int) $cu['id'];
                $chat_to_wp_map[$cu['id']] = $cu['external_id'];
            }
        }

        $results = $this->get_group_conversations($buyer_chat_user_ids, $limit, $messages_limit, $buyer_metrics, $chat_to_wp_map);

        return new WP_REST_Response($results, 200);
    }

    /**
     * Get conversations of users who did not buy credits.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function get_conversations_non_buyers($request)
    {
        global $wpdb;

        if (!class_exists('Growtype_Chat_Database')) {
            return new WP_Error('growtype_chat_not_found', __('Growtype Chat plugin is not active.', 'growtype-analytics'), array('status' => 404));
        }

        $limit = $request->get_param('limit') ?: 10;
        $messages_limit = $request->get_param('messages_limit') ?: 20;

        // 1. Get WP User IDs of buyers (those with completed orders) to exclude them
        $buyer_wp_user_ids = $wpdb->get_col("
            SELECT DISTINCT pm.meta_value 
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = '_customer_user' 
            AND p.post_type = 'shop_order' 
            AND p.post_status IN ('wc-completed', 'wc-processing')
            AND pm.meta_value > 0
        ");

        $buyer_chat_user_ids = [];
        if (!empty($buyer_wp_user_ids)) {
            $placeholders = implode(',', array_fill(0, count($buyer_wp_user_ids), '%d'));
            $buyer_chat_user_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}" . Growtype_Chat_Database::USERS_TABLE . " WHERE external_id IN ($placeholders) AND type = 'wp_user'",
                ...$buyer_wp_user_ids
            ));
        }

        // 2. Identify "High Intent" Non-Buyers (those with failed, pending, or cancelled orders)
        $intent_metrics = [];
        $high_intent_wp_ids = [];
        $attempts = $wpdb->get_results("
            SELECT pm.meta_value as wp_user_id, p.post_status, p.post_date, o.meta_value as total
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            INNER JOIN {$wpdb->postmeta} o ON p.ID = o.post_id AND o.meta_key = '_order_total'
            INNER JOIN {$wpdb->users} u ON pm.meta_value = u.ID
            WHERE pm.meta_key = '_customer_user' 
            AND p.post_type = 'shop_order' 
            AND p.post_status IN ('wc-failed', 'wc-pending', 'wc-cancelled')
            AND u.user_email NOT LIKE '%@talkiemate.com'
            " . (!empty($buyer_wp_user_ids) ? "AND pm.meta_value NOT IN (" . implode(',', $buyer_wp_user_ids) . ")" : "") . "
            ORDER BY p.post_date DESC
        ", ARRAY_A);

        foreach ($attempts as $at) {
            $uid = (int) $at['wp_user_id'];
            $high_intent_wp_ids[] = $uid;
            $intent_metrics[$uid]['attempts'][] = [
                'status' => str_replace('wc-', '', $at['post_status']),
                'date'   => $at['post_date'],
                'total'  => $at['total']
            ];
            $intent_metrics[$uid]['has_intent'] = true;
        }

        // 3. Get Non-Buyers (registered users who haven't bought)
        // We join with the users table to handle the email exclusion
        // We also order by: High Intent First, then Message Count
        $not_in_clause = !empty($buyer_chat_user_ids) ? "AND u.id NOT IN (" . implode(',', $buyer_chat_user_ids) . ")" : "";
        
        $intent_ids_str = !empty($high_intent_wp_ids) ? implode(',', array_unique($high_intent_wp_ids)) : '0';

        $non_buyer_chat_user_ids = $wpdb->get_col("
            SELECT u.id
            FROM {$wpdb->prefix}" . Growtype_Chat_Database::USERS_TABLE . " u
            INNER JOIN {$wpdb->users} wpu ON u.external_id = wpu.ID
            JOIN (
                SELECT user_id, MAX(created_at) as last_activity
                FROM {$wpdb->prefix}" . Growtype_Chat_Database::MESSAGES_TABLE . "
                GROUP BY user_id
            ) m ON u.id = m.user_id
            WHERE u.external_id > 0 
            AND u.type = 'wp_user'
            AND wpu.user_email NOT LIKE '%@talkiemate.com'
            $not_in_clause
            ORDER BY 
                wpu.user_registered DESC,
                m.last_activity DESC
            LIMIT 200
        ");

        $results = $this->get_group_conversations($non_buyer_chat_user_ids, $limit, $messages_limit, $intent_metrics);

        return new WP_REST_Response($results, 200);
    }

    /**
     * Get conversations of the newest registered users.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function get_conversations_newest($request)
    {
        global $wpdb;

        if (!class_exists('Growtype_Chat_Database')) {
            return new WP_Error('growtype_chat_not_found', __('Growtype Chat plugin is not active.', 'growtype-analytics'), array('status' => 404));
        }

        $limit = min(100, $request->get_param('limit') ?: 10);
        $messages_limit = $request->get_param('messages_limit') ?: 20;

        // 1. Get WP User IDs of newest registered users (excluding team)
        $newest_wp_users = $wpdb->get_results("
            SELECT ID as wp_user_id, user_registered 
            FROM {$wpdb->users} 
            WHERE user_email NOT LIKE '%@talkiemate.com'
            ORDER BY user_registered DESC
            LIMIT 200
        ", ARRAY_A);

        $wp_user_ids = array_column($newest_wp_users, 'wp_user_id');

        if (empty($wp_user_ids)) {
            return new WP_REST_Response([], 200);
        }

        // 2. Fetch Buyer Metrics (Spent, Last Order)
        $buyer_metrics = [];
        $placeholders = implode(',', array_fill(0, count($wp_user_ids), '%d'));
        
        $buyer_data = $wpdb->get_results($wpdb->prepare("
            SELECT pm.meta_value as wp_user_id, 
                   SUM(CAST(o.meta_value AS DECIMAL(10,2))) as total_spent, 
                   MAX(p.post_date) as last_order_date
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            INNER JOIN {$wpdb->postmeta} o ON p.ID = o.post_id AND o.meta_key = '_order_total'
            WHERE pm.meta_key = '_customer_user' 
            AND pm.meta_value IN ($placeholders)
            AND p.post_type = 'shop_order' 
            AND p.post_status IN ('wc-completed', 'wc-processing')
            GROUP BY pm.meta_value
        ", ...$wp_user_ids), ARRAY_A);

        foreach ($buyer_data as $b) {
            $buyer_metrics[$b['wp_user_id']] = [
                'total_spent'     => (float) $b['total_spent'],
                'last_order_date' => $b['last_order_date']
            ];
        }

        // 3. Fetch Purchase Intent (Failed attempts)
        $intent_metrics = $buyer_metrics;
        $attempts = $wpdb->get_results($wpdb->prepare("
            SELECT pm.meta_value as wp_user_id, p.post_status, p.post_date, o.meta_value as total
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            INNER JOIN {$wpdb->postmeta} o ON p.ID = o.post_id AND o.meta_key = '_order_total'
            WHERE pm.meta_key = '_customer_user' 
            AND pm.meta_value IN ($placeholders)
            AND p.post_type = 'shop_order' 
            AND p.post_status IN ('wc-failed', 'wc-pending', 'wc-cancelled')
            ORDER BY p.post_date DESC
        ", ...$wp_user_ids), ARRAY_A);

        foreach ($attempts as $at) {
            $uid = (int) $at['wp_user_id'];
            $intent_metrics[$uid]['attempts'][] = [
                'status' => str_replace('wc-', '', $at['post_status']),
                'date'   => $at['post_date'],
                'total'  => $at['total']
            ];
            $intent_metrics[$uid]['has_intent'] = true;
        }

        // 4. Map to Chat User IDs
        $chat_to_wp_map = [];
        $chat_user_ids = [];
        $order_by_field = "FIELD(external_id, " . implode(',', array_map('intval', $wp_user_ids)) . ")";
        $chat_users = $wpdb->get_results($wpdb->prepare(
            "SELECT id, external_id FROM {$wpdb->prefix}" . Growtype_Chat_Database::USERS_TABLE . " WHERE external_id IN ($placeholders) AND type = 'wp_user' ORDER BY $order_by_field",
            ...$wp_user_ids
        ), ARRAY_A);
        
        foreach ($chat_users as $cu) {
            $chat_user_ids[] = (int) $cu['id'];
            $chat_to_wp_map[$cu['id']] = $cu['external_id'];
        }

        $results = $this->get_group_conversations($chat_user_ids, $limit, $messages_limit, $intent_metrics, $chat_to_wp_map);

        return new WP_REST_Response($results, 200);
    }

    /**
     * Helper to get conversations for a group of users.
     */
    private function get_group_conversations($user_ids, $limit, $messages_limit, $metrics = [], $chat_to_wp_map = [])
    {
        global $wpdb;

        if (empty($user_ids)) {
            return [];
        }

        $results = [];
        $placeholders = implode(',', array_fill(0, count($user_ids), '%d'));

        $sessions_table = $wpdb->prefix . Growtype_Chat_Database::SESSIONS_TABLE;
        $user_session_table = $wpdb->prefix . Growtype_Chat_Database::USER_SESSION_TABLE;
        $messages_table = $wpdb->prefix . Growtype_Chat_Database::MESSAGES_TABLE;
        $session_message_table = $wpdb->prefix . Growtype_Chat_Database::SESSION_MESSAGE_TABLE;
        $users_table = $wpdb->prefix . Growtype_Chat_Database::USERS_TABLE;

        // Get user registration dates and lifetime message counts
        $user_metadata_results = $wpdb->get_results($wpdb->prepare("
            SELECT u.id, u.external_id, u.created_at as chat_created_at, wpu.user_registered, 
                   (SELECT COUNT(*) FROM $messages_table WHERE user_id = u.id) as lifetime_messages
            FROM $users_table u
            LEFT JOIN {$wpdb->users} wpu ON u.external_id = wpu.ID
            WHERE u.id IN ($placeholders)
        ", ...$user_ids), ARRAY_A);

        $user_meta = [];
        foreach ($user_metadata_results as $um) {
            $user_meta[$um['id']] = $um;
        }

        // Get recent sessions for these users
        $sessions = $wpdb->get_results($wpdb->prepare("
            SELECT s.id, us.user_id, s.created_at
            FROM $sessions_table s
            JOIN $user_session_table us ON s.id = us.session_id
            WHERE us.user_id IN ($placeholders)
            ORDER BY s.created_at DESC
            LIMIT 200
        ", ...$user_ids));

        $unique_users_seen = [];
        foreach ($sessions as $session) {
            if (count($results) >= $limit) {
                break;
            }
            if (isset($unique_users_seen[$session->user_id])) {
                continue;
            }
            
            $unique_users_seen[$session->user_id] = true;

            $user_id = (int) $session->user_id;
            $wp_user_id = $chat_to_wp_map[$user_id] ?? (int) ($user_meta[$user_id]['external_id'] ?? 0);

            // Fetch total credits purchased for this user if they are a buyer
            if ($wp_user_id > 0 && isset($metrics[$wp_user_id]) && !isset($metrics[$wp_user_id]['total_credits_purchased_fetched'])) {
                $total_credits_purchased = 0;
                if (class_exists('Growtype_Chat_Credits')) {
                   // Calculate from orders
                   $orders = $wpdb->get_results($wpdb->prepare("
                       SELECT p.ID 
                       FROM {$wpdb->posts} p
                       JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                       WHERE pm.meta_key = '_customer_user' AND pm.meta_value = %d
                       AND p.post_type = 'shop_order' AND p.post_status IN ('wc-completed', 'wc-processing')
                   ", $wp_user_id));
                   
                   foreach ($orders as $order_obj) {
                       $total_credits_purchased += Growtype_Chat_Credits::get_from_order($order_obj->ID, $wp_user_id);
                   }
                }
                $metrics[$wp_user_id]['total_credits_purchased'] = $total_credits_purchased;
                $metrics[$wp_user_id]['total_credits_purchased_fetched'] = true;
            }

            $messages = $wpdb->get_results($wpdb->prepare("
                SELECT m.*, u.type as author_type
                FROM $messages_table m
                JOIN $session_message_table sm ON m.id = sm.message_id
                JOIN $users_table u ON m.user_id = u.id
                WHERE sm.session_id = %d
                ORDER BY m.created_at ASC
                LIMIT %d
            ", $session->id, $messages_limit));

            if (empty($messages)) {
                continue;
            }

            $formatted_messages = [];
            foreach ($messages as $msg) {
                $content = $msg->content;
                if (class_exists('Growtype_Chat_Message')) {
                    $decoded = Growtype_Chat_Message::decode_content($content);
                    $content = is_array($decoded) ? ($decoded['main_text'] ?? '') : $decoded;
                }
                
                $formatted_messages[] = [
                    'id'          => (int) $msg->id,
                    'author_type' => $msg->author_type,
                    'content'     => $content,
                    'created_at'  => $msg->created_at,
                ];
            }

            // Get character slug
            $character_slug = '';
            $meta_table = $wpdb->prefix . Growtype_Chat_Database::SESSION_META_TABLE;
            $slug_meta = $wpdb->get_var($wpdb->prepare(
                "SELECT meta_value FROM $meta_table WHERE session_id = %d AND meta_key = 'slug' LIMIT 1",
                $session->id
            ));
            if (!empty($slug_meta)) {
               $character_slug = class_exists('Growtype_Chat_Message') ? Growtype_Chat_Message::decode_content($slug_meta) : $slug_meta;
            }

            $user_id = (int) $session->user_id;
            $wp_user_id = $chat_to_wp_map[$user_id] ?? (int) ($user_meta[$user_id]['external_id'] ?? 0);

            // Fetch current credits
            $current_credits = 0;
            if ($wp_user_id > 0 && function_exists('growtype_chat_user_credits')) {
                $current_credits = growtype_chat_user_credits($wp_user_id);
            }

            // Calculate credits spent
            // Total = Purchased + Registration (5)
            // Spent = Total - Current
            $purchased_credits = $metrics[$wp_user_id]['total_credits_purchased'] ?? 0;
            $registration_credits = function_exists('growtype_chat_registrations_credits_amount') ? growtype_chat_registrations_credits_amount() : 5;

            $total_lifetime_credits = $purchased_credits + $registration_credits;
            $credits_spent = max(0, $total_lifetime_credits - $current_credits);

            $results[] = [
                'session_id'             => (int) $session->id,
                'user_id'                => $user_id,
                'wp_user_id'             => $wp_user_id,
                'character_slug'         => $character_slug,
                'created_at'             => $session->created_at,
                'user_registered'        => $user_meta[$user_id]['user_registered'] ?? '',
                'lifetime_messages'      => (int) ($user_meta[$user_id]['lifetime_messages'] ?? 0),
                'current_credits'        => $current_credits,
                'credits_spent'          => $credits_spent,
                'total_credits_bought'   => $purchased_credits,
                'total_spent_currency'   => $metrics[$wp_user_id]['total_spent'] ?? 0,
                'last_order_date'        => $metrics[$wp_user_id]['last_order_date'] ?? null,
                'purchase_attempts'      => $metrics[$wp_user_id]['attempts'] ?? [],
                'has_purchase_intent'    => !empty($metrics[$wp_user_id]['has_intent']),
                'messages'               => $formatted_messages,
            ];
        }

        return $results;
    }
}
