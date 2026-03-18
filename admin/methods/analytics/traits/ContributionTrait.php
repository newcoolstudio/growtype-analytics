<?php

trait Growtype_Analytics_Admin_Page_Contribution_Trait
{
    public function get_margin_settings()
    {
        return $this->contribution->get_margin_settings();
    }

    public function get_contribution_margin_metrics($days, $margin_settings)
    {
        return $this->contribution->get_contribution_margin_metrics($days, $margin_settings);
    }

    public function get_contribution_margin_data()
    {
        return $this->contribution->get_contribution_margin_data();
    }

    public function get_real_cost_refund_chargeback_data($days = 30)
    {
        return $this->contribution->get_real_cost_refund_chargeback_data($days);
    }
}
