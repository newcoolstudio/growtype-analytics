<?php

class Growtype_Analytics_Admin_Settings_Fields
{
    private $settings_groups = [];

    public function __construct()
    {
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function register_settings()

    {
        $inputs = [
            [
                'name' => 'GTM details',
                'value' => 'growtype_analytics_gtm_details',
                'tab' => 'tracking',
                'options' => [
                    [
                        'title' => 'Enabled',
                        'name' => 'enabled',
                        'type' => 'checkbox',
                        'default_value' => false
                    ],
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
                'tab' => 'tracking',
                'options' => [
                    [
                        'title' => 'GA4 ID',
                        'name' => 'ga4_id',
                        'type' => 'input',
                        'default_value' => ''
                    ]
                ]
            ],
            [
                'name' => 'PostHog details',
                'value' => 'growtype_analytics_posthog_details',
                'tab' => 'tracking',
                'options' => [
                    [
                        'title' => 'API Key',
                        'name' => 'api_key',
                        'type' => 'input',
                        'default_value' => ''
                    ],
                    [
                        'title' => 'Project ID',
                        'name' => 'project_id',
                        'type' => 'input',
                        'default_value' => ''
                    ],
                    [
                        'title' => 'Host URL',
                        'name' => 'host',
                        'type' => 'input',
                        'default_value' => 'https://app.posthog.com'
                    ]
                ]
            ],
            [
                'name' => 'Decision snapshot',
                'value' => 'growtype_analytics_snapshot',
                'tab' => 'decision',
                'options' => [
                    [
                        'title' => 'Excluded email patterns',
                        'name' => 'excluded_email_patterns',
                        'type' => 'textarea',
                        'default_value' => '%@talkiemate.com'
                    ],
                    [
                        'title' => 'Paid order statuses',
                        'name' => 'paid_statuses',
                        'type' => 'input',
                        'default_value' => 'wc-completed,wc-processing'
                    ],
                    [
                        'title' => 'Order attempt statuses',
                        'name' => 'attempt_statuses',
                        'type' => 'input',
                        'default_value' => 'wc-completed,wc-processing,wc-pending,wc-failed,wc-cancelled'
                    ],
                    [
                        'title' => 'Activation message threshold',
                        'name' => 'activation_min_messages',
                        'type' => 'number',
                        'default_value' => 3
                    ],
                    [
                        'title' => 'Activation window days',
                        'name' => 'activation_window_days',
                        'type' => 'number',
                        'default_value' => 1
                    ],
                    [
                        'title' => 'Churn inactivity days',
                        'name' => 'churn_inactivity_days',
                        'type' => 'number',
                        'default_value' => 14
                    ],
                    [
                        'title' => 'Recent payer lookback days',
                        'name' => 'recent_payer_window_days',
                        'type' => 'number',
                        'default_value' => 90
                    ],
                    [
                        'title' => 'Marketing spend (30d)',
                        'name' => 'marketing_spend_30d',
                        'type' => 'input',
                        'default_value' => '0'
                    ],
                    [
                        'title' => 'Marketing spend by source',
                        'name' => 'marketing_spend_by_source',
                        'type' => 'textarea',
                        'default_value' => ''
                    ]
                ]
            ],
            [
                'name' => 'Contribution margin',
                'value' => 'growtype_analytics_margin',
                'tab' => 'decision',
                'options' => [
                    [
                        'title' => 'Payment fee percent',
                        'name' => 'payment_fee_percent',
                        'type' => 'input',
                        'default_value' => '3.5'
                    ],
                    [
                        'title' => 'Payment fee fixed',
                        'name' => 'payment_fee_fixed',
                        'type' => 'input',
                        'default_value' => '0.30'
                    ],
                    [
                        'title' => 'AI cost per active user (30d)',
                        'name' => 'ai_cost_per_active_user',
                        'type' => 'input',
                        'default_value' => '0'
                    ],
                    [
                        'title' => 'Media cost per paid order',
                        'name' => 'media_cost_per_paid_order',
                        'type' => 'input',
                        'default_value' => '0'
                    ],
                    [
                        'title' => 'Revenue share percent',
                        'name' => 'revenue_share_percent',
                        'type' => 'input',
                        'default_value' => '0'
                    ],
                    [
                        'title' => 'Monthly infra cost',
                        'name' => 'monthly_infra_cost',
                        'type' => 'input',
                        'default_value' => '0'
                    ],
                    [
                        'title' => 'Known chargeback count (30d)',
                        'name' => 'known_chargeback_count_30d',
                        'type' => 'input',
                        'default_value' => '0'
                    ],
                    [
                        'title' => 'Known chargeback amount (30d)',
                        'name' => 'known_chargeback_amount_30d',
                        'type' => 'input',
                        'default_value' => '0'
                    ]
                ]
            ]
        ];

        foreach ($inputs as $input) {
            $key_name = $input['name'];
            $key_value = $input['value'];
            $tab = $input['tab'] ?? 'tracking';
            $options = $input['options'];

            $this->settings_groups[$tab][] = [
                'title' => $key_name,
                'section_id' => $key_value . '_options_settings',
            ];

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
        } elseif ($type === 'textarea') {
            $value = !empty(get_option($name)) ? get_option($name) : $default_value;
            $html = '<textarea name="' . esc_attr($name) . '" rows="4" cols="50" class="large-text code">' . esc_textarea($value) . '</textarea>';
        } elseif ($type === 'number') {
            $value = get_option($name);
            $value = $value !== false && $value !== '' ? $value : $default_value;
            $html = '<input type="number" min="0" step="1" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '"/>';
        } else {
            $value = !empty(get_option($name)) ? get_option($name) : $default_value;
            $html = '<input type="text" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '"/>';
        }

        echo $html;
    }

    public function render_tab_sections($tab)

    {
        global $wp_settings_sections, $wp_settings_fields;

        $page = 'growtype-analytics-settings';
        $groups = $this->settings_groups[$tab] ?? [];

        foreach ($groups as $group) {
            $section_id = $group['section_id'];
            $section = $wp_settings_sections[$page][$section_id] ?? null;

            if (!$section) {
                continue;
            }

            echo '<h2>' . esc_html($group['title']) . '</h2>';

            if (!empty($section['callback'])) {
                call_user_func($section['callback'], $section);
            }

            echo '<table class="form-table" role="presentation">';
            do_settings_fields($page, $section_id);
            echo '</table>';
        }
    }

}
