<?php

class Growtype_Analytics_Admin_Settings_Tab_Status extends Growtype_Analytics_Admin_Settings_Tab_Base
{
    public function get_id() { return 'status'; }
    public function get_label() { return __('Status', 'growtype-analytics'); }
    public function get_description() { return __('Verify the connection status of configured analytics services.', 'growtype-analytics'); }

    public function render()
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
