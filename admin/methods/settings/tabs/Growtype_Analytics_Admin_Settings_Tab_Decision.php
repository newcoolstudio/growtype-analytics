<?php

class Growtype_Analytics_Admin_Settings_Tab_Decision extends Growtype_Analytics_Admin_Settings_Tab_Base
{
    public function get_id() { return 'decision'; }
    public function get_label() { return __('Decision snapshot', 'growtype-analytics'); }
    public function get_description() { return __('Configure the scale-or-pivot snapshot, churn logic, and contribution margin assumptions.', 'growtype-analytics'); }
    public function uses_native_form() { return true; }

    public function render() {}
}
