<?php

class Growtype_Analytics_Admin_Page_Product extends Growtype_Analytics_Admin_Base_Page
{
    public function get_page_title()
    {
        return __('Product', 'growtype-analytics');
    }

    public function get_menu_title()
    {
        return __('Product', 'growtype-analytics');
    }

    public function get_menu_slug()
    {
        return 'growtype-analytics-product';
    }

    public function render_page()
    {
        $this->render_page_header(__('Product Analytics', 'growtype-analytics'));
        
        $this->controller->paywall_chart->render();

        $this->render_page_footer();
    }
}
