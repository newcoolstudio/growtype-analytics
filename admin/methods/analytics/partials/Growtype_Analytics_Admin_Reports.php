<?php

/**
 * Analytics Admin Reports Partial
 *
 * Handles report data fetching from page classes
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/admin/methods/analytics
 */

class Growtype_Analytics_Admin_Reports
{
    private $controller;

    public function __construct($controller)
    {
        $this->controller = $controller;
    }

    public function get_shared_report_page()
    {
        return $this->controller->shared_report_page;
    }

    public function get_source_attribution_rows($limit = 50)
    {
        $page = $this->controller->get_page_by_class('Growtype_Analytics_Admin_Page_Source_Attribution');
        return $page ? $page->get_source_attribution_rows($limit) : array();
    }

    public function get_offer_test_rows($limit = 50)
    {
        $page = $this->controller->get_page_by_class('Growtype_Analytics_Admin_Page_Offer_Tests');
        return $page ? $page->get_offer_test_rows($limit) : array();
    }

    public function get_buyer_cohort_rows($months = 6)
    {
        $page = $this->controller->get_page_by_class('Growtype_Analytics_Admin_Page_Buyer_Cohorts');
        return $page ? $page->get_buyer_cohort_rows($months) : array();
    }

    public function get_top_characters_by_revenue_data($days = 30, $limit = 10, $offset = 0, $orderby = 'revenue', $order = 'desc')
    {
        $page = $this->controller->get_page_by_class('Growtype_Child_Growtype_Analytics_Admin_Page_Characters');
        return $page ? $page->get_top_characters_by_revenue_data($days, $limit, $offset, $orderby, $order) : array();
    }
}
