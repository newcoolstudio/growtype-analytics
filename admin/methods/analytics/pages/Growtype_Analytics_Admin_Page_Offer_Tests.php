<?php

class Growtype_Analytics_Admin_Page_Offer_Tests extends Growtype_Analytics_Admin_Base_Page
{
    public function get_offer_test_rows($limit = 50, $days = 30)
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

        $query = "SELECT
                oi.order_item_name as offer_name,
                COUNT(DISTINCT CASE WHEN p.post_status IN ($paid_placeholders) THEN p.ID END) as paid_orders,
                COUNT(DISTINCT CASE WHEN p.post_status IN ($failed_placeholders) THEN p.ID END) as failed_orders,
                SUM(CASE WHEN p.post_status IN ($paid_placeholders) THEN CAST(line_total.meta_value AS DECIMAL(10,2)) ELSE 0 END) as revenue
            FROM `{$wpdb->posts}` p
            INNER JOIN `{$wpdb->prefix}woocommerce_order_items` oi ON oi.order_id = p.ID AND oi.order_item_type = 'line_item'
            LEFT JOIN `{$wpdb->prefix}woocommerce_order_itemmeta` line_total ON line_total.order_item_id = oi.order_item_id AND line_total.meta_key = '_line_total'
            LEFT JOIN `{$wpdb->postmeta}` customer ON customer.post_id = p.ID AND customer.meta_key = '_customer_user'
            LEFT JOIN `{$wpdb->users}` u ON customer.meta_value = u.ID
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ($attempt_placeholders)
            AND p.post_date >= DATE_SUB(NOW(), INTERVAL %d DAY)
            {$email_exclusion['sql']}
            GROUP BY oi.order_item_name
            ORDER BY revenue DESC, paid_orders DESC
            LIMIT %d";

        $params = array_merge($paid, $failed, $paid, $attempts, array((int)$days), $email_exclusion['params'], array((int)$limit));
        $results = $wpdb->get_results($this->controller->prepare_dynamic_query($query, $params), ARRAY_A);

        return array_map(function ($row) {
            $paid_orders = (int)$row['paid_orders'];
            $failed_orders = (int)$row['failed_orders'];
            $attempts = $paid_orders + $failed_orders;
            $revenue = (float)($row['revenue'] ?: 0);

            return array(
                'offer_name' => $row['offer_name'] ?: 'unknown',
                'paid_orders' => $this->controller->format_number($paid_orders),
                'failed_orders' => $this->controller->format_number($failed_orders),
                'success_rate' => $this->controller->format_percent($attempts > 0 ? ($paid_orders / $attempts) * 100 : 0),
                'revenue' => $this->controller->format_money($revenue),
                'avg_revenue_per_order' => $this->controller->format_money($paid_orders > 0 ? $revenue / $paid_orders : 0),
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
        
        $rows = $this->get_offer_test_rows(50);
        ?>
        <div class="analytics-section">
            <h2><?php _e('Offer Performance', 'growtype-analytics'); ?></h2>
            <p class="description"><?php _e('Ranks product packs by paid orders, failed attempts, success rate, and revenue in the last 30 days.', 'growtype-analytics'); ?></p>
            <?php
            $this->controller->decision_renderer->render_growth_table(
                array('Offer', 'Paid Orders', 'Failed Attempts', 'Success Rate', 'Revenue', 'Avg Revenue / Order'),
                $rows
            );
            ?>
        </div>
        <?php
        $this->render_page_footer();
    }
}
