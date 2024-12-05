<?php

class Growtype_Analytics_Admin_Settings
{
    public function __construct()
    {
        add_action('admin_menu', array ($this, 'growtype_analytics_options_page'));
        add_action('admin_init', array ($this, 'growtype_analytics_register_settings'));
    }

    function growtype_analytics_options_page()
    {
        add_options_page(
            'analytics', // page <title>Title</title>
            'Growtype - Analytics', // menu link text
            'manage_options', // capability to access the page
            'growtype-analytics-settings', // page URL slug
            array ($this, 'growtype_analytics_options_content'), // callback function with content
            1 // priority
        );
    }

    function growtype_analytics_options_content()
    {
        echo '<div class="wrap">
	<h1>Analytics settings</h1>
	<form method="post" action="options.php">';

        settings_fields('analytics_options_settings'); // settings group name
        do_settings_sections('growtype-analytics-settings'); // just a page slug
        submit_button();

        echo '</form></div>';
    }

    function growtype_analytics_register_settings()
    {
        $inputs = [
            [
                'name' => 'GTM details',
                'value' => 'growtype_analytics_gtm_details',
                'options' => [
                    [
                        'title' => 'GTM ID',
                        'name' => 'gtm_id',
                        'type' => 'input',
                        'default_value' => ''
                    ]
                ]
            ],
            [
                'name' => 'GA4 details',
                'value' => 'growtype_analytics_ga4_details',
                'options' => [
                    [
                        'title' => 'GA4 ID',
                        'name' => 'ga4_id',
                        'type' => 'input',
                        'default_value' => ''
                    ]
                ]
            ]
        ];

        foreach ($inputs as $input) {
            $key_name = $input['name'];
            $key_value = $input['value'];
            $options = $input['options'];

            add_settings_section(
                $key_value . '_options_settings', // section ID
                $key_name, // title (if needed)
                '', // callback function (if needed)
                'growtype-analytics-settings' // page slug
            );

            foreach ($options as $option) {
                register_setting(
                    'analytics_options_settings', // settings group name
                    $key_value . '_' . $option['name'], // option name
                );

                add_settings_field(
                    $key_value . '_' . $option['name'],
                    $option['title'],
                    array ($this, 'input_callback'),
                    'growtype-analytics-settings',
                    $key_value . '_options_settings',
                    [
                        'type' => $option['type'] ?? 'text',
                        'name' => $key_value . '_' . $option['name'],
                        'default_value' => $option['default_value'] ?? '',
                    ]
                );
            }
        }
    }

    public function input_callback(array $args)
    {
        $name = $args['name'];
        $type = $args['type'];
        $default_value = $args['default_value'];

        if ($type === 'checkbox') {
            $html = '<input type="checkbox" name="' . $name . '" value="1" ' . checked(1, get_option($name), false) . ' />';
        } else {
            $html = '<input type="text" name="' . $name . '" value="' . (!empty(get_option($name)) ? get_option($name) : $default_value) . '"/>';
        }

        echo $html;
    }
}
