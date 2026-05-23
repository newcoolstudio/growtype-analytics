<?php

class Growtype_Analytics_Admin_Settings_Page
{
    private $custom_tabs = [];
    private $fields;

    public function __construct()
    {
        $this->load_settings();
        $this->load_tabs();

        $this->fields = new Growtype_Analytics_Admin_Settings_Fields();

        add_action('admin_menu', array($this, 'add_options_page'));
    }

    private function load_settings()
    {
        require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/settings/fields/Growtype_Analytics_Admin_Settings_Fields.php';
    }

    private function load_tabs()
    {
        $tabs_dir = GROWTYPE_ANALYTICS_PATH . 'admin/methods/settings/tabs/';
        
        require_once $tabs_dir . 'Growtype_Analytics_Admin_Settings_Tab_Base.php';

        foreach (glob($tabs_dir . 'Growtype_Analytics_Admin_Settings_Tab_*.php') as $file) {
            require_once $file;
        }

        $tabs = apply_filters('growtype_analytics_custom_settings_tabs', [
            'status'       => new Growtype_Analytics_Admin_Settings_Tab_Status(),
            'tracking'     => new Growtype_Analytics_Admin_Settings_Tab_Tracking(),
            'decision'     => new Growtype_Analytics_Admin_Settings_Tab_Decision(),
            'share-access' => new Growtype_Analytics_Admin_Settings_Tab_Share_Access(),
            'strategy'     => new Growtype_Analytics_Admin_Settings_Tab_Strategy(),
        ]);

        foreach ($tabs as $tab) {
            $this->custom_tabs[$tab->get_id()] = $tab;
        }
    }

    public function add_options_page()
    {
        add_options_page(
            'analytics',
            'Growtype - Analytics',
            'manage_options',
            'growtype-analytics-settings',
            array($this, 'render_options_content'),
            1
        );
    }

    public function render_options_content()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'growtype-analytics'));
        }

        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'status';
        $tabs = $this->get_settings_tabs();

        if (!isset($tabs[$tab])) {
            $tab = 'status';
        }

        if (isset($this->custom_tabs[$tab])) {
            $this->custom_tabs[$tab]->handle_actions();
        }

        echo '<div class="wrap">';
        echo '<h1>Analytics settings</h1>';
        echo '<h2 class="nav-tab-wrapper">';

        foreach ($tabs as $tab_key => $tab_label) {
            $tab_url = add_query_arg(
                array(
                    'page' => 'growtype-analytics-settings',
                    'tab' => $tab_key,
                ),
                admin_url('options-general.php')
            );

            $class = 'nav-tab' . ($tab === $tab_key ? ' nav-tab-active' : '');
            echo '<a href="' . esc_url($tab_url) . '" class="' . esc_attr($class) . '">' . esc_html($tab_label) . '</a>';
        }

        echo '</h2>';
        echo '<p class="description" style="margin-top: 12px;">' . esc_html($this->get_tab_description($tab)) . '</p>';
        
        if (isset($this->custom_tabs[$tab]) && !$this->custom_tabs[$tab]->uses_native_form()) {
            $this->custom_tabs[$tab]->render();
        } else {
            echo '<form method="post" action="options.php">';
            settings_fields('analytics_options_settings');
            $this->fields->render_tab_sections($tab);
            submit_button();
            echo '</form>';
        }

        echo '</div>';
    }

    private function get_settings_tabs()
    {
        $tabs = [];

        foreach ($this->custom_tabs as $id => $tab) {
            $tabs[$id] = $tab->get_label();
        }

        return $tabs;
    }

    private function get_tab_description($tab)
    {
        if (isset($this->custom_tabs[$tab])) {
            return $this->custom_tabs[$tab]->get_description();
        }

        return '';
    }

}
