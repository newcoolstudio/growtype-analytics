<?php

class Growtype_Analytics_Admin_Settings
{
    private $settings_groups = [];

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
        $this->handle_share_access_actions();

        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'status';
        $tabs = $this->get_settings_tabs();

        if (!isset($tabs[$tab])) {
            $tab = 'status';
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
        if ($tab === 'share-access') {
            $this->render_share_access_tab();
        } elseif ($tab === 'status') {
            $this->render_status_tab();
        } else {
            echo '<form method="post" action="options.php">';

            settings_fields('analytics_options_settings'); // settings group name
            $this->render_settings_tab_sections($tab);
            submit_button();

            echo '</form>';
        }

        echo '</div>';
    }

    function growtype_analytics_register_settings()
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

    private function render_settings_tab_sections($tab)
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

    private function get_settings_tabs()
    {
        return [
            'status' => __('Status', 'growtype-analytics'),
            'tracking' => __('Tracking codes / credentials', 'growtype-analytics'),
            'decision' => __('Decision snapshot', 'growtype-analytics'),
            'share-access' => __('Shared access URLs', 'growtype-analytics'),
        ];
    }

    private function get_tab_description($tab)
    {
        $descriptions = [
            'tracking' => __('Manage tracking integrations and analytics credentials used across the project.', 'growtype-analytics'),
            'decision' => __('Configure the scale-or-pivot snapshot, churn logic, and contribution margin assumptions.', 'growtype-analytics'),
            'share-access' => __('Create read-only URLs you can share without giving wp-admin access.', 'growtype-analytics'),
            'status' => __('Verify the connection status of configured analytics services.', 'growtype-analytics'),
        ];

        return $descriptions[$tab] ?? '';
    }

    private function render_share_access_tab()
    {
        $links = $this->get_share_access_links();
        ?>
        <div class="analytics-share-access">
            <h2><?php _e('Create Shared Decision Report URL', 'growtype-analytics'); ?></h2>
            <p><?php _e('Each URL opens a read-only business report with the overview, execution KPIs, source attribution, funnel drop-off, offer tests, buyer cohorts, and contribution margin.', 'growtype-analytics'); ?></p>

            <form method="post" action="">
                <?php wp_nonce_field('growtype_analytics_generate_share_link', 'growtype_analytics_share_nonce'); ?>
                <input type="hidden" name="growtype_analytics_share_action" value="generate" />
                <table class="form-table" role="presentation">
                    <tbody>
                    <tr>
                        <th scope="row">
                            <label for="growtype_analytics_share_label"><?php _e('Label', 'growtype-analytics'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="growtype_analytics_share_label" name="growtype_analytics_share_label" class="regular-text" placeholder="<?php esc_attr_e('Investor read-only link', 'growtype-analytics'); ?>" />
                            <p class="description"><?php _e('Use labels to track who each link was created for.', 'growtype-analytics'); ?></p>
                        </td>
                    </tr>
                    </tbody>
                </table>
                <?php submit_button(__('Generate access URL', 'growtype-analytics'), 'primary', 'submit', false); ?>
            </form>

            <hr style="margin: 24px 0;" />

            <h2><?php _e('Existing Shared URLs', 'growtype-analytics'); ?></h2>
            <p class="description"><?php _e('Base URLs return JSON by default. Append &content_format=html for a human-readable report page.', 'growtype-analytics'); ?></p>
            <table class="widefat striped">
                <thead>
                <tr>
                    <th><?php _e('Label', 'growtype-analytics'); ?></th>
                    <th><?php _e('Access URL (JSON)', 'growtype-analytics'); ?></th>
                    <th><?php _e('Access URL (HTML)', 'growtype-analytics'); ?></th>
                    <th><?php _e('Created', 'growtype-analytics'); ?></th>
                    <th><?php _e('Last used', 'growtype-analytics'); ?></th>
                    <th><?php _e('Action', 'growtype-analytics'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($links)): ?>
                    <tr>
                        <td colspan="6"><?php _e('No shared access URLs yet.', 'growtype-analytics'); ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($links as $link): ?>
                        <tr>
                            <td><?php echo esc_html($link['label']); ?></td>
                            <td>
                                <input type="text" readonly class="large-text code" value="<?php echo esc_attr($link['url']); ?>" onclick="this.select();" />
                            </td>
                            <td>
                                <input type="text" readonly class="large-text code" value="<?php echo esc_attr($link['html_url']); ?>" onclick="this.select();" />
                            </td>
                            <td><?php echo esc_html($link['created_at']); ?></td>
                            <td><?php echo esc_html(!empty($link['last_used_at']) ? $link['last_used_at'] : __('Never', 'growtype-analytics')); ?></td>
                            <td>
                                <form method="post" action="" onsubmit="return confirm('<?php echo esc_js(__('Revoke this shared URL?', 'growtype-analytics')); ?>');">
                                    <?php wp_nonce_field('growtype_analytics_revoke_share_link', 'growtype_analytics_share_nonce'); ?>
                                    <input type="hidden" name="growtype_analytics_share_action" value="revoke" />
                                    <input type="hidden" name="growtype_analytics_share_link_id" value="<?php echo esc_attr($link['id']); ?>" />
                                    <?php submit_button(__('Revoke', 'growtype-analytics'), 'delete', 'submit', false); ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function handle_share_access_actions()
    {
        if (!current_user_can('manage_options') || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        if (empty($_POST['growtype_analytics_share_action'])) {
            return;
        }

        $action = sanitize_key(wp_unslash($_POST['growtype_analytics_share_action']));

        if ($action === 'generate') {
            check_admin_referer('growtype_analytics_generate_share_link', 'growtype_analytics_share_nonce');
            $links = $this->get_share_access_links();
            $label = isset($_POST['growtype_analytics_share_label']) ? sanitize_text_field(wp_unslash($_POST['growtype_analytics_share_label'])) : '';

            $links[] = [
                'id' => wp_generate_uuid4(),
                'label' => !empty($label) ? $label : __('Shared business report', 'growtype-analytics'),
                'token' => wp_generate_password(32, false, false),
                'created_at' => current_time('mysql'),
                'last_used_at' => '',
            ];

            update_option('growtype_analytics_share_access_links', $links, false);
        } elseif ($action === 'revoke') {
            check_admin_referer('growtype_analytics_revoke_share_link', 'growtype_analytics_share_nonce');
            $link_id = isset($_POST['growtype_analytics_share_link_id']) ? sanitize_text_field(wp_unslash($_POST['growtype_analytics_share_link_id'])) : '';
            $links = array_values(array_filter($this->get_share_access_links(), function ($link) use ($link_id) {
                return ($link['id'] ?? '') !== $link_id;
            }));

            update_option('growtype_analytics_share_access_links', $links, false);
        }
    }

    private function get_share_access_links()
    {
        $links = get_option('growtype_analytics_share_access_links', []);

        if (!is_array($links)) {
            $links = [];
        }

        return array_map(function ($link) {
            $token = $link['token'] ?? '';

            return [
                'id' => $link['id'] ?? wp_generate_uuid4(),
                'label' => $link['label'] ?? __('Shared business report', 'growtype-analytics'),
                'token' => $token,
                'created_at' => $link['created_at'] ?? '',
                'last_used_at' => $link['last_used_at'] ?? '',
                'url' => rest_url('growtype-analytics/v1/shared-report/' . rawurlencode($token)),
                'html_url' => add_query_arg(
                    array(
                        'content_format' => 'html',
                    ),
                    rest_url('growtype-analytics/v1/shared-report/' . rawurlencode($token))
                ),
            ];
        }, $links);
    }

    private function render_status_tab()
    {
        $gtm_enabled = get_option('growtype_analytics_gtm_details_enabled');
        $gtm_id = get_option('growtype_analytics_gtm_details_gtm_id');
        $ga4_id = get_option('growtype_analytics_ga4_details_ga4_id');
        $posthog_api_key = get_option('growtype_analytics_posthog_details_api_key');

        ?>
        <div class="analytics-config-status" style="margin-top: 2rem;">
            <table class="wp-list-table widefat fixed">
                <thead>
                <tr>
                    <th><?php _e('Service', 'growtype-analytics'); ?></th>
                    <th><?php _e('Status', 'growtype-analytics'); ?></th>
                    <th><?php _e('Configuration', 'growtype-analytics'); ?></th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td><strong>Google Tag Manager</strong></td>
                    <td>
                        <?php if ($gtm_enabled && !empty($gtm_id)): ?>
                            <span class="status-badge status-active">✓ Active</span>
                        <?php else: ?>
                            <span class="status-badge status-inactive">○ Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo !empty($gtm_id) ? esc_html($gtm_id) : __('Not configured', 'growtype-analytics'); ?></td>
                </tr>
                <tr>
                    <td><strong>Google Analytics 4</strong></td>
                    <td>
                        <?php if (!empty($ga4_id)): ?>
                            <span class="status-badge status-active">✓ Active</span>
                        <?php else: ?>
                            <span class="status-badge status-inactive">○ Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo !empty($ga4_id) ? esc_html($ga4_id) : __('Not configured', 'growtype-analytics'); ?></td>
                </tr>
                <tr>
                    <td><strong>PostHog</strong></td>
                    <td>
                        <?php if (!empty($posthog_api_key)): ?>
                            <span class="status-badge status-active">✓ Active</span>
                        <?php else: ?>
                            <span class="status-badge status-inactive">○ Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo !empty($posthog_api_key) ? __('Configured', 'growtype-analytics') : __('Not configured', 'growtype-analytics'); ?></td>
                </tr>
                </tbody>
            </table>
        </div>
        <?php
    }
}
