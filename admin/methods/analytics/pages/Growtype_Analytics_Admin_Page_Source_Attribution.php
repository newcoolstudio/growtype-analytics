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

        // Sanitize orderby
        $allowed_orderby = array(
            'revenue' => 'revenue',
            'paid_orders' => 'paid_orders',
            'attempts' => 'attempts',
            'success_rate' => '(CAST(COUNT(DISTINCT CASE WHEN p.post_status IN (' . $paid_placeholders . ') THEN p.ID END) AS DECIMAL(10,2)) / CAST(COUNT(DISTINCT p.ID) AS DECIMAL(10,2)))'
        );
        
        $orderby_sql = $allowed_orderby[$orderby] ?? 'revenue';
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        $query = "SELECT
                COALESCE(source_type.meta_value, 'unknown') as source_type,
                COALESCE(source.meta_value, 'unknown') as source,
                COALESCE(campaign.meta_value, 'unknown') as campaign,
                COUNT(DISTINCT CASE WHEN p.post_status IN ($paid_placeholders) THEN p.ID END) as paid_orders,
                COUNT(DISTINCT p.ID) as attempts,
                SUM(CASE WHEN p.post_status IN ($paid_placeholders) THEN CAST(total.meta_value AS DECIMAL(10,2)) ELSE 0 END) as revenue,
                COUNT(*) OVER() as total_items_count
            FROM `{$wpdb->posts}` p
            INNER JOIN `{$wpdb->postmeta}` total ON total.post_id = p.ID AND total.meta_key = '_order_total'
            LEFT JOIN `{$wpdb->postmeta}` customer ON customer.post_id = p.ID AND customer.meta_key = '_customer_user'
            LEFT JOIN `{$wpdb->users}` u ON customer.meta_value = u.ID
            LEFT JOIN `{$wpdb->postmeta}` source_type ON source_type.post_id = p.ID AND source_type.meta_key = '_wc_order_attribution_source_type'
            LEFT JOIN `{$wpdb->postmeta}` source ON source.post_id = p.ID AND source.meta_key = '_wc_order_attribution_utm_source'
            LEFT JOIN `{$wpdb->postmeta}` campaign ON campaign.post_id = p.ID AND campaign.meta_key = '_wc_order_attribution_utm_campaign'
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ($attempt_placeholders)
            AND p.post_date >= DATE_SUB(NOW(), INTERVAL %d DAY)
            {$email_exclusion['sql']}
            GROUP BY source_type, source, campaign
            ORDER BY $orderby_sql $order
            LIMIT %d OFFSET %d";

        $params = array_merge($paid, $paid, $attempts, array((int)$days), $email_exclusion['params'], array((int)$limit, (int)$offset));
        $results = $wpdb->get_results($this->controller->prepare_dynamic_query($query, $params), ARRAY_A);

        return array_map(function ($row) {
            $paid_orders = (int)$row['paid_orders'];
            $attempts = (int)$row['attempts'];
            $revenue = (float)($row['revenue'] ?: 0);

            return array(
                'source_type' => $row['source_type'],
                'source' => $row['source'],
                'campaign' => $row['campaign'],
                'paid_orders' => $this->controller->format_number($paid_orders),
                'attempts' => $this->controller->format_number($attempts),
                'success_rate' => $this->controller->format_percent($attempts > 0 ? ($paid_orders / $attempts) * 100 : 0),
                'revenue' => $this->controller->format_money($revenue),
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
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'revenue';
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
            <p class="description"><?php _e('Shows which traffic sources and campaigns create paid orders, attempts, and revenue.', 'growtype-analytics'); ?></p>
            <?php
            $headers = array(
                __('Source Type', 'growtype-analytics'),
                __('Source', 'growtype-analytics'),
                __('Campaign', 'growtype-analytics'),
                $render_sortable_header(__('Paid Orders', 'growtype-analytics'), 'paid_orders'),
                $render_sortable_header(__('Attempts', 'growtype-analytics'), 'attempts'),
                $render_sortable_header(__('Success Rate', 'growtype-analytics'), 'success_rate'),
                $render_sortable_header(__('Revenue', 'growtype-analytics'), 'revenue'),
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
