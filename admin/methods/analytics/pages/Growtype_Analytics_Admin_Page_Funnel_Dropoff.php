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
        
        $funnel = $this->controller->funnel->get_funnel_dropoff_data();
        $renderer = $this->controller->decision_renderer;
        ?>
        <div class="analytics-section">
            <h2><?php _e('30 Day Funnel', 'growtype-analytics'); ?></h2>
            <p class="description"><?php _e('New users moving from registration to activation, checkout attempts, and paid conversion.', 'growtype-analytics'); ?></p>
            <?php $renderer->render_funnel_cards($funnel['30d']); ?>
            <?php $renderer->render_growth_table(array('Stage', 'Users', 'Vs Previous', 'Vs First'), $funnel['30d']['rows']); ?>
        </div>
        <div class="analytics-section">
            <h2><?php _e('7 Day Funnel', 'growtype-analytics'); ?></h2>
            <?php $renderer->render_funnel_cards($funnel['7d']); ?>
            <?php $renderer->render_growth_table(array('Stage', 'Users', 'Vs Previous', 'Vs First'), $funnel['7d']['rows']); ?>
        </div>
        <?php
        $this->render_page_footer();
    }
}
