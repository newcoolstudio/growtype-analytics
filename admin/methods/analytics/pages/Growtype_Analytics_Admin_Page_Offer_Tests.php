<?php

class Growtype_Analytics_Admin_Page_Offer_Tests extends Growtype_Analytics_Admin_Base_Page
{
    public function get_offer_test_rows($days = 30, $limit = 50, $offset = 0, $orderby = 'revenue', $order = 'DESC')
    {
        global $wpdb;

        $settings = $this->controller->get_snapshot_settings();
        $paid = $settings['paid_statuses'];
        $attempts = $settings['attempt_statuses'];
        $failed = array_values(array_filter($attempts, function ($status) use ($paid) {
            return !in_array($status, $paid, true);
        }));

        if (empty($failed)) {
            $failed = array('wc-pending', 'wc-failed', 'wc-cancelled');
        }

        $paid_placeholders = implode(',', array_fill(0, count($paid), '%s'));
        $attempt_placeholders = implode(',', array_fill(0, count($attempts), '%s'));
        $failed_placeholders = implode(',', array_fill(0, count($failed), '%s'));
        $email_exclusion = $this->controller->build_email_exclusion_sql('u.user_email', $settings['excluded_email_patterns'], true);

        // Sanitize orderby
        $allowed_orderby = array(
            'revenue' => 'revenue',
            'paid_orders' => 'paid_orders',
            'failed_orders' => 'failed_orders',
            'success_rate' => '(paid_orders / NULLIF(paid_orders + failed_orders, 0))',
            'avg_revenue_per_order' => '(revenue / NULLIF(paid_orders, 0))',
            'shown_unique' => 'shown_unique'
        );
        $orderby_sql = $allowed_orderby[$orderby] ?? 'revenue';
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        $query = "SELECT
                oi.order_item_name as offer_name,
                MAX(product_id_meta.meta_value) as product_id,
                COUNT(DISTINCT CASE WHEN p.post_status IN ($paid_placeholders) THEN p.ID END) as paid_orders,
                COUNT(DISTINCT CASE WHEN p.post_status IN ($failed_placeholders) THEN p.ID END) as failed_orders,
                SUM(CASE WHEN p.post_status IN ($paid_placeholders) THEN CAST(line_total.meta_value AS DECIMAL(10,2)) ELSE 0 END) as revenue,
                COALESCE(MAX(tracking.shown_count), 0) as shown_unique,
                COUNT(*) OVER() as total_items_count
            FROM `{$wpdb->posts}` p
            INNER JOIN `{$wpdb->prefix}woocommerce_order_items` oi ON oi.order_id = p.ID AND oi.order_item_type = 'line_item'
            LEFT JOIN `{$wpdb->prefix}woocommerce_order_itemmeta` line_total ON line_total.order_item_id = oi.order_item_id AND line_total.meta_key = '_line_total'
            LEFT JOIN `{$wpdb->prefix}woocommerce_order_itemmeta` product_id_meta ON product_id_meta.order_item_id = oi.order_item_id AND product_id_meta.meta_key = '_product_id'
            LEFT JOIN (
                SELECT object_id, COUNT(DISTINCT user_id) as shown_count
                FROM `{$wpdb->prefix}growtype_analytics_tracking`
                WHERE event_type = 'offer_shown'
                  AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
                GROUP BY object_id
            ) tracking ON tracking.object_id = product_id_meta.meta_value
            LEFT JOIN `{$wpdb->postmeta}` customer ON customer.post_id = p.ID AND customer.meta_key = '_customer_user'
            LEFT JOIN `{$wpdb->users}` u ON customer.meta_value = u.ID
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ($attempt_placeholders)
            AND p.post_date >= DATE_SUB(NOW(), INTERVAL %d DAY)
            {$email_exclusion['sql']}
            GROUP BY oi.order_item_name
            ORDER BY $orderby_sql $order
            LIMIT %d OFFSET %d";

        $params = array_merge($paid, $failed, $paid, array((int)$days), $attempts, array((int)$days), $email_exclusion['params'], array((int)$limit, (int)$offset));
        $results = $wpdb->get_results($this->controller->prepare_dynamic_query($query, $params), ARRAY_A);

        return array_map(function ($row) {
            $offer_name = $row['offer_name'] ?: 'unknown';
            $paid_orders = (int)$row['paid_orders'];
            $failed_orders = (int)$row['failed_orders'];
            $attempts = $paid_orders + $failed_orders;
            $revenue = (float)($row['revenue'] ?: 0);
            $shown_unique_count = (int)($row['shown_unique'] ?? 0);

            $product_id = $row['product_id'] ?: '-';

            return array(
                'offer_name' => $offer_name . ' <a href="' . esc_url(admin_url('post.php?post=' . $product_id . '&action=edit')) . '" target="_blank" style="opacity:0.5;text-decoration:none;font-size:12px">(ID: ' . esc_html($product_id) . ')</a>',
                'shown_unique' => $this->controller->format_number($shown_unique_count),
                'paid_orders' => $this->controller->format_number($paid_orders),
                'failed_orders' => $this->controller->format_number($failed_orders),
                'success_rate' => $this->controller->format_percent($attempts > 0 ? ($paid_orders / $attempts) * 100 : 0),
                'revenue' => $this->controller->format_money($revenue),
                'avg_revenue_per_order' => $this->controller->format_money($paid_orders > 0 ? $revenue / $paid_orders : 0),
                'total_items_count' => (int)($row['total_items_count'] ?? 0)
            );
        }, $results ?: array());
    }

    public function get_page_title()
    {
        return __('Offer Tests', 'growtype-analytics');
    }

    public function get_menu_title()
    {
        return __('Offer Tests', 'growtype-analytics');
    }

    public function get_menu_slug()
    {
        return 'growtype-analytics-offer-tests';
    }

    public function render_page()
    {
        $this->render_page_header(__('Offer / Pricing Tests', 'growtype-analytics'));

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

        $offer_data = $this->get_offer_test_rows($days, $per_page, $offset, $orderby, $order);
        $total_items = !empty($offer_data) ? $offer_data[0]['total_items_count'] : 0;

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
            <h2><?php _e('Offer Performance', 'growtype-analytics'); ?></h2>
            <p class="description"><?php printf(__('Analysis of which offers/products are being shown and purchased.', 'growtype-analytics'), $days); ?></p>
            <?php
            $headers = array(
                __('Offer', 'growtype-analytics'),
                $render_sortable_header(__('Shown (Unique)', 'growtype-analytics'), 'shown_unique'),
                $render_sortable_header(__('Paid Orders', 'growtype-analytics'), 'paid_orders'),
                $render_sortable_header(__('Failed Attempts', 'growtype-analytics'), 'failed_orders'),
                $render_sortable_header(__('Success Rate', 'growtype-analytics'), 'success_rate'),
                $render_sortable_header(__('Revenue', 'growtype-analytics'), 'revenue'),
                $render_sortable_header(__('Avg Revenue / Order', 'growtype-analytics'), 'avg_revenue_per_order')
            );

            $this->controller->table_renderer->render(
                $headers,
                $offer_data,
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
