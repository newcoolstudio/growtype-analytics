<?php

class Growtype_Analytics_Admin_Page_Chat extends Growtype_Analytics_Admin_Base_Page
{
    public function get_page_title()
    {
        return __('Chat', 'growtype-analytics');
    }

    public function get_menu_title()
    {
        return __('Chat', 'growtype-analytics');
    }

    public function get_menu_slug()
    {
        return 'growtype-analytics-chat';
    }

    public function render_page()
    {
        $this->render_page_header(__('Chat Analytics', 'growtype-analytics'));
        
        $this->controller->activation_chart->render();

        $this->render_page_footer();
    }
}
