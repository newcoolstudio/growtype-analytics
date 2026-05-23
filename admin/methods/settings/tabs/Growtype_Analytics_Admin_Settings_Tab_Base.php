<?php

abstract class Growtype_Analytics_Admin_Settings_Tab_Base
{
    abstract public function get_id();
    abstract public function get_label();
    abstract public function get_description();

    /**
     * Return true if this tab renders using the native WP settings form.
     * Return false if the tab has its own render() implementation.
     */
    public function uses_native_form() { return false; }

    public function handle_actions() {}
    abstract public function render();
}
