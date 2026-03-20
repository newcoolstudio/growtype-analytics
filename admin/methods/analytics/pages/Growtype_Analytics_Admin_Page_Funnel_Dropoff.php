<?php

class Growtype_Analytics_Admin_Page_Funnel_Dropoff extends Growtype_Analytics_Admin_Base_Page
{
    public function get_page_title()
    {
        return __('Funnel Drop-off', 'growtype-analytics');
    }

    public function get_menu_title()
    {
        return __('Funnel Drop-off', 'growtype-analytics');
    }

    public function get_menu_slug()
    {
        return 'growtype-analytics-funnel-dropoff';
    }

    public function render_page()
    {
        $this->render_page_header(__('Funnel Drop-off', 'growtype-analytics'));

        $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : date('Y-m-d', strtotime('-30 days'));
        $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : date('Y-m-d');
        $period = $date_from . ' - ' . $date_to;

        $marketing_spend = (float)get_option('growtype_analytics_snapshot_marketing_spend_30d', 0);
        $objective = get_option('growtype_analytics_growth_objective', '10x');

        $this->controller->decision_renderer->render_dashboard_filters($date_from, $date_to, $objective, $marketing_spend, $this->get_menu_slug());
        
        $funnel = $this->controller->funnel->get_funnel_dropoff_data($period);
        $renderer = $this->controller->decision_renderer;
        ?>
        <?php
        $paged = isset($_GET['paged']) ? max(1, (int)$_GET['paged']) : 1;
        $per_page = Growtype_Analytics_Admin_Table_Renderer::DEFAULT_PER_PAGE;
        $offset = ($paged - 1) * $per_page;

        $funnel_render = function($data_group) use ($paged, $per_page, $offset) {
            $all_rows = $data_group['rows'] ?? array();
            $total_items = count($all_rows);
            $paged_rows = array_slice($all_rows, $offset, $per_page);
            $this->controller->table_renderer->render(
                array(__('Stage', 'growtype-analytics'), __('Users', 'growtype-analytics'), __('Vs Previous', 'growtype-analytics'), __('Vs First', 'growtype-analytics')), 
                $paged_rows, 
                $total_items, 
                $per_page, 
                $paged
            );
        };
        ?>
        <div class="analytics-section">
            <h2><?php printf(__('Funnel: %s', 'growtype-analytics'), esc_html($this->controller->metrics->get_period_label($period))); ?></h2>
            <p class="description"><?php _e('Conversion funnel for users registered within the selected period.', 'growtype-analytics'); ?></p>
            <?php $renderer->render_funnel_cards($funnel['selected']); ?>
            <?php $funnel_render($funnel['selected']); ?>
        </div>
        <div class="analytics-section">
            <h2><?php _e('30 Day Funnel', 'growtype-analytics'); ?></h2>
            <?php $renderer->render_funnel_cards($funnel['30d']); ?>
            <?php $funnel_render($funnel['30d']); ?>
        </div>
        <div class="analytics-section">
            <h2><?php _e('7 Day Funnel', 'growtype-analytics'); ?></h2>
            <?php $renderer->render_funnel_cards($funnel['7d']); ?>
            <?php $funnel_render($funnel['7d']); ?>
        </div>
        <?php
        $this->render_page_footer();
    }
}
