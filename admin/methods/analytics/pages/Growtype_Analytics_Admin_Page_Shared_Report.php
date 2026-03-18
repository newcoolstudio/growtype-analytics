<?php

class Growtype_Analytics_Admin_Page_Shared_Report
{
    private $controller;
    private $decision_renderer;

    public function __construct($controller, $decision_renderer)
    {
        $this->controller = $controller;
        $this->decision_renderer = $decision_renderer;
    }

    public function get_payload($link = array(), $refresh = false)
    {
        $token = !empty($link['token']) ? $link['token'] : 'default';
        $cache_key = 'growtype_analytics_shared_report_' . $token;
        
        if ($refresh) {
            delete_transient($cache_key);
        }

        $cached_payload = get_transient($cache_key);
        
        if ($cached_payload !== false) {
            return $cached_payload;
        }

        $title = !empty($link['label']) ? $link['label'] : __('Shared Business Report', 'growtype-analytics');
        $metrics = $this->controller->get_snapshot_metrics();
        $source_page = $this->controller->analytics_page->get_page_by_class('Growtype_Analytics_Admin_Page_Source_Attribution');
        $source_rows = $source_page ? $source_page->get_source_attribution_rows(20) : array();

        $funnel = $this->controller->funnel->get_funnel_dropoff_data();
        $offer_rows = $this->controller->get_offer_test_rows(20);
        $cohort_rows = $this->controller->get_buyer_cohort_rows(6);
        $traffic_funnel = $this->controller->get_traffic_funnel_data(30);
        $cac_by_source = $this->controller->get_cac_by_source_data(30, 10);
        $retention_by_source = $this->controller->get_retention_by_source_data(10);
        $source_payback = $this->controller->get_source_payback_data(30, 10);
        $language_conversion = $this->controller->get_language_conversion_data(30, 10);
        $offer_repurchase_quality = $this->controller->get_offer_repurchase_quality_data(10);
        $top_characters = $this->controller->get_top_characters_by_revenue_data(30, 10);
        $growth_trends = $this->controller->get_growth_trends_data(30);

        $margin_page = $this->controller->analytics_page->get_page_by_class('Growtype_Analytics_Admin_Page_Contribution_Margin');
        $margin = $margin_page ? $margin_page->get_contribution_margin_data() : array('metrics' => array(), 'rows' => array());
        $real_cost_data = $margin_page ? $margin_page->get_real_cost_refund_chargeback_data(30) : array('metrics' => array(), 'rows' => array());
        $refund_chargeback_rates = $this->controller->get_refund_chargeback_rates_data(30);
        $failure_segments = $this->controller->get_payment_failure_segments_data($metrics['settings'], 30, 25);

        $payload = array(
            'generated_at' => current_time('mysql'),
            'label' => $title,
            'overview' => array(
                'registered_users_total' => $metrics['registered_users_total'],
                'new_users_7d' => $metrics['new_users_7d'],
                'new_users_30d' => $metrics['new_users_30d'],
                'activation_min_messages' => $metrics['activation_min_messages'],
                'activation_window_days' => $metrics['activation_window_days'],
                'activation_rate_7d' => $metrics['activation_rate_7d'],
                'activation_rate_30d' => $metrics['activation_rate_30d'],
                'buyers_total' => $metrics['buyers_total'],
                'buyer_conversion_total' => $metrics['buyer_conversion_total'],
                'new_user_to_buyer_conversion_30d' => $metrics['new_user_to_buyer_conversion_30d'],
                'revenue_30d' => $metrics['revenue_30d'],
                'payment_success_rate_30d' => $metrics['payment_success_rate_30d'],
                'repurchase_rate_total' => $metrics['repurchase_rate_total'],
                'arppu_total' => $metrics['arppu_total'],
                'dau' => $metrics['dau'],
                'wau' => $metrics['wau'],
                'mau' => $metrics['mau'],
                'stickiness_ratio' => $metrics['stickiness_ratio'],
                'churn_inactivity_days' => $metrics['churn_inactivity_days'],
                'recent_payer_window_days' => $metrics['recent_payer_window_days'],
                'churn_risk_recent_payers' => $metrics['churn_risk_recent_payers'],
                'aov_30d' => $metrics['aov_30d'],
                // P0 growth & scale metrics
                'revenue_prev_30d' => $metrics['revenue_prev_30d'],
                'revenue_growth_mom' => $metrics['revenue_growth_mom'],
                'new_users_prev_7d' => $metrics['new_users_prev_7d'],
                'new_users_growth_wow' => $metrics['new_users_growth_wow'],
                'ltv_estimate' => $metrics['ltv_estimate'],
                'arpu' => $metrics['arpu'],
                // P1 retention & conversion speed metrics
                'payer_churn_rate' => $metrics['payer_churn_rate'],
                'user_churn_rate' => $metrics['user_churn_rate'],
                'median_days_to_first_purchase' => $metrics['median_days_to_first_purchase'],
                // P2 metrics
                'cac_estimate' => $metrics['cac_estimate'],
                'revenue_daily_30d' => $metrics['revenue_daily_30d'],
            ),
            'execution_kpis' => array(
                'payment_success_rate_30d' => $metrics['payment_success_rate_30d'],
                'new_user_to_buyer_conversion_30d' => $metrics['new_user_to_buyer_conversion_30d'],
                'new_user_to_buyer_conversion_daily' => $metrics['new_user_to_buyer_conversion_daily'],
                'repurchase_rate_total' => $metrics['repurchase_rate_total'],
                'unpaid_attempts_30d' => $metrics['unpaid_attempts_30d'],
                'targets' => array(
                    'payment_success_rate_30d' => 45.0,
                    'new_user_to_buyer_conversion_30d' => 2.5,
                    'repurchase_rate_total' => 25.0,
                ),
                'payment_failure_segments' => $failure_segments,
            ),
            'source_attribution' => $source_rows,
            'traffic_funnel' => $traffic_funnel,
            'cac_by_source' => $cac_by_source,
            'retention_by_source' => $retention_by_source,
            'source_payback' => $source_payback,
            'language_conversion' => $language_conversion,
            'funnel_dropoff_30d' => $funnel['30d'],
            'offer_tests' => $offer_rows,
            'offer_repurchase_quality' => $offer_repurchase_quality,
            'top_characters_by_revenue' => $top_characters,
            'buyer_cohorts' => $cohort_rows,
            'growth_trends_30d' => $growth_trends,
            'contribution_margin' => $margin['metrics'],
            'real_cost_refund_chargeback' => $real_cost_data['metrics'],
            'refund_chargeback_rates' => $refund_chargeback_rates,
        );

        set_transient($cache_key, $payload, 10 * MINUTE_IN_SECONDS);
        
        return $payload;
    }

    public function render_page($link = array(), $format = 'html')
    {
        $refresh = isset($_GET['refresh']) && $_GET['refresh'] === '1';
        $payload = $this->get_payload($link, $refresh);

        if ($format === 'json') {
            return $payload;
        }

        $title = $payload['label'];
        $source_rows = $payload['source_attribution'];
        $traffic_funnel = $payload['traffic_funnel'];
        $cac_by_source = $payload['cac_by_source'];
        $retention_by_source = $payload['retention_by_source'];
        $source_payback = $payload['source_payback'];
        $language_conversion = $payload['language_conversion'];
        $funnel_30 = $payload['funnel_dropoff_30d'];
        $offer_rows = $payload['offer_tests'];
        $offer_repurchase_quality = $payload['offer_repurchase_quality'];
        $top_characters = $payload['top_characters_by_revenue'];
        $cohort_rows = $payload['buyer_cohorts'];
        $growth_trends = $payload['growth_trends_30d'];
        $margin_metrics = $payload['contribution_margin'];
        $real_cost_metrics = $payload['real_cost_refund_chargeback'];
        $refund_chargeback_rates = $payload['refund_chargeback_rates'];
        
        $margin_page = $this->controller->analytics_page->get_page_by_class('Growtype_Analytics_Admin_Page_Contribution_Margin');
        $margin_rows = $margin_page ? $margin_page->get_contribution_margin_data()['rows'] : array();
        $real_cost_rows = $margin_page ? $margin_page->get_real_cost_refund_chargeback_data(30)['rows'] : array();
        ?><!doctype html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo esc_html($title); ?></title>
            <link rel="stylesheet" href="<?php echo esc_url(GROWTYPE_ANALYTICS_URL . 'admin/css/growtype-analytics-page.css'); ?>">
            <style>
                body { margin: 0; padding: 24px; background: #f3f4f6; color: #111827; font: 14px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; }
                .growtype-analytics-page { max-width: 1280px; margin: 0 auto; }
                .growtype-analytics-page h1 { margin-bottom: 8px; }
                .growtype-analytics-page .description { color: #4b5563; }
                .analytics-section { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
                .wp-list-table { width: 100%; border-collapse: collapse; background: #fff; }
                .wp-list-table th, .wp-list-table td { border: 1px solid #e5e7eb; padding: 10px; text-align: left; vertical-align: top; }
                .wp-list-table thead th { background: #f9fafb; }
                textarea[readonly] { width: 100%; min-height: 180px; }
                .shared-report-meta { margin-bottom: 24px; color: #6b7280; display: flex; justify-content: space-between; align-items: center; }
                .refresh-button { background: #764ba2; color: #fff; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 13px; }
                .refresh-button:hover { background: #667eea; }
            </style>
        </head>
        <body>
        <div class="growtype-analytics-page">
            <div class="shared-report-meta">
                <div>
                    <h1><?php echo esc_html($title); ?></h1>
                    <?php
                    printf(
                        esc_html__('Read-only analytics report generated on %1$s.', 'growtype-analytics'),
                        esc_html($payload['generated_at'])
                    );
                    ?>
                </div>
                <div>
                    <a href="<?php echo esc_url(add_query_arg('refresh', '1')); ?>" class="refresh-button"><?php _e('Refresh Data', 'growtype-analytics'); ?></a>
                </div>
            </div>

            <div class="analytics-section">
                <h2><?php _e('Analytics Overview', 'growtype-analytics'); ?></h2>
                <p class="description"><?php _e('Core business snapshot across users, activation, conversion, revenue, and retention.', 'growtype-analytics'); ?></p>
                <?php $this->decision_renderer->render_analytics_snapshot(); ?>
            </div>

            <div class="analytics-section">
                <h2><?php _e('Execution KPIs', 'growtype-analytics'); ?></h2>
                <p class="description"><?php _e('Payment success, new-user-to-buyer conversion, repurchase rate, and payment-failure segmentation.', 'growtype-analytics'); ?></p>
                <?php $this->decision_renderer->render_execution_kpis(); ?>
            </div>

            <div class="analytics-section">
                <h2><?php _e('Source Attribution', 'growtype-analytics'); ?></h2>
                <?php $this->decision_renderer->render_growth_table(array('Source Type', 'Source', 'Campaign', 'Paid Orders', 'Attempts', 'Success Rate', 'Revenue', 'AOV'), $source_rows); ?>
            </div>

            <div class="analytics-section">
                <h2><?php _e('Traffic Funnel', 'growtype-analytics'); ?></h2>
                <p class="description">
                    <?php echo esc_html($traffic_funnel['traffic_available'] ? __('Uses PostHog pageviews as the traffic entry stage, then follows registrations, activation, checkout attempts, and paid users.', 'growtype-analytics') : __('PostHog pageviews are not available, so this view starts from registrations instead of traffic.', 'growtype-analytics')); ?>
                </p>
                <?php $this->decision_renderer->render_funnel_cards($traffic_funnel); ?>
                <?php $this->decision_renderer->render_growth_table(array('Stage', 'Users', 'Vs Previous', 'Vs First'), $traffic_funnel['rows']); ?>
            </div>

            <div class="analytics-section">
                <h2><?php _e('CAC By Source', 'growtype-analytics'); ?></h2>
                <p class="description"><?php _e('Uses first-purchase acquisition source and your configured spend-by-source inputs from settings.', 'growtype-analytics'); ?></p>
                <?php $this->decision_renderer->render_growth_table(array('Source', 'New Buyers 30d', 'Active Buyers 30d', 'Revenue 30d', 'Spend 30d', 'CAC', 'ROAS'), $cac_by_source); ?>
            </div>

            <div class="analytics-section">
                <h2><?php _e('Retention By Source', 'growtype-analytics'); ?></h2>
                <p class="description"><?php _e('Groups buyers by the source of their first paid order to show source quality, not just source volume.', 'growtype-analytics'); ?></p>
                <?php $this->decision_renderer->render_growth_table(array('Source', 'Buyers', 'Repeat 30d', 'Repeat Rate 30d', 'Active 30d', 'Active Rate 30d', 'ARPPU'), $retention_by_source); ?>
            </div>

            <div class="analytics-section">
                <h2><?php _e('Source Payback', 'growtype-analytics'); ?></h2>
                <p class="description"><?php _e('Helps decide which sources deserve more budget by comparing spend, 30-day revenue, lifetime revenue, and estimated payback time.', 'growtype-analytics'); ?></p>
                <?php $this->decision_renderer->render_growth_table(array('Source', 'New Buyers 30d', 'Revenue 30d', 'Revenue Total', '30d Revenue / New Buyer', 'Payback Estimate'), $source_payback); ?>
            </div>

            <div class="analytics-section">
                <h2><?php _e('Language Conversion', 'growtype-analytics'); ?></h2>
                <p class="description"><?php _e('Shows which locales turn registration into payment best, so you know where localization and geo-targeting will matter most.', 'growtype-analytics'); ?></p>
                <?php $this->decision_renderer->render_growth_table(array('Locale', 'Registered 30d', 'Buyers 30d', 'Conversion Rate'), $language_conversion); ?>
            </div>

            <div class="analytics-section">
                <h2><?php _e('Funnel Drop-off', 'growtype-analytics'); ?></h2>
                <?php $this->decision_renderer->render_funnel_cards($funnel_30); ?>
                <?php $this->decision_renderer->render_growth_table(array('Stage', 'Users', 'Vs Previous', 'Vs First'), $funnel_30['rows']); ?>
            </div>

            <div class="analytics-section">
                <h2><?php _e('Offer / Pricing Tests', 'growtype-analytics'); ?></h2>
                <?php $this->decision_renderer->render_growth_table(array('Offer', 'Paid Orders', 'Failed Attempts', 'Success Rate', 'Revenue', 'Avg Revenue / Order'), $offer_rows); ?>
            </div>

            <div class="analytics-section">
                <h2><?php _e('Offer -> Repurchase Quality', 'growtype-analytics'); ?></h2>
                <p class="description"><?php _e('Shows which first-purchase offers create repeat buying behavior, not just first-order cash.', 'growtype-analytics'); ?></p>
                <?php $this->decision_renderer->render_growth_table(array('First Offer', 'Buyers', 'Repeat 30d', 'Repeat Rate 30d', 'Repeat Ever', 'Repeat Rate Ever', 'ARPPU'), $offer_repurchase_quality); ?>
            </div>

            <div class="analytics-section">
                <h2><?php _e('Top Characters By Revenue', 'growtype-analytics'); ?></h2>
                <p class="description"><?php _e('Identifies which characters deserve more traffic, promotion, and product iteration based on attributed paid revenue.', 'growtype-analytics'); ?></p>
                <?php $this->decision_renderer->render_growth_table(array('Character', 'Slug', 'Revenue 30d', 'Orders', 'Buyers', 'Revenue / Buyer'), $top_characters); ?>
            </div>

            <div class="analytics-section">
                <h2><?php _e('Buyer Cohorts', 'growtype-analytics'); ?></h2>
                <?php $this->decision_renderer->render_growth_table(array('Cohort', 'Buyers', 'Repeat in 30d', 'Repeat Rate 30d', 'Revenue', 'ARPPU'), $cohort_rows); ?>
            </div>

            <div class="analytics-section">
                <h2><?php _e('Growth Trends (30d)', 'growtype-analytics'); ?></h2>
                <p class="description"><?php _e('Daily registrations, paid orders, revenue, and day-level conversion. Use this to see whether fixes are improving the business over time.', 'growtype-analytics'); ?></p>
                <?php
                $trend_rows = array_map(function ($row) {
                    return array(
                        $row['date'],
                        $this->controller->format_number($row['registrations']),
                        $this->controller->format_number($row['paid_orders']),
                        $this->controller->format_money($row['revenue']),
                        $this->controller->format_percent($row['conversion_rate']),
                    );
                }, $growth_trends);
                $this->decision_renderer->render_growth_table(array('Date', 'Registrations', 'Paid Orders', 'Revenue', 'Conversion'), $trend_rows);
                ?>
            </div>

            <div class="analytics-section">
                <h2><?php _e('Contribution Margin', 'growtype-analytics'); ?></h2>
                <div class="analytics-scale-snapshot-grid">
                    <?php $this->decision_renderer->render_snapshot_card(__('Revenue 30d', 'growtype-analytics'), $this->controller->format_money($margin_metrics['revenue']), __('Paid order revenue', 'growtype-analytics')); ?>
                    <?php $this->decision_renderer->render_snapshot_card(__('Contribution Margin', 'growtype-analytics'), $this->controller->format_money($margin_metrics['contribution_margin']), __('Revenue minus estimated variable + fixed costs', 'growtype-analytics')); ?>
                    <?php $this->decision_renderer->render_snapshot_card(__('Margin %', 'growtype-analytics'), $this->controller->format_percent($margin_metrics['contribution_margin_percent']), __('Estimated contribution margin percentage', 'growtype-analytics')); ?>
                </div>
                <?php $this->decision_renderer->render_growth_table(array('Metric', 'Value'), $margin_rows); ?>
            </div>

            <div class="analytics-section">
                <h2><?php _e('Real Cost / Refund / Chargeback Metrics', 'growtype-analytics'); ?></h2>
                <p class="description"><?php _e('Blends actual refund data with configured payment, AI, media, rev-share, infra, and known chargeback inputs.', 'growtype-analytics'); ?></p>
                <div class="analytics-scale-snapshot-grid">
                    <?php $this->decision_renderer->render_snapshot_card(__('Refund Amount 30d', 'growtype-analytics'), $this->controller->format_money($real_cost_metrics['refund_amount']), __('Actual WooCommerce refund posts in the last 30 days', 'growtype-analytics')); ?>
                    <?php $this->decision_renderer->render_snapshot_card(__('Known Chargeback Amount 30d', 'growtype-analytics'), $this->controller->format_money($real_cost_metrics['known_chargeback_amount']), __('Configured from your payment processor data', 'growtype-analytics')); ?>
                    <?php $this->decision_renderer->render_snapshot_card(__('Net After Refunds & Chargebacks', 'growtype-analytics'), $this->controller->format_money($real_cost_metrics['net_after_refunds_chargebacks']), __('Contribution margin after refunds and known chargebacks', 'growtype-analytics')); ?>
                    <?php $this->decision_renderer->render_snapshot_card(__('Net Margin %', 'growtype-analytics'), $this->controller->format_percent($real_cost_metrics['net_margin_percent']), __('Net after refunds and chargebacks / revenue', 'growtype-analytics')); ?>
                    <?php $this->decision_renderer->render_snapshot_card(__('Refund Rate', 'growtype-analytics'), $this->controller->format_percent($refund_chargeback_rates['refund_order_rate']), __('Refunded orders / paid orders in the last 30 days', 'growtype-analytics')); ?>
                    <?php $this->decision_renderer->render_snapshot_card(__('Chargeback Rate', 'growtype-analytics'), $this->controller->format_percent($refund_chargeback_rates['chargeback_order_rate']), __('Known chargebacks / paid orders in the last 30 days', 'growtype-analytics')); ?>
                </div>
                <?php $this->decision_renderer->render_growth_table(array('Metric', 'Value'), $real_cost_rows); ?>
            </div>
        </div>
        </body>
        </html><?php
    }
}
