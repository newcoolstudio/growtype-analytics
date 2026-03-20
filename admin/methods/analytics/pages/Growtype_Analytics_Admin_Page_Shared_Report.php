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
        $cache_key = 'growtype_analytics_shared_report_v3_' . $token;
        
        if ($refresh) {
            delete_transient($cache_key);
        }

        $cached_payload = get_transient($cache_key);
        
        if ($cached_payload !== false) {
            return $cached_payload;
        }

        // Fetch all fragments. In the future, this can be called selectively via AJAX.
        $overview = $this->get_fragment('overview', $link, $refresh);
        $pd = !empty($overview['active_period_string']) ? $overview['active_period_string'] : (!empty($overview['active_period_days']) ? $overview['active_period_days'] . 'd' : '30d');

        $payload = array(
            'generated_at' => current_time('mysql'),
            'label' => !empty($link['label']) ? $link['label'] : __('Shared Business Report', 'growtype-analytics'),
            'period' => $pd,
            'overview' => $overview,
            'execution_kpis' => $this->get_fragment('execution_kpis', $link, $refresh),
            'payment_failure_segmentation' => $this->get_fragment('payment_failure_segmentation', $link, $refresh),
            'source_attribution' => $this->get_fragment('source_attribution', $link, $refresh),
            'traffic_funnel' => $this->get_fragment('traffic_funnel', $link, $refresh),
            'cac_by_source' => $this->get_fragment('cac_by_source', $link, $refresh),
            'retention_by_source' => $this->get_fragment('retention_by_source', $link, $refresh),
            'source_payback' => $this->get_fragment('source_payback', $link, $refresh),
            'language_conversion' => $this->get_fragment('language_conversion', $link, $refresh),
            'funnel_dropoff_30d' => $this->get_fragment('funnel_dropoff_30d', $link, $refresh),
            'offer_tests' => $this->get_fragment('offer_tests', $link, $refresh),
            'offer_repurchase_quality' => $this->get_fragment('offer_repurchase_quality', $link, $refresh),
            'top_characters_by_revenue' => $this->get_fragment('top_characters_by_revenue', $link, $refresh),
            'buyer_cohorts' => $this->get_fragment('buyer_cohorts', $link, $refresh),
            'growth_trends_30d' => $this->get_fragment('growth_trends_30d', $link, $refresh),
            'contribution_margin' => $this->get_fragment('contribution_margin', $link, $refresh),
            'real_cost_refund_chargeback' => $this->get_fragment('real_cost_refund_chargeback', $link, $refresh),
            'refund_chargeback_rates' => $this->get_fragment('refund_chargeback_rates', $link, $refresh),
        );

        set_transient($cache_key, $payload, GROWTYPE_ANALYTICS_CACHE_TIME);
        
        return $payload;
    }

    public function get_fragment($fragment, $link = array(), $refresh = false)
    {
        $token = !empty($link['token']) ? $link['token'] : 'default';
        $cache_key = 'growtype_analytics_shared_fragment_' . $fragment . '_' . $token;

        if ($refresh) {
            delete_transient($cache_key);
        }

        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $data = null;
        switch ($fragment) {
            case 'overview':
                $metrics = $this->controller->get_snapshot_metrics($refresh);
                $data = array(
                    'registered_users_total' => $metrics['registered_users_total'],
                    'new_users_7d' => $metrics['new_users_7d'],
                    'new_users' => $metrics['new_users'],
                    'activation_min_messages' => $metrics['activation_min_messages'],
                    'activation_window_days' => $metrics['activation_window_days'],
                    'activation_rate_7d' => $metrics['activation_rate_7d'],
                    'activation_rate' => $metrics['activation_rate'],
                    'buyers_total' => $metrics['buyers_total'],
                    'buyer_conversion_total' => $metrics['buyer_conversion_total'],
                    'new_user_to_buyer_conversion' => $metrics['new_user_to_buyer_conversion'],
                    'revenue' => $metrics['revenue'],
                    'payment_success_rate' => $metrics['payment_success_rate'],
                    'repurchase_rate_total' => $metrics['repurchase_rate_total'],
                    'arppu_total' => $metrics['arppu_total'],
                    'dau' => $metrics['dau'],
                    'wau' => $metrics['wau'],
                    'mau' => $metrics['mau'],
                    'stickiness_ratio' => $metrics['stickiness_ratio'],
                    'churn_inactivity_days' => $metrics['churn_inactivity_days'],
                    'recent_payer_window_days' => $metrics['recent_payer_window_days'],
                    'churn_risk_recent_payers' => $metrics['churn_risk_recent_payers'],
                    'aov' => $metrics['aov'],
                    'revenue_prev' => $metrics['revenue_prev'],
                    'revenue_growth_mom' => $metrics['revenue_growth_mom'],
                    'new_users_prev_7d' => $metrics['new_users_prev_7d'],
                    'new_users_growth_wow' => $metrics['new_users_growth_wow'],
                    'ltv_estimate' => $metrics['ltv_estimate'],
                    'arpu' => $metrics['arpu'],
                    'payer_churn_rate' => $metrics['payer_churn_rate'],
                    'user_churn_rate' => $metrics['user_churn_rate'],
                    'median_days_to_first_purchase' => $metrics['median_days_to_first_purchase'],
                    'cac_estimate' => $metrics['cac_estimate'],
                    'revenue_daily' => $metrics['revenue_daily'],
                    'active_period_days' => $metrics['active_period_days'] ?? 30,
                    'active_period_string' => $metrics['active_period_string'] ?? '',
                    'settings' => $metrics['settings'],
                );
                // Merge all metrics to ensure map has access to everything
                $data = array_merge($metrics, $data);
                break;

            case 'execution_kpis':
                $metrics = $this->controller->get_snapshot_metrics($refresh);
                $pd = $metrics['active_period_days'] ?? 30;
                $failure_segments = $this->controller->get_payment_failure_segments_data($metrics['settings'], $pd, 25);
                $data = array(
                    'payment_success_rate' => $metrics['payment_success_rate'],
                    'new_user_to_buyer_conversion' => $metrics['new_user_to_buyer_conversion'],
                    'new_user_to_buyer_conversion_daily' => $metrics['new_user_to_buyer_conversion_daily'],
                    'repurchase_rate_total' => $metrics['repurchase_rate_total'],
                    'unpaid_attempts' => $metrics['unpaid_attempts'],
                    'active_period_days' => $metrics['active_period_days'] ?? 30,
                    'registered_users_total' => $metrics['registered_users_total'],
                    'payment_failure_segments' => $failure_segments,
                    'settings' => $metrics['settings'],
                );
                // Merge all metrics to ensure map has access to everything
                $data = array_merge($metrics, $data);
                break;
            case 'payment_failure_segmentation':
                $metrics = $this->controller->get_snapshot_metrics($refresh);
                $pd = $metrics['active_period_days'] ?? 30;
                $failure_segments = $this->controller->get_payment_failure_segments_data($metrics['settings'], $pd, 25);
                $data = array(
                    'active_period_days' => $pd,
                    'payment_failure_segments' => $failure_segments,
                    'settings' => $metrics['settings'],
                );
                $data = array_merge($metrics, $data);
                break;

            case 'source_attribution':
                $metrics = $this->controller->get_snapshot_metrics($refresh);
                $pd = $metrics['active_period_days'] ?? 30;
                $data = $this->controller->get_source_attribution_rows($pd);
                break;

            case 'traffic_funnel':
                $metrics = $this->controller->get_snapshot_metrics($refresh);
                $pd = $metrics['active_period_days'] ?? 30;
                $data = $this->controller->get_traffic_funnel_data($pd);
                break;

            case 'cac_by_source':
                $metrics = $this->controller->get_snapshot_metrics($refresh);
                $pd = $metrics['active_period_days'] ?? 30;
                $data = $this->controller->get_cac_by_source_data($pd, 10);
                $data['active_period_days'] = $pd; // Pass to renderer
                break;

            case 'retention_by_source':
                $data = $this->controller->get_retention_by_source_data(10);
                break;

            case 'source_payback':
                $data = $this->controller->get_source_payback_data(30, 10);
                break;

            case 'language_conversion':
                $data = $this->controller->get_language_conversion_data(30, 10);
                break;

            case 'funnel_dropoff_30d':
                $funnel = $this->controller->funnel->get_funnel_dropoff_data();
                $data = $funnel['30d'];
                break;

            case 'offer_tests':
                $data = $this->controller->get_offer_test_rows(20);
                break;

            case 'offer_repurchase_quality':
                $data = $this->controller->get_offer_repurchase_quality_data(10);
                break;

            case 'top_characters_by_revenue':
                $data = $this->controller->get_top_characters_by_revenue_data(30, 10);
                break;

            case 'buyer_cohorts':
                $data = $this->controller->get_buyer_cohort_rows(6);
                break;

            case 'growth_trends_30d':
                $data = $this->controller->get_growth_trends_data(30);
                break;

            case 'contribution_margin':
                $data = $this->controller->get_contribution_margin_data();
                break;

            case 'real_cost_refund_chargeback':
                $data = $this->controller->get_real_cost_refund_chargeback_data(30);
                break;

            case 'refund_chargeback_rates':
                $data = $this->controller->get_refund_chargeback_rates_data(30);
                break;
        }

        if ($data !== null) {
            set_transient($cache_key, $data, GROWTYPE_ANALYTICS_CACHE_TIME);
        }

        return $data;
    }

    public function render_page($link = array(), $format = 'html')
    {
        $refresh = isset($_GET['refresh']) && $_GET['refresh'] === '1';
        $token = !empty($link['token']) ? $link['token'] : 'default';
        $cache_key_html = 'growtype_analytics_shared_report_html_v3_' . $token;

        if ($format === 'html' && !$refresh) {
            $cached_html = get_transient($cache_key_html);
            if ($cached_html !== false) {
                echo $cached_html;
                return;
            }
        }

        // If JSON, we still want the full payload for now to maintain compatibility
        if ($format === 'json') {
            return $this->get_payload($link, $refresh);
        }

        $title = !empty($link['label']) ? $link['label'] : __('Shared Business Report', 'growtype-analytics');
        $generated_at = current_time('mysql');

        // Render Skeleton
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
                .analytics-section { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 2px rgba(0,0,0,.04); min-height: 100px; position: relative; }
                .wp-list-table { width: 100%; border-collapse: collapse; background: #fff; }
                .wp-list-table th, .wp-list-table td { border: 1px solid #e5e7eb; padding: 10px; text-align: left; vertical-align: top; }
                .wp-list-table thead th { background: #f9fafb; }
                textarea[readonly] { width: 100%; min-height: 180px; }
                .shared-report-meta { margin-bottom: 24px; color: #6b7280; display: flex; justify-content: space-between; align-items: center; }
                .refresh-button { background: #764ba2; color: #fff; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 13px; }
                .refresh-button:hover { background: #667eea; }
                
                /* Loading styles */
                .section-loading { display: flex; align-items: center; justify-content: center; height: 100px; color: #9ca3af; font-weight: 500; }
                .section-loading:after { content: ""; width: 20px; height: 20px; border: 2px solid #e5e7eb; border-top-color: #764ba2; border-radius: 50%; animation: spin 1s linear infinite; margin-left: 10px; }
                @keyframes spin { to { transform: rotate(360deg); } }
                .fade-in { animation: fadeIn 0.5s ease-in; }
                @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
            </style>
        </head>
        <body>
        <div class="growtype-analytics-page">
            <div class="shared-report-meta">
                <div>
                    <h1><?php echo esc_html($title); ?></h1>
                    <span id="generated-at-text">
                        <?php printf(esc_html__('Read-only analytics report generated on %1$s.', 'growtype-analytics'), esc_html($generated_at)); ?>
                    </span>
                </div>
                <div>
                    <a href="<?php echo esc_url(add_query_arg('refresh', '1')); ?>" class="refresh-button"><?php _e('Refresh Data', 'growtype-analytics'); ?></a>
                </div>
            </div>

            <div id="section-overview" class="analytics-section">
                <h2><?php _e('Analytics Overview', 'growtype-analytics'); ?></h2>
                <p class="description"><?php _e('Core business snapshot across users, activation, conversion, revenue, and retention.', 'growtype-analytics'); ?></p>
                <div class="section-content"><div class="section-loading"><?php _e('Loading analysis...', 'growtype-analytics'); ?></div></div>
            </div>

            <div id="section-execution_kpis" class="analytics-section">
                <h2><?php _e('Execution KPIs', 'growtype-analytics'); ?></h2>
                <p class="description"><?php _e('Core business metrics for tracking short-term growth and payment health.', 'growtype-analytics'); ?></p>
                <div class="section-content"><div class="section-loading"><?php _e('Loading KPIs...', 'growtype-analytics'); ?></div></div>
            </div>

            <div id="section-payment_failure_segmentation" class="analytics-section">
                <h2><?php _e('Payment Failure Segmentation', 'growtype-analytics'); ?></h2>
                <p class="description"><?php _e('Breakdown of unsuccessful payment attempts by device, country, and gateway.', 'growtype-analytics'); ?></p>
                <div class="section-content"><div class="section-loading"><?php _e('Loading data...', 'growtype-analytics'); ?></div></div>
            </div>

            <div id="section-source_attribution" class="analytics-section">
                <h2><?php _e('Source Attribution', 'growtype-analytics'); ?></h2>
                <div class="section-content"><div class="section-loading"><?php _e('Loading sources...', 'growtype-analytics'); ?></div></div>
            </div>

            <div id="section-traffic_funnel" class="analytics-section">
                <h2><?php _e('Traffic Funnel', 'growtype-analytics'); ?></h2>
                <div class="section-content"><div class="section-loading"><?php _e('Loading funnel...', 'growtype-analytics'); ?></div></div>
            </div>

            <div id="section-cac_by_source" class="analytics-section">
                <h2><?php _e('CAC By Source', 'growtype-analytics'); ?></h2>
                <p class="description"><?php _e('Uses first-purchase acquisition source and your configured spend-by-source inputs from settings.', 'growtype-analytics'); ?></p>
                <div class="section-content"><div class="section-loading"><?php _e('Calculating CAC...', 'growtype-analytics'); ?></div></div>
            </div>

            <div id="section-retention_by_source" class="analytics-section">
                <h2><?php _e('Retention By Source', 'growtype-analytics'); ?></h2>
                <p class="description"><?php _e('Groups buyers by the source of their first paid order to show source quality, not just source volume.', 'growtype-analytics'); ?></p>
                <div class="section-content"><div class="section-loading"><?php _e('Analyzing retention...', 'growtype-analytics'); ?></div></div>
            </div>

            <div id="section-source_payback" class="analytics-section">
                <h2><?php _e('Source Payback', 'growtype-analytics'); ?></h2>
                <p class="description"><?php _e('Helps decide which sources deserve more budget by comparing spend, 30-day revenue, lifetime revenue, and estimated payback time.', 'growtype-analytics'); ?></p>
                <div class="section-content"><div class="section-loading"><?php _e('Calculating payback...', 'growtype-analytics'); ?></div></div>
            </div>

            <div id="section-language_conversion" class="analytics-section">
                <h2><?php _e('Language Conversion', 'growtype-analytics'); ?></h2>
                <p class="description"><?php _e('Shows which locales turn registration into payment best, so you know where localization and geo-targeting will matter most.', 'growtype-analytics'); ?></p>
                <div class="section-content"><div class="section-loading"><?php _e('Loading locales...', 'growtype-analytics'); ?></div></div>
            </div>

            <div id="section-funnel_dropoff_30d" class="analytics-section">
                <h2><?php _e('Funnel Drop-off', 'growtype-analytics'); ?></h2>
                <div class="section-content"><div class="section-loading"><?php _e('Loading drop-offs...', 'growtype-analytics'); ?></div></div>
            </div>

            <div id="section-offer_tests" class="analytics-section">
                <h2><?php _e('Offer / Pricing Tests', 'growtype-analytics'); ?></h2>
                <div class="section-content"><div class="section-loading"><?php _e('Loading offers...', 'growtype-analytics'); ?></div></div>
            </div>

            <div id="section-offer_repurchase_quality" class="analytics-section">
                <h2><?php _e('Offer -> Repurchase Quality', 'growtype-analytics'); ?></h2>
                <p class="description"><?php _e('Shows which first-purchase offers create repeat buying behavior, not just first-order cash.', 'growtype-analytics'); ?></p>
                <div class="section-content"><div class="section-loading"><?php _e('Analyzing quality...', 'growtype-analytics'); ?></div></div>
            </div>

            <div id="section-top_characters_by_revenue" class="analytics-section">
                <h2><?php _e('Top Characters By Revenue', 'growtype-analytics'); ?></h2>
                <p class="description"><?php _e('Identifies which characters deserve more traffic, promotion, and product iteration based on attributed paid revenue.', 'growtype-analytics'); ?></p>
                <div class="section-content"><div class="section-loading"><?php _e('Loading characters...', 'growtype-analytics'); ?></div></div>
            </div>

            <div id="section-buyer_cohorts" class="analytics-section">
                <h2><?php _e('Buyer Cohorts', 'growtype-analytics'); ?></h2>
                <div class="section-content"><div class="section-loading"><?php _e('Loading cohorts...', 'growtype-analytics'); ?></div></div>
            </div>

            <div id="section-growth_trends_30d" class="analytics-section">
                <h2><?php _e('Growth Trends (30d)', 'growtype-analytics'); ?></h2>
                <p class="description"><?php _e('Daily registrations, paid orders, revenue, and 7-day registration-cohort conversion.', 'growtype-analytics'); ?></p>
                <div class="section-content"><div class="section-loading"><?php _e('Loading trends...', 'growtype-analytics'); ?></div></div>
            </div>

            <div id="section-contribution_margin" class="analytics-section">
                <h2><?php _e('Contribution Margin', 'growtype-analytics'); ?></h2>
                <div class="section-content"><div class="section-loading"><?php _e('Calculating margin...', 'growtype-analytics'); ?></div></div>
            </div>

            <div id="section-real_cost_refund_chargeback" class="analytics-section">
                <h2><?php _e('Real Cost / Refund / Chargeback Metrics', 'growtype-analytics'); ?></h2>
                <div class="section-content"><div class="section-loading"><?php _e('Loading costs...', 'growtype-analytics'); ?></div></div>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const token = <?php echo json_encode($token); ?>;
                const refresh = <?php echo $refresh ? 'true' : 'false'; ?>;
                const baseUrl = <?php echo json_encode(get_rest_url(null, 'growtype-analytics/v1/shared-report/')); ?>;
                
                const sections = [
                    'overview', 'execution_kpis', 'payment_failure_segmentation', 'source_attribution', 'traffic_funnel', 
                    'cac_by_source', 'retention_by_source', 'source_payback', 'language_conversion',
                    'funnel_dropoff_30d', 'offer_tests', 'offer_repurchase_quality', 
                    'top_characters_by_revenue', 'buyer_cohorts', 'growth_trends_30d',
                    'contribution_margin', 'real_cost_refund_chargeback'
                ];

                sections.forEach(fragment => {
                    const url = `${baseUrl}${token}/fragment/${fragment}?content_format=html${refresh ? '&clear_cache=1' : ''}`;
                    fetch(url)
                        .then(response => response.json())
                        .then(data => {
                            const container = document.querySelector(`#section-${fragment} .section-content`);
                            if (container) {
                                container.innerHTML = `<div class="fade-in">${data.html}</div>`;
                            }
                        })
                        .catch(err => {
                            console.error(`Error loading ${fragment}:`, err);
                            const container = document.querySelector(`#section-${fragment} .section-content`);
                            if (container) {
                                container.innerHTML = `<div style="color: #ef4444; padding: 20px; font-weight: 500;">Error: Failed to load data.</div>`;
                            }
                        });
                });
            });
        </script>
        </body>
        </html><?php
        return;
    }

    public function render_fragment_html($fragment, $data)
    {
        if (empty($data)) {
            return '<div style="padding: 24px; color: #6b7280;">' . __('No data available for this section.', 'growtype-analytics') . '</div>';
        }

        ob_start();
        switch ($fragment) {
            case 'overview':
                $this->decision_renderer->render_analytics_snapshot($data);
                break;
            case 'execution_kpis':
                $this->decision_renderer->render_execution_kpis($data);
                break;
            case 'payment_failure_segmentation':
                $this->decision_renderer->render_payment_failure_segmentation($data);
                break;
            case 'source_attribution':
                $this->controller->table_renderer->render(array('Source Type', 'Source', 'Campaign', 'Paid Orders', 'Attempts', 'Success Rate', 'Revenue', 'AOV'), $data);
                break;
            case 'traffic_funnel':
                ?>
                <p class="description">
                    <?php echo esc_html($data['traffic_available'] ? __('Uses PostHog pageviews as the traffic entry stage, then follows registrations, activation, checkout attempts, and paid users.', 'growtype-analytics') : __('PostHog pageviews are not available, so this view starts from registrations instead of traffic.', 'growtype-analytics')); ?>
                </p>
                <?php
                $this->decision_renderer->render_funnel_cards($data);
                $this->controller->table_renderer->render(array('Stage', 'Users', 'Vs Previous', 'Vs First'), $data['rows']);
                break;
            case 'cac_by_source':
                $pd = !empty($data['active_period_days']) ? $data['active_period_days'] : 30;
                $this->controller->table_renderer->render(array('Source', "New Buyers {$pd}d", "Active Buyers {$pd}d", "Revenue {$pd}d", "Spend {$pd}d", 'CAC', 'ROAS'), $data);
                break;
            case 'retention_by_source':
                $this->controller->table_renderer->render(array('Source', 'Buyers', 'Repeat 30d', 'Repeat Rate 30d', 'Active 30d', 'Active Rate 30d', 'ARPPU'), $data);
                break;
            case 'source_payback':
                $this->controller->table_renderer->render(array('Source', 'New Buyers 30d', 'Revenue 30d', 'Revenue Total', '30d Revenue / New Buyer', 'Payback Estimate'), $data);
                break;
            case 'language_conversion':
                $this->controller->table_renderer->render(array('Locale', 'Registered 30d', 'Buyers 30d', 'Conversion Rate'), $data);
                break;
            case 'funnel_dropoff_30d':
                $this->decision_renderer->render_funnel_cards(array('rows' => $data));
                $this->controller->table_renderer->render(array('Stage', 'Users', 'Vs Previous', 'Vs First'), $data);
                break;
            case 'offer_tests':
                $this->controller->table_renderer->render(array('Offer', 'Paid Orders', 'Failed Attempts', 'Success Rate', 'Revenue', 'Avg Revenue / Order'), $data);
                break;
            case 'offer_repurchase_quality':
                $this->controller->table_renderer->render(array('First Offer', 'Buyers', 'Repeat 30d', 'Repeat Rate 30d', 'Repeat Ever', 'Repeat Rate Ever', 'ARPPU'), $data);
                break;
            case 'top_characters_by_revenue':
                $this->controller->table_renderer->render(array('Character', 'Slug', 'Revenue 30d', 'Orders', 'Buyers', 'Revenue / Buyer'), $data);
                break;
            case 'buyer_cohorts':
                $this->controller->table_renderer->render(array('Cohort', 'Buyers', 'Repeat in 30d', 'Repeat Rate 30d', 'Revenue', 'ARPPU'), $data);
                break;
            case 'growth_trends_30d':
                ?>
                <p class="description"><?php _e('Daily registrations, paid orders, revenue, and 7-day registration-cohort conversion. Use this to see whether fixes are improving the business over time without the misleading same-day order/register ratio.', 'growtype-analytics'); ?></p>
                <?php
                $trend_rows = array_map(function ($row) {
                    return array(
                        $row['date'],
                        $this->controller->format_number($row['registrations']),
                        $this->controller->format_number($row['paid_orders']),
                        $this->controller->format_number($row['buyers_within_window']),
                        $this->controller->format_money($row['revenue']),
                        $this->controller->format_percent($row['conversion_rate']),
                    );
                }, $data);
                $this->controller->table_renderer->render(array('Date', 'Registrations', 'Paid Orders', '7d Buyers', 'Revenue', '7d Cohort Conversion'), $trend_rows);
                break;
            case 'contribution_margin':
                ?>
                <div class="analytics-scale-snapshot-grid">
                    <?php $this->decision_renderer->render_snapshot_card(__('Revenue 30d', 'growtype-analytics'), $this->controller->format_money($data['metrics']['revenue']), __('Paid order revenue', 'growtype-analytics')); ?>
                    <?php $this->decision_renderer->render_snapshot_card(__('Contribution Margin', 'growtype-analytics'), $this->controller->format_money($data['metrics']['contribution_margin']), __('Revenue minus estimated variable + fixed costs', 'growtype-analytics')); ?>
                    <?php $this->decision_renderer->render_snapshot_card(__('Margin %', 'growtype-analytics'), $this->controller->format_percent($data['metrics']['contribution_margin_percent']), __('Estimated contribution margin percentage', 'growtype-analytics')); ?>
                </div>
                <?php $this->controller->table_renderer->render(array('Metric', 'Value'), $data['rows']); ?>
                <?php
                break;
            case 'real_cost_refund_chargeback':
                $metrics = $data['metrics'];
                $rows = $data['rows'];
                $rates = $this->get_fragment('refund_chargeback_rates', array(), false); // Small enough to fetch sync
                ?>
                <p class="description"><?php _e('Blends actual refund data with configured payment, AI, media, rev-share, infra, and known chargeback inputs.', 'growtype-analytics'); ?></p>
                <div class="analytics-scale-snapshot-grid">
                    <?php $this->decision_renderer->render_snapshot_card(__('Refund Amount 30d', 'growtype-analytics'), $this->controller->format_money($metrics['refund_amount']), __('Actual WooCommerce refund posts in the last 30 days', 'growtype-analytics')); ?>
                    <?php $this->decision_renderer->render_snapshot_card(__('Known Chargeback Amount 30d', 'growtype-analytics'), $this->controller->format_money($metrics['known_chargeback_amount']), __('Configured from your payment processor data', 'growtype-analytics')); ?>
                    <?php $this->decision_renderer->render_snapshot_card(__('Net After Refunds & Chargebacks', 'growtype-analytics'), $this->controller->format_money($metrics['net_after_refunds_chargebacks']), __('Contribution margin after refunds and known chargebacks', 'growtype-analytics')); ?>
                    <?php $this->decision_renderer->render_snapshot_card(__('Net Margin %', 'growtype-analytics'), $this->controller->format_percent($metrics['net_margin_percent']), __('Net after refunds and chargebacks / revenue', 'growtype-analytics')); ?>
                    <?php $this->decision_renderer->render_snapshot_card(__('Margin Confidence', 'growtype-analytics'), strtoupper($metrics['quality']['confidence']), __('Based on configured AI, media, rev-share, and infra inputs', 'growtype-analytics')); ?>
                    <?php if ($rates): ?>
                        <?php $this->decision_renderer->render_snapshot_card(__('Refund Rate', 'growtype-analytics'), $this->controller->format_percent($rates['refund_order_rate']), __('Refunded orders / paid orders in the last 30 days', 'growtype-analytics')); ?>
                        <?php $this->decision_renderer->render_snapshot_card(__('Chargeback Rate', 'growtype-analytics'), $this->controller->format_percent($rates['chargeback_order_rate']), __('Known chargebacks / paid orders in the last 30 days', 'growtype-analytics')); ?>
                    <?php endif; ?>
                </div>
                <?php $this->controller->table_renderer->render(array('Metric', 'Value'), $rows); ?>
                <?php
                break;
        }
        return ob_get_clean();
    }
}
