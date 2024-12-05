<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              http://growtype.com/
 * @since             1.0.0
 * @package           growtype_analytics
 *
 * @wordpress-plugin
 * Plugin Name:       Growtype - Analytics
 * Plugin URI:        http://growtype.com/
 * Description:       Advanced analytics functionality.
 * Version:           1.0.0
 * Author:            Growtype
 * Author URI:        http://growtype.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       growtype-analytics
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define('GROWTYPE_ANALYTICS_VERSION', '1.0.4.4');

/**
 * Plugin text domain
 */
define('GROWTYPE_ANALYTICS_TEXT_DOMAIN', 'growtype-analytics');

/**
 * Plugin dir path
 */
define('GROWTYPE_ANALYTICS_PATH', plugin_dir_path(__FILE__));

/**
 * Plugin url
 */
define('GROWTYPE_ANALYTICS_URL', plugin_dir_url(__FILE__));

/**
 * Plugin url public
 */
define('GROWTYPE_ANALYTICS_URL_PUBLIC', plugin_dir_url(__FILE__) . 'public/');

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-growtype-analytics-activator.php
 */
function activate_growtype_analytics()
{
    require_once GROWTYPE_ANALYTICS_PATH . 'includes/class-growtype-analytics-activator.php';
    Growtype_Analytics_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-growtype-analytics-deactivator.php
 */
function deactivate_growtype_analytics()
{
    require_once GROWTYPE_ANALYTICS_PATH . 'includes/class-growtype-analytics-deactivator.php';
    Growtype_Analytics_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_growtype_analytics');
register_deactivation_hook(__FILE__, 'deactivate_growtype_analytics');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require GROWTYPE_ANALYTICS_PATH . 'includes/class-growtype-analytics.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_growtype_analytics()
{
    $plugin = new Growtype_Analytics();
    $plugin->run();
}

run_growtype_analytics();
