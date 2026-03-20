<?php

trait Growtype_Analytics_Admin_Page_Metrics_Trait
{
    public function get_snapshot_metrics($refresh = false, $period = 30)
    {
        return $this->metrics->get_scale_or_pivot_metrics($refresh, $period);
    }

    public function get_snapshot_settings()
    {
        return $this->metrics->get_snapshot_settings();
    }

    public function prepare_dynamic_query($query, $params = array())
    {
        return $this->metrics->prepare_dynamic_query($query, $params);
    }

    public function build_email_exclusion_sql($column, $patterns, $allow_null = false)
    {
        return $this->metrics->build_email_exclusion_sql($column, $patterns, $allow_null);
    }

    public function get_payment_failure_segments_data($settings, $days = 30, $limit = 25)
    {
        return $this->metrics->get_payment_failure_segments($settings, $days, $limit);
    }

    public function get_traffic_funnel_data($days = 30)
    {
        return $this->metrics->get_traffic_funnel_data($days);
    }

    public function get_cac_by_source_data($days = 30, $limit = 10)
    {
        return $this->metrics->get_cac_by_source_data($days, $limit);
    }

    public function get_retention_by_source_data($limit = 10)
    {
        return $this->metrics->get_retention_by_source_data($limit);
    }

    public function get_offer_repurchase_quality_data($limit = 10)
    {
        return $this->metrics->get_offer_repurchase_quality_data($limit);
    }

    public function get_source_payback_data($days = 30, $limit = 10)
    {
        return $this->metrics->get_source_payback_data($days, $limit);
    }

    public function get_language_conversion_data($days = 30, $limit = 10)
    {
        return $this->metrics->get_language_conversion_data($days, $limit);
    }

    public function get_refund_chargeback_rates_data($days = 30)
    {
        return $this->metrics->get_refund_chargeback_rates_data($days);
    }

    public function get_growth_trends_data($days = 30)
    {
        return $this->metrics->get_growth_trends_data($days);
    }

}
