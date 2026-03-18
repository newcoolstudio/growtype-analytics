<?php

/**
 * Base class for all analytics admin pages
 */
class Growtype_Analytics_Admin_Base_Page
{
    protected $controller;

    public function __construct($controller)
    {
        $this->controller = $controller;
    }

    public function render_page_header($title = '')
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'growtype-analytics'));
        }

        if (empty($title)) {
            $title = $this->get_page_title();
        }

        ?>
        <div class="wrap growtype-analytics-page">
            <h1><?php echo esc_html($title); ?></h1>
            <div class="growtype-analytics-dashboard">
        <?php
    }

    public function render_page_footer()
    {
        ?>
            </div>
        </div>
        <?php
    }

    /**
     * These should be overridden by child classes
     */
    public function get_page_title() { return ''; }
    public function get_menu_title() { return ''; }
    public function get_menu_slug() { return ''; }
    public function render_page() {}
}
