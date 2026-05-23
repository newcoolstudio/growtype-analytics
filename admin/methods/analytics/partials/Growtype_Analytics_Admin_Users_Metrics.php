<?php

/**
 * Users Metrics Partial
 *
 * Handles user-specific metrics and data fetching
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/admin/methods/analytics/partials
 */

class Growtype_Analytics_Admin_Users_Metrics
{
    /**
     * @var Growtype_Analytics_Admin_Page
     */
    private $controller;

    public function __construct($controller)
    {
        $this->controller = $controller;
    }

    /**
     * Get paginated list of registered users with various stats
     */
    public function get_registered_users_list($days, $paged = 1, $per_page = 50, $filters = [], $orderby = '', $order = 'DESC', $user_search = '')
    {
        global $wpdb;
        $settings = $this->controller->metrics->get_snapshot_settings();
        $email_exclusion = $this->controller->metrics->build_email_exclusion_sql('u.user_email', $settings['excluded_email_patterns']);
        $period_sql = $this->controller->metrics->build_period_sql('u.user_registered', $days);

        $offset = ($paged - 1) * $per_page;
        $paid_statuses = $settings['paid_statuses'];
        $paid_placeholders = implode(',', array_fill(0, count($paid_statuses), '%s'));

        $chat_users_table = $wpdb->prefix . 'growtype_chat_users';
        $chat_messages_table = $wpdb->prefix . 'growtype_chat_messages';
        $analytics_tracking_table = $wpdb->prefix . 'growtype_analytics_tracking';
        $quiz_results_table = $wpdb->prefix . 'growtype_quiz_results';
        $mail_logs_table = $wpdb->prefix . 'growtype_mail_action_logs';

        $has_chat = $this->controller->table_exists($chat_users_table) && $this->controller->table_exists($chat_messages_table);
        $has_analytics_tracking = $this->controller->table_exists($analytics_tracking_table);
        $has_quiz = $this->controller->table_exists($quiz_results_table);
        $has_mail_logs = $this->controller->table_exists($mail_logs_table);

        // ── Step 1: Build JOINs + WHERE from active filters ───────────────────────
        $active_filters = (array)$filters;
        $join_sql = '';
        $join_params = [];   
        $where_sql = '';   

        if (in_array('paid_orders_only', $active_filters, true)) {
            $order_period_sql = $this->controller->metrics->build_period_sql('p.post_date', $days);
            $join_sql .= " INNER JOIN (
                SELECT DISTINCT pm.meta_value AS user_id
                FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                WHERE pm.meta_key = '_customer_user'
                  AND p.post_type = 'shop_order'
                  AND p.post_status IN ($paid_placeholders)
                  {$order_period_sql['sql']}
            ) paid_filter ON paid_filter.user_id = u.ID";
            $join_params = array_merge($join_params, $paid_statuses, $order_period_sql['params']);
            $active_filters = array_diff($active_filters, ['paid_orders_only']);
        }

        if (in_array('zero_credits', $active_filters, true)) {
            $join_sql .= " LEFT JOIN {$wpdb->usermeta} um_c
                              ON um_c.user_id = u.ID
                             AND um_c.meta_key = 'growtype_chat_credits'";
            $where_sql .= " AND (um_c.meta_value IS NULL OR um_c.meta_value = '0')";
            $active_filters = array_diff($active_filters, ['zero_credits']);
        }

        if (in_array('has_characters', $active_filters, true)) {
            $join_sql .= " INNER JOIN (
                SELECT DISTINCT post_author AS user_id
                FROM {$wpdb->posts}
                WHERE post_type = 'character' AND post_status = 'publish'
            ) char_filter ON char_filter.user_id = u.ID";
            $active_filters = array_diff($active_filters, ['has_characters']);
        }

        $having_sql = Growtype_Analytics_Admin_Users_Filters::build_having_sql($active_filters);
        $having_selects = ''; 
        $having_params = [];

        if (!empty($having_sql)) {
            if (strpos($having_sql, 'paid_orders') !== false) {
                $having_selects .= ", (SELECT COUNT(p2.ID)
                      FROM {$wpdb->posts} p2
                      INNER JOIN {$wpdb->postmeta} pm2 ON pm2.post_id = p2.ID
                      WHERE pm2.meta_key = '_customer_user'
                        AND pm2.meta_value = u.ID
                        AND p2.post_type = 'shop_order'
                        AND p2.post_status IN ($paid_placeholders)) AS paid_orders";
                $having_params = array_merge($having_params, $paid_statuses);
            }
            if (strpos($having_sql, 'chat_credits_amount') !== false) {
                $having_selects .= ", COALESCE((SELECT CAST(um2.meta_value AS SIGNED)
                      FROM {$wpdb->usermeta} um2
                      WHERE um2.user_id = u.ID
                        AND um2.meta_key = 'growtype_chat_credits'
                      LIMIT 1), 0) AS chat_credits_amount";
            }
            if (strpos($having_sql . ' ' . implode(' ', (array)$filters), 'total_spent') !== false) {
                $having_selects .= ", COALESCE((SELECT SUM(CAST(pm3.meta_value AS DECIMAL(10,2)))
                      FROM {$wpdb->postmeta} pm3
                      INNER JOIN {$wpdb->posts} p3 ON p3.ID = pm3.post_id
                      WHERE pm3.meta_key = '_order_total'
                        AND p3.ID IN (
                            SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_customer_user' AND meta_value = u.ID
                        )
                        AND p3.post_type = 'shop_order'
                        AND p3.post_status IN ($paid_placeholders)), 0) AS total_spent";
                $having_params = array_merge($having_params, $paid_statuses);
            }
        }

        // Column mapping for sorting
        $sort_map = [
            'id' => 'u.ID',
            'email' => 'u.user_email',
            'registered' => 'u.user_registered',
            'paid_orders' => 'paid_orders',
            'total_spent' => 'total_spent',
            'messages' => 'message_count',
            'regular_chat_visits' => 'regular_chat_visits',
            'roleplay_chat_visits' => 'roleplay_chat_visits',
            'roleplays_created' => 'roleplays_created',
            'quiz_solved' => 'quiz_solved',
            'offer_shown' => 'offer_shown',
            'checkout_visited' => 'checkout_visited',
            'credits_page_visited' => 'credits_page_visited',
            'subscription_modal_shown' => 'subscription_modal_shown',
            'character_profile_visits' => 'character_profile_visits',
            'roleplay_profile_visits' => 'roleplay_profile_visits',
            'create_character_visited' => 'create_character_visited',
            'create_roleplay_visited' => 'create_roleplay_visited',
            'chat_credits' => 'chat_credits_amount',
            'emails_sent' => 'emails_sent',
        ];

        $actual_orderby = isset($sort_map[$orderby]) ? $sort_map[$orderby] : '';
        $actual_order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        // Auto-include select for sorted column if it's a dynamic alias
        if (!empty($actual_orderby) && strpos($actual_orderby, 'u.') === false) {
            $check_str = $having_sql . ' ' . implode(' ', (array)$filters) . ' ' . $actual_orderby;
            
            if (strpos($check_str, 'paid_orders') !== false && strpos($having_selects, 'paid_orders') === false) {
                $having_selects .= ", (SELECT COUNT(p2.ID) FROM {$wpdb->posts} p2 INNER JOIN {$wpdb->postmeta} pm2 ON pm2.post_id = p2.ID WHERE pm2.meta_key = '_customer_user' AND pm2.meta_value = u.ID AND p2.post_type = 'shop_order' AND p2.post_status IN ($paid_placeholders)) AS paid_orders";
                $having_params = array_merge($having_params, $paid_statuses);
            }
            if (strpos($check_str, 'total_spent') !== false && strpos($having_selects, 'total_spent') === false) {
                $having_selects .= ", COALESCE((SELECT SUM(CAST(pm3.meta_value AS DECIMAL(10,2))) FROM {$wpdb->postmeta} pm3 INNER JOIN {$wpdb->posts} p3 ON p3.ID = pm3.post_id WHERE pm3.meta_key = '_order_total' AND p3.ID IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_customer_user' AND meta_value = u.ID) AND p3.post_type = 'shop_order' AND p3.post_status IN ($paid_placeholders)), 0) AS total_spent";
                $having_params = array_merge($having_params, $paid_statuses);
            }
            if (strpos($check_str, 'chat_credits_amount') !== false && strpos($having_selects, 'chat_credits_amount') === false) {
                $having_selects .= ", COALESCE((SELECT CAST(um2.meta_value AS SIGNED) FROM {$wpdb->usermeta} um2 WHERE um2.user_id = u.ID AND um2.meta_key = 'growtype_chat_credits' LIMIT 1), 0) AS chat_credits_amount";
            }
            if (strpos($check_str, 'message_count') !== false && strpos($having_selects, 'message_count') === false && $has_chat) {
                $having_selects .= ", (SELECT COUNT(m2.id) FROM `{$chat_messages_table}` m2 INNER JOIN `{$chat_users_table}` cu2 ON cu2.id = m2.user_id WHERE cu2.external_id = u.ID AND cu2.type = 'wp_user') AS message_count";
            }
            if (strpos($check_str, 'quiz_solved') !== false && strpos($having_selects, 'quiz_solved') === false && $has_quiz) {
                $having_selects .= ", (SELECT COUNT(id) FROM `{$quiz_results_table}` WHERE user_id = u.ID) AS quiz_solved";
            }
            if (strpos($check_str, 'roleplays_created') !== false && strpos($having_selects, 'roleplays_created') === false) {
                $having_selects .= ", (SELECT COUNT(ID) FROM `{$wpdb->posts}` WHERE post_author = u.ID AND post_type = 'roleplay' AND post_status = 'publish') AS roleplays_created";
            }
            if (strpos($check_str, 'emails_sent') !== false && strpos($having_selects, 'emails_sent') === false && $has_mail_logs) {
                $having_selects .= ", (SELECT COUNT(id) FROM `{$mail_logs_table}` WHERE recipient = u.user_email AND delivery_status = 'SENT') AS emails_sent";
            }

            // Tracking events
            $event_tracking_map = [
                'regular_chat_visits' => 'character_chat',
                'roleplay_chat_visits' => 'roleplay_chat',
                'offer_shown' => 'offer_shown',
                'checkout_visited' => 'page_plans_visit',
                'credits_page_visited' => 'page_credits_visit',
                'subscription_modal_shown' => 'subscription_modal_shown',
                'character_profile_visits' => 'character_profile',
                'roleplay_profile_visits' => 'roleplay_profile',
                'create_character_visited' => 'page_create_character_visit',
                'create_roleplay_visited' => 'page_create_roleplay_visit',
            ];
            foreach ($event_tracking_map as $key => $event_type) {
                if (strpos($check_str, $key) !== false && strpos($having_selects, $key) === false && $has_analytics_tracking) {
                    $having_selects .= ", (SELECT COUNT(*) FROM `{$analytics_tracking_table}` WHERE user_id = u.ID AND event_type = '{$event_type}') AS {$key}";
                }
            }
        }

        if (!empty($actual_orderby)) {
            $orderby_sql = "ORDER BY {$actual_orderby} {$actual_order}";
        } else {
            $orderby_sql = Growtype_Analytics_Admin_Users_Filters::build_orderby_sql((array)$filters);
        }

        // Add user_registered to subquery selects if we are sorting by it and have a having clause
        if (!empty($having_sql) && strpos($orderby_sql, 'user_registered') !== false) {
            $having_selects .= ", u.user_registered";
        }

        $user_search_sql = '';
        $user_search_params = [];
        if (!empty($user_search)) {
            $like = '%' . $wpdb->esc_like($user_search) . '%';
            $user_search_sql = " AND (u.user_email LIKE %s OR u.user_login LIKE %s OR u.display_name LIKE %s)";
            $user_search_params = [$like, $like, $like];
        }

        $from_sql = " FROM {$wpdb->users} u
                     {$join_sql}
                     WHERE 1=1
                     {$period_sql['sql']}
                     {$email_exclusion['sql']}
                     {$user_search_sql}
                     {$where_sql}";

        if (!empty($having_sql) || !empty($having_selects)) {
            $ids_query = "SELECT t.ID
                FROM (SELECT u.ID {$having_selects} {$from_sql}) AS t
                " . (!empty($having_sql) ? $having_sql : "") . "
                " . str_replace('u.', 't.', $orderby_sql) . "
                LIMIT %d, %d";
            $ids_params = array_merge(
                $having_params,
                $join_params,
                $period_sql['params'],
                $email_exclusion['params'],
                $user_search_params,
                [$offset, (int)$per_page]
            );
        } else {
            $ids_query = "SELECT u.ID {$from_sql} {$orderby_sql} LIMIT %d, %d";
            $ids_params = array_merge(
                $join_params,
                $period_sql['params'],
                $email_exclusion['params'],
                $user_search_params,
                [$offset, (int)$per_page]
            );
        }

        $user_ids = $wpdb->get_col($this->controller->metrics->prepare_dynamic_query($ids_query, $ids_params));

        $count_params = array_merge(
            !empty($having_sql) ? $having_params : [],
            $join_params,
            $period_sql['params'],
            $email_exclusion['params'],
            $user_search_params
        );

        if (empty($user_ids)) {
            $count_query = (!empty($having_sql) || !empty($having_selects))
                ? "SELECT COUNT(*) FROM (SELECT u.ID {$having_selects} {$from_sql}) AS t " . (!empty($having_sql) ? $having_sql : "")
                : "SELECT COUNT(DISTINCT u.ID) {$from_sql}";
            return [
                'items' => [],
                'total_items' => (int)$wpdb->get_var($this->controller->metrics->prepare_dynamic_query($count_query, $count_params)),
            ];
        }

        $user_ids_ph = implode(',', array_fill(0, count($user_ids), '%d'));
        $user_ids_int = array_map('intval', $user_ids);

        $users_data = $wpdb->get_results($this->controller->metrics->prepare_dynamic_query(
            "SELECT ID, user_email, display_name, user_registered
             FROM {$wpdb->users} WHERE ID IN ($user_ids_ph)",
            $user_ids_int
        ), ARRAY_A);

        $results_map = [];
        foreach ($users_data as $row) {
            $results_map[(int)$row['ID']] = array_merge($row, [
                'paid_orders' => 0,
                'chat_credits_amount' => 0,
                'message_count' => 0,
                'regular_chat_visits' => 0,
                'roleplay_chat_visits' => 0,
                'roleplay_visited' => 0,
                'quizzes_solved' => 0,
                'payment_form_shown' => 0,
                'checkout_visited' => 0,
                'credits_page_visited' => 0,
                'subscription_modal_shown' => 0,
                'character_profile_visits' => 0,
                'roleplay_profile_visits' => 0,
                'create_character_visited' => 0,
                'create_roleplay_visited' => 0,
                'emails_sent'              => 0,
                'total_spent'              => 0,
            ]);

        }

        $rows = $wpdb->get_results($this->controller->metrics->prepare_dynamic_query(
            "SELECT pm.meta_value AS user_id, COUNT(p.ID) AS cnt, SUM(CAST(pm_total.meta_value AS DECIMAL(10,2))) AS total_spent
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
             LEFT JOIN {$wpdb->postmeta} pm_total ON pm_total.post_id = p.ID AND pm_total.meta_key = '_order_total'
             WHERE pm.meta_key = '_customer_user'
               AND pm.meta_value IN ($user_ids_ph)
               AND p.post_type = 'shop_order'
               AND p.post_status IN ($paid_placeholders)
             GROUP BY pm.meta_value",
            array_merge($user_ids_int, $paid_statuses)
        ), ARRAY_A);
        foreach ($rows as $r) {
            $results_map[(int)$r['user_id']]['paid_orders'] = (int)$r['cnt'];
            $results_map[(int)$r['user_id']]['total_spent'] = (float)$r['total_spent'];
        }

        if ($has_analytics_tracking) {
            $rows = $wpdb->get_results($this->controller->metrics->prepare_dynamic_query(
                "SELECT user_id, event_type, COUNT(*) AS cnt
                 FROM `{$analytics_tracking_table}`
                 WHERE user_id IN ($user_ids_ph)
                 GROUP BY user_id, event_type",
                $user_ids_int
            ), ARRAY_A);
            $event_map = [
                'character_chat'            => 'regular_chat_visits',
                'roleplay_chat'             => 'roleplay_chat_visits',
                'offer_shown'               => 'payment_form_shown',
                'page_plans_visit'          => 'checkout_visited',
                'page_credits_visit'        => 'credits_page_visited',
                'subscription_modal_shown'  => 'subscription_modal_shown',
                'character_profile'         => 'character_profile_visits',
                'roleplay_profile'          => 'roleplay_profile_visits',
                'page_create_character_visit' => 'create_character_visited',
                'page_create_roleplay_visit'  => 'create_roleplay_visited',
            ];
            foreach ($rows as $r) {
                if (isset($event_map[$r['event_type']])) {
                    $results_map[(int)$r['user_id']][$event_map[$r['event_type']]] = (int)$r['cnt'];
                }
            }
        }

        if ($has_chat) {
            $rows = $wpdb->get_results($this->controller->metrics->prepare_dynamic_query(
                "SELECT cu.external_id AS user_id, COUNT(m.id) AS cnt
                 FROM `{$chat_messages_table}` m
                 INNER JOIN `{$chat_users_table}` cu ON cu.id = m.user_id
                 WHERE cu.external_id IN ($user_ids_ph) AND cu.type = 'wp_user'
                 GROUP BY cu.external_id",
                $user_ids_int
            ), ARRAY_A);
            foreach ($rows as $r) {
                $results_map[(int)$r['user_id']]['message_count'] = (int)$r['cnt'];
            }
        }

        $rows = $wpdb->get_results($this->controller->metrics->prepare_dynamic_query(
            "SELECT post_author AS user_id, COUNT(ID) AS cnt
             FROM `{$wpdb->posts}`
             WHERE post_author IN ($user_ids_ph) AND post_type = 'roleplay' AND post_status = 'publish'
             GROUP BY post_author",
            $user_ids_int
        ), ARRAY_A);
        foreach ($rows as $r) {
            $results_map[(int)$r['user_id']]['roleplay_visited'] = (int)$r['cnt'];
        }

        $rows = $wpdb->get_results($this->controller->metrics->prepare_dynamic_query(
            "SELECT user_id, meta_value
             FROM `{$wpdb->usermeta}`
             WHERE user_id IN ($user_ids_ph) AND meta_key = 'growtype_chat_credits'",
            $user_ids_int
        ), ARRAY_A);
        foreach ($rows as $r) {
            $results_map[(int)$r['user_id']]['chat_credits_amount'] = (int)$r['meta_value'];
        }

        if ($has_mail_logs) {
            $rows = $wpdb->get_results($this->controller->metrics->prepare_dynamic_query(
                "SELECT u.ID AS user_id, COUNT(ml.id) AS cnt
                 FROM `{$mail_logs_table}` ml
                 INNER JOIN `{$wpdb->users}` u ON ml.recipient = u.user_email
                 WHERE u.ID IN ($user_ids_ph) AND ml.delivery_status = 'SENT'
                 GROUP BY u.ID",
                $user_ids_int
            ), ARRAY_A);
            foreach ($rows as $r) {
                $results_map[(int)$r['user_id']]['emails_sent'] = (int)$r['cnt'];
            }
        }

        if ($has_quiz) {
            $rows = $wpdb->get_results($this->controller->metrics->prepare_dynamic_query(
                "SELECT user_id, COUNT(id) AS cnt
                 FROM `{$quiz_results_table}`
                 WHERE user_id IN ($user_ids_ph)
                 GROUP BY user_id",
                $user_ids_int
            ), ARRAY_A);
            foreach ($rows as $r) {
                $results_map[(int)$r['user_id']]['quizzes_solved'] = (int)$r['cnt'];
            }
        }

        foreach ($user_ids as $uid) {
            if (isset($results_map[(int)$uid])) {
                $results[] = $results_map[(int)$uid];
            }
        }

        $count_query = (!empty($having_sql) || !empty($having_selects))
            ? "SELECT COUNT(*) FROM (SELECT u.ID {$having_selects} {$from_sql}) AS t " . (!empty($having_sql) ? $having_sql : "")
            : "SELECT COUNT(DISTINCT u.ID) {$from_sql}";
        $total_items = (int)$wpdb->get_var($this->controller->metrics->prepare_dynamic_query($count_query, $count_params));

        return [
            'items' => $results,
            'total_items' => $total_items,
        ];
    }

    public function get_new_buyers_count($days, $settings)
    {
        global $wpdb;
        $paid_status_placeholders = implode(',', array_fill(0, count($settings['paid_statuses']), '%s'));
        $email_exclusion = $this->controller->metrics->build_email_exclusion_sql('u.user_email', $settings['excluded_email_patterns']);
        $period_sql = $this->controller->metrics->build_period_sql('u.user_registered', $days);
        $query = "SELECT COUNT(DISTINCT u.ID)
            FROM `{$wpdb->users}` u
            INNER JOIN `{$wpdb->postmeta}` pm ON pm.meta_key = '_customer_user' AND pm.meta_value = u.ID
            INNER JOIN `{$wpdb->posts}` p ON p.ID = pm.post_id
            WHERE 1=1 {$period_sql['sql']}
            AND p.post_type = 'shop_order'
            AND p.post_status IN ($paid_status_placeholders)
            {$email_exclusion['sql']}";
        return (int)$wpdb->get_var(
            $this->controller->metrics->prepare_dynamic_query(
                $query,
                array_merge($period_sql['params'], $settings['paid_statuses'], $email_exclusion['params'])
            )
        );
    }

    public function get_new_user_to_buyer_conversion($days, $settings)
    {
        $new_users = $this->controller->funnel->get_new_users_count($days, $settings);
        if ($new_users === 0) {
            return 0;
        }
        $buyers = $this->get_new_buyers_count($days, $settings);
        return round(($buyers / $new_users) * 100, 2);
    }

    public function get_new_attempt_users_count($days, $settings)
    {
        global $wpdb;
        $attempt_placeholders = implode(',', array_fill(0, count($settings['attempt_statuses']), '%s'));
        $email_exclusion = $this->controller->metrics->build_email_exclusion_sql('u.user_email', $settings['excluded_email_patterns']);
        $period_sql = $this->controller->metrics->build_period_sql('u.user_registered', $days);
        $query = "SELECT COUNT(DISTINCT u.ID)
            FROM `{$wpdb->users}` u
            INNER JOIN `{$wpdb->postmeta}` pm ON pm.meta_key = '_customer_user' AND pm.meta_value = u.ID
            INNER JOIN `{$wpdb->posts}` p ON p.ID = pm.post_id
            WHERE 1=1 {$period_sql['sql']}
            AND p.post_type = 'shop_order'
            AND p.post_status IN ($attempt_placeholders)
            {$email_exclusion['sql']}";
        return (int)$wpdb->get_var(
            $this->controller->metrics->prepare_dynamic_query(
                $query,
                array_merge($period_sql['params'], $settings['attempt_statuses'], $email_exclusion['params'])
            )
        );
    }

    public function get_active_user_count($days, $settings)
    {
        global $wpdb;

        $chat_users_table = $wpdb->prefix . 'growtype_chat_users';
        $chat_messages_table = $wpdb->prefix . 'growtype_chat_messages';

        if (!$this->controller->table_exists($chat_users_table) || !$this->controller->table_exists($chat_messages_table)) {
            return 0;
        }

        $email_exclusion = $this->controller->metrics->build_email_exclusion_sql('u.user_email', $settings['excluded_email_patterns']);
        $query = "SELECT COUNT(DISTINCT cu.external_id)
            FROM $chat_messages_table m
            INNER JOIN $chat_users_table cu ON cu.id = m.user_id
            INNER JOIN $wpdb->users u ON u.ID = cu.external_id
            WHERE cu.type = 'wp_user'
            AND cu.external_id > 0
            AND m.created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            {$email_exclusion['sql']}";

        return (int)$wpdb->get_var(
            $this->controller->metrics->prepare_dynamic_query($query, array_merge(array ((int)$days), $email_exclusion['params']))
        );
    }

    public function get_batched_user_data($start_date)
    {
        global $wpdb;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(user_registered) as reg_date, COUNT(ID) as count
             FROM $wpdb->users 
             WHERE user_registered >= %s
             GROUP BY reg_date",
            $start_date
        ), OBJECT_K);

        $data = array ();
        foreach ($results as $date => $row) {
            $data[$date] = (int)$row->count;
        }
        return $data;
    }

    public function get_repurchase_metrics($settings)
    {
        global $wpdb;
        $paid_placeholders = implode(',', array_fill(0, count($settings['paid_statuses']), '%s'));
        $email_exclusion = $this->controller->metrics->build_email_exclusion_sql('u.user_email', $settings['excluded_email_patterns']);

        $query = "SELECT
                COUNT(*) as buyers,
                SUM(CASE WHEN buyer_orders.order_cnt > 1 THEN 1 ELSE 0 END) as recurring_buyers,
                SUM(buyer_orders.total_revenue) as total_revenue
            FROM (
                SELECT
                    pm.meta_value as user_id,
                    COUNT(DISTINCT p.ID) as order_cnt,
                    SUM(CAST(total.meta_value AS DECIMAL(10,2))) as total_revenue
                FROM `{$wpdb->postmeta}` pm
                INNER JOIN `{$wpdb->posts}` p ON p.ID = pm.post_id
                INNER JOIN `{$wpdb->users}` u ON u.ID = pm.meta_value
                LEFT JOIN `{$wpdb->postmeta}` total ON total.post_id = p.ID AND total.meta_key = '_order_total'
                WHERE pm.meta_key = '_customer_user'
                AND pm.meta_value > 0
                AND p.post_type = 'shop_order'
                AND p.post_status IN ($paid_placeholders)
                {$email_exclusion['sql']}
                GROUP BY pm.meta_value
            ) buyer_orders";

        $row = $wpdb->get_row(
            $this->controller->metrics->prepare_dynamic_query($query, array_merge($settings['paid_statuses'], $email_exclusion['params'])),
            ARRAY_A
        );

        $buyers = (int)($row['buyers'] ?? 0);
        $recurring_buyers = (int)($row['recurring_buyers'] ?? 0);
        $total_revenue = (float)($row['total_revenue'] ?? 0);

        return array (
            'rate' => $buyers > 0 ? round(($recurring_buyers / $buyers) * 100, 2) : 0,
            'arppu' => $buyers > 0 ? round($total_revenue / $buyers, 2) : 0,
        );
    }

    public function get_activity_metrics($settings)
    {
        global $wpdb;

        $chat_users_table = $wpdb->prefix . 'growtype_chat_users';
        $chat_messages_table = $wpdb->prefix . 'growtype_chat_messages';

        if (!$this->controller->table_exists($chat_users_table) || !$this->controller->table_exists($chat_messages_table)) {
            return array ('dau' => 0, 'wau' => 0, 'mau' => 0, 'stickiness_ratio' => 0);
        }

        $email_exclusion = $this->controller->metrics->build_email_exclusion_sql('u.user_email', $settings['excluded_email_patterns']);

        $query = "SELECT 
                COUNT(DISTINCT CASE WHEN m.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN cu.external_id END) as dau,
                COUNT(DISTINCT CASE WHEN m.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN cu.external_id END) as wau,
                COUNT(DISTINCT cu.external_id) as mau
            FROM `{$chat_messages_table}` m
            INNER JOIN `{$chat_users_table}` cu ON cu.id = m.user_id
            INNER JOIN `{$wpdb->users}` u ON u.ID = cu.external_id
            WHERE cu.type = 'wp_user'
            AND cu.external_id > 0
            AND m.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            {$email_exclusion['sql']}";

        $results = $wpdb->get_row(
            $this->controller->metrics->prepare_dynamic_query($query, $email_exclusion['params']),
            ARRAY_A
        );

        $dau = (int)($results['dau'] ?? 0);
        $wau = (int)($results['wau'] ?? 0);
        $mau = (int)($results['mau'] ?? 0);

        return array (
            'dau' => $dau,
            'wau' => $wau,
            'mau' => $mau,
            'stickiness_ratio' => $mau > 0 ? round(($dau / $mau) * 100, 2) : 0,
        );
    }

    public function get_churn_risk_count($settings)
    {
        global $wpdb;

        $chat_users_table = $wpdb->prefix . 'growtype_chat_users';
        $chat_messages_table = $wpdb->prefix . 'growtype_chat_messages';

        if (!$this->controller->table_exists($chat_users_table) || !$this->controller->table_exists($chat_messages_table)) {
            return 0;
        }

        $paid_placeholders = implode(',', array_fill(0, count($settings['paid_statuses']), '%s'));
        $email_exclusion = $this->controller->metrics->build_email_exclusion_sql('u.user_email', $settings['excluded_email_patterns']);
        $churn_inactivity_days = (int)$settings['churn_inactivity_days'];
        $recent_payer_window_days = (int)$settings['recent_payer_window_days'];

        $query = "SELECT COUNT(DISTINCT u.ID)
            FROM `{$wpdb->users}` u
            INNER JOIN `{$wpdb->postmeta}` customer ON customer.meta_key = '_customer_user' AND customer.meta_value = u.ID
            INNER JOIN `{$wpdb->posts}` p ON p.ID = customer.post_id
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ($paid_placeholders)
            AND p.post_date >= DATE_SUB(NOW(), INTERVAL %d DAY)
            AND NOT EXISTS (
                SELECT 1
                FROM `{$chat_messages_table}` m
                INNER JOIN `{$chat_users_table}` cu ON cu.id = m.user_id
                WHERE cu.external_id = u.ID AND cu.type = 'wp_user'
                AND m.created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            )
            {$email_exclusion['sql']}";

        return (int)$wpdb->get_var(
            $this->controller->metrics->prepare_dynamic_query(
                $query,
                array_merge(
                    $settings['paid_statuses'],
                    array ($recent_payer_window_days, $churn_inactivity_days),
                    $email_exclusion['params']
                )
            )
        );
    }

    public function get_payer_churn_rate($settings)
    {
        global $wpdb;

        $paid_placeholders = implode(',', array_fill(0, count($settings['paid_statuses']), '%s'));
        $email_exclusion = $this->controller->metrics->build_email_exclusion_sql('u.user_email', $settings['excluded_email_patterns'], true);
        $recent_payer_window_days = max(1, (int)$settings['recent_payer_window_days']);
        $churn_inactivity_days = max(1, (int)$settings['churn_inactivity_days']);

        $recent_query = "SELECT COUNT(DISTINCT pm.meta_value)
            FROM `{$wpdb->postmeta}` pm
            INNER JOIN `{$wpdb->posts}` p ON p.ID = pm.post_id
            LEFT JOIN `{$wpdb->users}` u ON u.ID = pm.meta_value
            WHERE pm.meta_key = '_customer_user' AND pm.meta_value > 0
            AND p.post_type = 'shop_order'
            AND p.post_status IN ($paid_placeholders)
            AND p.post_date >= DATE_SUB(NOW(), INTERVAL %d DAY)
            {$email_exclusion['sql']}";

        $recent_payers = (int)$wpdb->get_var(
            $this->controller->metrics->prepare_dynamic_query(
                $recent_query,
                array_merge($settings['paid_statuses'], array ($recent_payer_window_days), $email_exclusion['params'])
            )
        );

        if ($recent_payers === 0) {
            return 0;
        }

        $inactive_query = "SELECT COUNT(DISTINCT pm.meta_value)
            FROM `{$wpdb->postmeta}` pm
            INNER JOIN `{$wpdb->posts}` p ON p.ID = pm.post_id
            LEFT JOIN `{$wpdb->users}` u ON u.ID = pm.meta_value
            WHERE pm.meta_key = '_customer_user' AND pm.meta_value > 0
            AND p.post_type = 'shop_order'
            AND p.post_status IN ($paid_placeholders)
            AND p.post_date >= DATE_SUB(NOW(), INTERVAL %d DAY)
            AND NOT EXISTS (
                SELECT 1
                FROM `{$wpdb->prefix}growtype_chat_messages` m
                INNER JOIN `{$wpdb->prefix}growtype_chat_users` cu ON cu.id = m.user_id
                WHERE cu.external_id = pm.meta_value
                AND cu.type = 'wp_user'
                AND m.created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            )
            {$email_exclusion['sql']}";

        if (
            !$this->controller->table_exists($wpdb->prefix . 'growtype_chat_messages') ||
            !$this->controller->table_exists($wpdb->prefix . 'growtype_chat_users')
        ) {
            return 0;
        }

        $inactive_recent_payers = (int)$wpdb->get_var(
            $this->controller->metrics->prepare_dynamic_query(
                $inactive_query,
                array_merge(
                    $settings['paid_statuses'],
                    array ($recent_payer_window_days, $churn_inactivity_days),
                    $email_exclusion['params']
                )
            )
        );

        return round(($inactive_recent_payers / $recent_payers) * 100, 2);
    }

    public function get_user_churn_rate($settings)
    {
        global $wpdb;

        $chat_users_table = $wpdb->prefix . 'growtype_chat_users';
        $chat_messages_table = $wpdb->prefix . 'growtype_chat_messages';

        if (!$this->controller->table_exists($chat_users_table) || !$this->controller->table_exists($chat_messages_table)) {
            return 0;
        }

        $email_exclusion = $this->controller->metrics->build_email_exclusion_sql('u.user_email', $settings['excluded_email_patterns']);
        $churn_inactivity_days = max(1, (int)$settings['churn_inactivity_days']);

        $active_30_query = "SELECT COUNT(DISTINCT cu.external_id)
            FROM `{$chat_messages_table}` m
            INNER JOIN `{$chat_users_table}` cu ON cu.id = m.user_id AND cu.type = 'wp_user'
            INNER JOIN `{$wpdb->users}` u ON u.ID = cu.external_id
            WHERE cu.external_id > 0
            AND m.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            {$email_exclusion['sql']}";

        $active_30d = (int)$wpdb->get_var(
            $this->controller->metrics->prepare_dynamic_query($active_30_query, $email_exclusion['params'])
        );

        if ($active_30d === 0) {
            return 0;
        }

        $active_recent_query = "SELECT COUNT(DISTINCT cu.external_id)
            FROM `{$chat_messages_table}` m
            INNER JOIN `{$chat_users_table}` cu ON cu.id = m.user_id AND cu.type = 'wp_user'
            INNER JOIN `{$wpdb->users}` u ON u.ID = cu.external_id
            WHERE cu.external_id > 0
            AND m.created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            {$email_exclusion['sql']}";

        $active_recent = (int)$wpdb->get_var(
            $this->controller->metrics->prepare_dynamic_query(
                $active_recent_query,
                array_merge(array ($churn_inactivity_days), $email_exclusion['params'])
            )
        );

        $inactive_recent = max(0, $active_30d - $active_recent);
        return round(($inactive_recent / $active_30d) * 100, 2);
    }

    public function get_median_days_to_first_purchase($settings)
    {
        global $wpdb;

        $paid_placeholders = implode(',', array_fill(0, count($settings['paid_statuses']), '%s'));
        $email_exclusion = $this->controller->metrics->build_email_exclusion_sql('u.user_email', $settings['excluded_email_patterns']);

        $query = "SELECT DATEDIFF(MIN(p.post_date), u.user_registered) as days_to_purchase
            FROM `{$wpdb->users}` u
            INNER JOIN `{$wpdb->postmeta}` pm ON pm.meta_key = '_customer_user' AND pm.meta_value = u.ID
            INNER JOIN `{$wpdb->posts}` p ON p.ID = pm.post_id
            WHERE pm.meta_value > 0
            AND p.post_type = 'shop_order'
            AND p.post_status IN ($paid_placeholders)
            {$email_exclusion['sql']}
            GROUP BY u.ID
            ORDER BY days_to_purchase ASC";

        $all_days = $wpdb->get_col(
            $this->controller->metrics->prepare_dynamic_query($query, array_merge($settings['paid_statuses'], $email_exclusion['params']))
        );

        if (empty($all_days)) {
            return 0;
        }

        $count = count($all_days);
        $mid = (int)floor($count / 2);

        if ($count % 2 === 0) {
            return round(((float)$all_days[$mid - 1] + (float)$all_days[$mid]) / 2, 1);
        }

        return round((float)$all_days[$mid], 1);
    }
}
