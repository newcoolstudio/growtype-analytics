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
    public function enqueue_styles()
    {
        wp_enqueue_style($this->growtype_analytics, GROWTYPE_ANALYTICS_URL . 'admin/css/growtype-analytics-admin.css', array (), $this->version, 'all');
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts()
    {
        wp_enqueue_script($this->growtype_analytics, GROWTYPE_ANALYTICS_URL . 'admin/js/growtype-analytics-admin.js', array ('jquery'), $this->version, false);
    }

    /**
     * Load the required methods for this plugin.
     *
     */
    private function load_methods()
    {
        /**
         * Settings
         */
        require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/settings/index.php';
        $this->methods = new Growtype_Analytics_Admin_Settings();
    }
}
