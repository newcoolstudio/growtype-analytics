<?php

/**
 * Analytics Admin Page Activation Rate Chart Partial
 *
 * Handles the activation rate chart rendering and data fetching
 * Shows % of new users who send ≥3 messages
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/admin/methods/analytics
 */

class Growtype_Analytics_Admin_Contribution
{
    private $controller;

    public function __construct($controller)
    {
        $this->controller = $controller;
    }

    public function get_margin_settings()
    {
        $page = $this->controller->analytics_page->get_page_by_class('Growtype_Analytics_Admin_Page_Contribution_Margin');
        return $page ? $page->get_margin_settings() : array();
    }

    public function get_contribution_margin_metrics($days, $margin_settings)
    {
        $page = $this->controller->analytics_page->get_page_by_class('Growtype_Analytics_Admin_Page_Contribution_Margin');
        return $page ? $page->get_contribution_margin_metrics($days, $margin_settings) : array();
    }

    public function get_contribution_margin_data()
    {
        $page = $this->controller->analytics_page->get_page_by_class('Growtype_Analytics_Admin_Page_Contribution_Margin');
        return $page ? $page->get_contribution_margin_data() : array();
    }

    public function get_real_cost_refund_chargeback_data($days = 30)
    {
        $page = $this->controller->analytics_page->get_page_by_class('Growtype_Analytics_Admin_Page_Contribution_Margin');
        return $page ? $page->get_real_cost_refund_chargeback_data($days) : array();
    }
}
