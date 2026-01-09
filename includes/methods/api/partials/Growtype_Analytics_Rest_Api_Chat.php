<?php

/**
 * Growtype Analytics REST API Chat Partial
 *
 * Handles REST API routes for chat-related data.
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
}
