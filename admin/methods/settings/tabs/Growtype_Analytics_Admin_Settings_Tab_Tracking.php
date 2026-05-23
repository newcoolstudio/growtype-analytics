<?php

class Growtype_Analytics_Admin_Settings_Tab_Tracking extends Growtype_Analytics_Admin_Settings_Tab_Base
{
    public function get_id() { return 'tracking'; }
    public function get_label() { return __('Tracking codes / credentials', 'growtype-analytics'); }
    public function get_description() { return __('Manage tracking integrations and analytics credentials used across the project.', 'growtype-analytics'); }
    public function uses_native_form() { return true; }

    public function render() {}
}
