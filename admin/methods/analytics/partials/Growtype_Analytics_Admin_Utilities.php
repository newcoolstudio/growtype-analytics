<?php

/**
 * Analytics Admin Utilities Partial
 *
 * Handles common utility functions like formatting and database checks
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/admin/methods/analytics
 */

class Growtype_Analytics_Admin_Utilities
{
    private $controller;

    public function __construct($controller)
    {
        $this->controller = $controller;
    }

    /**
     * Check if a DB table exists.
     * Result is cached statically per request to avoid repeated SHOW TABLES queries.
     */
    public function table_exists($table_name)
    {
        static $cache = array();

        if (!isset($cache[$table_name])) {
            global $wpdb;
            $cache[$table_name] = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;
        }

        return $cache[$table_name];
    }

    public function format_number($value)
    {
        return number_format_i18n((float)$value);
    }

    public function format_percent($value)
    {
        return number_format_i18n((float)$value, 2) . '%';
    }

    public function format_money($value)
    {
        return '$' . number_format_i18n((float)$value, 2);
    }
}
