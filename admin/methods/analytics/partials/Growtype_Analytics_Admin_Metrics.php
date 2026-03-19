<?php

/**
 * Metrics utilities
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/admin/methods/analytics
 */

class Growtype_Analytics_Admin_Metrics
{
    private $controller;

    public function __construct($controller)
    {
        $this->controller = $controller;

        // Bust the snapshot cache whenever orders or users change
        add_action('woocommerce_order_status_changed', array($this, 'bust_snapshot_cache'));
        add_action('woocommerce_new_order',            array($this, 'bust_snapshot_cache'));
        add_action('user_register',                    array($this, 'bust_snapshot_cache'));
    }

    public function bust_snapshot_cache()
    {
        delete_transient('growtype_analytics_snapshot_metrics_v1');
    }

    public function get_scale_or_pivot_metrics($refresh = false)
    {
        $transient_key = 'growtype_analytics_snapshot_metrics_v1';
        
        if ($refresh) {
            delete_transient($transient_key);
        }

        $cached = get_transient($transient_key);
        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $settings = $this->get_snapshot_settings();

        $metrics = array(
            'registered_users_total' => 0,
            'new_users_7d' => 0,
            'new_users_30d' => 0,
            'activation_rate_7d' => 0,
            'activation_rate_30d' => 0,
            'buyers_total' => 0,
            'buyer_conversion_total' => 0,
            'new_user_to_buyer_conversion_30d' => 0,
            'paid_orders_30d' => 0,
            'revenue_30d' => 0,
            'aov_30d' => 0,
            'payment_success_rate_30d' => 0,
            'unpaid_attempts_30d' => 0,
            'repurchase_rate_total' => 0,
            'arppu_total' => 0,
            'new_user_to_buyer_conversion_daily' => 0,
            'dau' => 0,
            'wau' => 0,
            'mau' => 0,
            'stickiness_ratio' => 0,
            'churn_risk_recent_payers' => 0,
            // P0 growth & scale metrics
            'revenue_prev_30d' => 0,
            'revenue_growth_mom' => 0,
            'new_users_prev_7d' => 0,
            'new_users_growth_wow' => 0,
            'ltv_estimate' => 0,
            'arpu' => 0,
            // P1 metrics
            'payer_churn_rate' => 0,
            'user_churn_rate' => 0,
            'median_days_to_first_purchase' => 0,
            // P2 metrics
            'cac_estimate' => 0,
            'revenue_daily_30d' => array(),
            'settings' => $settings,
            'activation_min_messages' => (int)$settings['activation_min_messages'],
            'activation_window_days' => (int)$settings['activation_window_days'],
            'churn_inactivity_days' => (int)$settings['churn_inactivity_days'],
            'recent_payer_window_days' => (int)$settings['recent_payer_window_days'],
        );

        $email_exclusion = $this->build_email_exclusion_sql('u.user_email', $settings['excluded_email_patterns']);
        $users_query = "SELECT COUNT(u.ID) FROM $wpdb->users u WHERE 1=1 {$email_exclusion['sql']}";
        $metrics['registered_users_total'] = (int)$wpdb->get_var(
            $this->prepare_dynamic_query($users_query, $email_exclusion['params'])
        );

        $metrics['new_users_7d'] = $this->controller->funnel->get_new_users_count(7, $settings);
        $metrics['new_users_30d'] = $this->controller->funnel->get_new_users_count(30, $settings);

        $activation_7d = $this->controller->funnel->get_activation_metrics(7, $settings);
        $activation_30d = $this->controller->funnel->get_activation_metrics(30, $settings);
        $metrics['activation_rate_7d'] = $activation_7d['rate'];
        $metrics['activation_rate_30d'] = $activation_30d['rate'];

        $paid_status_placeholders = implode(',', array_fill(0, count($settings['paid_statuses']), '%s'));
        $buyer_exclusion = $this->build_email_exclusion_sql('u.user_email', $settings['excluded_email_patterns']);
        $buyers_query = "SELECT COUNT(DISTINCT pm.meta_value)
            FROM $wpdb->postmeta pm
            INNER JOIN $wpdb->posts p ON pm.post_id = p.ID
            INNER JOIN $wpdb->users u ON pm.meta_value = u.ID
            WHERE pm.meta_key = '_customer_user'
            AND pm.meta_value > 0
            AND p.post_type = 'shop_order'
            AND p.post_status IN ($paid_status_placeholders)
            {$buyer_exclusion['sql']}";
        $metrics['buyers_total'] = (int)$wpdb->get_var(
            $this->prepare_dynamic_query(
            $buyers_query,
            array_merge($settings['paid_statuses'], $buyer_exclusion['params'])
        )
        );

        $metrics['buyer_conversion_total'] = $metrics['registered_users_total'] > 0
            ? round(($metrics['buyers_total'] / $metrics['registered_users_total']) * 100, 2)
            : 0;

        $metrics['new_user_to_buyer_conversion_30d'] = $this->get_new_user_to_buyer_conversion(30, $settings);
        $metrics['new_user_to_buyer_conversion_daily'] = $this->get_new_user_to_buyer_conversion(1, $settings);

        $orders_30d = $this->get_order_metrics(30, $settings);
        $metrics['paid_orders_30d'] = $orders_30d['paid_orders'];
        $metrics['revenue_30d'] = $orders_30d['revenue'];
        $metrics['aov_30d'] = $orders_30d['aov'];
        $metrics['payment_success_rate_30d'] = $orders_30d['success_rate'];
        $metrics['unpaid_attempts_30d'] = $orders_30d['unpaid_attempts'];

        $repurchase = $this->get_repurchase_metrics($settings);
        $metrics['repurchase_rate_total'] = $repurchase['rate'];
        $metrics['arppu_total'] = $repurchase['arppu'];

        $activity = $this->get_activity_metrics($settings);
        $metrics['dau'] = $activity['dau'];
        $metrics['wau'] = $activity['wau'];
        $metrics['mau'] = $activity['mau'];
        $metrics['stickiness_ratio'] = $activity['stickiness_ratio'];

        $metrics['churn_risk_recent_payers'] = $this->get_churn_risk_count($settings);

        // --- P0 growth metrics ---

        // MoM Revenue Growth: compare current 30d vs previous 30d (days 31-60)
        $orders_prev_30d = $this->get_order_metrics_range(60, 30, $settings);
        $metrics['revenue_prev_30d'] = $orders_prev_30d['revenue'];
        $metrics['revenue_growth_mom'] = $metrics['revenue_prev_30d'] > 0
            ? round((($metrics['revenue_30d'] - $metrics['revenue_prev_30d']) / $metrics['revenue_prev_30d']) * 100, 2)
            : ($metrics['revenue_30d'] > 0 ? 100 : 0);

        // WoW New User Growth: compare current 7d vs previous 7d
        $metrics['new_users_prev_7d'] = $this->controller->funnel->get_new_users_count_range(14, 7, $settings);
        $metrics['new_users_growth_wow'] = $metrics['new_users_prev_7d'] > 0
            ? round((($metrics['new_users_7d'] - $metrics['new_users_prev_7d']) / $metrics['new_users_prev_7d']) * 100, 2)
            : ($metrics['new_users_7d'] > 0 ? 100 : 0);

        // Simple LTV estimate: ARPPU × 1 / (1 - repurchase_rate_decimal)
        $repurchase_decimal = $metrics['repurchase_rate_total'] / 100;
        $metrics['ltv_estimate'] = $repurchase_decimal < 1
            ? round($metrics['arppu_total'] * (1 / (1 - $repurchase_decimal)), 2)
            : round($metrics['arppu_total'] * 10, 2); // cap at 10× if 100% repurchase

        // ARPU: revenue across ALL registered users
        $metrics['arpu'] = $metrics['registered_users_total'] > 0
            ? round($orders_30d['revenue'] / $metrics['registered_users_total'], 2)
            : 0;

        // --- P1 metrics ---

        // Payer Churn Rate: % of payers in prev 30d who did NOT pay in current 30d
        $metrics['payer_churn_rate'] = $this->get_payer_churn_rate($settings);

        // User Churn Rate: % of active users in prev 30d who are NOT active in current 30d
        $metrics['user_churn_rate'] = $this->get_user_churn_rate($settings);

        // Median days from registration to first purchase
        $metrics['median_days_to_first_purchase'] = $this->get_median_days_to_first_purchase($settings);

        // --- P2 metrics ---

        // CAC Estimate = marketing spend 30d / new buyers 30d
        $marketing_spend = (float)$settings['marketing_spend_30d'];
        $new_buyers_30d = $this->get_new_buyers_count(30, $settings);
        $metrics['cac_estimate'] = ($marketing_spend > 0 && $new_buyers_30d > 0)
            ? round($marketing_spend / $new_buyers_30d, 2)
            : 0;

        // Daily Revenue Trend for the last 30d
        $metrics['revenue_daily_30d'] = $this->get_revenue_trend_daily(30, $settings);

        set_transient($transient_key, $metrics, GROWTYPE_ANALYTICS_CACHE_TIME);

        return $metrics;
    }

    public function get_new_buyers_count($days, $settings)
    {
        global $wpdb;
        $paid_status_placeholders = implode(',', array_fill(0, count($settings['paid_statuses']), '%s'));
        $email_exclusion = $this->build_email_exclusion_sql('u.user_email', $settings['excluded_email_patterns']);
        $query = "SELECT COUNT(DISTINCT u.ID)
            FROM `{$wpdb->users}` u
            INNER JOIN `{$wpdb->postmeta}` pm ON pm.meta_key = '_customer_user' AND pm.meta_value = u.ID
            INNER JOIN `{$wpdb->posts}` p ON p.ID = pm.post_id
            WHERE u.user_registered >= DATE_SUB(NOW(), INTERVAL %d DAY)
            AND p.post_type = 'shop_order'
            AND p.post_status IN ($paid_status_placeholders)
            {$email_exclusion['sql']}";
        return (int)$wpdb->get_var(
            $this->prepare_dynamic_query(
                $query,
                array_merge(array((int)$days), $settings['paid_statuses'], $email_exclusion['params'])
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

    public function get_order_metrics($days, $settings)
    {
        global $wpdb;
        $attempt_placeholders = implode(',', array_fill(0, count($settings['attempt_statuses']), '%s'));
        $email_exclusion = $this->build_email_exclusion_sql('u.user_email', $settings['excluded_email_patterns'], true);
        $query = "SELECT p.post_status, COUNT(DISTINCT p.ID) as order_count, SUM(CAST(total.meta_value AS DECIMAL(10,2))) as total_revenue
            FROM $wpdb->posts p
            INNER JOIN $wpdb->postmeta total ON total.post_id = p.ID AND total.meta_key = '_order_total'
            LEFT JOIN $wpdb->postmeta customer ON customer.post_id = p.ID AND customer.meta_key = '_customer_user'
            LEFT JOIN $wpdb->users u ON customer.meta_value = u.ID
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ($attempt_placeholders)
            AND p.post_date >= DATE_SUB(NOW(), INTERVAL %d DAY)
            {$email_exclusion['sql']}
            GROUP BY p.post_status";
        $results = $wpdb->get_results(
            $this->prepare_dynamic_query(
            $query,
            array_merge($settings['attempt_statuses'], array((int)$days), $email_exclusion['params'])
        ),
            ARRAY_A
        );

        $paid_orders = 0;
        $paid_revenue = 0.0;
        $attempts = 0;
        $unpaid_attempts = 0;

        foreach ($results as $row) {
            $count = (int)$row['order_count'];
            $revenue = (float)($row['total_revenue'] ?: 0);
            $status = $row['post_status'];

            $attempts += $count;

            if (in_array($status, $settings['paid_statuses'], true)) {
                $paid_orders += $count;
                $paid_revenue += $revenue;
            }
            else {
                $unpaid_attempts += $count;
            }
        }

        return array(
            'paid_orders' => $paid_orders,
            'revenue' => round($paid_revenue, 2),
            'aov' => $paid_orders > 0 ? round($paid_revenue / $paid_orders, 2) : 0,
            'success_rate' => $attempts > 0 ? round(($paid_orders / $attempts) * 100, 2) : 0,
            'unpaid_attempts' => $unpaid_attempts,
        );
    }

    /**
     * Get order metrics for a specific date range (from $from_days ago to $to_days ago).
     * Used for period-over-period comparisons (e.g. previous 30d revenue).
     */
    public function get_order_metrics_range($from_days, $to_days, $settings)
    {
        global $wpdb;
        $attempt_placeholders = implode(',', array_fill(0, count($settings['attempt_statuses']), '%s'));
        $email_exclusion = $this->build_email_exclusion_sql('u.user_email', $settings['excluded_email_patterns'], true);
        $query = "SELECT p.post_status, COUNT(DISTINCT p.ID) as order_count, SUM(CAST(total.meta_value AS DECIMAL(10,2))) as total_revenue
            FROM `{$wpdb->posts}` p
            INNER JOIN `{$wpdb->postmeta}` total ON total.post_id = p.ID AND total.meta_key = '_order_total'
            LEFT JOIN `{$wpdb->postmeta}` customer ON customer.post_id = p.ID AND customer.meta_key = '_customer_user'
            LEFT JOIN `{$wpdb->users}` u ON customer.meta_value = u.ID
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ($attempt_placeholders)
            AND p.post_date >= DATE_SUB(NOW(), INTERVAL %d DAY)
            AND p.post_date < DATE_SUB(NOW(), INTERVAL %d DAY)
            {$email_exclusion['sql']}
            GROUP BY p.post_status";
        $results = $wpdb->get_results(
            $this->prepare_dynamic_query(
                $query,
                array_merge($settings['attempt_statuses'], array((int)$from_days, (int)$to_days), $email_exclusion['params'])
            ),
            ARRAY_A
        );

        $paid_orders = 0;
        $paid_revenue = 0.0;

        foreach ($results ?: array() as $row) {
            if (in_array($row['post_status'], $settings['paid_statuses'], true)) {
                $paid_orders += (int)$row['order_count'];
                $paid_revenue += (float)($row['total_revenue'] ?: 0);
            }
        }

        return array(
            'paid_orders' => $paid_orders,
            'revenue' => round($paid_revenue, 2),
        );
    }

    /**
     * Get daily revenue for the last $days.
     */
    public function get_revenue_trend_daily($days, $settings)
    {
        global $wpdb;
        $attempt_placeholders = implode(',', array_fill(0, count($settings['paid_statuses']), '%s'));
        $email_exclusion = $this->build_email_exclusion_sql('u.user_email', $settings['excluded_email_patterns'], true);
        $query = "SELECT DATE(p.post_date) as order_date, SUM(CAST(total.meta_value AS DECIMAL(10,2))) as total_revenue
            FROM `{$wpdb->posts}` p
            INNER JOIN `{$wpdb->postmeta}` total ON total.post_id = p.ID AND total.meta_key = '_order_total'
            LEFT JOIN `{$wpdb->postmeta}` customer ON customer.post_id = p.ID AND customer.meta_key = '_customer_user'
            LEFT JOIN `{$wpdb->users}` u ON customer.meta_value = u.ID
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ($attempt_placeholders)
            AND p.post_date >= DATE_SUB(NOW(), INTERVAL %d DAY)
            {$email_exclusion['sql']}
            GROUP BY order_date
            ORDER BY order_date ASC";
        $results = $wpdb->get_results(
            $this->prepare_dynamic_query(
                $query,
                array_merge($settings['paid_statuses'], array((int)$days), $email_exclusion['params'])
            ),
            ARRAY_A
        );

        $daily_data = array();
        foreach ($results ?: array() as $row) {
            $daily_data[] = array(
                'date' => $row['order_date'],
                'amount' => round((float)$row['total_revenue'], 2)
            );
        }

        return $daily_data;
    }

    public function get_repurchase_metrics($settings)
    {
        global $wpdb;
        $paid_placeholders = implode(',', array_fill(0, count($settings['paid_statuses']), '%s'));
        $email_exclusion = $this->build_email_exclusion_sql('u.user_email', $settings['excluded_email_patterns']);

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
            $this->prepare_dynamic_query($query, array_merge($settings['paid_statuses'], $email_exclusion['params'])),
            ARRAY_A
        );

        $buyers = (int)($row['buyers'] ?? 0);
        $recurring_buyers = (int)($row['recurring_buyers'] ?? 0);
        $total_revenue = (float)($row['total_revenue'] ?? 0);

        return array(
            'rate'  => $buyers > 0 ? round(($recurring_buyers / $buyers) * 100, 2) : 0,
            'arppu' => $buyers > 0 ? round($total_revenue / $buyers, 2) : 0,
        );
    }

    public function get_activity_metrics($settings)
    {
        global $wpdb;

        $chat_users_table = $wpdb->prefix . 'growtype_chat_users';
        $chat_messages_table = $wpdb->prefix . 'growtype_chat_messages';

        if (!$this->controller->table_exists($chat_users_table) || !$this->controller->table_exists($chat_messages_table)) {
            return array('dau' => 0, 'wau' => 0, 'mau' => 0, 'stickiness_ratio' => 0);
        }

        $email_exclusion = $this->build_email_exclusion_sql('u.user_email', $settings['excluded_email_patterns']);
        
        // Single optimized query for DAU, WAU, and MAU
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
            $this->prepare_dynamic_query($query, $email_exclusion['params']),
            ARRAY_A
        );

        $dau = (int)($results['dau'] ?? 0);
        $wau = (int)($results['wau'] ?? 0);
        $mau = (int)($results['mau'] ?? 0);

        return array(
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
        $email_exclusion = $this->build_email_exclusion_sql('u.user_email', $settings['excluded_email_patterns']);
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
            $this->prepare_dynamic_query(
                $query,
                array_merge(
                    $settings['paid_statuses'],
                    array($recent_payer_window_days, $churn_inactivity_days),
                    $email_exclusion['params']
                )
            )
        );
    }

    /**
     * Payer Churn Rate: % of users who paid in the previous 30d window (days 31-60)
     * but did NOT pay in the current 30d window (days 0-30).
     */
    public function get_payer_churn_rate($settings)
    {
        global $wpdb;

        $paid_placeholders = implode(',', array_fill(0, count($settings['paid_statuses']), '%s'));
        $email_exclusion = $this->build_email_exclusion_sql('u.user_email', $settings['excluded_email_patterns'], true);
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
            $this->prepare_dynamic_query(
                $recent_query,
                array_merge($settings['paid_statuses'], array($recent_payer_window_days), $email_exclusion['params'])
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
            $this->prepare_dynamic_query(
                $inactive_query,
                array_merge(
                    $settings['paid_statuses'],
                    array($recent_payer_window_days, $churn_inactivity_days),
                    $email_exclusion['params']
                )
            )
        );

        return round(($inactive_recent_payers / $recent_payers) * 100, 2);
    }

    /**
     * User Churn Rate: % of active users in previous 30d (days 31-60)
     * who are NOT active in the current 30d (days 0-30).
     */
    public function get_user_churn_rate($settings)
    {
        global $wpdb;

        $chat_users_table = $wpdb->prefix . 'growtype_chat_users';
        $chat_messages_table = $wpdb->prefix . 'growtype_chat_messages';

        if (!$this->controller->table_exists($chat_users_table) || !$this->controller->table_exists($chat_messages_table)) {
            return 0;
        }

        $email_exclusion = $this->build_email_exclusion_sql('u.user_email', $settings['excluded_email_patterns']);
        $churn_inactivity_days = max(1, (int)$settings['churn_inactivity_days']);

        $active_30_query = "SELECT COUNT(DISTINCT cu.external_id)
            FROM `{$chat_messages_table}` m
            INNER JOIN `{$chat_users_table}` cu ON cu.id = m.user_id AND cu.type = 'wp_user'
            INNER JOIN `{$wpdb->users}` u ON u.ID = cu.external_id
            WHERE cu.external_id > 0
            AND m.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            {$email_exclusion['sql']}";

        $active_30d = (int)$wpdb->get_var(
            $this->prepare_dynamic_query($active_30_query, $email_exclusion['params'])
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
            $this->prepare_dynamic_query(
                $active_recent_query,
                array_merge(array($churn_inactivity_days), $email_exclusion['params'])
            )
        );

        $inactive_recent = max(0, $active_30d - $active_recent);
        return round(($inactive_recent / $active_30d) * 100, 2);
    }

    /**
     * Median number of days from user registration to their first paid order.
     * Calculated over all buyers (not time-windowed).
     */
    public function get_median_days_to_first_purchase($settings)
    {
        global $wpdb;

        $paid_placeholders = implode(',', array_fill(0, count($settings['paid_statuses']), '%s'));
        $email_exclusion = $this->build_email_exclusion_sql('u.user_email', $settings['excluded_email_patterns']);

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
            $this->prepare_dynamic_query($query, array_merge($settings['paid_statuses'], $email_exclusion['params']))
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

    public function get_snapshot_settings()
    {
        $excluded_patterns = $this->parse_csv_or_lines(get_option('growtype_analytics_snapshot_excluded_email_patterns', '%@talkiemate.com'));
        $paid_statuses = $this->sanitize_wc_statuses($this->parse_csv_or_lines(get_option('growtype_analytics_snapshot_paid_statuses', 'wc-completed,wc-processing')));
        $attempt_statuses = $this->sanitize_wc_statuses($this->parse_csv_or_lines(get_option('growtype_analytics_snapshot_attempt_statuses', 'wc-completed,wc-processing,wc-pending,wc-failed,wc-cancelled')));
        $marketing_spend_30d = (float)get_option('growtype_analytics_snapshot_marketing_spend_30d', 0);
        $marketing_spend_by_source = $this->parse_key_value_map(get_option('growtype_analytics_snapshot_marketing_spend_by_source', ''));

        if (empty($paid_statuses)) {
            $paid_statuses = array('wc-completed', 'wc-processing');
        }

        if (empty($attempt_statuses)) {
            $attempt_statuses = array('wc-completed', 'wc-processing', 'wc-pending', 'wc-failed', 'wc-cancelled');
        }

        return array(
            'excluded_email_patterns' => $excluded_patterns,
            'paid_statuses' => $paid_statuses,
            'attempt_statuses' => $attempt_statuses,
            'activation_min_messages' => max(1, (int)get_option('growtype_analytics_snapshot_activation_min_messages', 3)),
            'activation_window_days' => max(1, (int)get_option('growtype_analytics_snapshot_activation_window_days', 1)),
            'churn_inactivity_days' => max(1, (int)get_option('growtype_analytics_snapshot_churn_inactivity_days', 14)),
            'recent_payer_window_days' => max(1, (int)get_option('growtype_analytics_snapshot_recent_payer_window_days', 90)),
            'marketing_spend_30d' => $marketing_spend_30d,
            'marketing_spend_by_source' => $marketing_spend_by_source,
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

        $email_exclusion = $this->build_email_exclusion_sql('u.user_email', $settings['excluded_email_patterns']);
        $query = "SELECT COUNT(DISTINCT cu.external_id)
            FROM $chat_messages_table m
            INNER JOIN $chat_users_table cu ON cu.id = m.user_id
            INNER JOIN $wpdb->users u ON u.ID = cu.external_id
            WHERE cu.type = 'wp_user'
            AND cu.external_id > 0
            AND m.created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            {$email_exclusion['sql']}";

        return (int)$wpdb->get_var(
            $this->prepare_dynamic_query($query, array_merge(array((int)$days), $email_exclusion['params']))
        );
    }

    private function parse_csv_or_lines($value)
    {
        if (!is_string($value) || $value === '') {
            return array();
        }

        $parts = preg_split('/[\r\n,;]+/', $value);
        $parts = array_map('trim', $parts);
        $parts = array_filter($parts, function ($item) {
            return $item !== '';
        });

        return array_values(array_unique($parts));
    }

    private function parse_key_value_map($value)
    {
        if (!is_string($value) || trim($value) === '') {
            return array();
        }

        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            $result = array();
            foreach ($decoded as $key => $amount) {
                $normalized_key = $this->normalize_dimension_key($key);
                if ($normalized_key === '') {
                    continue;
                }

                $result[$normalized_key] = (float)$amount;
            }

            return $result;
        }

        $result = array();
        $lines = preg_split('/[\r\n]+/', $value);

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (strpos($line, '=') !== false) {
                list($key, $amount) = array_map('trim', explode('=', $line, 2));
            } elseif (strpos($line, ':') !== false) {
                list($key, $amount) = array_map('trim', explode(':', $line, 2));
            } else {
                continue;
            }

            $normalized_key = $this->normalize_dimension_key($key);
            if ($normalized_key === '') {
                continue;
            }

            $result[$normalized_key] = (float)str_replace(',', '.', preg_replace('/[^0-9,\.-]/', '', $amount));
        }

        return $result;
    }

    private function normalize_dimension_key($value)
    {
        $value = is_string($value) ? strtolower(trim($value)) : '';
        return $value !== '' ? $value : 'unknown';
    }

    private function sanitize_wc_statuses($statuses)
    {
        $sanitized = array();

        foreach ($statuses as $status) {
            $normalized = sanitize_key($status);
            if (strpos($normalized, 'wc-') !== 0) {
                $normalized = 'wc-' . $normalized;
            }
            $sanitized[] = $normalized;
        }

        return array_unique($sanitized);
    }

    public function build_email_exclusion_sql($column, $patterns, $allow_null = false)
    {
        global $wpdb;

        if (empty($patterns)) {
            return array('sql' => '', 'params' => array());
        }

        $sql_parts = array();
        $params = array();

        foreach ($patterns as $pattern) {
            $sql_parts[] = "$column NOT LIKE %s";
            $params[] = str_replace('*', '%', $pattern);
        }

        $sql = ' AND (' . implode(' AND ', $sql_parts);
        if ($allow_null) {
            $sql .= " OR $column IS NULL";
        }
        $sql .= ')';

        return array(
            'sql' => $sql,
            'params' => $params
        );
    }

    public function prepare_dynamic_query($query, $params = array())
    {
        global $wpdb;

        if (empty($params)) {
            return $query;
        }

        return $wpdb->prepare($query, $params);
    }

    public function get_payment_failure_segments($settings, $days = 30, $limit = 25)
    {
        global $wpdb;

        $failure_statuses = array_values(array_filter($settings['attempt_statuses'], function ($status) use ($settings) {
            return !in_array($status, $settings['paid_statuses'], true);
        }));

        if (empty($failure_statuses)) {
            return array();
        }

        $status_placeholders = implode(',', array_fill(0, count($failure_statuses), '%s'));
        $email_exclusion = $this->build_email_exclusion_sql('u.user_email', $settings['excluded_email_patterns'], true);

        // Standard WooCommerce meta keys
        // _payment_method_title (Gateway)
        // _billing_country (Country)
        $query = "SELECT
                pm_gateway.meta_value as gateway,
                pm_country.meta_value as country,
                COUNT(p.ID) as count,
                SUM(CAST(total.meta_value AS DECIMAL(10,2))) as lost_revenue
            FROM $wpdb->posts p
            INNER JOIN $wpdb->postmeta total ON total.post_id = p.ID AND total.meta_key = '_order_total'
            LEFT JOIN $wpdb->postmeta pm_gateway ON p.ID = pm_gateway.post_id AND pm_gateway.meta_key = '_payment_method_title'
            LEFT JOIN $wpdb->postmeta pm_country ON p.ID = pm_country.post_id AND pm_country.meta_key = '_billing_country'
            LEFT JOIN $wpdb->postmeta customer ON customer.post_id = p.ID AND customer.meta_key = '_customer_user'
            LEFT JOIN $wpdb->users u ON customer.meta_value = u.ID
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ($status_placeholders)
            AND p.post_date >= DATE_SUB(NOW(), INTERVAL %d DAY)
            {$email_exclusion['sql']}
            GROUP BY gateway, country
            ORDER BY count DESC
            LIMIT %d";

        $params = array_merge($failure_statuses, array((int)$days), $email_exclusion['params'], array((int)$limit));
        $results = $wpdb->get_results($this->prepare_dynamic_query($query, $params), ARRAY_A);

        return array_map(function ($row) {
            return array(
                'gateway' => $row['gateway'] ?: __('Unknown', 'growtype-analytics'),
                'device' => __('Unknown', 'growtype-analytics'), // Device info is not standard in WC order meta
                'country' => $row['country'] ?: __('Unknown', 'growtype-analytics'),
                'product_pack' => __('N/A', 'growtype-analytics'),
                'attempts' => (int)$row['count'],
                'lost_revenue' => (float)$row['lost_revenue']
            );
        }, $results ?: array());
    }

    public function get_traffic_funnel_data($days = 30)
    {
        $settings = $this->get_snapshot_settings();
        $funnel = $this->controller->funnel->get_funnel_dropoff_metrics($days, $settings);
        $pageviews = $this->get_posthog_pageview_data($days);
        $traffic_count = array_sum($pageviews['values']);
        $registered = $this->controller->funnel->get_new_users_count($days, $settings);
        $activated = (int)($funnel['activated'] ?? 0);
        $attempts = $this->controller->funnel->get_new_user_attempt_count($days, $settings);
        $paid = $this->controller->funnel->get_new_user_paid_count($days, $settings);

        $stages = array();

        if ($traffic_count > 0) {
            $stages[] = array(
                'label' => __('Pageviews', 'growtype-analytics'),
                'count' => $traffic_count,
            );
        }

        $stages = array_merge($stages, array(
            array('label' => __('Registrations', 'growtype-analytics'), 'count' => $registered),
            array('label' => __('Activated', 'growtype-analytics'), 'count' => $activated),
            array('label' => __('Checkout Attempt', 'growtype-analytics'), 'count' => $attempts),
            array('label' => __('Paid', 'growtype-analytics'), 'count' => $paid),
        ));

        $first = max(1, (int)$stages[0]['count']);
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

        return array(
            'period_days' => (int)$days,
            'traffic_source' => $traffic_count > 0 ? 'posthog_pageviews' : 'registrations_only',
            'traffic_available' => $traffic_count > 0,
            'pageviews' => $traffic_count,
            'registrations' => $registered,
            'activated' => $activated,
            'checkout_attempts' => $attempts,
            'paid_users' => $paid,
            'rows' => $rows,
        );
    }

    public function get_cac_by_source_data($days = 30, $limit = 10)
    {
        $settings = $this->get_snapshot_settings();
        $profiles = $this->get_paid_buyer_profiles($settings);
        $cutoff = strtotime('-' . (int)$days . ' days');
        $source_costs = $settings['marketing_spend_by_source'];
        $sources = array();

        foreach ($profiles as $profile) {
            $source = $profile['acquisition_source'];

            if (!isset($sources[$source])) {
                $sources[$source] = array(
                    'source' => $profile['acquisition_source_label'],
                    'new_buyers' => 0,
                    'buyers_active_30d' => 0,
                    'revenue_30d' => 0.0,
                );
            }

            if ($profile['first_paid_at'] >= $cutoff) {
                $sources[$source]['new_buyers']++;
            }

            if ($profile['last_paid_at'] >= $cutoff) {
                $sources[$source]['buyers_active_30d']++;
            }

            $sources[$source]['revenue_30d'] += $profile['revenue_30d'];
        }

        foreach ($sources as $source_key => &$source) {
            $cost = (float)($source_costs[$source_key] ?? 0);
            $new_buyers = max(0, (int)$source['new_buyers']);
            $revenue = (float)$source['revenue_30d'];

            $source['cost_30d'] = $cost;
            $source['cac'] = $cost > 0 && $new_buyers > 0 ? $cost / $new_buyers : 0;
            $source['roas'] = $cost > 0 ? $revenue / $cost : 0;
        }
        unset($source);

        uasort($sources, function ($left, $right) {
            if ($left['revenue_30d'] === $right['revenue_30d']) {
                return $right['new_buyers'] <=> $left['new_buyers'];
            }

            return $right['revenue_30d'] <=> $left['revenue_30d'];
        });

        $sources = array_slice($sources, 0, $limit, true);

        return array_map(function ($source) {
            return array(
                'source' => $source['source'],
                'new_buyers_30d' => $this->controller->format_number($source['new_buyers']),
                'active_buyers_30d' => $this->controller->format_number($source['buyers_active_30d']),
                'revenue_30d' => $this->controller->format_money($source['revenue_30d']),
                'cost_30d' => $this->controller->format_money($source['cost_30d']),
                'cac' => $this->controller->format_money($source['cac']),
                'roas' => $source['cost_30d'] > 0 ? round($source['roas'], 2) . 'x' : __('N/A', 'growtype-analytics'),
            );
        }, $sources);
    }

    public function get_retention_by_source_data($limit = 10)
    {
        $settings = $this->get_snapshot_settings();
        $profiles = $this->get_paid_buyer_profiles($settings);
        $cutoff_30d = strtotime('-30 days');
        $sources = array();

        foreach ($profiles as $profile) {
            $source = $profile['acquisition_source'];

            if (!isset($sources[$source])) {
                $sources[$source] = array(
                    'source' => $profile['acquisition_source_label'],
                    'buyers' => 0,
                    'repeat_30d' => 0,
                    'active_30d' => 0,
                    'revenue' => 0.0,
                );
            }

            $sources[$source]['buyers']++;
            $sources[$source]['revenue'] += $profile['revenue_total'];

            if ($profile['repeat_30d']) {
                $sources[$source]['repeat_30d']++;
            }

            if ($profile['last_paid_at'] >= $cutoff_30d) {
                $sources[$source]['active_30d']++;
            }
        }

        uasort($sources, function ($left, $right) {
            return $right['buyers'] <=> $left['buyers'];
        });

        $sources = array_slice($sources, 0, $limit, true);

        return array_map(function ($source) {
            $buyers = max(1, (int)$source['buyers']);

            return array(
                'source' => $source['source'],
                'buyers' => $this->controller->format_number($source['buyers']),
                'repeat_count_30d' => $this->controller->format_number($source['repeat_30d']),
                'repeat_rate_30d' => $this->controller->format_percent(($source['repeat_30d'] / $buyers) * 100),
                'active_30d' => $this->controller->format_number($source['active_30d']),
                'active_rate_30d' => $this->controller->format_percent(($source['active_30d'] / $buyers) * 100),
                'arppu' => $this->controller->format_money($source['revenue'] / $buyers),
            );
        }, $sources);
    }

    public function get_offer_repurchase_quality_data($limit = 10)
    {
        global $wpdb;

        $settings = $this->get_snapshot_settings();
        $paid = $settings['paid_statuses'];
        $paid_placeholders = implode(',', array_fill(0, count($paid), '%s'));
        $email_exclusion = $this->build_email_exclusion_sql('u.user_email', $settings['excluded_email_patterns']);

        $query = "SELECT
                customer.meta_value as user_id,
                p.ID as order_id,
                p.post_date as paid_at,
                oi.order_item_id,
                oi.order_item_name as offer_name,
                CAST(COALESCE(line_total.meta_value, 0) AS DECIMAL(10,2)) as line_total
            FROM `{$wpdb->posts}` p
            INNER JOIN `{$wpdb->postmeta}` customer ON customer.post_id = p.ID AND customer.meta_key = '_customer_user'
            INNER JOIN `{$wpdb->users}` u ON u.ID = customer.meta_value
            INNER JOIN `{$wpdb->prefix}woocommerce_order_items` oi ON oi.order_id = p.ID AND oi.order_item_type = 'line_item'
            LEFT JOIN `{$wpdb->prefix}woocommerce_order_itemmeta` line_total ON line_total.order_item_id = oi.order_item_id AND line_total.meta_key = '_line_total'
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ($paid_placeholders)
            {$email_exclusion['sql']}
            ORDER BY customer.meta_value ASC, p.post_date ASC, oi.order_item_id ASC";

        $rows = $wpdb->get_results(
            $this->prepare_dynamic_query($query, array_merge($paid, $email_exclusion['params'])),
            ARRAY_A
        );

        $buyers = array();

        foreach ($rows ?: array() as $row) {
            $user_id = (int)$row['user_id'];
            $order_id = (int)$row['order_id'];
            $paid_at = strtotime($row['paid_at']);
            $revenue = (float)$row['line_total'];

            if (!isset($buyers[$user_id])) {
                $buyers[$user_id] = array(
                    'offer' => $row['offer_name'] ?: 'unknown',
                    'first_order_id' => $order_id,
                    'first_paid_at' => $paid_at,
                    'repeat_30d' => false,
                    'repeat_ever' => false,
                    'revenue' => 0.0,
                    'seen_orders' => array(),
                );
            }

            if (!isset($buyers[$user_id]['seen_orders'][$order_id])) {
                $buyers[$user_id]['seen_orders'][$order_id] = true;

                if ($order_id !== $buyers[$user_id]['first_order_id']) {
                    $buyers[$user_id]['repeat_ever'] = true;

                    if ($paid_at <= strtotime('+30 days', $buyers[$user_id]['first_paid_at'])) {
                        $buyers[$user_id]['repeat_30d'] = true;
                    }
                }
            }

            $buyers[$user_id]['revenue'] += $revenue;
        }

        $offers = array();

        foreach ($buyers as $buyer) {
            $offer = $buyer['offer'];

            if (!isset($offers[$offer])) {
                $offers[$offer] = array(
                    'buyers' => 0,
                    'repeat_30d' => 0,
                    'repeat_ever' => 0,
                    'revenue' => 0.0,
                );
            }

            $offers[$offer]['buyers']++;
            $offers[$offer]['revenue'] += $buyer['revenue'];

            if ($buyer['repeat_30d']) {
                $offers[$offer]['repeat_30d']++;
            }

            if ($buyer['repeat_ever']) {
                $offers[$offer]['repeat_ever']++;
            }
        }

        uasort($offers, function ($left, $right) {
            return $right['buyers'] <=> $left['buyers'];
        });

        $offers = array_slice($offers, 0, $limit, true);

        $formatted = array();
        foreach ($offers as $offer_name => $offer) {
            $buyers_count = max(1, (int)$offer['buyers']);
            $formatted[] = array(
                'offer_name' => $offer_name,
                'buyers' => $this->controller->format_number($offer['buyers']),
                'repeat_30d' => $this->controller->format_number($offer['repeat_30d']),
                'repeat_rate_30d' => $this->controller->format_percent(($offer['repeat_30d'] / $buyers_count) * 100),
                'repeat_ever' => $this->controller->format_number($offer['repeat_ever']),
                'repeat_rate_ever' => $this->controller->format_percent(($offer['repeat_ever'] / $buyers_count) * 100),
                'arppu' => $this->controller->format_money($offer['revenue'] / $buyers_count),
            );
        }

        return $formatted;
    }

    private function get_paid_buyer_profiles($settings)
    {
        $orders = $this->get_paid_orders_with_source($settings);
        $buyers = array();

        foreach ($orders as $order) {
            $user_id = (int)$order['user_id'];
            $paid_at = strtotime($order['paid_at']);
            $revenue = (float)$order['revenue'];
            $source_label = $order['source_label'];
            $source_key = $this->normalize_dimension_key($source_label);

            if (!isset($buyers[$user_id])) {
                $buyers[$user_id] = array(
                    'acquisition_source' => $source_key,
                    'acquisition_source_label' => $source_label,
                    'first_paid_at' => $paid_at,
                    'last_paid_at' => $paid_at,
                    'repeat_30d' => false,
                    'revenue_total' => 0.0,
                    'revenue_30d' => 0.0,
                );
            } else {
                $buyers[$user_id]['last_paid_at'] = max($buyers[$user_id]['last_paid_at'], $paid_at);

                if (!$buyers[$user_id]['repeat_30d'] && $paid_at <= strtotime('+30 days', $buyers[$user_id]['first_paid_at'])) {
                    $buyers[$user_id]['repeat_30d'] = true;
                }
            }

            $buyers[$user_id]['revenue_total'] += $revenue;
            if ($paid_at >= strtotime('-30 days')) {
                $buyers[$user_id]['revenue_30d'] += $revenue;
            }
        }

        return $buyers;
    }

    private function get_paid_orders_with_source($settings)
    {
        global $wpdb;

        $paid = $settings['paid_statuses'];
        $paid_placeholders = implode(',', array_fill(0, count($paid), '%s'));
        $email_exclusion = $this->build_email_exclusion_sql('u.user_email', $settings['excluded_email_patterns']);

        $query = "SELECT
                customer.meta_value as user_id,
                p.ID as order_id,
                p.post_date as paid_at,
                CAST(total.meta_value AS DECIMAL(10,2)) as revenue,
                COALESCE(NULLIF(source.meta_value, ''), NULLIF(source_type.meta_value, ''), 'unknown') as source_label
            FROM `{$wpdb->posts}` p
            INNER JOIN `{$wpdb->postmeta}` customer ON customer.post_id = p.ID AND customer.meta_key = '_customer_user'
            INNER JOIN `{$wpdb->postmeta}` total ON total.post_id = p.ID AND total.meta_key = '_order_total'
            INNER JOIN `{$wpdb->users}` u ON u.ID = customer.meta_value
            LEFT JOIN `{$wpdb->postmeta}` source_type ON source_type.post_id = p.ID AND source_type.meta_key = '_wc_order_attribution_source_type'
            LEFT JOIN `{$wpdb->postmeta}` source ON source.post_id = p.ID AND source.meta_key = '_wc_order_attribution_utm_source'
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ($paid_placeholders)
            {$email_exclusion['sql']}
            ORDER BY customer.meta_value ASC, p.post_date ASC, p.ID ASC";

        return $wpdb->get_results(
            $this->prepare_dynamic_query($query, array_merge($paid, $email_exclusion['params'])),
            ARRAY_A
        ) ?: array();
    }

    private function get_posthog_pageview_data($days)
    {
        $api_key = get_option('growtype_analytics_posthog_details_api_key');
        $project_id = get_option('growtype_analytics_posthog_details_project_id');
        $host = get_option('growtype_analytics_posthog_details_host', 'https://eu.i.posthog.com');

        if (empty($api_key) || empty($project_id)) {
            return array('labels' => array(), 'values' => array());
        }

        $transient_key = 'growtype_analytics_posthog_pageviews_' . $days;
        $cached = get_transient($transient_key);
        if ($cached !== false) {
            return $cached;
        }

        $host = rtrim($host, '/');
        $date_from = '-' . ($days - 1) . 'd';

        $url = add_query_arg(array(
            'insight'   => 'TRENDS',
            'interval'  => 'day',
            'date_from' => $date_from,
            'events'    => wp_json_encode(array(
                array(
                    'id'   => '$pageview',
                    'math' => 'total',
                )
            ))
        ), $host . '/api/projects/' . $project_id . '/insights/trend/');

        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'timeout' => 15,
        ));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return array('labels' => array(), 'values' => array());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['result']) || !isset($body['result'][0]['data'])) {
            return array('labels' => array(), 'values' => array());
        }

        $result = array(
            'labels' => $body['result'][0]['labels'],
            'values' => array_map('intval', $body['result'][0]['data']),
        );

        set_transient($transient_key, $result, HOUR_IN_SECONDS);

        return $result;
    }

    public function get_source_payback_data($days = 30, $limit = 10)
    {
        $settings = $this->get_snapshot_settings();
        $profiles = $this->get_paid_buyer_profiles($settings);
        $cutoff = strtotime('-' . (int)$days . ' days');
        $source_costs = $settings['marketing_spend_by_source'];
        $sources = array();

        foreach ($profiles as $profile) {
            $source = $profile['acquisition_source'];

            if (!isset($sources[$source])) {
                $sources[$source] = array(
                    'source' => $profile['acquisition_source_label'],
                    'new_buyers' => 0,
                    'revenue_30d' => 0.0,
                    'revenue_total' => 0.0,
                );
            }

            if ($profile['first_paid_at'] >= $cutoff) {
                $sources[$source]['new_buyers']++;
            }

            $sources[$source]['revenue_30d'] += $profile['revenue_30d'];
            $sources[$source]['revenue_total'] += $profile['revenue_total'];
        }

        foreach ($sources as $source_key => &$source) {
            $cost = (float)($source_costs[$source_key] ?? 0);
            $new_buyers = max(0, (int)$source['new_buyers']);
            $gross_revenue_per_new_buyer = $new_buyers > 0 ? ($source['revenue_30d'] / $new_buyers) : 0;

            $source['cost_30d'] = $cost;
            $source['payback_months_estimate'] = ($cost > 0 && $gross_revenue_per_new_buyer > 0)
                ? round(($cost / max(1, $new_buyers)) / $gross_revenue_per_new_buyer, 2)
                : 0;
        }
        unset($source);

        uasort($sources, function ($left, $right) {
            return $right['revenue_30d'] <=> $left['revenue_30d'];
        });

        $sources = array_slice($sources, 0, $limit, true);

        return array_map(function ($source) {
            $new_buyers = max(1, (int)$source['new_buyers']);
            $gross_revenue_per_new_buyer = $source['revenue_30d'] / $new_buyers;

            return array(
                'source' => $source['source'],
                'new_buyers' => $this->controller->format_number($source['new_buyers']),
                'revenue_30d' => $this->controller->format_money($source['revenue_30d']),
                'revenue_total' => $this->controller->format_money($source['revenue_total']),
                'revenue_per_new_buyer' => $this->controller->format_money($gross_revenue_per_new_buyer),
                'payback_estimate' => $source['cost_30d'] > 0 ? $source['payback_months_estimate'] . ' mo' : __('N/A', 'growtype-analytics'),
            );
        }, $sources);
    }

    public function get_language_conversion_data($days = 30, $limit = 10)
    {
        global $wpdb;

        $settings = $this->get_snapshot_settings();
        $paid = $settings['paid_statuses'];
        $paid_placeholders = implode(',', array_fill(0, count($paid), '%s'));
        $email_exclusion = $this->build_email_exclusion_sql('u.user_email', $settings['excluded_email_patterns']);

        $query = "SELECT
                COALESCE(NULLIF(locale.meta_value, ''), 'unknown') as locale_code,
                COUNT(DISTINCT u.ID) as registered_users,
                COUNT(DISTINCT CASE WHEN p.ID IS NOT NULL THEN u.ID END) as buyers
            FROM `{$wpdb->users}` u
            LEFT JOIN `{$wpdb->usermeta}` locale ON locale.user_id = u.ID AND locale.meta_key = 'locale'
            LEFT JOIN `{$wpdb->postmeta}` customer ON customer.meta_key = '_customer_user' AND customer.meta_value = u.ID
            LEFT JOIN `{$wpdb->posts}` p ON p.ID = customer.post_id
                AND p.post_type = 'shop_order'
                AND p.post_status IN ($paid_placeholders)
                AND p.post_date >= DATE_SUB(NOW(), INTERVAL %d DAY)
            WHERE u.user_registered >= DATE_SUB(NOW(), INTERVAL %d DAY)
            {$email_exclusion['sql']}
            GROUP BY locale_code
            ORDER BY buyers DESC, registered_users DESC
            LIMIT %d";

        $results = $wpdb->get_results(
            $this->prepare_dynamic_query(
                $query,
                array_merge($paid, array((int)$days, (int)$days), $email_exclusion['params'], array((int)$limit))
            ),
            ARRAY_A
        );

        return array_map(function ($row) {
            $registered = (int)$row['registered_users'];
            $buyers = (int)$row['buyers'];

            return array(
                'locale' => $row['locale_code'],
                'registered' => $this->controller->format_number($registered),
                'buyers' => $this->controller->format_number($buyers),
                'conversion_rate' => $this->controller->format_percent($registered > 0 ? ($buyers / $registered) * 100 : 0),
            );
        }, $results ?: array());
    }

    public function get_refund_chargeback_rates_data($days = 30)
    {
        $settings = $this->get_snapshot_settings();
        $orders = $this->get_order_metrics($days, $settings);
        $paid_orders = max(0, (int)$orders['paid_orders']);

        $margin_page = $this->controller->analytics_page->get_page_by_class('Growtype_Analytics_Admin_Page_Contribution_Margin');
        $real_cost = $margin_page ? $margin_page->get_real_cost_refund_chargeback_data($days) : array('metrics' => array());
        $cost_metrics = $real_cost['metrics'] ?? array();

        $refund_orders = (int)($cost_metrics['refund_orders'] ?? 0);
        $chargeback_count = (int)($cost_metrics['known_chargeback_count'] ?? 0);
        $revenue = (float)($cost_metrics['revenue'] ?? 0);
        $refund_amount = (float)($cost_metrics['refund_amount'] ?? 0);
        $chargeback_amount = (float)($cost_metrics['known_chargeback_amount'] ?? 0);

        return array(
            'refund_order_rate' => $paid_orders > 0 ? round(($refund_orders / $paid_orders) * 100, 2) : 0,
            'chargeback_order_rate' => $paid_orders > 0 ? round(($chargeback_count / $paid_orders) * 100, 2) : 0,
            'refund_revenue_rate' => $revenue > 0 ? round(($refund_amount / $revenue) * 100, 2) : 0,
            'chargeback_revenue_rate' => $revenue > 0 ? round(($chargeback_amount / $revenue) * 100, 2) : 0,
        );
    }

    public function get_growth_trends_data($days = 30)
    {
        $settings = $this->get_snapshot_settings();
        $registrations = $this->get_registration_trend_daily($days, $settings);
        $paid_orders = $this->get_paid_orders_trend_daily($days, $settings);
        $revenue = $this->get_revenue_trend_daily($days, $settings);
        $buyer_conversion = $this->get_registration_cohort_conversion_daily($days, $settings, 7);

        $rows_by_date = array();

        foreach ($registrations as $row) {
            $rows_by_date[$row['date']]['date'] = $row['date'];
            $rows_by_date[$row['date']]['registrations'] = $row['count'];
        }

        foreach ($paid_orders as $row) {
            $rows_by_date[$row['date']]['date'] = $row['date'];
            $rows_by_date[$row['date']]['paid_orders'] = $row['count'];
        }

        foreach ($revenue as $row) {
            $rows_by_date[$row['date']]['date'] = $row['date'];
            $rows_by_date[$row['date']]['revenue'] = $row['amount'];
        }

        foreach ($buyer_conversion as $row) {
            $rows_by_date[$row['date']]['date'] = $row['date'];
            $rows_by_date[$row['date']]['buyers_within_window'] = $row['buyers_within_window'];
            $rows_by_date[$row['date']]['conversion_rate'] = $row['conversion_rate'];
        }

        ksort($rows_by_date);

        $formatted = array();
        foreach ($rows_by_date as $date => $row) {
            $formatted[] = array(
                'date' => $date,
                'registrations' => (int)($row['registrations'] ?? 0),
                'paid_orders' => (int)($row['paid_orders'] ?? 0),
                'revenue' => round((float)($row['revenue'] ?? 0), 2),
                'buyers_within_window' => (int)($row['buyers_within_window'] ?? 0),
                'conversion_rate' => round((float)($row['conversion_rate'] ?? 0), 2),
                'conversion_window_days' => 7,
            );
        }

        return $formatted;
    }

    public function get_top_characters_by_revenue_data($days = 30, $limit = 10)
    {
        global $wpdb;

        if (!class_exists('Growtype_Chat_Database')) {
            return array();
        }

        $messages_table = $wpdb->prefix . Growtype_Chat_Database::MESSAGES_TABLE;
        $session_message_table = $wpdb->prefix . Growtype_Chat_Database::SESSION_MESSAGE_TABLE;
        $user_session_table = $wpdb->prefix . Growtype_Chat_Database::USER_SESSION_TABLE;
        $chat_users_table = $wpdb->prefix . Growtype_Chat_Database::USERS_TABLE;
        $characters_table = $wpdb->prefix . 'characters';

        foreach (array($messages_table, $session_message_table, $user_session_table, $chat_users_table, $characters_table) as $table_name) {
            if (!$this->controller->table_exists($table_name)) {
                return array();
            }
        }

        $settings = $this->get_snapshot_settings();
        $paid = $settings['paid_statuses'];
        $paid_placeholders = implode(',', array_fill(0, count($paid), '%s'));
        $email_exclusion = $this->build_email_exclusion_sql('u.user_email', $settings['excluded_email_patterns']);

        $orders_query = "SELECT
                customer.meta_value as user_id,
                p.post_date as paid_at,
                CAST(total.meta_value AS DECIMAL(10,2)) as revenue
            FROM `{$wpdb->posts}` p
            INNER JOIN `{$wpdb->postmeta}` customer ON customer.post_id = p.ID AND customer.meta_key = '_customer_user'
            INNER JOIN `{$wpdb->postmeta}` total ON total.post_id = p.ID AND total.meta_key = '_order_total'
            INNER JOIN `{$wpdb->users}` u ON u.ID = customer.meta_value
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ($paid_placeholders)
            AND p.post_date >= DATE_SUB(NOW(), INTERVAL %d DAY)
            {$email_exclusion['sql']}
            ORDER BY p.post_date DESC";

        $orders = $wpdb->get_results(
            $this->prepare_dynamic_query($orders_query, array_merge($paid, array((int)$days), $email_exclusion['params'])),
            ARRAY_A
        );

        if (empty($orders)) {
            return array();
        }

        $character_rows = array();
        $chat_user_cache = array();

        foreach ($orders as $order) {
            $user_id = (int)$order['user_id'];
            $paid_at = $order['paid_at'];
            $revenue = (float)$order['revenue'];

            if (!isset($chat_user_cache[$user_id])) {
                $chat_user_cache[$user_id] = (int)$wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM `{$chat_users_table}` WHERE external_id = %d AND type = 'wp_user' LIMIT 1",
                    $user_id
                ));
            }

            $chat_user_id = $chat_user_cache[$user_id];
            if ($chat_user_id <= 0) {
                continue;
            }

            $character_id = $wpdb->get_var($wpdb->prepare(
                "SELECT bot.external_id
                FROM `{$messages_table}` m
                INNER JOIN `{$session_message_table}` sm ON sm.message_id = m.id
                INNER JOIN `{$user_session_table}` us ON us.session_id = sm.session_id
                INNER JOIN `{$chat_users_table}` bot ON bot.id = us.user_id
                WHERE m.user_id = %d
                AND m.created_at <= %s
                AND bot.type = 'bot'
                ORDER BY m.created_at DESC
                LIMIT 1",
                $chat_user_id,
                $paid_at
            ));

            if (empty($character_id)) {
                continue;
            }

            if (!isset($character_rows[$character_id])) {
                $character_rows[$character_id] = array(
                    'revenue' => 0.0,
                    'orders' => 0,
                    'buyers' => array(),
                    'name' => '',
                    'slug' => '',
                );
            }

            $character_rows[$character_id]['revenue'] += $revenue;
            $character_rows[$character_id]['orders']++;
            $character_rows[$character_id]['buyers'][$user_id] = true;
        }

        if (empty($character_rows)) {
            return array();
        }

        $character_ids = array_keys($character_rows);
        $placeholders = implode(',', array_fill(0, count($character_ids), '%s'));
        $character_meta_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT external_id, slug, metadata FROM `{$characters_table}` WHERE external_id IN ($placeholders)",
                ...$character_ids
            ),
            ARRAY_A
        );

        foreach ($character_meta_rows as $meta_row) {
            $character_id = $meta_row['external_id'];
            if (!isset($character_rows[$character_id])) {
                continue;
            }

            $character_rows[$character_id]['slug'] = $meta_row['slug'];
            $metadata = json_decode($meta_row['metadata'], true);
            $character_rows[$character_id]['name'] = $metadata['details']['character_title'] ?? $meta_row['slug'];
        }

        uasort($character_rows, function ($left, $right) {
            return $right['revenue'] <=> $left['revenue'];
        });

        $character_rows = array_slice($character_rows, 0, $limit, true);

        $formatted = array();
        foreach ($character_rows as $character) {
            $buyers = count($character['buyers']);
            $formatted[] = array(
                'character_name' => $character['name'] ?: $character['slug'] ?: __('Unknown', 'growtype-analytics'),
                'slug' => $character['slug'] ?: 'unknown',
                'revenue' => $this->controller->format_money($character['revenue']),
                'orders' => $this->controller->format_number($character['orders']),
                'buyers' => $this->controller->format_number($buyers),
                'revenue_per_buyer' => $this->controller->format_money($buyers > 0 ? ($character['revenue'] / $buyers) : 0),
            );
        }

        return $formatted;
    }

    private function get_registration_trend_daily($days, $settings)
    {
        global $wpdb;

        $email_exclusion = $this->build_email_exclusion_sql('u.user_email', $settings['excluded_email_patterns']);
        $query = "SELECT DATE(u.user_registered) as registered_date, COUNT(u.ID) as registrations
            FROM `{$wpdb->users}` u
            WHERE u.user_registered >= DATE_SUB(NOW(), INTERVAL %d DAY)
            {$email_exclusion['sql']}
            GROUP BY registered_date
            ORDER BY registered_date ASC";

        $results = $wpdb->get_results(
            $this->prepare_dynamic_query($query, array_merge(array((int)$days), $email_exclusion['params'])),
            ARRAY_A
        );

        $daily = array();
        foreach ($results ?: array() as $row) {
            $daily[] = array(
                'date' => $row['registered_date'],
                'count' => (int)$row['registrations'],
            );
        }

        return $daily;
    }

    private function get_paid_orders_trend_daily($days, $settings)
    {
        global $wpdb;

        $paid_placeholders = implode(',', array_fill(0, count($settings['paid_statuses']), '%s'));
        $email_exclusion = $this->build_email_exclusion_sql('u.user_email', $settings['excluded_email_patterns'], true);
        $query = "SELECT DATE(p.post_date) as order_date, COUNT(DISTINCT p.ID) as paid_orders
            FROM `{$wpdb->posts}` p
            LEFT JOIN `{$wpdb->postmeta}` customer ON customer.post_id = p.ID AND customer.meta_key = '_customer_user'
            LEFT JOIN `{$wpdb->users}` u ON customer.meta_value = u.ID
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ($paid_placeholders)
            AND p.post_date >= DATE_SUB(NOW(), INTERVAL %d DAY)
            {$email_exclusion['sql']}
            GROUP BY order_date
            ORDER BY order_date ASC";

        $results = $wpdb->get_results(
            $this->prepare_dynamic_query($query, array_merge($settings['paid_statuses'], array((int)$days), $email_exclusion['params'])),
            ARRAY_A
        );

        $daily = array();
        foreach ($results ?: array() as $row) {
            $daily[] = array(
                'date' => $row['order_date'],
                'count' => (int)$row['paid_orders'],
            );
        }

        return $daily;
    }

    private function get_registration_cohort_conversion_daily($days, $settings, $window_days = 7)
    {
        global $wpdb;

        $paid_placeholders = implode(',', array_fill(0, count($settings['paid_statuses']), '%s'));
        $email_exclusion = $this->build_email_exclusion_sql('u.user_email', $settings['excluded_email_patterns']);

        $query = "SELECT
                DATE(u.user_registered) as cohort_date,
                COUNT(DISTINCT u.ID) as registrations,
                COUNT(DISTINCT CASE
                    WHEN EXISTS (
                        SELECT 1
                        FROM `{$wpdb->postmeta}` pm
                        INNER JOIN `{$wpdb->posts}` p ON p.ID = pm.post_id
                        WHERE pm.meta_key = '_customer_user'
                        AND pm.meta_value = u.ID
                        AND p.post_type = 'shop_order'
                        AND p.post_status IN ($paid_placeholders)
                        AND p.post_date >= u.user_registered
                        AND p.post_date < DATE_ADD(u.user_registered, INTERVAL %d DAY)
                    ) THEN u.ID
                END) as buyers_within_window
            FROM `{$wpdb->users}` u
            WHERE u.user_registered >= DATE_SUB(NOW(), INTERVAL %d DAY)
            {$email_exclusion['sql']}
            GROUP BY cohort_date
            ORDER BY cohort_date ASC";

        $params = array_merge(
            $settings['paid_statuses'],
            array((int)$window_days, (int)$days),
            $email_exclusion['params']
        );

        $results = $wpdb->get_results($this->prepare_dynamic_query($query, $params), ARRAY_A);

        $daily = array();
        foreach ($results ?: array() as $row) {
            $registrations = (int)$row['registrations'];
            $buyers_within_window = (int)$row['buyers_within_window'];
            $daily[] = array(
                'date' => $row['cohort_date'],
                'buyers_within_window' => $buyers_within_window,
                'conversion_rate' => $registrations > 0 ? round(($buyers_within_window / $registrations) * 100, 2) : 0,
            );
        }

        return $daily;
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

        $data = array();
        foreach ($results as $date => $row) {
            $data[$date] = (int)$row->count;
        }
        return $data;
    }

    public function get_posthog_unique_users_data($days)
    {
        $api_key = get_option('growtype_analytics_posthog_details_api_key');
        $project_id = get_option('growtype_analytics_posthog_details_project_id');
        $host = get_option('growtype_analytics_posthog_details_host', 'https://eu.i.posthog.com');

        if (empty($api_key) || empty($project_id)) {
            return array('labels' => array(), 'values' => array());
        }

        $transient_key = 'growtype_analytics_posthog_dau_' . $days;
        $cached = get_transient($transient_key);
        if ($cached !== false) {
            return $cached;
        }

        $host = rtrim($host, '/');
        $date_from = '-' . ($days - 1) . 'd';

        $url = add_query_arg(array(
            'insight'   => 'TRENDS',
            'interval'  => 'day',
            'date_from' => $date_from,
            'events'    => json_encode(array(
                array(
                    'id'   => '$pageview',
                    'math' => 'dau'
                )
            ))
        ), $host . '/api/projects/' . $project_id . '/insights/trend/');

        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'timeout' => 15
        ));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return array('labels' => array(), 'values' => array());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['result']) || !isset($body['result'][0]['data'])) {
            return array('labels' => array(), 'values' => array());
        }

        $result_data = $body['result'][0]['data'];
        $result_labels = $body['result'][0]['labels'];

        $labels = array();
        $values = array();

        foreach ($result_labels as $index => $label) {
            $labels[] = date('M d', strtotime($label));
            $values[] = (int) ($result_data[$index] ?? 0);
        }

        $result = array(
            'labels' => $labels,
            'values' => $values
        );

        set_transient($transient_key, $result, HOUR_IN_SECONDS);

        return $result;
    }
}
