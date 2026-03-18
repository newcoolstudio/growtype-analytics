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
        $this->render_page_header(__('Users Analytics', 'growtype-analytics'));
        
        $this->controller->chart->render();
        $this->controller->registrations_chart->render();
        $this->controller->retention_chart->render();

        $this->render_page_footer();
    }
}
