<?php

class Growtype_Analytics_Admin_Page_Contribution_Margin extends Growtype_Analytics_Admin_Base_Page
{
    public function get_margin_settings()
    {
        return array(
            'payment_fee_percent' => (float)get_option('growtype_analytics_margin_payment_fee_percent', '3.5'),
            'payment_fee_fixed' => (float)get_option('growtype_analytics_margin_payment_fee_fixed', '0.30'),
            'ai_cost_per_active_user' => (float)get_option('growtype_analytics_margin_ai_cost_per_active_user', '0'),
            'media_cost_per_paid_order' => (float)get_option('growtype_analytics_margin_media_cost_per_paid_order', '0'),
            'revenue_share_percent' => (float)get_option('growtype_analytics_margin_revenue_share_percent', '0'),
            'monthly_infra_cost' => (float)get_option('growtype_analytics_margin_monthly_infra_cost', '0'),
            'known_chargeback_count_30d' => (int)get_option('growtype_analytics_margin_known_chargeback_count_30d', '0'),
            'known_chargeback_amount_30d' => (float)get_option('growtype_analytics_margin_known_chargeback_amount_30d', '0'),
        );
    }

    public function get_contribution_margin_metrics($days, $margin_settings)
    {
        $snapshot_settings = $this->controller->get_snapshot_settings();
        $orders = $this->controller->metrics->get_order_metrics($days, $snapshot_settings);
        $active_users = $this->controller->metrics->get_active_user_count($days, $snapshot_settings);
        $revenue = (float)$orders['revenue'];
        $paid_orders = (int)$orders['paid_orders'];

        $payment_fees = ($revenue * ($margin_settings['payment_fee_percent'] / 100)) + ($paid_orders * $margin_settings['payment_fee_fixed']);
        $ai_cost = $active_users * $margin_settings['ai_cost_per_active_user'];
        $media_cost = $paid_orders * $margin_settings['media_cost_per_paid_order'];
        $revenue_share_cost = $revenue * ($margin_settings['revenue_share_percent'] / 100);
        $infra_cost = $margin_settings['monthly_infra_cost'] * ($days / 30);
        $contribution_margin = $revenue - $payment_fees - $ai_cost - $media_cost - $revenue_share_cost - $infra_cost;

        return array(
            'revenue' => $revenue,
            'payment_fees' => $payment_fees,
            'ai_cost' => $ai_cost,
            'media_cost' => $media_cost,
            'revenue_share_cost' => $revenue_share_cost,
            'infra_cost' => $infra_cost,
            'contribution_margin' => $contribution_margin,
            'contribution_margin_percent' => $revenue > 0 ? ($contribution_margin / $revenue) * 100 : 0,
        );
    }

    public function get_contribution_margin_data()
    {
        $margin_settings = $this->get_margin_settings();
        $metrics = $this->get_contribution_margin_metrics(30, $margin_settings);

        return array(
            'metrics' => $metrics,
            'rows' => array(
                array('Revenue', $this->controller->format_money($metrics['revenue'])),
                array('Estimated Payment Fees', $this->controller->format_money($metrics['payment_fees'])),
                array('Estimated AI Cost', $this->controller->format_money($metrics['ai_cost'])),
                array('Estimated Media Cost', $this->controller->format_money($metrics['media_cost'])),
                array('Estimated Revenue Share', $this->controller->format_money($metrics['revenue_share_cost'])),
                array('Estimated Infra Cost', $this->controller->format_money($metrics['infra_cost'])),
                array('Contribution Margin', $this->controller->format_money($metrics['contribution_margin'])),
                array('Contribution Margin %', $this->controller->format_percent($metrics['contribution_margin_percent'])),
            ),
        );
    }

    public function get_refund_metrics($days = 30)
    {
        global $wpdb;

        $settings = $this->controller->get_snapshot_settings();
        $email_exclusion = $this->controller->build_email_exclusion_sql('u.user_email', $settings['excluded_email_patterns'], true);

        $query = "SELECT
                COUNT(DISTINCT refunds.post_parent) as refund_orders,
                SUM(ABS(CAST(COALESCE(amount.meta_value, 0) AS DECIMAL(10,2)))) as refund_amount
            FROM `{$wpdb->posts}` refunds
            LEFT JOIN `{$wpdb->postmeta}` amount ON amount.post_id = refunds.ID AND amount.meta_key = '_refund_amount'
            LEFT JOIN `{$wpdb->posts}` parent_order ON parent_order.ID = refunds.post_parent AND parent_order.post_type = 'shop_order'
            LEFT JOIN `{$wpdb->postmeta}` customer ON customer.post_id = parent_order.ID AND customer.meta_key = '_customer_user'
            LEFT JOIN `{$wpdb->users}` u ON customer.meta_value = u.ID
            WHERE refunds.post_type = 'shop_order_refund'
            AND refunds.post_date >= DATE_SUB(NOW(), INTERVAL %d DAY)
            {$email_exclusion['sql']}";

        $result = $wpdb->get_row(
            $this->controller->prepare_dynamic_query($query, array_merge(array((int)$days), $email_exclusion['params'])),
            ARRAY_A
        );

        return array(
            'refund_orders' => (int)($result['refund_orders'] ?? 0),
            'refund_amount' => (float)($result['refund_amount'] ?? 0),
        );
    }

    public function get_real_cost_refund_chargeback_data($days = 30)
    {
        $margin_settings = $this->get_margin_settings();
        $metrics = $this->get_contribution_margin_metrics($days, $margin_settings);
        $refunds = $this->get_refund_metrics($days);
        $chargeback_amount = (float)$margin_settings['known_chargeback_amount_30d'];
        $chargeback_count = (int)$margin_settings['known_chargeback_count_30d'];
        $net_after_refunds = $metrics['contribution_margin'] - $refunds['refund_amount'] - $chargeback_amount;
        $net_margin_percent = $metrics['revenue'] > 0 ? ($net_after_refunds / $metrics['revenue']) * 100 : 0;

        return array(
            'metrics' => array(
                'revenue' => $metrics['revenue'],
                'payment_fees' => $metrics['payment_fees'],
                'ai_cost' => $metrics['ai_cost'],
                'media_cost' => $metrics['media_cost'],
                'revenue_share_cost' => $metrics['revenue_share_cost'],
                'infra_cost' => $metrics['infra_cost'],
                'refund_orders' => $refunds['refund_orders'],
                'refund_amount' => $refunds['refund_amount'],
                'known_chargeback_count' => $chargeback_count,
                'known_chargeback_amount' => $chargeback_amount,
                'net_after_refunds_chargebacks' => $net_after_refunds,
                'net_margin_percent' => $net_margin_percent,
            ),
            'rows' => array(
                array('Revenue', $this->controller->format_money($metrics['revenue'])),
                array('Payment Fees', $this->controller->format_money($metrics['payment_fees'])),
                array('AI Cost', $this->controller->format_money($metrics['ai_cost'])),
                array('Media Cost', $this->controller->format_money($metrics['media_cost'])),
                array('Revenue Share', $this->controller->format_money($metrics['revenue_share_cost'])),
                array('Infra Cost', $this->controller->format_money($metrics['infra_cost'])),
                array('Refund Orders 30d', $this->controller->format_number($refunds['refund_orders'])),
                array('Refund Amount 30d', $this->controller->format_money($refunds['refund_amount'])),
                array('Known Chargebacks 30d', $this->controller->format_number($chargeback_count)),
                array('Known Chargeback Amount 30d', $this->controller->format_money($chargeback_amount)),
                array('Net After Refunds & Chargebacks', $this->controller->format_money($net_after_refunds)),
                array('Net Margin %', $this->controller->format_percent($net_margin_percent)),
            ),
        );
    }

    public function get_page_title()
    {
        return __('Contribution Margin', 'growtype-analytics');
    }

    public function get_menu_title()
    {
        return __('Contribution Margin', 'growtype-analytics');
    }

    public function get_menu_slug()
    {
        return 'growtype-analytics-contribution-margin';
    }

    public function render_page()
    {
        $this->render_page_header(__('Contribution Margin', 'growtype-analytics'));
        
        $data = $this->get_contribution_margin_data();
        $renderer = $this->controller->decision_renderer;
        $metrics = $data['metrics'];
        ?>
        <div class="analytics-section">
            <h2><?php _e('Estimated 30 Day Margin', 'growtype-analytics'); ?></h2>
            <p class="description"><?php _e('Uses your configurable cost assumptions from Settings > Growtype - Analytics.', 'growtype-analytics'); ?></p>
            <div class="analytics-scale-snapshot-grid">
                <?php $renderer->render_snapshot_card(__('Revenue 30d', 'growtype-analytics'), $this->controller->format_money($metrics['revenue']), __('Paid order revenue', 'growtype-analytics')); ?>
                <?php $renderer->render_snapshot_card(__('Contribution Margin', 'growtype-analytics'), $this->controller->format_money($metrics['contribution_margin']), __('Revenue minus estimated variable + fixed costs', 'growtype-analytics')); ?>
                <?php $renderer->render_snapshot_card(__('Margin %', 'growtype-analytics'), $this->controller->format_percent($metrics['contribution_margin_percent']), __('Estimated contribution margin percentage', 'growtype-analytics')); ?>
            </div>
            <?php $renderer->render_growth_table(array('Metric', 'Value'), $data['rows']); ?>
        </div>
        <?php
        $this->render_page_footer();
    }
}
