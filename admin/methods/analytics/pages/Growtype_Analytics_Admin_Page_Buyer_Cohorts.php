<?php

class Growtype_Analytics_Admin_Page_Buyer_Cohorts extends Growtype_Analytics_Admin_Base_Page
{
    public function get_buyer_cohort_rows($months = 6)
    {
        global $wpdb;

        $settings = $this->controller->get_snapshot_settings();
        $paid = $settings['paid_statuses'];
        $paid_placeholders = implode(',', array_fill(0, count($paid), '%s'));
        $email_exclusion = $this->controller->build_email_exclusion_sql('u.user_email', $settings['excluded_email_patterns']);

        $query = "SELECT pm.meta_value as user_id, p.post_date as paid_at, CAST(total.meta_value AS DECIMAL(10,2)) as revenue
            FROM `{$wpdb->posts}` p
            INNER JOIN `{$wpdb->postmeta}` pm ON pm.post_id = p.ID AND pm.meta_key = '_customer_user'
            INNER JOIN `{$wpdb->postmeta}` total ON total.post_id = p.ID AND total.meta_key = '_order_total'
            INNER JOIN `{$wpdb->users}` u ON u.ID = pm.meta_value
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ($paid_placeholders)
            {$email_exclusion['sql']}
            ORDER BY user_id ASC, p.post_date ASC";

        $orders = $wpdb->get_results($this->controller->prepare_dynamic_query($query, array_merge($paid, $email_exclusion['params'])), ARRAY_A);

        $cohorts = array();
        $buyers = array();

        foreach ($orders as $order) {
            $user_id = (int)$order['user_id'];
            $paid_at = strtotime($order['paid_at']);
            $revenue = (float)$order['revenue'];

            if (!isset($buyers[$user_id])) {
                $cohort_key = date('Y-m', $paid_at);
                $buyers[$user_id] = array(
                    'cohort' => $cohort_key,
                    'first_paid_at' => $paid_at,
                    'revenue' => 0.0,
                    'repeat_30d' => false,
                );
            }
            elseif (!$buyers[$user_id]['repeat_30d'] && $paid_at <= strtotime('+30 days', $buyers[$user_id]['first_paid_at'])) {
                $buyers[$user_id]['repeat_30d'] = true;
            }

            $buyers[$user_id]['revenue'] += $revenue;
        }

        foreach ($buyers as $buyer) {
            $cohort = $buyer['cohort'];
            if (!isset($cohorts[$cohort])) {
                $cohorts[$cohort] = array(
                    'buyers' => 0,
                    'repeat_30d' => 0,
                    'revenue' => 0.0,
                );
            }

            $cohorts[$cohort]['buyers']++;
            $cohorts[$cohort]['revenue'] += $buyer['revenue'];
            if ($buyer['repeat_30d']) {
                $cohorts[$cohort]['repeat_30d']++;
            }
        }

        krsort($cohorts);
        $cohorts = array_slice($cohorts, 0, $months, true);

        $rows = array();
        foreach ($cohorts as $cohort => $data) {
            $buyers_count = max(1, $data['buyers']);
            $rows[] = array(
                $cohort,
                $this->controller->format_number($data['buyers']),
                $this->controller->format_number($data['repeat_30d']),
                $this->controller->format_percent(($data['repeat_30d'] / $buyers_count) * 100),
                $this->controller->format_money($data['revenue']),
                $this->controller->format_money($data['revenue'] / $buyers_count),
            );
        }

        return $rows;
    }

    public function get_page_title()
    {
        return __('Buyer Cohorts', 'growtype-analytics');
    }

    public function get_menu_title()
    {
        return __('Buyer Cohorts', 'growtype-analytics');
    }

    public function get_menu_slug()
    {
        return 'growtype-analytics-buyer-cohorts';
    }

    public function render_page()
    {
        $this->render_page_header(__('Buyer Cohorts', 'growtype-analytics'));
        
        $rows = $this->get_buyer_cohort_rows(6);
        ?>
        <div class="analytics-section">
            <h2><?php _e('First Purchase Cohorts', 'growtype-analytics'); ?></h2>
            <p class="description"><?php _e('Cohorts are grouped by month of first paid order. Use this to see repeat behavior after acquisition.', 'growtype-analytics'); ?></p>
            <?php
            $this->controller->decision_renderer->render_growth_table(
                array('Cohort', 'Buyers', 'Repeat in 30d', 'Repeat Rate 30d', 'Revenue', 'ARPPU'),
                $rows
            );
            ?>
        </div>
        <?php
        $this->render_page_footer();
    }
}
