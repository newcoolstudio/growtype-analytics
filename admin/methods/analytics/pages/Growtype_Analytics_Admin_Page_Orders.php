<?php

class Growtype_Analytics_Admin_Page_Orders extends Growtype_Analytics_Admin_Base_Page
{
    public function get_page_title()
    {
        return __('Orders', 'growtype-analytics');
    }

    public function get_menu_title()
    {
        return __('Orders', 'growtype-analytics');
    }

    public function get_menu_slug()
    {
        return 'growtype-analytics-orders';
    }

    public function render_page()
    {
        $this->render_page_header(__('Orders Analytics', 'growtype-analytics'));

        $metrics = $this->controller->metrics->get_scale_or_pivot_metrics();
        $pd = $metrics['active_period_days'] ?? 30;

        $this->controller->decision_renderer->render_dashboard_filters(
            $_GET['date_from'] ?? '',
            $_GET['date_to'] ?? '',
            get_option('growtype_analytics_growth_objective', '10x'),
            get_option('growtype_analytics_snapshot_marketing_spend_30d', 0),
            $this->get_menu_slug()
        );

        ?>
        <div class="analytics-section">
            <h2><?php printf(__('Orders Overview (%sd)', 'growtype-analytics'), $pd); ?></h2>
            <div class="analytics-scale-snapshot-grid">
                <?php
                $map = array(
                    'paid_orders' => array(
                        'title' => __('Paid Orders', 'growtype-analytics'),
                        'value' => $this->controller->format_number($metrics['paid_orders']),
                        'desc' => __('Successful payments', 'growtype-analytics')
                    ),
                    'revenue' => array(
                        'title' => __('Revenue', 'growtype-analytics'),
                        'value' => $this->controller->format_money($metrics['revenue']),
                        'desc' => __('Total from paid orders', 'growtype-analytics')
                    ),
                    'aov' => array(
                        'title' => __('AOV', 'growtype-analytics'),
                        'value' => $this->controller->format_money($metrics['aov']),
                        'desc' => __('Average order value', 'growtype-analytics')
                    ),
                    'payment_success_rate' => array(
                        'title' => __('Success Rate', 'growtype-analytics'),
                        'value' => $this->controller->format_percent($metrics['payment_success_rate']),
                        'desc' => __('Paid / Total attempts', 'growtype-analytics'),
                        'is_good' => $metrics['payment_success_rate'] >= 45
                    ),
                    'unpaid_attempts' => array(
                        'title' => __('Unpaid Attempts', 'growtype-analytics'),
                        'value' => $this->controller->format_number($metrics['unpaid_attempts']),
                        'desc' => __('Failed or pending', 'growtype-analytics')
                    ),
                    'repurchase_rate_total' => array(
                        'title' => __('Repurchase Rate', 'growtype-analytics'),
                        'value' => $this->controller->format_percent($metrics['repurchase_rate_total']),
                        'desc' => __('Recurring buyers', 'growtype-analytics'),
                        'is_good' => $metrics['repurchase_rate_total'] >= 25
                    ),
                );

                foreach ($map as $id => $m) {
                    $this->controller->decision_renderer->render_snapshot_card(
                        $m['title'],
                        $m['value'],
                        $m['desc'],
                        $m['is_good'] ?? null,
                        '',
                        $id
                    );
                }
                ?>
            </div>
        </div>

        <div class="analytics-section">
            <h2><?php _e('Payment Failure Segments', 'growtype-analytics'); ?></h2>
            <?php $this->controller->decision_renderer->render_payment_failure_segmentation(); ?>
        </div>

        <?php
        $this->render_page_footer();
    }
}
