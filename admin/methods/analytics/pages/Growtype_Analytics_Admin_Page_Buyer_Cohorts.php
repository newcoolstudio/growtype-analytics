<?php

class Growtype_Analytics_Admin_Page_Buyer_Cohorts extends Growtype_Analytics_Admin_Base_Page
{
    public function get_buyer_cohort_rows($limit = 50, $offset = 0, $orderby = 'cohort', $order = 'DESC')
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
                    'cohort' => $cohort,
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

        // Sorting
        $order_str = is_array($order) ? (reset($order) ?: 'DESC') : (string)$order;
        $order_multiplier = strtoupper($order_str) === 'DESC' ? -1 : 1;
        uasort($cohorts, function($a, $b) use ($orderby, $order_multiplier) {
            $val_a = $a[$orderby] ?? 0;
            $val_b = $b[$orderby] ?? 0;
            
            if ($val_a == $val_b) return 0;
            return ($val_a < $val_b) ? -1 * $order_multiplier : 1 * $order_multiplier;
        });

        $total_items = count($cohorts);
        $cohorts = array_slice($cohorts, $offset, $limit, true);

        $rows = array();
        foreach ($cohorts as $data) {
            $buyers_count = max(1, $data['buyers']);
            $rows[] = array(
                'cohort' => $data['cohort'],
                'buyers' => $this->controller->format_number($data['buyers']),
                'repeat_30d' => $this->controller->format_number($data['repeat_30d']),
                'repeat_rate_30d' => $this->controller->format_percent(($data['repeat_30d'] / $buyers_count) * 100),
                'revenue' => $this->controller->format_money($data['revenue']),
                'arppu' => $this->controller->format_money($data['revenue'] / $buyers_count),
                'total_items_count' => $total_items,
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

        $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : date('Y-m-d', strtotime('-30 days'));
        $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : date('Y-m-d');
        
        $snapshot_settings = $this->controller->get_snapshot_settings();
        $objective = $snapshot_settings['growth_objective'] ?? '10x';
        $marketing_spend = $snapshot_settings['marketing_spend'] ?? 0;

        $this->controller->decision_renderer->render_dashboard_filters($date_from, $date_to, $objective, $marketing_spend, $this->get_menu_slug());
        
        $paged = isset($_GET['paged']) ? max(1, (int)$_GET['paged']) : 1;
        $per_page = Growtype_Analytics_Admin_Table_Renderer::DEFAULT_PER_PAGE;
        $offset = ($paged - 1) * $per_page;
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'cohort';
        $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'DESC';

        $cohort_data = $this->get_buyer_cohort_rows($per_page, $offset, $orderby, $order);
        $total_items = !empty($cohort_data) ? $cohort_data[0]['total_items_count'] : 0;

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
            <h2><?php _e('First Purchase Cohorts', 'growtype-analytics'); ?></h2>
            <p class="description"><?php _e('Cohorts are grouped by month of first paid order. Use this to see repeat behavior after acquisition.', 'growtype-analytics'); ?></p>
            <?php
            $headers = array(
                $render_sortable_header(__('Cohort', 'growtype-analytics'), 'cohort'),
                $render_sortable_header(__('Buyers', 'growtype-analytics'), 'buyers'),
                __('Repeat in 30d', 'growtype-analytics'),
                __('Repeat Rate 30d', 'growtype-analytics'),
                $render_sortable_header(__('Revenue', 'growtype-analytics'), 'revenue'),
                __('ARPPU', 'growtype-analytics')
            );

            $this->controller->table_renderer->render(
                $headers,
                $cohort_data,
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
