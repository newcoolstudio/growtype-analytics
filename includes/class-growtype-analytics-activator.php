<?php

/**
 * Fired during plugin activation
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/includes
 * @author     Your Name <email@example.com>
 */
class Growtype_Analytics_Activator
{

    /**
     * Short Description. (use period)
     *
     * Long Description.
     *
     * @since    1.0.0
     */
    public static function activate()
    {
        require_once GROWTYPE_ANALYTICS_PATH . 'includes/methods/database/Growtype_Analytics_Database.php';
        Growtype_Analytics_Database::init();

        global $wp_rewrite;
        $wp_rewrite->flush_rules();
    }
}