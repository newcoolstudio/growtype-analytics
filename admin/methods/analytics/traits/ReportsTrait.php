<?php

trait Growtype_Analytics_Admin_Page_Reports_Trait
{
    public function get_shared_report_page()
    {
        return $this->reports->get_shared_report_page();
    }

    public function get_source_attribution_rows($limit = 50)
    {
        return $this->reports->get_source_attribution_rows($limit);
    }

    public function get_offer_test_rows($limit = 50)
    {
        return $this->reports->get_offer_test_rows($limit);
    }

    public function get_buyer_cohort_rows($months = 6)
    {
        return $this->reports->get_buyer_cohort_rows($months);
    }
}