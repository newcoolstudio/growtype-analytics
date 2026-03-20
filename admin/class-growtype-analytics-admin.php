<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/admin
 * @author     Your Name <email@example.com>
 */
class Growtype_Analytics_Admin
{

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $growtype_analytics The ID of this plugin.
     */
    private $growtype_analytics;

    private $methods;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $version The current version of this plugin.
     */
    private $version;

    /**
     * Traits
     */

    /**
     * Initialize the class and set its properties.
     *
     * @param string $growtype_analytics The name of this plugin.
     * @param string $version The version of this plugin.
     * @since    1.0.0
     */
    public function __construct($growtype_analytics, $version)
    {
        $this->growtype_analytics = $growtype_analytics;
        $this->version = $version;

        if (is_admin()) {
            /**
             * Load methods
             */
            $this->load_methods();
        }
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles($hook)
    {
        // Only load styles on our plugin pages
        if (strpos($hook, 'growtype-analytics') === false) {
            return;
        }

        wp_enqueue_style($this->growtype_analytics, GROWTYPE_ANALYTICS_URL . 'admin/css/growtype-analytics-admin.css', array (), $this->version, 'all');
        wp_enqueue_style('growtype-analytics-page', GROWTYPE_ANALYTICS_URL . 'admin/css/growtype-analytics-page.css', array(), $this->version);
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts($hook)
    {
        // Only load scripts on our plugin pages to prevent WP dashboard bloat
        if (strpos($hook, 'growtype-analytics') === false) {
            return;
        }

        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js', array(), '3.9.1', true);
        
        wp_enqueue_script($this->growtype_analytics, GROWTYPE_ANALYTICS_URL . 'admin/js/growtype-analytics-admin.js', array('jquery', 'jquery-ui-sortable', 'chart-js'), $this->version, true);
        wp_enqueue_script('growtype-analytics-ajax', GROWTYPE_ANALYTICS_URL . 'admin/js/growtype-analytics-ajax.js', array('jquery'), $this->version, true);

        $vars = array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('growtype_analytics_nonce')
        );

        wp_localize_script($this->growtype_analytics, 'growtype_analytics_vars', $vars);
        wp_localize_script('growtype-analytics-ajax', 'growtype_analytics_vars', $vars);
    }

    /**
     * Load the required methods for this plugin.
     *
     */
    private function load_methods()
    {
        /**
         * Analytics Page
         */
        require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/Growtype_Analytics_Admin_Page.php';
        new Growtype_Analytics_Admin_Page();

        /**
         * Settings
         */
        require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/settings/Growtype_Analytics_Admin_Settings.php';
        new Growtype_Analytics_Admin_Settings();

        /**
         * Users
         */
        require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/users/Growtype_Analytics_Admin_Users.php';
        new Growtype_Analytics_Admin_Users();
    }
}
