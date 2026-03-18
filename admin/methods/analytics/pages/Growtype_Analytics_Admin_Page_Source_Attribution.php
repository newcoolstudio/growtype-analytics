<?php

class Growtype_Analytics_Admin_Page_Source_Attribution extends Growtype_Analytics_Admin_Base_Page
{
    public function get_source_attribution_rows($limit = 50)
    {
        global $wpdb;

        $settings = $this->controller->get_snapshot_settings();
        $paid = $settings['paid_statuses'];
        $attempts = $settings['attempt_statuses'];
        $paid_placeholders = implode(',', array_fill(0, count($paid), '%s'));
        $attempt_placeholders = implode(',', array_fill(0, count($attempts), '%s'));
        $email_exclusion = $this->controller->build_email_exclusion_sql('u.user_email', $settings['excluded_email_patterns'], true);

        $query = "SELECT
                COALESCE(source_type.meta_value, 'unknown') as source_type,
                COALESCE(source.meta_value, 'unknown') as source,
                COALESCE(campaign.meta_value, 'unknown') as campaign,
                COUNT(DISTINCT CASE WHEN p.post_status IN ($paid_placeholders) THEN p.ID END) as paid_orders,
                COUNT(DISTINCT p.ID) as attempts,
                SUM(CASE WHEN p.post_status IN ($paid_placeholders) THEN CAST(total.meta_value AS DECIMAL(10,2)) ELSE 0 END) as revenue
            FROM `{$wpdb->posts}` p
            INNER JOIN `{$wpdb->postmeta}` total ON total.post_id = p.ID AND total.meta_key = '_order_total'
            LEFT JOIN `{$wpdb->postmeta}` customer ON customer.post_id = p.ID AND customer.meta_key = '_customer_user'
            LEFT JOIN `{$wpdb->users}` u ON customer.meta_value = u.ID
            LEFT JOIN `{$wpdb->postmeta}` source_type ON source_type.post_id = p.ID AND source_type.meta_key = '_wc_order_attribution_source_type'
            LEFT JOIN `{$wpdb->postmeta}` source ON source.post_id = p.ID AND source.meta_key = '_wc_order_attribution_utm_source'
            LEFT JOIN `{$wpdb->postmeta}` campaign ON campaign.post_id = p.ID AND campaign.meta_key = '_wc_order_attribution_utm_campaign'
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ($attempt_placeholders)
            {$email_exclusion['sql']}
            GROUP BY source_type, source, campaign
            ORDER BY revenue DESC, attempts DESC
            LIMIT %d";

        $params = array_merge($paid, $paid, $attempts, $email_exclusion['params'], array((int)$limit));
        $results = $wpdb->get_results($this->controller->prepare_dynamic_query($query, $params), ARRAY_A);

        return array_map(function ($row) {
            $paid_orders = (int)$row['paid_orders'];
            $attempts = (int)$row['attempts'];
            $revenue = (float)($row['revenue'] ?: 0);

            return array(
                $row['source_type'],
                $row['source'],
                $row['campaign'],
                $this->controller->format_number($paid_orders),
                $this->controller->format_number($attempts),
                $this->controller->format_percent($attempts > 0 ? ($paid_orders / $attempts) * 100 : 0),
                $this->controller->format_money($revenue),
                $this->controller->format_money($paid_orders > 0 ? $revenue / $paid_orders : 0),
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
        
        $rows = $this->get_source_attribution_rows(50);
        ?>
        <div class="analytics-section">
            <h2><?php _e('Revenue By Source', 'growtype-analytics'); ?></h2>
            <p class="description"><?php _e('Shows which traffic sources and campaigns create paid orders, attempts, and revenue.', 'growtype-analytics'); ?></p>
            <?php
            $this->controller->decision_renderer->render_growth_table(
                array('Source Type', 'Source', 'Campaign', 'Paid Orders', 'Attempts', 'Success Rate', 'Revenue', 'AOV'),
                $rows
            );
            ?>
        </div>
        <?php
        $this->render_page_footer();
    }
}
