<?php

class Growtype_Analytics_Admin_Page_Source_Attribution extends Growtype_Analytics_Admin_Base_Page
{
    public function get_source_attribution_rows($days = 30, $limit = 50, $offset = 0, $orderby = 'revenue', $order = 'DESC')
    {
        global $wpdb;

        $settings = $this->controller->get_snapshot_settings();
        $paid = $settings['paid_statuses'];
        $attempts = $settings['attempt_statuses'];
        $paid_placeholders = implode(',', array_fill(0, count($paid), '%s'));
        $attempt_placeholders = implode(',', array_fill(0, count($attempts), '%s'));
        $email_exclusion = $this->controller->build_email_exclusion_sql('u.user_email', $settings['excluded_email_patterns'], true);

        // Fetch all affiliate mappings (UTM sources and garef codes) to associate them with names and IDs
        $affiliate_data_raw = $wpdb->get_results("
            SELECT u.ID, u.display_name, u.user_login, m.meta_key, m.meta_value 
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->usermeta} m ON u.ID = m.user_id
            WHERE m.meta_key IN ('growtype_affiliate_attribution_utm_source', 'growtype_affiliate_code')
            AND m.meta_value != ''
        ", ARRAY_A);

        $affiliate_utm_map = [];
        $affiliate_code_map = [];
        $all_affiliate_utm_sources = [];

        foreach ($affiliate_data_raw as $row) {
            $name = !empty($row['display_name']) ? $row['display_name'] : $row['user_login'];
            $user_data = [
                'name' => $name,
                'id' => $row['ID']
            ];
            
            if ($row['meta_key'] === 'growtype_affiliate_code') {
                $affiliate_code_map[$row['meta_value']] = $user_data;
            } else {
                $split = explode(',', $row['meta_value']);
                foreach ($split as $s) {
                    $s = trim($s);
                    if (!empty($s)) {
                        $affiliate_utm_map[strtolower($s)] = $user_data;
                        $all_affiliate_utm_sources[] = $s;
                    }
                }
            }
        }
        
        $all_affiliate_utm_sources = array_unique($all_affiliate_utm_sources);
        $affiliate_utm_placeholders = !empty($all_affiliate_utm_sources) ? implode(',', array_fill(0, count($all_affiliate_utm_sources), '%s')) : "'__none__'";

        // Sanitize orderby
        $allowed_orderby = array(
            'revenue' => 'revenue',
            'paid_orders' => 'paid_orders',
            'attempts' => 'attempts',
            'registrations' => 'registrations',
            'engaged_users' => 'engaged_users',
            'paywall_users' => 'paywall_users',
            'score' => 'score',
            'success_rate' => '(paid_orders / NULLIF(attempts, 0))'
        );
        
        $orderby_sql = $allowed_orderby[$orderby] ?? 'score';
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        $chat_users_table = $wpdb->prefix . 'growtype_chat_users';
        $chat_messages_table = $wpdb->prefix . 'growtype_chat_messages';
        $has_chat_tables = $this->controller->table_exists($chat_users_table) && $this->controller->table_exists($chat_messages_table);
        
        $activated_join = $has_chat_tables ? "
            LEFT JOIN $chat_users_table cu ON cu.external_id = u.ID
            LEFT JOIN (
                SELECT user_id
                FROM $chat_messages_table
                GROUP BY user_id
                HAVING COUNT(*) >= 3
            ) as activated ON activated.user_id = cu.id" : "";
        
        $engaged_user_id = $has_chat_tables ? "IF(activated.user_id IS NOT NULL, u.ID, NULL)" : "NULL";

        $tracking_table = $wpdb->prefix . 'growtype_analytics_tracking';
        $has_tracking_table = $this->controller->table_exists($tracking_table);
        $paywall_join = $has_tracking_table ? "
            LEFT JOIN (
                SELECT DISTINCT user_id
                FROM $tracking_table
                WHERE event_type IN ('subscription_modal_shown', 'offer_shown', 'page_credits_visit', 'page_plans_visit')
            ) as paywall ON paywall.user_id = u.ID" : "";
        $paywall_user_id = $has_tracking_table ? "IF(paywall.user_id IS NOT NULL, u.ID, NULL)" : "NULL";

        $query = "SELECT 
                source_type, source, campaign, detected_garef,
                COUNT(DISTINCT order_id) as paid_orders,
                COUNT(DISTINCT attempt_id) as attempts,
                SUM(order_revenue) as revenue,
                COUNT(DISTINCT lead_id) as registrations,
                COUNT(DISTINCT engaged_user_id) as engaged_users,
                COUNT(DISTINCT paywall_user_id) as paywall_users,
                (SUM(order_revenue) + (COUNT(DISTINCT order_id) * 50) + (COUNT(DISTINCT engaged_user_id) * 20)) as score,
                COUNT(*) OVER() as total_items_count
            FROM (
                SELECT
                    COALESCE(source_type.meta_value, 'unknown') as source_type,
                    COALESCE(source.meta_value, 'unknown') as source,
                    COALESCE(campaign.meta_value, 'unknown') as campaign,
                    CASE 
                        WHEN garef.meta_value IS NOT NULL THEN garef.meta_value
                        WHEN source.meta_value LIKE '%%garef=%%' THEN SUBSTRING_INDEX(SUBSTRING_INDEX(source.meta_value, 'garef=', -1), '&', 1)
                        WHEN referrer.meta_value LIKE '%%garef=%%' THEN SUBSTRING_INDEX(SUBSTRING_INDEX(referrer.meta_value, 'garef=', -1), '&', 1)
                        ELSE NULL
                    END as detected_garef,
                    IF(p.post_status IN ($paid_placeholders), p.ID, NULL) as order_id,
                    p.ID as attempt_id,
                    IF(p.post_status IN ($paid_placeholders), CAST(total.meta_value AS DECIMAL(10,2)), 0) as order_revenue,
                    NULL as lead_id,
                    $engaged_user_id as engaged_user_id,
                    $paywall_user_id as paywall_user_id
                FROM `{$wpdb->posts}` p
                INNER JOIN `{$wpdb->postmeta}` total ON total.post_id = p.ID AND total.meta_key = '_order_total'
                LEFT JOIN `{$wpdb->postmeta}` customer ON customer.post_id = p.ID AND customer.meta_key = '_customer_user'
                LEFT JOIN `{$wpdb->users}` u ON customer.meta_value = u.ID
                $activated_join
                $paywall_join
                LEFT JOIN `{$wpdb->postmeta}` source_type ON source_type.post_id = p.ID AND source_type.meta_key = '_wc_order_attribution_source_type'
                LEFT JOIN `{$wpdb->postmeta}` source ON source.post_id = p.ID AND source.meta_key = '_wc_order_attribution_utm_source'
                LEFT JOIN `{$wpdb->postmeta}` campaign ON campaign.post_id = p.ID AND campaign.meta_key = '_wc_order_attribution_utm_campaign'
                LEFT JOIN `{$wpdb->postmeta}` garef ON garef.post_id = p.ID AND garef.meta_key = '_wc_order_attribution_garef'
                LEFT JOIN `{$wpdb->postmeta}` referrer ON referrer.post_id = p.ID AND referrer.meta_key = '_wc_order_attribution_referrer'
                WHERE p.post_type = 'shop_order'
                AND p.post_status IN ($attempt_placeholders)
                AND p.post_date >= DATE_SUB(NOW(), INTERVAL %d DAY)
                {$email_exclusion['sql']}

                UNION ALL

                SELECT 
                    IF(
                        JSON_SEARCH(pm.meta_value, 'one', 'utm_source') IS NOT NULL OR
                        JSON_SEARCH(pm.meta_value, 'one', 'garef') IS NOT NULL OR
                        JSON_SEARCH(pm.meta_value, 'one', 'ref') IS NOT NULL,
                        'utm',
                        'unknown'
                    ) as source_type,
                    COALESCE(
                        JSON_UNQUOTE(JSON_EXTRACT(pm.meta_value, REPLACE(JSON_UNQUOTE(JSON_SEARCH(pm.meta_value, 'one', 'utm_source')), '.key', '.value'))),
                        JSON_UNQUOTE(JSON_EXTRACT(pm.meta_value, REPLACE(JSON_UNQUOTE(JSON_SEARCH(pm.meta_value, 'one', 'garef')), '.key', '.value'))),
                        JSON_UNQUOTE(JSON_EXTRACT(pm.meta_value, REPLACE(JSON_UNQUOTE(JSON_SEARCH(pm.meta_value, 'one', 'ref')), '.key', '.value'))),
                        'unknown'
                    ) as source,
                    COALESCE(
                        JSON_UNQUOTE(JSON_EXTRACT(pm.meta_value, REPLACE(JSON_UNQUOTE(JSON_SEARCH(pm.meta_value, 'one', 'utm_campaign')), '.key', '.value'))),
                        'unknown'
                    ) as campaign,
                    COALESCE(
                        JSON_UNQUOTE(JSON_EXTRACT(pm.meta_value, REPLACE(JSON_UNQUOTE(JSON_SEARCH(pm.meta_value, 'one', 'garef')), '.key', '.value'))),
                        JSON_UNQUOTE(JSON_EXTRACT(pm.meta_value, REPLACE(JSON_UNQUOTE(JSON_SEARCH(pm.meta_value, 'one', 'garef_id')), '.key', '.value')))
                    ) as detected_garef,
                    NULL as order_id,
                    NULL as attempt_id,
                    0 as order_revenue,
                    p.ID as lead_id,
                    $engaged_user_id as engaged_user_id,
                    $paywall_user_id as paywall_user_id
                FROM `{$wpdb->posts}` p
                JOIN `{$wpdb->postmeta}` pm ON p.ID = pm.post_id AND pm.meta_key = 'growtype_analytics_marketing_sources'
                LEFT JOIN `{$wpdb->users}` u ON p.post_title = u.user_email
                $activated_join
                $paywall_join
                WHERE p.post_type = 'gf_lead'
                AND p.post_date >= DATE_SUB(NOW(), INTERVAL %d DAY)
                {$email_exclusion['sql']}
            ) combined
            GROUP BY source_type, source, campaign, detected_garef
            ORDER BY $orderby_sql $order
            LIMIT %d OFFSET %d";



        $query_params = array_merge(
            $paid, 
            $paid, 
            $attempts, 
            array((int)$days), 
            $email_exclusion['params'], 
            array((int)$days),
            $email_exclusion['params'],
            array((int)$limit, (int)$offset)
        );

        $results = $wpdb->get_results($this->controller->prepare_dynamic_query($query, $query_params), ARRAY_A);

        return array_map(function ($row) use ($affiliate_utm_map, $affiliate_code_map) {
            $paid_orders = (int)$row['paid_orders'];
            $attempts = (int)$row['attempts'];
            $registrations = (int)$row['registrations'];
            $engaged_users = (int)$row['engaged_users'];
            $paywall_users = (int)$row['paywall_users'];
            $score = (float)$row['score'];
            $revenue = (float)($row['revenue'] ?: 0);
            $source_type = $row['source_type'];
            $source = strtolower($row['source']);
            
            // Resolve Product ID hints
            if ($source_type === 'ga_payment_product_id_hint' && is_numeric($source)) {
                $product_name = get_the_title($source);
                if ($product_name) {
                    $source = $product_name . ' (' . $source . ')';
                }
            }

            // Human friendly source types
            $source_type_labels = array(
                'typein' => __('Direct', 'growtype-analytics'),
                'organic' => __('Search Engine', 'growtype-analytics'),
                'referral' => __('Referral', 'growtype-analytics'),
                'social' => __('Social Media', 'growtype-analytics'),
                'utm' => __('Campaign (UTM)', 'growtype-analytics'),
            );
            $display_source_type = $source_type_labels[$source_type] ?? $source_type;
            
            $detected_garef = $row['detected_garef'];

            $affiliate_data = null;
            if ($detected_garef && isset($affiliate_code_map[$detected_garef])) {
                $affiliate_data = $affiliate_code_map[$detected_garef];
            } elseif (isset($affiliate_utm_map[$source])) {
                $affiliate_data = $affiliate_utm_map[$source];
            }

            $affiliate_html = '—';
            if ($affiliate_data) {
                $profile_url = admin_url('user-edit.php?user_id=' . $affiliate_data['id']);
                $affiliate_html = sprintf(
                    '<a href="%s" target="_blank"><span class="badge badge-success" style="background:#2271b1; color:#fff; padding:2px 8px; border-radius:4px; font-size:10px; font-weight:bold; text-transform:uppercase; cursor:pointer;">%s</span></a>',
                    esc_url($profile_url),
                    esc_html($affiliate_data['name'])
                );
            }

            return array(
                'source_type' => $display_source_type,
                'source' => $source,
                'campaign' => $row['campaign'],
                'is_affiliate' => $affiliate_html,
                'registrations' => $this->controller->format_number($registrations),
                'engaged_users' => $this->controller->format_number($engaged_users),
                'paywall_users' => $this->controller->format_number($paywall_users),
                'paid_orders' => $this->controller->format_number($paid_orders),
                'attempts' => $this->controller->format_number($attempts),
                'success_rate' => $this->controller->format_percent($attempts > 0 ? ($paid_orders / $attempts) * 100 : 0),
                'revenue' => $this->controller->format_money($revenue),
                'score' => round($score, 1),
                'aov' => $this->controller->format_money($paid_orders > 0 ? $revenue / $paid_orders : 0),
                'total_items_count' => (int)($row['total_items_count'] ?? 0)
            );
        }, $results ?: array());
    }

    public function get_page_title()
    {
        return __('Source Attribution', 'growtype-analytics');
    }

    public function get_menu_title()
    {
        return __('Source Attribution', 'growtype-analytics');
    }

    public function get_menu_slug()
    {
        return 'growtype-analytics-source-attribution';
    }

    public function render_page()
    {
        $this->render_page_header(__('Source Attribution', 'growtype-analytics'));

        $date_from = isset($_GET['date_from']) ? sanitize_text_field(wp_unslash($_GET['date_from'])) : date('Y-m-d', strtotime('-30 days'));
        $date_to = isset($_GET['date_to']) ? sanitize_text_field(wp_unslash($_GET['date_to'])) : date('Y-m-d');
        $days = max(1, (int)((strtotime($date_to) - strtotime($date_from)) / 86400));
        
        $snapshot_settings = $this->controller->get_snapshot_settings();
        $objective = $snapshot_settings['growth_objective'] ?? '10x';
        $marketing_spend = $snapshot_settings['marketing_spend'] ?? 0;

        $this->controller->decision_renderer->render_dashboard_filters($date_from, $date_to, $objective, $marketing_spend, $this->get_menu_slug());

        $paged = isset($_GET['paged']) ? max(1, (int)$_GET['paged']) : 1;
        $per_page = Growtype_Analytics_Admin_Table_Renderer::DEFAULT_PER_PAGE;
        $offset = ($paged - 1) * $per_page;
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'score';
        $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'DESC';

        $attribution_data = $this->get_source_attribution_rows($days, $per_page, $offset, $orderby, $order);
        $total_items = !empty($attribution_data) ? $attribution_data[0]['total_items_count'] : 0;

        // Helper for sortable headers
        $render_sortable_header = function($label, $key) use ($orderby, $order, $date_from, $date_to) {
            $new_order = ($orderby === $key && $order === 'DESC') ? 'ASC' : 'DESC';
            $url = add_query_arg(array(
                'orderby' => $key,
                'order' => $new_order,
                'date_from' => $date_from,
                'date_to' => $date_to,
            ), $_SERVER['REQUEST_URI']);
            $icon = $orderby === $key ? ($order === 'DESC' ? ' &darr;' : ' &uarr;') : '';
            return '<a href="' . esc_url($url) . '">' . esc_html($label) . $icon . '</a>';
        };

        ?>
        <div class="analytics-section">
            <h2><?php _e('Revenue By Source', 'growtype-analytics'); ?></h2>
            <p class="description"><?php _e('Shows which traffic sources and campaigns create registrations, paid orders, attempts, and revenue.', 'growtype-analytics'); ?></p>
            <?php
            $headers = array(
                __('Source Type', 'growtype-analytics'),
                __('Source', 'growtype-analytics'),
                __('Campaign', 'growtype-analytics'),
                __('Affiliate', 'growtype-analytics'),
                $render_sortable_header(__('Registrations', 'growtype-analytics'), 'registrations'),
                $render_sortable_header(__('Engaged (3+)', 'growtype-analytics'), 'engaged_users'),
                $render_sortable_header(__('Saw Paywall', 'growtype-analytics'), 'paywall_users'),
                $render_sortable_header(__('Paid Orders', 'growtype-analytics'), 'paid_orders'),
                $render_sortable_header(__('Attempts', 'growtype-analytics'), 'attempts'),
                $render_sortable_header(__('Success Rate', 'growtype-analytics'), 'success_rate'),
                $render_sortable_header(__('Revenue', 'growtype-analytics'), 'revenue'),
                $render_sortable_header(__('Score', 'growtype-analytics'), 'score'),
                __('AOV', 'growtype-analytics')
            );

            $this->controller->table_renderer->render(
                $headers,
                $attribution_data,
                $total_items,
                $per_page,
                $paged
            );
            ?>
        </div>
        <?php
        $this->render_page_footer();
    }
}
