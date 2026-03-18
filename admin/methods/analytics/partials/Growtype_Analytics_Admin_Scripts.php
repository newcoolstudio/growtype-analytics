<?php

/**
 * Admin Scripts handler for analytics page
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/admin/methods/analytics
 */

class Growtype_Analytics_Admin_Scripts
{
    private $controller;

    public function __construct($controller)
    {
        $this->controller = $controller;
        add_action('admin_enqueue_scripts', array($this, 'enqueue_analytics_page_assets'));
    }

    /**
     * Enqueue styles and scripts for analytics page
     */
    public function enqueue_analytics_page_assets($hook)
    {
        $pages = $this->controller->analytics_page->get_admin_page_hooks();

        if (!in_array($hook, $pages)) {
            return;
        }

        // Enqueue custom CSS for analytics page
        wp_enqueue_style(
            'growtype-analytics-page',
            GROWTYPE_ANALYTICS_URL . 'admin/css/growtype-analytics-page.css',
            array(),
            GROWTYPE_ANALYTICS_VERSION
        );

        // Enqueue Chart.js
        wp_enqueue_script(
            'chart-js',
            'https://cdn.jsdelivr.net/npm/chart.js',
            array(),
            '3.9.1',
            true
        );

        // Enqueue custom JS for analytics page
        wp_enqueue_script(
            'growtype-analytics-page',
            GROWTYPE_ANALYTICS_URL . 'admin/js/growtype-analytics-page.js',
            array('jquery', 'chart-js'),
            GROWTYPE_ANALYTICS_VERSION,
            true
        );

        // Pass data to JavaScript
        wp_localize_script('growtype-analytics-page', 'growtypeAnalytics', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('growtype_analytics_nonce'),
        ));
    }
}