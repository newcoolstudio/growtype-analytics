<?php

trait Growtype_Analytics_Admin_Page_Utilities_Trait
{
    public function table_exists($table_name)
    {
        return $this->utilities->table_exists($table_name);
    }

    public function format_number($value)
    {
        return $this->utilities->format_number($value);
    }

    public function format_percent($value)
    {
        return $this->utilities->format_percent($value);
    }

    public function format_money($value)
    {
        return $this->utilities->format_money($value);
    }
}