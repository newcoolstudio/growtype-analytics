<?php

class Growtype_Analytics_Admin_Settings_Tab_Share_Access extends Growtype_Analytics_Admin_Settings_Tab_Base
{
    public function get_id() { return 'share-access'; }
    public function get_label() { return __('Shared access URLs', 'growtype-analytics'); }
    public function get_description() { return __('Create read-only URLs you can share without giving wp-admin access.', 'growtype-analytics'); }

    private static $report_types = [
        'home'     => 'Analytics Home',
        'metrics'  => 'Metrics',
        'strategy' => 'Strategy',
    ];

    public function __construct()
    {
        require_once GROWTYPE_ANALYTICS_PATH . 'includes/methods/share/Growtype_Analytics_Share_Links_Helper.php';
    }

    public function handle_actions()
    {
        if (!current_user_can('manage_options') || wp_unslash($_SERVER['REQUEST_METHOD']) !== 'POST') {
            return;
        }

        if (empty($_POST['growtype_analytics_share_action'])) {
            return;
        }

        $action = sanitize_key(wp_unslash($_POST['growtype_analytics_share_action']));

        if ($action === 'generate') {
            check_admin_referer('growtype_analytics_generate_share_link', 'growtype_analytics_share_nonce');
            $links = $this->get_links_raw();
            $label = isset($_POST['growtype_analytics_share_label']) ? sanitize_text_field(wp_unslash($_POST['growtype_analytics_share_label'])) : '';
            $report_type = isset($_POST['growtype_analytics_share_report_type']) ? sanitize_key(wp_unslash($_POST['growtype_analytics_share_report_type'])) : 'metrics';

            if (!array_key_exists($report_type, self::$report_types)) {
                $report_type = 'metrics';
            }

            $links[] = [
                'id'          => wp_generate_uuid4(),
                'label'       => !empty($label) ? $label : __('Shared business report', 'growtype-analytics'),
                'report_type' => $report_type,
                'token'       => wp_generate_password(32, false, false),
                'created_at'  => current_time('mysql'),
                'last_used_at' => '',
            ];

            update_option('growtype_analytics_share_access_links', $links, false);
        } elseif ($action === 'revoke') {
            check_admin_referer('growtype_analytics_revoke_share_link', 'growtype_analytics_share_nonce');
            $link_id = isset($_POST['growtype_analytics_share_link_id']) ? sanitize_text_field(wp_unslash($_POST['growtype_analytics_share_link_id'])) : '';
            $links = array_values(array_filter($this->get_links_raw(), function ($link) use ($link_id) {
                return ($link['id'] ?? '') !== $link_id;
            }));

            update_option('growtype_analytics_share_access_links', $links, false);
        }
    }

    public function render()
    {
        $links = $this->get_links();
        ?>
        <div class="analytics-share-access">
            <h2><?php _e('Create Shared Report URL', 'growtype-analytics'); ?></h2>
            <p><?php _e('Each URL opens a read-only report. Choose the report type to share.', 'growtype-analytics'); ?></p>

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
                    <tr>
                        <th scope="row">
                            <label for="growtype_analytics_share_report_type"><?php _e('Report type', 'growtype-analytics'); ?></label>
                        </th>
                        <td>
                            <select id="growtype_analytics_share_report_type" name="growtype_analytics_share_report_type">
                                <?php foreach (self::$report_types as $type_key => $type_label): ?>
                                    <option value="<?php echo esc_attr($type_key); ?>"><?php echo esc_html($type_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <strong>Analytics Home</strong> — links directly to <code>/growtype-analytics/</code> (requires admin login).<br />
                                <strong>Metrics</strong> — business analytics report (KPIs, funnel, cohorts).<br />
                                <strong>Strategy</strong> — your saved strategy answers and AI prompt context.
                            </p>
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
                    <th><?php _e('Type', 'growtype-analytics'); ?></th>
                    <th><?php _e('Access URL', 'growtype-analytics'); ?></th>
                    <th><?php _e('Created', 'growtype-analytics'); ?></th>
                    <th><?php _e('Last used', 'growtype-analytics'); ?></th>
                    <th><?php _e('Action', 'growtype-analytics'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($links)): ?>
                    <tr><td colspan="6"><?php _e('No shared access URLs yet.', 'growtype-analytics'); ?></td></tr>
                <?php
                $badge_colors = [
                    'home'     => ['bg' => '#fff3e0', 'color' => '#e65100'],
                    'metrics'  => ['bg' => '#e3f2fd', 'color' => '#1565c0'],
                    'strategy' => ['bg' => '#e8f5e9', 'color' => '#2e7d32'],
                ];
                ?>
                <?php else: ?>
                    <?php foreach ($links as $idx => $link):
                        $json_id = 'ga-admin-json-' . $idx;
                        $html_id = 'ga-admin-html-' . $idx;
                        $bc      = $badge_colors[$link['report_type']] ?? ['bg' => '#f5f5f5', 'color' => '#333'];
                    ?>
                        <tr>
                            <td><?php echo esc_html($link['label']); ?></td>
                            <td>
                                <span style="display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; text-transform: uppercase; background: <?php echo esc_attr($bc['bg']); ?>; color: <?php echo esc_attr($bc['color']); ?>;">
                                    <?php echo esc_html(self::$report_types[$link['report_type']] ?? $link['report_type']); ?>
                                </span>
                            </td>
                            <td>
                                <input id="<?php echo esc_attr($json_id); ?>" type="text" readonly class="large-text code" value="<?php echo esc_attr($link['url']); ?>" onclick="this.select();" />
                                <input id="<?php echo esc_attr($html_id); ?>" type="hidden" value="<?php echo esc_attr($link['html_url']); ?>" />
                            </td>
                            <td><?php echo esc_html($link['created_at']); ?></td>
                            <td><?php echo esc_html(!empty($link['last_used_at']) ? $link['last_used_at'] : __('Never', 'growtype-analytics')); ?></td>
                            <td style="white-space:nowrap; vertical-align:middle;">
                                <button type="button" class="button" onclick="gaAdminCopy('<?php echo esc_attr($json_id); ?>', this)" style="margin:0 4px 4px 0;"><?php _e('Copy URL', 'growtype-analytics'); ?></button>
                                <button type="button" class="button" onclick="gaAdminCopy('<?php echo esc_attr($html_id); ?>', this)" style="margin:0 4px 4px 0;"><?php _e('Copy HTML URL', 'growtype-analytics'); ?></button>
                                <form method="post" action="" onsubmit="return confirm('<?php echo esc_js(__('Revoke this shared URL?', 'growtype-analytics')); ?>');" style="display:inline;">
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
            <script>
            function gaAdminCopy(inputId, btn) {
                var el = document.getElementById(inputId);
                if (!el) return;
                var val = el.value;
                navigator.clipboard ? navigator.clipboard.writeText(val) : (el.select && el.select(), document.execCommand('copy'));
                var orig = btn.textContent;
                btn.textContent = '\u2713 Copied';
                setTimeout(function() { btn.textContent = orig; }, 2000);
            }
            </script>
        </div>
        <?php
    }

    private function get_links_raw(): array
    {
        $links = get_option('growtype_analytics_share_access_links', []);
        return is_array($links) ? $links : [];
    }

    private function get_links(): array
    {
        return Growtype_Analytics_Share_Links_Helper::get_links();
    }
}
