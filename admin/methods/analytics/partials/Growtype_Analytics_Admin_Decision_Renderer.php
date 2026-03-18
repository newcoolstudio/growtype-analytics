<?php

/**
 * Analytics decision snapshot renderer
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/admin/methods/analytics/partials
 */

class Growtype_Analytics_Admin_Decision_Renderer
{
    /**
     * @var Growtype_Analytics_Admin_Page
     */
    private $page;

    public function __construct($page)
    {
        $this->page = $page;
    }

    public function render_analytics_snapshot()
    {
        $metrics = $this->page->get_snapshot_metrics();
        $growth_sign_revenue = $metrics['revenue_growth_mom'] >= 0 ? '+' : '';
        $growth_sign_users = $metrics['new_users_growth_wow'] >= 0 ? '+' : '';
        $summary_lines = array(
            'Snapshot generated: ' . current_time('mysql'),
            '--- Growth ---',
            'Revenue Growth MoM: ' . $growth_sign_revenue . $this->page->format_percent($metrics['revenue_growth_mom']) . ' ($' . number_format_i18n($metrics['revenue_prev_30d'], 2) . ' → $' . number_format_i18n($metrics['revenue_30d'], 2) . ')',
            'New User Growth WoW: ' . $growth_sign_users . $this->page->format_percent($metrics['new_users_growth_wow']) . ' (' . $this->page->format_number($metrics['new_users_prev_7d']) . ' → ' . $this->page->format_number($metrics['new_users_7d']) . ')',
            'LTV Estimate: ' . $this->page->format_money($metrics['ltv_estimate']),
            'CAC Estimate: ' . $this->page->format_money($metrics['cac_estimate']) . ' (based on $' . number_format_i18n((float)$metrics['settings']['marketing_spend_30d'], 2) . ' spend / ' . $this->page->format_number($this->page->metrics->get_new_buyers_count(30, $metrics['settings'])) . ' new buyers)',
            'LTV:CAC Ratio: ' . ($metrics['cac_estimate'] > 0 ? round($metrics['ltv_estimate'] / $metrics['cac_estimate'], 1) . 'x' : 'N/A'),
            'ARPU: ' . $this->page->format_money($metrics['arpu']),
            '--- Users ---',
            'Registered users total: ' . $this->page->format_number($metrics['registered_users_total']),
            'New users last 7d: ' . $this->page->format_number($metrics['new_users_7d']),
            'New users last 30d: ' . $this->page->format_number($metrics['new_users_30d']),
            'Activation rate last 7d (>=' . $metrics['activation_min_messages'] . ' msgs within ' . $metrics['activation_window_days'] . 'd): ' . $this->page->format_percent($metrics['activation_rate_7d']),
            'Activation rate last 30d (>=' . $metrics['activation_min_messages'] . ' msgs within ' . $metrics['activation_window_days'] . 'd): ' . $this->page->format_percent($metrics['activation_rate_30d']),
            '--- Monetization ---',
            'Total buyers all time: ' . $this->page->format_number($metrics['buyers_total']),
            'Buyer conversion all time: ' . $this->page->format_percent($metrics['buyer_conversion_total']),
            'New user -> buyer conversion last 30d: ' . $this->page->format_percent($metrics['new_user_to_buyer_conversion_30d']),
            'Revenue last 30d: ' . $this->page->format_money($metrics['revenue_30d']),
            'Payment success rate last 30d: ' . $this->page->format_percent($metrics['payment_success_rate_30d']),
            'Repurchase rate all time: ' . $this->page->format_percent($metrics['repurchase_rate_total']),
            'ARPPU all time: ' . $this->page->format_money($metrics['arppu_total']),
            '--- Engagement ---',
            'DAU / WAU / MAU: ' . $this->page->format_number($metrics['dau']) . ' / ' . $this->page->format_number($metrics['wau']) . ' / ' . $this->page->format_number($metrics['mau']),
            'Stickiness (DAU/MAU): ' . $this->page->format_percent($metrics['stickiness_ratio']),
            'Recent payers at churn risk (' . $metrics['churn_inactivity_days'] . 'd inactive, paid in last ' . $metrics['recent_payer_window_days'] . 'd): ' . $this->page->format_number($metrics['churn_risk_recent_payers']),
            'AOV last 30d: ' . $this->page->format_money($metrics['aov_30d']),
            '--- Retention & Conversion Speed ---',
            'Payer Inactivity Rate: ' . $this->page->format_percent($metrics['payer_churn_rate']) . ' (recent payers inactive for ' . $metrics['churn_inactivity_days'] . '+ days)',
            'User Inactivity Rate: ' . $this->page->format_percent($metrics['user_churn_rate']) . ' (30d active users inactive for ' . $metrics['churn_inactivity_days'] . '+ days)',
            'Median Days to First Purchase: ' . $metrics['median_days_to_first_purchase'] . ' days',
        );
        ?>
        <h3><?php _e('Growth & Scale', 'growtype-analytics'); ?></h3>
        <div class="analytics-scale-snapshot-grid">
            <?php $this->render_snapshot_card(__('Revenue Growth MoM', 'growtype-analytics'), $growth_sign_revenue . $this->page->format_percent($metrics['revenue_growth_mom']), sprintf(__('$%s → $%s (prev 30d → current 30d)', 'growtype-analytics'), number_format_i18n($metrics['revenue_prev_30d'], 2), number_format_i18n($metrics['revenue_30d'], 2)), $metrics['revenue_growth_mom'] >= 0); ?>
            <?php $this->render_snapshot_card(__('User Growth WoW', 'growtype-analytics'), $growth_sign_users . $this->page->format_percent($metrics['new_users_growth_wow']), sprintf(__('%s → %s (prev 7d → current 7d)', 'growtype-analytics'), $this->page->format_number($metrics['new_users_prev_7d']), $this->page->format_number($metrics['new_users_7d'])), $metrics['new_users_growth_wow'] >= 0); ?>
            <?php $this->render_snapshot_card(__('LTV Estimate', 'growtype-analytics'), $this->page->format_money($metrics['ltv_estimate']), __('ARPPU × 1 / (1 − repurchase rate)', 'growtype-analytics')); ?>
            <?php $this->render_snapshot_card(__('CAC Estimate', 'growtype-analytics'), $this->page->format_money($metrics['cac_estimate']), sprintf(__('Marketing spend ($%s) / new buyers 30d', 'growtype-analytics'), number_format_i18n((float)$metrics['settings']['marketing_spend_30d'], 2))); ?>
            <?php $this->render_snapshot_card(__('ARPU', 'growtype-analytics'), $this->page->format_money($metrics['arpu']), __('Revenue 30d / all registered users', 'growtype-analytics')); ?>
        </div>
        <h3><?php _e('Core Metrics', 'growtype-analytics'); ?></h3>
        <div class="analytics-scale-snapshot-grid">
            <?php $this->render_snapshot_card(__('Registered Users', 'growtype-analytics'), $this->page->format_number($metrics['registered_users_total']), __('Public WP users after configured email exclusions', 'growtype-analytics')); ?>
            <?php $this->render_snapshot_card(__('New Users 30d', 'growtype-analytics'), $this->page->format_number($metrics['new_users_30d']), __('Last 30 days', 'growtype-analytics')); ?>
            <?php $this->render_snapshot_card(__('Activation 30d', 'growtype-analytics'), $this->page->format_percent($metrics['activation_rate_30d']), sprintf(__('New users reaching %1$d+ messages within %2$d day(s)', 'growtype-analytics'), $metrics['activation_min_messages'], $metrics['activation_window_days'])); ?>
            <?php $this->render_snapshot_card(__('Buyer Conv. Total', 'growtype-analytics'), $this->page->format_percent($metrics['buyer_conversion_total']), __('Unique buyers / registered users', 'growtype-analytics')); ?>
            <?php $this->render_snapshot_card(__('New User -> Buyer 30d', 'growtype-analytics'), $this->page->format_percent($metrics['new_user_to_buyer_conversion_30d']), __('Users registered in the last 30 days who paid', 'growtype-analytics')); ?>
            <?php $this->render_snapshot_card(__('Revenue 30d', 'growtype-analytics'), $this->page->format_money($metrics['revenue_30d']), __('Configured paid order statuses only', 'growtype-analytics')); ?>
            <?php $this->render_snapshot_card(__('Payment Success 30d', 'growtype-analytics'), $this->page->format_percent($metrics['payment_success_rate_30d']), __('Configured paid statuses / attempt statuses', 'growtype-analytics')); ?>
            <?php $this->render_snapshot_card(__('Repurchase Rate', 'growtype-analytics'), $this->page->format_percent($metrics['repurchase_rate_total']), __('Buyers with more than one paid order', 'growtype-analytics')); ?>
            <?php $this->render_snapshot_card(__('ARPPU', 'growtype-analytics'), $this->page->format_money($metrics['arppu_total']), __('Average revenue per paying user', 'growtype-analytics')); ?>
            <?php $this->render_snapshot_card(__('Stickiness', 'growtype-analytics'), $this->page->format_percent($metrics['stickiness_ratio']), __('DAU / MAU', 'growtype-analytics')); ?>
            <?php $this->render_snapshot_card(__('Churn Risk', 'growtype-analytics'), $this->page->format_number($metrics['churn_risk_recent_payers']), sprintf(__('Recent payers inactive for %1$d+ days', 'growtype-analytics'), $metrics['churn_inactivity_days'])); ?>
            <?php $this->render_snapshot_card(__('AOV 30d', 'growtype-analytics'), $this->page->format_money($metrics['aov_30d']), __('Average order value', 'growtype-analytics')); ?>
        </div>
        <h3><?php _e('Retention & Conversion Speed', 'growtype-analytics'); ?></h3>
        <div class="analytics-scale-snapshot-grid">
            <?php $this->render_snapshot_card(__('Payer Inactivity Rate', 'growtype-analytics'), $this->page->format_percent($metrics['payer_churn_rate']), sprintf(__('Recent payers inactive for %d+ days', 'growtype-analytics'), $metrics['churn_inactivity_days']), $metrics['payer_churn_rate'] <= 50); ?>
            <?php $this->render_snapshot_card(__('User Inactivity Rate', 'growtype-analytics'), $this->page->format_percent($metrics['user_churn_rate']), sprintf(__('30d active users inactive for %d+ days', 'growtype-analytics'), $metrics['churn_inactivity_days']), $metrics['user_churn_rate'] <= 50); ?>
            <?php $this->render_snapshot_card(__('Time to First Purchase', 'growtype-analytics'), $metrics['median_days_to_first_purchase'] . ' ' . __('days', 'growtype-analytics'), __('Median registration → first paid order', 'growtype-analytics')); ?>
        </div>
        <div class="analytics-snapshot-copy">
            <label for="growtype-analytics-overview-summary"><strong><?php _e('Copy/Paste Summary', 'growtype-analytics'); ?></strong></label>
            <textarea id="growtype-analytics-overview-summary" readonly><?php echo esc_textarea(implode("\n", $summary_lines)); ?></textarea>
        </div>
        <?php
    }

    public function render_execution_kpis()
    {
        $metrics = $this->page->get_snapshot_metrics();
        $targets = array(
            'payment_success_rate_30d' => 45.0,
            'new_user_to_buyer_conversion_30d' => 2.5,
            'repurchase_rate_total' => 25.0,
        );
        $failure_segments = $this->page->get_payment_failure_segments_data($metrics['settings'], 30, 25);

        $summary_lines = array(
            'Snapshot generated: ' . current_time('mysql'),
            'Targets: payment_success>=45.00%, new_user_to_buyer>=2.50%, repurchase>=25.00%',
            'Payment success rate last 30d: ' . $this->page->format_percent($metrics['payment_success_rate_30d']),
            'New user -> buyer conversion last 30d rolling: ' . $this->page->format_percent($metrics['new_user_to_buyer_conversion_30d']),
            'New user -> buyer conversion daily (last 24h): ' . $this->page->format_percent($metrics['new_user_to_buyer_conversion_daily']),
            'Repurchase rate all time: ' . $this->page->format_percent($metrics['repurchase_rate_total']),
            'Unpaid attempts last 30d: ' . $this->page->format_number($metrics['unpaid_attempts_30d']),
        );

        $payment_status = $metrics['payment_success_rate_30d'] >= $targets['payment_success_rate_30d'];
        $buyer_status = $metrics['new_user_to_buyer_conversion_30d'] >= $targets['new_user_to_buyer_conversion_30d'];
        $repurchase_status = $metrics['repurchase_rate_total'] >= $targets['repurchase_rate_total'];
        ?>
        <div class="analytics-scale-snapshot-grid">
            <?php $this->render_snapshot_card(__('Payment Success (30d)', 'growtype-analytics'), $this->page->format_percent($metrics['payment_success_rate_30d']), __('Target >= 45.00%', 'growtype-analytics'), $payment_status); ?>
            <?php $this->render_snapshot_card(__('New User -> Buyer (30d)', 'growtype-analytics'), $this->page->format_percent($metrics['new_user_to_buyer_conversion_30d']), __('Target >= 2.50%', 'growtype-analytics'), $buyer_status); ?>
            <?php $this->render_snapshot_card(__('Repurchase Rate', 'growtype-analytics'), $this->page->format_percent($metrics['repurchase_rate_total']), __('Target >= 25.00%', 'growtype-analytics'), $repurchase_status); ?>
        </div>

        <div class="analytics-snapshot-copy">
            <label for="growtype-scale-pivot-summary"><strong><?php _e('Copy/Paste Summary', 'growtype-analytics'); ?></strong></label>
            <textarea id="growtype-scale-pivot-summary" readonly><?php echo esc_textarea(implode("\n", $summary_lines)); ?></textarea>
        </div>

        <div class="analytics-recent-events" style="margin-top:16px;">
            <h3><?php _e('Payment Failure Segmentation (last 30 days)', 'growtype-analytics'); ?></h3>
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th><?php _e('Gateway', 'growtype-analytics'); ?></th>
                        <th><?php _e('Device', 'growtype-analytics'); ?></th>
                        <th><?php _e('Country', 'growtype-analytics'); ?></th>
                        <th><?php _e('Product Pack', 'growtype-analytics'); ?></th>
                        <th><?php _e('Attempts', 'growtype-analytics'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($failure_segments)): ?>
                    <tr><td colspan="5"><?php _e('No failed/pending/cancelled attempts found in the selected period.', 'growtype-analytics'); ?></td></tr>
                <?php else: ?>
                    <?php foreach ($failure_segments as $row): ?>
                        <tr>
                            <td><?php echo esc_html($row['gateway']); ?></td>
                            <td><?php echo esc_html($row['device']); ?></td>
                            <td><?php echo esc_html($row['country']); ?></td>
                            <td><?php echo esc_html($row['product_pack']); ?></td>
                            <td><?php echo esc_html($this->page->format_number($row['attempts'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function render_snapshot_card($title, $value, $description = '', $is_good = null)
    {
        $status_class = '';
        if ($is_good === true) {
            $status_class = ' analytics-snapshot-card--good';
        } elseif ($is_good === false) {
            $status_class = ' analytics-snapshot-card--bad';
        }
        ?>
        <div class="analytics-snapshot-card<?php echo esc_attr($status_class); ?>">
            <div class="analytics-snapshot-card__title"><?php echo esc_html($title); ?></div>
            <div class="analytics-snapshot-card__value"><?php echo esc_html($value); ?></div>
            <?php if (!empty($description)): ?>
                <div class="analytics-snapshot-card__description"><?php echo esc_html($description); ?></div>
            <?php endif; ?>
        </div>
        <?php
    }

    public function render_growth_table($headers, $rows)
    {
        ?>
        <div class="analytics-recent-events">
            <table class="wp-list-table widefat striped">
                <thead>
                <tr>
                    <?php foreach ($headers as $header): ?>
                        <th><?php echo esc_html($header); ?></th>
                    <?php endforeach; ?>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="<?php echo esc_attr(count($headers)); ?>"><?php _e('No data available for this view yet.', 'growtype-analytics'); ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php foreach ($row as $cell): ?>
                                <td><?php echo esc_html($cell); ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function render_funnel_cards($funnel)
    {
        ?>
        <div class="analytics-scale-snapshot-grid" style="margin-bottom:16px;">
            <?php foreach ($funnel['rows'] as $row): ?>
                <?php $this->render_snapshot_card($row['label'], $row['count'], 'Vs first: ' . $row['vs_first'] . ' | Vs previous: ' . $row['vs_previous']); ?>
            <?php endforeach; ?>
        </div>
        <?php
    }
}
