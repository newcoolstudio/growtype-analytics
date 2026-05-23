<?php

class Growtype_Analytics_Admin_Settings
{
    private $page;

    public function __construct()
    {
        $this->load_pages();

        $this->page = new Growtype_Analytics_Admin_Settings_Page();
    }

    private function load_pages()
    {
        require_once __DIR__ . '/page/Growtype_Analytics_Admin_Settings_Page.php';
    }
}
