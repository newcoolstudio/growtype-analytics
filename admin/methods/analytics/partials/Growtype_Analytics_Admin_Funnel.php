<?php

/**
 * Funnel Analytics
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/admin/methods/analytics
 */

class Growtype_Analytics_Admin_Funnel
{
    private $controller;

    public function __construct($controller)
    {
        $this->controller = $controller;
    }

    public function get_funnel_dropoff_data()
    {
        $settings = $this->controller->get_snapshot_settings();

        return array(
            '7d' => $this->get_funnel_dropoff_metrics(7, $settings),
            '30d' => $this->get_funnel_dropoff_metrics(30, $settings),
        );
    }

    public function get_funnel_dropoff_metrics($days, $settings)
    {
        global $wpdb;

        $email_exclusion = $this->controller->build_email_exclusion_sql('u.user_email', $settings['excluded_email_patterns']);
        $attempt_statuses = $settings['attempt_statuses'];
        $paid_statuses = $settings['paid_statuses'];
        $activation_min_messages = max(1, (int)$settings['activation_min_messages']);
        $activation_window_days = max(1, (int)$settings['activation_window_days']);

        $chat_users_table = $wpdb->prefix . 'growtype_chat_users';
        $chat_messages_table = $wpdb->prefix . 'growtype_chat_messages';
        $has_chat = $this->controller->table_exists($chat_users_table) && $this->controller->table_exists($chat_messages_table);

        $attempt_placeholders = implode(',', array_fill(0, count($attempt_statuses), '%s'));
        $paid_placeholders = implode(',', array_fill(0, count($paid_statuses), '%s'));

        // Main optimized query to get all funnel stages in one scan of users
        $query = "SELECT 
                COUNT(DISTINCT u.ID) as registered,
                COUNT(DISTINCT CASE WHEN p_attempt.ID IS NOT NULL THEN u.ID END) as attempts,
                COUNT(DISTINCT CASE WHEN p_paid.ID IS NOT NULL THEN u.ID END) as paid";
        
        if ($has_chat) {
            $query .= ", COUNT(DISTINCT CASE WHEN active_users.user_id IS NOT NULL THEN u.ID END) as activated";
        } else {
            $query .= ", 0 as activated";
        }

        $query .= " FROM `{$wpdb->users}` u
                LEFT JOIN `{$wpdb->postmeta}` customer ON customer.meta_key = '_customer_user' AND customer.meta_value = u.ID
                LEFT JOIN `{$wpdb->posts}` p_attempt ON p_attempt.ID = customer.post_id AND p_attempt.post_type = 'shop_order' AND p_attempt.post_status IN ($attempt_placeholders)
                LEFT JOIN `{$wpdb->posts}` p_paid ON p_paid.ID = customer.post_id AND p_paid.post_type = 'shop_order' AND p_paid.post_status IN ($paid_placeholders)";

        if ($has_chat) {
            $query .= " LEFT JOIN `{$chat_users_table}` cu ON cu.external_id = u.ID AND cu.type = 'wp_user'
                        LEFT JOIN (
                            SELECT m.user_id
                            FROM `{$chat_messages_table}` m
                            INNER JOIN `{$chat_users_table}` cu2 ON cu2.id = m.user_id AND cu2.type = 'wp_user'
                            INNER JOIN `{$wpdb->users}` u2 ON u2.ID = cu2.external_id
                            WHERE u2.user_registered >= DATE_SUB(NOW(), INTERVAL %d DAY)
                            AND m.created_at >= u2.user_registered
                            AND m.created_at < DATE_ADD(u2.user_registered, INTERVAL %d DAY)
                            GROUP BY m.user_id
                            HAVING COUNT(*) >= %d
                        ) active_users ON active_users.user_id = cu.id";
        }

        $query .= " WHERE u.user_registered >= DATE_SUB(NOW(), INTERVAL %d DAY)
                {$email_exclusion['sql']}";

        $params = array_merge($attempt_statuses, $paid_statuses);
        if ($has_chat) {
            $params = array_merge($params, array((int)$days, (int)$activation_window_days, (int)$activation_min_messages));
        }
        $params = array_merge($params, array((int)$days), $email_exclusion['params']);

        $metrics = $wpdb->get_row($this->controller->prepare_dynamic_query($query, $params), ARRAY_A);

        $registered = (int)($metrics['registered'] ?? 0);
        $activated = (int)($metrics['activated'] ?? 0);
        $attempts = (int)($metrics['attempts'] ?? 0);
        $paid = (int)($metrics['paid'] ?? 0);

        $stages = array(
            array('label' => 'Registered', 'count' => $registered),
            array('label' => 'Activated', 'count' => $activated),
            array('label' => 'Checkout Attempt', 'count' => $attempts),
            array('label' => 'Paid', 'count' => $paid),
        );

        $first = max(1, $registered);
        $previous = null;
        $rows = array();

        foreach ($stages as $stage) {
            $count = (int)$stage['count'];
            $vs_previous = $previous === null ? 100 : ($previous > 0 ? ($count / $previous) * 100 : 0);
            $vs_first = ($count / $first) * 100;

            $rows[] = array(
                'label' => $stage['label'],
                'count' => $this->controller->format_number($count),
                'vs_previous' => $this->controller->format_percent($vs_previous),
                'vs_first' => $this->controller->format_percent($vs_first),
            );

            $previous = $count;
        }

        return array('rows' => $rows, 'activated' => $activated, 'registered' => $registered);
    }

    public function get_new_user_attempt_count($days, $settings)
    {
        global $wpdb;

        $attempt_placeholders = implode(',', array_fill(0, count($settings['attempt_statuses']), '%s'));
        $email_exclusion = $this->controller->build_email_exclusion_sql('u.user_email', $settings['excluded_email_patterns']);
        $query = "SELECT COUNT(DISTINCT u.ID)
            FROM $wpdb->users u
            INNER JOIN $wpdb->postmeta customer ON customer.meta_key = '_customer_user' AND customer.meta_value = u.ID
            INNER JOIN $wpdb->posts p ON p.ID = customer.post_id
            WHERE u.user_registered >= DATE_SUB(NOW(), INTERVAL %d DAY)
            AND p.post_type = 'shop_order'
            AND p.post_status IN ($attempt_placeholders)
            {$email_exclusion['sql']}";

        return (int)$wpdb->get_var(
            $this->controller->prepare_dynamic_query(
                $query,
                array_merge(array((int)$days), $settings['attempt_statuses'], $email_exclusion['params'])
            )
        );
    }

    public function get_new_user_paid_count($days, $settings)
    {
        global $wpdb;

        $paid_placeholders = implode(',', array_fill(0, count($settings['paid_statuses']), '%s'));
        $email_exclusion = $this->controller->build_email_exclusion_sql('u.user_email', $settings['excluded_email_patterns']);
        $query = "SELECT COUNT(DISTINCT u.ID)
            FROM $wpdb->users u
            INNER JOIN $wpdb->postmeta customer ON customer.meta_key = '_customer_user' AND customer.meta_value = u.ID
            INNER JOIN $wpdb->posts p ON p.ID = customer.post_id
            WHERE u.user_registered >= DATE_SUB(NOW(), INTERVAL %d DAY)
            AND p.post_type = 'shop_order'
            AND p.post_status IN ($paid_placeholders)
            {$email_exclusion['sql']}";

        return (int)$wpdb->get_var(
            $this->controller->prepare_dynamic_query(
                $query,
                array_merge(array((int)$days), $settings['paid_statuses'], $email_exclusion['params'])
            )
        );
    }

    public function get_new_users_count($days, $settings)
    {
        global $wpdb;

        $email_exclusion = $this->controller->build_email_exclusion_sql('u.user_email', $settings['excluded_email_patterns']);
        $query = "SELECT COUNT(u.ID)
            FROM $wpdb->users u
            WHERE u.user_registered >= DATE_SUB(NOW(), INTERVAL %d DAY)
            {$email_exclusion['sql']}";

        return (int)$wpdb->get_var(
            $this->controller->prepare_dynamic_query($query, array_merge(array((int)$days), $email_exclusion['params']))
        );
    }

    /**
     * Count new users registered between $from_days ago and $to_days ago.
     * Used for period-over-period comparisons (e.g. previous 7d user count).
     */
    public function get_new_users_count_range($from_days, $to_days, $settings)
    {
        global $wpdb;

        $email_exclusion = $this->controller->build_email_exclusion_sql('u.user_email', $settings['excluded_email_patterns']);
        $query = "SELECT COUNT(u.ID)
            FROM `{$wpdb->users}` u
            WHERE u.user_registered >= DATE_SUB(NOW(), INTERVAL %d DAY)
            AND u.user_registered < DATE_SUB(NOW(), INTERVAL %d DAY)
            {$email_exclusion['sql']}";

        return (int)$wpdb->get_var(
            $this->controller->prepare_dynamic_query(
                $query,
                array_merge(array((int)$from_days, (int)$to_days), $email_exclusion['params'])
            )
        );
    }

    public function get_activation_metrics($days, $settings)
    {
        global $wpdb;

        $registered = $this->get_new_users_count($days, $settings);

        if ($registered === 0) {
            return array('registered' => 0, 'activated' => 0, 'rate' => 0);
        }

        $chat_users_table = $wpdb->prefix . 'growtype_chat_users';
        $chat_messages_table = $wpdb->prefix . 'growtype_chat_messages';

        if (!$this->controller->table_exists($chat_users_table) || !$this->controller->table_exists($chat_messages_table)) {
            return array('registered' => $registered, 'activated' => 0, 'rate' => 0);
        }

        $email_exclusion = $this->controller->build_email_exclusion_sql('u.user_email', $settings['excluded_email_patterns']);
        $activation_min_messages = max(1, (int)$settings['activation_min_messages']);
        $activation_window_days = max(1, (int)$settings['activation_window_days']);

        $query = "SELECT COUNT(DISTINCT u.ID)
            FROM $wpdb->users u
            INNER JOIN $chat_users_table cu ON cu.external_id = u.ID AND cu.type = 'wp_user'
            INNER JOIN (
                SELECT m.user_id
                FROM $chat_messages_table m
                INNER JOIN $chat_users_table cu2 ON cu2.id = m.user_id AND cu2.type = 'wp_user'
                INNER JOIN $wpdb->users u2 ON u2.ID = cu2.external_id
                WHERE u2.user_registered >= DATE_SUB(NOW(), INTERVAL %d DAY)
                AND m.created_at >= u2.user_registered
                AND m.created_at < DATE_ADD(u2.user_registered, INTERVAL %d DAY)
                GROUP BY m.user_id
                HAVING COUNT(*) >= %d
            ) active_users ON active_users.user_id = cu.id
            WHERE u.user_registered >= DATE_SUB(NOW(), INTERVAL %d DAY)
            {$email_exclusion['sql']}";

        $activated = (int)$wpdb->get_var(
            $this->controller->prepare_dynamic_query(
                $query,
                array_merge(
                    array((int)$days, (int)$activation_window_days, (int)$activation_min_messages, (int)$days),
                    $email_exclusion['params']
                )
            )
        );

        return array(
            'registered' => $registered,
            'activated' => $activated,
            'rate' => $registered > 0 ? round(($activated / $registered) * 100, 2) : 0,
        );
    }
}
