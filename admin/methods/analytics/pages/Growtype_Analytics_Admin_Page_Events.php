<?php

class Growtype_Analytics_Admin_Page_Events extends Growtype_Analytics_Admin_Base_Page
{
    public function get_page_title()
    {
        return __('Live Events', 'growtype-analytics');
    }

    public function get_menu_title()
    {
        return __('Live Events', 'growtype-analytics');
    }

    public function get_menu_slug()
    {
        return 'growtype-analytics-events';
    }

    public function render_page()
    {
        $this->render_page_header(__('Live Tracking Events', 'growtype-analytics'));

        $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : date('Y-m-d', strtotime('-30 days'));
        $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : date('Y-m-d');
        
        $snapshot_settings = $this->controller->get_snapshot_settings();
        $objective = $snapshot_settings['growth_objective'] ?? '10x';
        $marketing_spend = $snapshot_settings['marketing_spend'] ?? 0;

        $this->controller->decision_renderer->render_dashboard_filters($date_from, $date_to, $objective, $marketing_spend, $this->get_menu_slug());

        $paged = isset($_GET['paged']) ? max(1, (int)$_GET['paged']) : 1;
        $per_page = Growtype_Analytics_Admin_Table_Renderer::DEFAULT_PER_PAGE;
        $offset = ($paged - 1) * $per_page;

        $paginated_data = Growtype_Analytics_Database::get_paginated_events($date_from, $date_to, $per_page, $offset);
        $total_events = $paginated_data['total'];
        $events = $paginated_data['events'];

        ?>
        <div class="analytics-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <div>
                    <h2 style="margin: 0;"><?php _e('Recent Tracking Events', 'growtype-analytics'); ?></h2>
                    <p class="description" style="margin: 5px 0 0;"><?php printf(__('Showing %d events out of %s total in selected period.', 'growtype-analytics'), count($events), '<strong>' . number_format($total_events) . '</strong>'); ?></p>
                </div>
            </div>

            <?php
            $rows = array();
            foreach ($events as $event) {
                // Object cell
                $object_html = $event['object_type'] . ': ' . $event['object_id'];
                if ($event['object_type'] === 'product' && function_exists('wc_get_product')) {
                    $product = wc_get_product($event['object_id']);
                    if ($product) {
                        $object_html = '<strong>' . esc_html($product->get_name()) . '</strong> <br>';
                        $object_html .= '<small style="opacity: 0.6;">(ID: ' . esc_html($event['object_id']) . ')</small>';
                    }
                }

                // User cell
                $user_html = '<span style="opacity: 0.5;">' . __('Guest', 'growtype-analytics') . '</span>';
                if ($event['user_id'] > 0) {
                    $user = get_user_by('id', $event['user_id']);
                    if ($user) {
                        $user_html = '<a href="' . get_edit_user_link($user->ID) . '"><strong>' . esc_html($user->display_name) . '</strong></a>';
                    } else {
                        $user_html = 'User #' . esc_html($event['user_id']);
                    }
                }

                // Metadata cell
                $metadata_html = '-';
                if (!empty($event['metadata'])) {
                    $meta = json_decode($event['metadata'], true);
                    if ($meta) {
                        $metadata_html = '<pre style="margin: 0; font-size: 10px; line-height: 1.2; background: #f9f9f9; padding: 5px; border-radius: 4px; border: 1px solid #eee;">' . esc_html(json_encode($meta, JSON_PRETTY_PRINT)) . '</pre>';
                    } else {
                        $metadata_html = esc_html($event['metadata']);
                    }
                }

                $rows[] = array(
                    'timestamp' => esc_html($event['created_at']),
                    'event_type' => '<span class="status-badge" style="background: #f0f0f1; padding: 4px 8px; border-radius: 4px; font-weight: 600; font-size: 11px; text-transform: uppercase;">' . esc_html($event['event_type']) . '</span>',
                    'object' => $object_html,
                    'user' => $user_html,
                    'metadata' => $metadata_html
                );
            }

            $this->controller->table_renderer->render(
                array(__('Timestamp', 'growtype-analytics'), __('Event Type', 'growtype-analytics'), __('Object', 'growtype-analytics'), __('User', 'growtype-analytics'), __('Metadata', 'growtype-analytics')),
                $rows,
                (int)$total_events,
                $per_page,
                $paged
            );
            ?>
        </div>
        <?php
        $this->render_page_footer();
    }
}
