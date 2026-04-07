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

    public function render_dashboard_filters($date_from, $date_to, $objective, $marketing_spend, $page_name = 'growtype-analytics')
    {
        ?>
        <div class="analytics-filter-container" style="display: flex; justify-content: space-between; align-items: center; gap: 20px; margin-bottom: 20px; background: #fff; padding: 18px 24px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.04); border: 1px solid #f0f0f1;">
            <div class="analytics-filter-item">
                <?php $this->render_period_filter($date_from, $date_to, $page_name); ?>
            </div>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display: flex; align-items: center; gap: 20px;">
                <input type="hidden" name="action" value="growtype_analytics_update_quick_spend">
                <?php wp_nonce_field('growtype_analytics_quick_spend'); ?>

                <div class="analytics-filter-item" style="display: flex; align-items: center; gap: 10px;">
                    <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #646970; letter-spacing: 0.05em;"><?php _e('Objective', 'growtype-analytics'); ?></label>
                    <select name="growth_objective" style="height: 38px; border-radius: 8px; border: 1px solid #dcdcde; width: 110px; padding: 0 10px; font-weight: 600;">
                        <option value="10x" <?php selected($objective, '10x'); ?>>10x Goal</option>
                        <option value="100x" <?php selected($objective, '100x'); ?>>100x Goal</option>
                    </select>
                </div>

                <div class="analytics-filter-item" style="display: flex; align-items: center; gap: 10px;">
                    <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #646970; letter-spacing: 0.05em;"><?php _e('Budget ($)', 'growtype-analytics'); ?></label>
                    <input type="number" name="marketing_spend" value="<?php echo esc_attr($marketing_spend); ?>" step="0.01" style="height: 38px; border-radius: 8px; border: 1px solid #dcdcde; width: 100px; padding: 0 12px; font-weight: 600;"/>
                </div>

                <button type="submit" class="button button-primary" style="height: 38px; border-radius: 8px; font-weight: 600; padding: 0 20px;"><?php _e('Update Matrix', 'growtype-analytics'); ?></button>
            </form>
        </div>
        <?php
    }

    public function render_period_filter($date_from, $date_to, $page_name = 'growtype-analytics')
    {
        // Both active filters and available filters come from the single registry.
        $active_filters    = Growtype_Analytics_Admin_User_Filters::active_from_request();
        $available_filters = Growtype_Analytics_Admin_User_Filters::registry();
        ?>
        <?php /* ── Row 1: Period controls ── */ ?>
        <div class="analytics-filter-toolbar" style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
            <div style="display:flex; gap:6px;">
                <button type="button" class="button" onclick="setAnalyticsPeriod(1)"><?php _e('Last 24h', 'growtype-analytics'); ?></button>
                <button type="button" class="button" onclick="setAnalyticsPeriod(7)"><?php _e('Last 7d', 'growtype-analytics'); ?></button>
                <button type="button" class="button" onclick="setAnalyticsPeriod(30)"><?php _e('Last 30d', 'growtype-analytics'); ?></button>
            </div>

            <form id="analytics-period-form" method="GET" action="<?php echo esc_url(admin_url('admin.php')); ?>"
                  style="display:inline-flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <input type="hidden" name="page"    value="<?php echo esc_attr($page_name); ?>">
                <input type="hidden" name="refresh" value="1">
                <?php foreach ($active_filters as $af): ?>
                    <input type="hidden" name="user_filters[]" value="<?php echo esc_attr($af); ?>">
                <?php endforeach; ?>

                <span style="font-weight:500; font-size:13px; color:#50575e;"><?php _e('Or custom:', 'growtype-analytics'); ?></span>
                <input type="date" id="date_from" name="date_from" value="<?php echo esc_attr($date_from); ?>"
                       style="width:auto; height:32px; padding:0 8px; border-radius:6px; border:1px solid #dcdcde;">
                <span style="color:#8c8f94;">&rarr;</span>
                <input type="date" id="date_to" name="date_to" value="<?php echo esc_attr($date_to); ?>"
                       style="width:auto; height:32px; padding:0 8px; border-radius:6px; border:1px solid #dcdcde;">
                <button type="submit" class="button button-primary" style="display:inline-flex; align-items:center; gap:5px; height:32px;">
                    <span class="dashicons dashicons-image-rotate" style="font-size:15px; width:15px; height:15px;"></span>
                    <?php _e('Apply', 'growtype-analytics'); ?>
                </button>
            </form>
        </div>


        <?php /* ── Row 2: Filter pills (opt-in pages only) ── */ ?>
        <?php if (in_array($page_name, Growtype_Analytics_Admin_User_Filters::FILTER_PAGES, true) && !empty($available_filters)): ?>
        <form id="analytics-filters-form" method="GET" action="<?php echo esc_url(admin_url('admin.php')); ?>"
              style="margin-top:10px; padding-top:10px; border-top:1px solid #f0f0f1;"
              onchange="this.submit()">
            <input type="hidden" name="page"      value="<?php echo esc_attr($page_name); ?>">
            <input type="hidden" name="refresh"   value="1">
            <input type="hidden" name="date_from" value="<?php echo esc_attr($date_from); ?>">
            <input type="hidden" name="date_to"   value="<?php echo esc_attr($date_to); ?>">

            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                <span style="font-size:11px; font-weight:700; text-transform:uppercase; color:#646970; letter-spacing:0.06em; white-space:nowrap;">
                    <?php _e('Filter by', 'growtype-analytics'); ?>
                </span>

                <?php foreach ($available_filters as $key => $f):
                    $active = in_array($key, $active_filters, true);
                    $c      = $f['color'];
                ?>
                    <label style="
                            display:inline-flex; align-items:center; gap:5px;
                            background:<?php echo $active ? $c : 'transparent'; ?>;
                            color:<?php echo $active ? '#fff' : $c; ?>;
                            border:1.5px solid <?php echo $c; ?>;
                            border-radius:999px; padding:3px 13px;
                            font-size:12px; font-weight:600;
                            cursor:pointer; user-select:none; white-space:nowrap;
                            transition:background .12s, color .12s;
                        "
                        onmouseover="if(!this.querySelector('input').checked){ this.style.background='<?php echo $c; ?>22'; }"
                        onmouseout="if(!this.querySelector('input').checked){ this.style.background='transparent'; }"
                    >
                        <input type="checkbox" name="user_filters[]" value="<?php echo esc_attr($key); ?>"
                               <?php checked($active); ?>
                               style="position:absolute; opacity:0; width:0; height:0;">
                        <?php echo esc_html($f['icon'] . ' ' . $f['label']); ?>
                        <?php if ($active): ?><span style="margin-left:2px; opacity:.75; font-size:10px;">✕</span><?php endif; ?>
                    </label>
                <?php endforeach; ?>

                <?php if (!empty($active_filters)): ?>
                    <a href="<?php echo esc_url(add_query_arg([
                            'page'      => $page_name,
                            'date_from' => $date_from,
                            'date_to'   => $date_to,
                        ], admin_url('admin.php'))); ?>"
                       style="font-size:12px; color:#646970; text-decoration:none; opacity:.75; margin-left:4px;">
                        <?php _e('✕ Clear filters', 'growtype-analytics'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </form>
        <?php endif; ?>


        <script>
            function setAnalyticsPeriod(days) {
                const to = new Date(), from = new Date();
                from.setDate(to.getDate() - (days - 1));
                const offset = to.getTimezoneOffset() * 60000;
                document.getElementById('date_from').value = new Date(from - offset).toISOString().split('T')[0];
                document.getElementById('date_to').value   = new Date(to   - offset).toISOString().split('T')[0];
                document.getElementById('analytics-period-form').submit();
            }
            if (window.history.replaceState && window.location.search.indexOf('refresh=1') !== -1) {
                const url = new URL(window.location.href);
                url.searchParams.delete('refresh');
                window.history.replaceState({}, '', url.toString());
            }
        </script>
        <?php
    }


    public function render_analytics_snapshot($metrics = null)
    {
        if ($metrics === null) {
            $metrics = $this->page->get_snapshot_metrics();
        }
        $pd = $metrics['active_period_days'] ?? 30;
        $growth_sign_revenue = $metrics['revenue_growth_mom'] >= 0 ? '+' : '';
        $growth_sign_users = $metrics['new_users_growth_wow'] >= 0 ? '+' : '';
        $summary_lines = array (
            'Snapshot generated: ' . current_time('mysql'),
            '--- Growth ---',
            "Revenue Growth vs Prev Period: " . $growth_sign_revenue . $this->page->format_percent($metrics['revenue_growth_mom']) . ' ($' . number_format_i18n($metrics['revenue_prev'], 2) . ' → $' . number_format_i18n($metrics['revenue'], 2) . ')',
            'New User Growth WoW: ' . $growth_sign_users . $this->page->format_percent($metrics['new_users_growth_wow']) . ' (' . $this->page->format_number($metrics['new_users_prev_7d']) . ' → ' . $this->page->format_number($metrics['new_users_7d']) . ')',
            'LTV Estimate: ' . $this->page->format_money($metrics['ltv_estimate']),
            'CAC Estimate: ' . $this->page->format_money($metrics['cac_estimate']) . ' (based on $' . number_format_i18n((float)$metrics['settings']['marketing_spend_30d'], 2) . ' spend / ' . $this->page->format_number($this->page->metrics->get_new_buyers_count(30, $metrics['settings'])) . ' new buyers)',
            'LTV:CAC Ratio: ' . ($metrics['cac_estimate'] > 0 ? round($metrics['ltv_estimate'] / $metrics['cac_estimate'], 1) . 'x' : 'N/A'),
            "ARPU: " . $this->page->format_money($metrics['arpu']),
            '--- Users ---',
            'Registered users total: ' . $this->page->format_number($metrics['registered_users_total']),
            'New users last 7d: ' . $this->page->format_number($metrics['new_users_7d']),
            "New users last {$pd}d: " . $this->page->format_number($metrics['new_users']),
            'Activation rate last 7d (>=' . $metrics['activation_min_messages'] . ' msgs within ' . $metrics['activation_window_days'] . 'd): ' . $this->page->format_percent($metrics['activation_rate_7d']),
            "Activation rate last {$pd}d (>=" . $metrics['activation_min_messages'] . ' msgs within ' . $metrics['activation_window_days'] . 'd): ' . $this->page->format_percent($metrics['activation_rate']),
            '--- Monetization ---',
            'Total buyers all time: ' . $this->page->format_number($metrics['buyers_total']),
            'Buyer conversion all time: ' . $this->page->format_percent($metrics['buyer_conversion_total']),
            "New user -> buyer conversion last {$pd}d: " . $this->page->format_percent($metrics['new_user_to_buyer_conversion']),
            "Revenue last {$pd}d: " . $this->page->format_money($metrics['revenue']),
            "Payment success rate last {$pd}d: " . $this->page->format_percent($metrics['payment_success_rate']),
            'Repurchase rate all time: ' . $this->page->format_percent($metrics['repurchase_rate_total']),
            'ARPPU all time: ' . $this->page->format_money($metrics['arppu_total']),
            '--- Engagement ---',
            'DAU / WAU / MAU: ' . $this->page->format_number($metrics['dau']) . ' / ' . $this->page->format_number($metrics['wau']) . ' / ' . $this->page->format_number($metrics['mau']),
            'Stickiness (DAU/MAU): ' . $this->page->format_percent($metrics['stickiness_ratio']),
            'Recent payers at churn risk (' . $metrics['churn_inactivity_days'] . 'd inactive, paid in last ' . $metrics['recent_payer_window_days'] . 'd): ' . $this->page->format_number($metrics['churn_risk_recent_payers']),
            "AOV last {$pd}d: " . $this->page->format_money($metrics['aov']),
            '--- Retention & Conversion Speed ---',
            'Payer Inactivity Rate: ' . $this->page->format_percent($metrics['payer_churn_rate']) . ' (recent payers inactive for ' . $metrics['churn_inactivity_days'] . '+ days)',
            'User Inactivity Rate: ' . $this->page->format_percent($metrics['user_churn_rate']) . ' (30d active users inactive for ' . $metrics['churn_inactivity_days'] . '+ days)',
            'Median Days to First Purchase: ' . $metrics['median_days_to_first_purchase'] . ' days',
        );
        $pinned = get_option('growtype_analytics_pinned_kpis', $metrics['settings']['pinned_kpis'] ?? array ());
        $map = $this->get_kpi_meta_map($metrics, $pd);
        ?>
        <h3><?php _e('Growth & Scale', 'growtype-analytics'); ?></h3>
        <div class="analytics-scale-snapshot-grid">
            <?php
            $ids = array ('revenue_growth_mom', 'new_users_growth_wow', 'ltv_estimate', 'cac_estimate', 'ltv_cac_ratio', 'arpu');
            foreach ($ids as $id) {
                if (!isset($map[$id])) {
                    continue;
                }
                $m = $map[$id];
                $this->render_snapshot_card($m['title'], $m['value'], $m['desc'], $m['is_good'] ?? null, $m['tooltip'] ?? '', $id, in_array($id, $pinned));
            }
            ?>
        </div>
        <h3><?php _e('Core Metrics', 'growtype-analytics'); ?></h3>
        <div class="analytics-scale-snapshot-grid">
            <?php
            $ids = array ('registered_users_total', 'new_users', 'activation_rate', 'buyer_conversion_total', 'new_user_to_buyer_conversion', 'revenue', 'payment_success_rate', 'repurchase_rate_total', 'arppu_total', 'stickiness_ratio', 'churn_risk_recent_payers', 'aov');
            foreach ($ids as $id) {
                if (!isset($map[$id])) {
                    continue;
                }
                $m = $map[$id];
                $this->render_snapshot_card($m['title'], $m['value'], $m['desc'], $m['is_good'] ?? null, $m['tooltip'] ?? '', $id, in_array($id, $pinned));
            }
            ?>
        </div>
        <h3><?php _e('Retention & Conversion Speed', 'growtype-analytics'); ?></h3>
        <div class="analytics-scale-snapshot-grid">
            <?php
            $ids = array ('payer_churn_rate', 'user_churn_rate', 'median_days_to_first_purchase');
            foreach ($ids as $id) {
                if (!isset($map[$id])) {
                    continue;
                }
                $m = $map[$id];
                $this->render_snapshot_card($m['title'], $m['value'], $m['desc'], $m['is_good'] ?? null, $m['tooltip'] ?? '', $id, in_array($id, $pinned));
            }
            ?>
        </div>
        <div class="analytics-snapshot-copy" style="display:none;">
            <label for="growtype-analytics-overview-summary"><strong><?php _e('Copy/Paste Summary', 'growtype-analytics'); ?></strong></label>
            <textarea id="growtype-analytics-overview-summary" readonly><?php echo esc_textarea(implode("\n", $summary_lines)); ?></textarea>
        </div>
        <?php
    }

    public function render_execution_kpis($data = null)
    {
        if ($data === null) {
            $metrics = $this->page->get_snapshot_metrics();
            $pd = $metrics['active_period_days'] ?? 30;
            $failure_segments = $this->page->get_payment_failure_segments_data($metrics['settings'], $pd, 25);
        } else {
            $metrics = $data;
            $failure_segments = $data['payment_failure_segments'];
        }
        $targets = array (
            'payment_success_rate' => 45.0,
            'new_user_to_buyer_conversion' => 2.5,
            'repurchase_rate_total' => 25.0,
            'user_try_to_buy_rate' => 10.0,
        );

        $pd = $metrics['active_period_days'] ?? 30;

        $summary_lines = array (
            'Snapshot generated: ' . current_time('mysql'),
            'Targets: payment_success>=45.00%, new_user_to_buyer>=2.50%, repurchase>=25.00%',
            "Payment success rate last {$pd}d: " . $this->page->format_percent($metrics['payment_success_rate']),
            "New user -> buyer conversion last {$pd}d rolling: " . $this->page->format_percent($metrics['new_user_to_buyer_conversion']),
            'New user -> buyer conversion daily (last 24h): ' . $this->page->format_percent($metrics['new_user_to_buyer_conversion_daily']),
            "User -> Attempt rate ({$pd}d from registered): " . $this->page->format_percent($metrics['user_try_to_buy_rate'] ?? 0),
            'Repurchase rate all time: ' . $this->page->format_percent($metrics['repurchase_rate_total']),
            "Unpaid attempts last {$pd}d: " . $this->page->format_number($metrics['unpaid_attempts']),
        );

        $payment_status = $metrics['payment_success_rate'] >= $targets['payment_success_rate'];
        $buyer_status = $metrics['new_user_to_buyer_conversion'] >= $targets['new_user_to_buyer_conversion'];
        $repurchase_status = $metrics['repurchase_rate_total'] >= $targets['repurchase_rate_total'];
        $user_try_rate = $metrics['user_try_to_buy_rate'] ?? 0;
        $pinned = get_option('growtype_analytics_pinned_kpis', $metrics['settings']['pinned_kpis'] ?? array ());
        $map = $this->get_kpi_meta_map($metrics, $pd);
        ?>
        <div class="analytics-scale-snapshot-grid">
            <?php
            foreach ($pinned as $id) {
                if (!isset($map[$id])) {
                    continue;
                }
                $m = $map[$id];
                $this->render_snapshot_card($m['title'], $m['value'], $m['desc'], $m['is_good'] ?? null, $m['tooltip'] ?? '', $id, true);
            }
            ?>
        </div>

        <div class="analytics-snapshot-copy" style="display:none;">
            <label for="growtype-scale-pivot-summary"><strong><?php _e('Copy/Paste Summary', 'growtype-analytics'); ?></strong></label>
            <textarea id="growtype-scale-pivot-summary" readonly><?php echo esc_textarea(implode("\n", $summary_lines)); ?></textarea>
        </div>
        <?php
    }

    public function render_payment_failure_segmentation($data = null)
    {
        if ($data === null) {
            $metrics = $this->page->get_snapshot_metrics();
            $pd = $metrics['active_period_days'] ?? 30;
            $failure_segments = $this->page->get_payment_failure_segments_data($metrics['settings'], $pd, 25);
        } else {
            $metrics = $data;
            $failure_segments = $data['payment_failure_segments'];
        }

        $pd = $metrics['active_period_days'] ?? 30;
        ?>
        <div class="analytics-recent-events" style="margin-top:16px;">
            <h3><?php printf(__('Payment Failure Segmentation (%sd)', 'growtype-analytics'), $pd); ?></h3>
            <?php
            $headers = array (
                __('Gateway', 'growtype-analytics'),
                __('Device', 'growtype-analytics'),
                __('Country', 'growtype-analytics'),
                __('Product Pack', 'growtype-analytics'),
                __('Attempts', 'growtype-analytics')
            );

            $rows = array ();
            foreach ($failure_segments as $row) {
                $rows[] = array (
                    esc_html($row['gateway']),
                    esc_html($row['device']),
                    esc_html($row['country']),
                    esc_html($row['product_pack']),
                    esc_html($this->page->format_number($row['attempts']))
                );
            }

            $this->page->table_renderer->render($headers, $rows);
            ?>
        </div>
        <?php
    }

    public function render_registered_users_table($date_from, $date_to)
    {
        $days = $this->page->metrics->get_period_days_count($date_from . ' - ' . $date_to);
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 10;
        $active_filters = Growtype_Analytics_Admin_User_Filters::active_from_request();

        $results = $this->page->get_registered_users_list_data($days, $paged, $per_page, $active_filters);
        $users = $results['items'];
        $total_items = $results['total_items'];

        $bulk_nonce = wp_create_nonce('growtype_analytics_bulk_actions');
        ?>
        <div class="analytics-section" style="margin-top:24px;">
            <h2><?php _e('Registered Users', 'growtype-analytics'); ?></h2>
            <p class="description">
                <?php printf(__('Total registered users found for this period: %s', 'growtype-analytics'), number_format_i18n($total_items)); ?>
                <?php
                $registry = Growtype_Analytics_Admin_User_Filters::registry();
                foreach ($active_filters as $f) {
                    $label = isset($registry[$f]) ? ($registry[$f]['icon'] . ' ' . $registry[$f]['label']) : ucwords(str_replace('_', ' ', $f));
                    echo '<span style="display:inline-block; background:#fff3cd; color:#856404; border-radius:4px; padding:1px 8px; font-size:0.85em; font-weight:600; margin-left:6px;">' . esc_html($label) . ' Active</span>';
                }
                ?>
            </p>

            <?php /* ── Bulk action bar ── */ ?>
            <div id="ga-bulk-bar" style="
                display:none;
                align-items:center; gap:10px; flex-wrap:wrap;
                background:#f0f6fc; border:1px solid #c8d7e8;
                border-radius:8px; padding:10px 16px; margin-bottom:12px;
            ">
                <span id="ga-bulk-count" style="font-weight:600; font-size:13px; color:#2271b1;"></span>
                
                <select id="ga-bulk-action-select" style="height:30px; line-height:1; padding:0 10px; border-radius:4px;">
                    <option value="none"><?php _e('Bulk Actions', 'growtype-analytics'); ?></option>
                    <option value="export_conversations"><?php _e('Export Conversations', 'growtype-analytics'); ?></option>
                </select>

                <button type="button" id="ga-bulk-submit-btn" class="button button-secondary">
                    <?php _e('Apply', 'growtype-analytics'); ?>
                </button>
                
                <span id="ga-bulk-status" style="font-size:12px; color:#646970;"></span>
            </div>

            <?php /* ── Table ── */ ?>
            <div class="analytics-recent-events">
                <table class="wp-list-table widefat striped">
                    <thead>
                    <tr>
                        <th style="width:32px;">
                            <input type="checkbox" id="ga-select-all" title="<?php esc_attr_e('Select all on this page', 'growtype-analytics'); ?>">
                        </th>
                        <th><?php _e('ID', 'growtype-analytics'); ?></th>
                        <th><?php _e('Email', 'growtype-analytics'); ?></th>
                        <th><?php _e('Registered', 'growtype-analytics'); ?></th>
                        <th><?php _e('Paid Orders', 'growtype-analytics'); ?></th>
                        <th><?php _e('Messages', 'growtype-analytics'); ?></th>
                        <th><?php _e('Regular Chat Visits', 'growtype-analytics'); ?></th>
                        <th><?php _e('Roleplay Chat Visits', 'growtype-analytics'); ?></th>
                        <th><?php _e('Roleplays Created', 'growtype-analytics'); ?></th>
                        <th><?php _e('Quiz Solved', 'growtype-analytics'); ?></th>
                        <th><?php _e('Offer Shown', 'growtype-analytics'); ?></th>
                        <th><?php _e('Checkout Page', 'growtype-analytics'); ?></th>
                        <th><?php _e('Credits Page', 'growtype-analytics'); ?></th>
                        <th><?php _e('Subscription Modal Shown', 'growtype-analytics'); ?></th>
                        <th><?php _e('Character Profile Visits', 'growtype-analytics'); ?></th>
                        <th><?php _e('Roleplay Profile Visits', 'growtype-analytics'); ?></th>
                        <th><?php _e('Chat Credits', 'growtype-analytics'); ?></th>
                        <th><?php _e('Actions', 'growtype-analytics'); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="18"><?php _e('No data available for this view yet.', 'growtype-analytics'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user):
                            $analytics_url = add_query_arg(
                                ['page' => 'user-analytics', 'user_id' => $user['ID']],
                                admin_url('users.php')
                            );
                            $profile_url = add_query_arg(
                                ['user_id' => $user['ID']],
                                admin_url('user-edit.php')
                            );
                        ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="ga-user-checkbox" value="<?php echo esc_attr($user['ID']); ?>">
                                </td>
                                <td><?php echo esc_html($user['ID']); ?></td>
                                <td><?php echo esc_html($user['user_email']); ?></td>
                                <td><?php echo esc_html(wp_date(get_option('date_format') . ' H:i', strtotime($user['user_registered']))); ?></td>
                                <td><?php echo esc_html($user['paid_orders']); ?></td>
                                <td><?php echo esc_html($user['message_count']); ?></td>
                                <td><?php echo (int)($user['regular_chat_visits'] ?? 0); ?></td>
                                <td><?php echo (int)($user['roleplay_chat_visits'] ?? 0); ?></td>
                                <td><?php echo (int)($user['roleplay_visited'] ?? 0); ?></td>
                                <td><?php echo (int)($user['quizzes_solved'] ?? 0); ?></td>
                                <td><?php echo (int)($user['payment_form_shown'] ?? 0); ?></td>
                                <td><?php echo (int)($user['checkout_visited'] ?? 0); ?></td>
                                <td><?php echo (int)($user['credits_page_visited'] ?? 0); ?></td>
                                <td><?php echo (int)($user['subscription_modal_shown'] ?? 0); ?></td>
                                <td><?php echo (int)($user['character_profile_visits'] ?? 0); ?></td>
                                <td><?php echo (int)($user['roleplay_profile_visits'] ?? 0); ?></td>
                                <td><?php echo (int)($user['chat_credits_amount'] ?? 0); ?></td>
                                <td>
                                    <a href="<?php echo esc_url($analytics_url); ?>" class="button button-small" target="_blank"><?php _e('View Analytics', 'growtype-analytics'); ?></a>
                                    <a href="<?php echo esc_url($profile_url); ?>" class="button button-small" target="_blank"><?php _e('Profile', 'growtype-analytics'); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>

                <?php /* Pagination */ ?>
                <?php
                $total_pages = ceil($total_items / $per_page);
                if ($total_pages > 1):
                    $base_url = $_SERVER['REQUEST_URI'] ?? admin_url('admin.php');
                    $base = add_query_arg('paged', '%#%', remove_query_arg(['paged', 'refresh'], $base_url));
                ?>
                    <div class="tablenav bottom">
                        <div class="tablenav-pages">
                            <span class="displaying-num"><?php printf(_n('%s item', '%s items', $total_items, 'growtype-analytics'), number_format_i18n($total_items)); ?></span>
                            <span class="pagination-links">
                                <?php echo paginate_links([
                                    'base'      => $base,
                                    'format'    => '',
                                    'prev_text' => __('&laquo;'),
                                    'next_text' => __('&raquo;'),
                                    'total'     => $total_pages,
                                    'current'   => $paged,
                                ]); ?>
                            </span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <script>
        (function($) {
            const nonce   = '<?php echo esc_js($bulk_nonce); ?>';
            const ajaxUrl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';

            // ── Checkbox logic ──────────────────────────────────────────────
            function getChecked() {
                return $('.ga-user-checkbox:checked').map(function() { return $(this).val(); }).get();
            }

            function updateBulkBar() {
                const ids = getChecked();
                if (ids.length > 0) {
                    $('#ga-bulk-bar').css('display', 'flex');
                    $('#ga-bulk-count').text(ids.length + ' user' + (ids.length > 1 ? 's' : '') + ' selected');
                } else {
                    $('#ga-bulk-bar').hide();
                }
            }

            $('#ga-select-all').on('change', function() {
                $('.ga-user-checkbox').prop('checked', this.checked);
                updateBulkBar();
            });

            $(document).on('change', '.ga-user-checkbox', function() {
                if (!this.checked) { $('#ga-select-all').prop('checked', false); }
                updateBulkBar();
            });

            // ── Bulk Actions ────────────────────────────────────────────────
            $('#ga-bulk-submit-btn').on('click', function() {
                const action = $('#ga-bulk-action-select').val();
                const ids = getChecked();
                
                if (action === 'none') {
                    alert('Please select an action.');
                    return;
                }
                
                if (!ids.length) {
                    alert('Please select at least one user.');
                    return;
                }

                if (action === 'export_conversations') {
                    executeExportConversations(ids);
                }
            });

            function executeExportConversations(ids) {
                const $btn    = $('#ga-bulk-submit-btn');
                const $status = $('#ga-bulk-status');

                $btn.prop('disabled', true).text('Working…');
                $status.text('Fetching data from server…');

                $.post(ajaxUrl, {
                    action   : 'growtype_analytics_bulk_export_conversations',
                    nonce    : nonce,
                    user_ids : ids,
                }, function(response) {
                    $btn.prop('disabled', false).text('Apply');

                    if (!response.success) {
                        $status.text('Error: ' + (response.data || 'Unknown error'));
                        return;
                    }

                    const data   = response.data;
                    const blob   = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
                    const url    = URL.createObjectURL(blob);
                    const anchor = document.createElement('a');
                    const ts     = new Date().toISOString().slice(0, 10);

                    anchor.href     = url;
                    anchor.download = 'conversations-export-' + ts + '.json';
                    document.body.appendChild(anchor);
                    anchor.click();
                    document.body.removeChild(anchor);
                    URL.revokeObjectURL(url);

                    $status.text(
                        '✓ Exported ' + data.user_count + ' user(s) · ' +
                        data.session_count + ' session(s) · ' + data.exported_at
                    );
                }).fail(function() {
                    $btn.prop('disabled', false).text('Apply');
                    $status.text('Request failed. Check permissions or try again.');
                });
            }
        })(jQuery);
        </script>
        <?php
    }

    private function get_kpi_meta_map($metrics, $pd)
    {
        $growth_sign_revenue = ($metrics['revenue_growth_mom'] ?? 0) >= 0 ? '+' : '';
        $growth_sign_users = ($metrics['new_users_growth_wow'] ?? 0) >= 0 ? '+' : '';

        // Define dynamic targets based on growth objective (10x vs 100x)
        $objective = get_option('growtype_analytics_growth_objective', '10x');
        $is_100x = ($objective === '100x');

        $targets = array (
            'payment_success_rate' => $is_100x ? 65.0 : 45.0,
            'new_user_to_buyer_conversion' => $is_100x ? 5.0 : 2.5,
            'repurchase_rate_total' => $is_100x ? 40.0 : 25.0,
            'user_try_to_buy_rate' => $is_100x ? 15.0 : 10.0,
            'ltv_cac_ratio' => $is_100x ? 5.0 : 3.0,
            'stickiness_ratio' => $is_100x ? 35.0 : 20.0,
        );

        $payment_status = ($metrics['payment_success_rate'] ?? 0) >= $targets['payment_success_rate'];
        $buyer_status = ($metrics['new_user_to_buyer_conversion'] ?? 0) >= $targets['new_user_to_buyer_conversion'];
        $repurchase_status = ($metrics['repurchase_rate_total'] ?? 0) >= $targets['repurchase_rate_total'];
        $user_try_rate = (float)($metrics['user_try_to_buy_rate'] ?? 0);

        $map = array (
            'revenue_growth_mom' => array (
                'title' => sprintf(__('Revenue Growth (%sd vs Prev)', 'growtype-analytics'), $pd),
                'value' => $growth_sign_revenue . $this->page->format_percent($metrics['revenue_growth_mom']),
                'desc' => sprintf(__('$%s → $%s (prev vs current)', 'growtype-analytics'), number_format_i18n($metrics['revenue_prev'] ?? 0, 2), number_format_i18n($metrics['revenue'] ?? 0, 2)),
                'is_good' => ($metrics['revenue_growth_mom'] ?? 0) >= 0,
                'tooltip' => __('Growth of total revenue in the current period compared to the previous period of the same length.', 'growtype-analytics')
            ),
            'new_users_growth_wow' => array (
                'title' => __('User Growth WoW', 'growtype-analytics'),
                'value' => $growth_sign_users . $this->page->format_percent($metrics['new_users_growth_wow']),
                'desc' => sprintf(__('%s → %s (prev 7d → current 7d)', 'growtype-analytics'), $this->page->format_number($metrics['new_users_prev_7d'] ?? 0), $this->page->format_number($metrics['new_users_7d'] ?? 0)),
                'is_good' => ($metrics['new_users_growth_wow'] ?? 0) >= 0,
                'tooltip' => __('Growth of new user registrations in the last 7 days compared to the 7 days before that.', 'growtype-analytics')
            ),
            'ltv_estimate' => array (
                'title' => __('LTV Estimate', 'growtype-analytics'),
                'value' => $this->page->format_money($metrics['ltv_estimate'] ?? 0),
                'desc' => __('ARPPU × 1 / (1 − repurchase rate)', 'growtype-analytics'),
                'tooltip' => __('Estimated Lifetime Value of a paying customer based on current Average Revenue Per Paying User and Repurchase Rate.', 'growtype-analytics')
            ),
            'cac_estimate' => array (
                'title' => __('CAC Estimate', 'growtype-analytics'),
                'value' => $this->page->format_money($metrics['cac_estimate'] ?? 0),
                'desc' => sprintf(__('Marketing spend ($%s) / new buyers', 'growtype-analytics'), number_format_i18n((float)($metrics['settings']['marketing_spend_30d'] ?? 0), 2)),
                'tooltip' => __('Estimated Customer Acquisition Cost based on your monthly marketing spend and the number of new buyers in the last 30 days.', 'growtype-analytics')
            ),
            'ltv_cac_ratio' => array (
                'title' => __('LTV / CAC Ratio', 'growtype-analytics'),
                'value' => ($metrics['ltv_cac_ratio'] ?? 0) > 0 ? ($metrics['ltv_cac_ratio'] . 'x') : 'N/A',
                'desc' => sprintf(__('Goal: > %s', 'growtype-analytics'), $targets['ltv_cac_ratio'] . '.0x'),
                'is_good' => ($metrics['ltv_cac_ratio'] ?? 0) >= $targets['ltv_cac_ratio'],
                'tooltip' => __('The return on investment for acquiring a new customer. A ratio over 3.0x (or 5.0x for 100x growth) means you can scale profitably.', 'growtype-analytics')
            ),
            'stickiness_ratio' => array (
                'title' => __('Stickiness (DAU/MAU)', 'growtype-analytics'),
                'value' => $this->page->format_percent($metrics['stickiness_ratio'] ?? 0),
                'desc' => sprintf(__('Goal: > %s', 'growtype-analytics'), $targets['stickiness_ratio'] . '%'),
                'is_good' => ($metrics['stickiness_ratio'] ?? 0) >= $targets['stickiness_ratio'],
                'tooltip' => __('Measure of how often users return to the app daily. A higher percentage indicates more habitual engagement.', 'growtype-analytics')
            ),
            'arpu' => array (
                'title' => __('ARPU', 'growtype-analytics'),
                'value' => $this->page->format_money($metrics['arpu'] ?? 0),
                'desc' => sprintf(__('Revenue %sd / all registered users', 'growtype-analytics'), $pd),
                'tooltip' => __('Average Revenue Per User. Total revenue in the selected period divided by the total number of registered users.', 'growtype-analytics')
            ),
            'registered_users_total' => array (
                'title' => __('Registered Users', 'growtype-analytics'),
                'value' => $this->page->format_number($metrics['registered_users_total'] ?? 0),
                'desc' => __('Public WP users after configured email exclusions', 'growtype-analytics'),
                'tooltip' => __('Total number of registered users, excluding those with filtered email domains.', 'growtype-analytics')
            ),
            'new_users' => array (
                'title' => sprintf(__('New Users %sd', 'growtype-analytics'), $pd),
                'value' => $this->page->format_number($metrics['new_users'] ?? 0),
                'desc' => sprintf(__('Last %s days', 'growtype-analytics'), $pd),
                'tooltip' => __('Total number of new user registrations during the selected period.', 'growtype-analytics')
            ),
            'activation_rate' => array (
                'title' => sprintf(__('Activation %sd', 'growtype-analytics'), $pd),
                'value' => $this->page->format_percent($metrics['activation_rate'] ?? 0),
                'desc' => sprintf(__('New users reaching %1$d+ messages within %2$d day(s)', 'growtype-analytics'), $metrics['activation_min_messages'] ?? 0, $metrics['activation_window_days'] ?? 0),
                'tooltip' => __('Percentage of new users who achieved a minimum level of engagement (messages sent) within their first days.', 'growtype-analytics')
            ),
            'buyer_conversion_total' => array (
                'title' => __('Buyer Conv. Total', 'growtype-analytics'),
                'value' => $this->page->format_percent($metrics['buyer_conversion_total'] ?? 0),
                'desc' => __('Unique buyers / registered users', 'growtype-analytics')
            ),
            'new_user_to_buyer_conversion' => array (
                'title' => sprintf(__('New User -> Buyer (%sd)', 'growtype-analytics'), $pd),
                'value' => $this->page->format_percent($metrics['new_user_to_buyer_conversion'] ?? 0),
                'desc' => sprintf(__('Goal: > %s (current vs prev registration)', 'growtype-analytics'), $targets['new_user_to_buyer_conversion'] . '%'),
                'is_good' => $buyer_status,
                'tooltip' => __('Percentage of users who registered in the selected period and made at least one successful payment.', 'growtype-analytics')
            ),
            'revenue' => array (
                'title' => sprintf(__('Revenue %sd', 'growtype-analytics'), $pd),
                'value' => $this->page->format_money($metrics['revenue'] ?? 0),
                'desc' => __('Configured paid order statuses only', 'growtype-analytics')
            ),
            'payment_success_rate' => array (
                'title' => sprintf(__('Payment Success (%sd)', 'growtype-analytics'), $pd),
                'value' => $this->page->format_percent($metrics['payment_success_rate'] ?? 0),
                'desc' => sprintf(__('Goal: > %s', 'growtype-analytics'), $targets['payment_success_rate'] . '%'),
                'is_good' => $payment_status,
                'tooltip' => __('Ratio of successful payments to total payment attempts initiated during the selected period.', 'growtype-analytics')
            ),
            'repurchase_rate_total' => array (
                'title' => __('Repurchase Rate', 'growtype-analytics'),
                'value' => $this->page->format_percent($metrics['repurchase_rate_total'] ?? 0),
                'desc' => sprintf(__('Goal: > %s', 'growtype-analytics'), $targets['repurchase_rate_total'] . '%'),
                'is_good' => $repurchase_status,
                'tooltip' => __('Percentage of total unique buyers who have made more than one successful purchase.', 'growtype-analytics')
            ),
            'arppu_total' => array (
                'title' => __('ARPPU', 'growtype-analytics'),
                'value' => $this->page->format_money($metrics['arppu_total'] ?? 0),
                'desc' => __('Average revenue per paying user', 'growtype-analytics'),
                'tooltip' => __('The average revenue generated by each paying customer. High ARPPU helps offset high CAC.', 'growtype-analytics')
            ),
            'churn_risk_recent_payers' => array (
                'title' => __('Churn Risk', 'growtype-analytics'),
                'value' => $this->page->format_number($metrics['churn_risk_recent_payers'] ?? 0),
                'desc' => sprintf(__('Recent payers inactive for %1$d+ days', 'growtype-analytics'), $metrics['churn_inactivity_days'] ?? 0)
            ),
            'aov' => array (
                'title' => sprintf(__('AOV %sd', 'growtype-analytics'), $pd),
                'value' => $this->page->format_money($metrics['aov'] ?? 0),
                'desc' => __('Average order value', 'growtype-analytics')
            ),
            'payer_churn_rate' => array (
                'title' => __('Payer Inactivity Rate', 'growtype-analytics'),
                'value' => $this->page->format_percent($metrics['payer_churn_rate'] ?? 0),
                'desc' => sprintf(__('Recent payers inactive for %d+ days', 'growtype-analytics'), $metrics['churn_inactivity_days'] ?? 0),
                'is_good' => ($metrics['payer_churn_rate'] ?? 0) <= 50
            ),
            'user_churn_rate' => array (
                'title' => __('User Inactivity Rate', 'growtype-analytics'),
                'value' => $this->page->format_percent($metrics['user_churn_rate'] ?? 0),
                'desc' => sprintf(__('30d active users inactive for %d+ days', 'growtype-analytics'), $metrics['churn_inactivity_days'] ?? 0),
                'is_good' => ($metrics['user_churn_rate'] ?? 0) <= 50
            ),
            'median_days_to_first_purchase' => array (
                'title' => __('Time to First Purchase', 'growtype-analytics'),
                'value' => ($metrics['median_days_to_first_purchase'] ?? 0) . ' ' . __('days', 'growtype-analytics'),
                'desc' => __('Median registration → first paid order', 'growtype-analytics'),
                'tooltip' => __('How long it takes for a newly registered user to make their first purchase. Shorter time means faster cash flow.', 'growtype-analytics')
            ),
            'user_try_to_buy_rate' => array (
                'title' => sprintf(__('User -> Attempt (%sd)', 'growtype-analytics'), $pd),
                'value' => $this->page->format_percent($user_try_rate),
                'desc' => sprintf(__('Goal: > %s', 'growtype-analytics'), $targets['user_try_to_buy_rate'] . '%'),
                'is_good' => $user_try_rate >= $targets['user_try_to_buy_rate'],
                'tooltip' => __('Percentage of registered users who initiated a checkout.', 'growtype-analytics')
            ),
        );

        $map = $this->page->posthog->add_to_kpi_meta_map($map, $metrics, $pd);

        return apply_filters('growtype_analytics_kpi_meta_map', $map, $metrics, $pd);
    }

    public function render_custom_kpi_section($title = '', $description = '', $date_from = '', $date_to = '')
    {
        ob_start();
        do_action('growtype_analytics_custom_kpi_cards', $this, $date_from, $date_to);
        $cards_content = ob_get_clean();

        if (empty(trim($cards_content))) {
            return;
        }

        $title = $title ?: __('Custom Insights', 'growtype-analytics');
        ?>
        <div class="analytics-section">
            <h2><?php echo esc_html($title); ?></h2>
            <?php if ($description): ?>
                <p class="description"><?php echo esc_html($description); ?></p>
            <?php endif; ?>

            <div class="analytics-scale-snapshot-grid">
                <?php echo $cards_content; ?>
            </div>
        </div>
        <?php
    }

    public function render_snapshot_card($title, $value, $description = '', $is_good = null, $tooltip = '', $id = '', $is_pinned = false)
    {
        $status_class = '';
        if ($is_good === true) {
            $status_class = ' analytics-snapshot-card--good';
        } elseif ($is_good === false) {
            $status_class = ' analytics-snapshot-card--bad';
        }
        ?>
        <div class="analytics-snapshot-card<?php echo esc_attr($status_class); ?>" <?php echo !empty($id) ? 'data-kpi-id="' . esc_attr($id) . '"' : ''; ?>>
            <?php if ($is_pinned): ?>
                <div class="analytics-snapshot-card__pinned-indicator" title="<?php echo esc_attr__('Pinned to Execution KPIs', 'growtype-analytics'); ?>">
                    <span class="dashicons dashicons-star-filled"></span>
                </div>
            <?php endif; ?>
            <div class="analytics-snapshot-card__title">
                <?php echo esc_html($title); ?>
                <?php if (!empty($tooltip)): ?>
                    <span class="analytics-info-bubble" tabindex="0" data-tooltip="<?php echo esc_attr($tooltip); ?>">
                        <span class="dashicons dashicons-info-outline"></span>
                    </span>
                <?php endif; ?>
            </div>

            <?php if (!empty($id)): ?>
                <div class="analytics-card-menu-container">
                    <div class="analytics-card-menu" onclick="toggleAnalyticsCardDropdown(this)">
                        <span class="dashicons dashicons-ellipsis"></span>
                    </div>
                    <div class="analytics-card-dropdown">
                        <div class="analytics-card-dropdown-item" onclick="togglePinnedKPI('<?php echo esc_js($id); ?>', this)">
                            <span class="dashicons <?php echo $is_pinned ? 'dashicons-star-filled' : 'dashicons-star-empty'; ?>" style="font-size: 14px; width: 14px; height: 14px; margin-right: 5px;"></span>
                            <?php echo $is_pinned ? __('Unpin from Execution KPIs', 'growtype-analytics') : __('Pin to Execution KPIs', 'growtype-analytics'); ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="analytics-snapshot-card__value"><?php echo esc_html($value); ?></div>
            <?php if (!empty($description)): ?>
                <?php
                $description_html = esc_html($description);
                if (strpos($description, 'Goal:') !== false) {
                    $description_html = preg_replace('/(Goal:.*)/', '<span class="analytics-card-goal">$1</span>', $description_html);
                }
                ?>
                <div class="analytics-snapshot-card__description"><?php echo $description_html; ?></div>
            <?php endif; ?>
        </div>
        <?php
    }

    public function render_funnel_cards($funnel)
    {
        $settings = $this->page->metrics->get_snapshot_settings();
        ?>
        <div class="analytics-scale-snapshot-grid" style="margin-bottom:16px;">
            <?php foreach ($funnel['rows'] as $row): ?>
                <?php
                $tooltip = '';
                if ($row['label'] === __('> 3 messages', 'growtype-analytics') || $row['label'] === '> 3 messages' || $row['label'] === __('More detailed', 'growtype-analytics') || $row['label'] === __('Activated', 'growtype-analytics')) {
                    $tooltip = sprintf(__('Users who sent %d+ messages within %d day(s) of registration.', 'growtype-analytics'), $settings['activation_min_messages'], $settings['activation_window_days']);
                } elseif ($row['label'] === __('Checkout Attempt', 'growtype-analytics') || $row['label'] === 'Checkout Attempt') {
                    $tooltip = __('Users who reached the checkout page or initiated a payment, including pending, failed, and successful orders.', 'growtype-analytics');
                }
                $this->render_snapshot_card($row['label'], $row['count'], 'Vs first: ' . $row['vs_first'] . ' | Vs previous: ' . $row['vs_previous'], null, $tooltip);
                ?>
            <?php endforeach; ?>
        </div>
        <?php
    }
}
