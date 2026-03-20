<?php

/**
 * Admin Menu handler for analytics page
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/admin/methods/analytics
 */

class Growtype_Analytics_Admin_Menu
{
    private $controller;
    private $page_hooks = array();

    public function __construct($controller)
    {
        $this->controller = $controller;
        add_action('admin_menu', array($this, 'register_menu_pages'));
    }

    /**
     * Register menu pages
     */
    public function register_menu_pages()
    {
        $this->page_hooks[] = add_menu_page(
            __('GA - Analytics', 'growtype-analytics'),
            __('GA - Analytics', 'growtype-analytics'),
            'manage_options',
            'growtype-analytics',
            array($this->controller->analytics_page, 'render_page'),
            'dashicons-chart-line',
            30
        );

        foreach ($this->controller->get_submenu_pages() as $page) {
            $this->page_hooks[] = add_submenu_page(
                'growtype-analytics',
                $page->get_page_title(),
                $page->get_menu_title(),
                'manage_options',
                $page->get_menu_slug(),
                array($page, 'render_page')
            );
        }
    }

    /**
     * Get admin page hooks
     */
    public function get_admin_page_hooks()
    {
        return $this->page_hooks;
    }
}
