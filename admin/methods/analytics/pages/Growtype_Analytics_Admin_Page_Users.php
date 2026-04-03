<?php

class Growtype_Analytics_Admin_Page_Users extends Growtype_Analytics_Admin_Base_Page
{
    public function get_page_title()
    {
        return __('Users', 'growtype-analytics');
    }

    public function get_menu_title()
    {
        return __('Users', 'growtype-analytics');
    }

    public function get_menu_slug()
    {
        return 'growtype-analytics-users';
    }

    public function render_page()
    {
        if (isset($_GET['refresh']) && $_GET['refresh'] == '1') {
            $this->controller->metrics->bust_snapshot_cache();
            echo '<div class="notice notice-success is-dismissible" style="margin-top: 20px;"><p>' . __('Analytics data has been refreshed successfully.', 'growtype-analytics') . '</p></div>';
        }

        $this->render_page_header(__('Users Analytics', 'growtype-analytics'));

        $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : date('Y-m-d', strtotime('-30 days'));
        $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : date('Y-m-d');
        $marketing_spend = (float)get_option('growtype_analytics_snapshot_marketing_spend_30d', 0);
        $objective = get_option('growtype_analytics_growth_objective', '10x');

        $this->controller->decision_renderer->render_dashboard_filters($date_from, $date_to, $objective, $marketing_spend, 'growtype-analytics-users');

        $this->controller->decision_renderer->render_registered_users_table($date_from, $date_to);

        $this->controller->chart->render();
        $this->controller->registrations_chart->render();
        $this->controller->retention_chart->render();

        $this->render_page_footer();
    }
}
