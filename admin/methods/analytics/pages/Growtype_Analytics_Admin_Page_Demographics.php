<?php

class Growtype_Analytics_Admin_Page_Demographics extends Growtype_Analytics_Admin_Base_Page
{
    public function get_page_title()
    {
        return __('Demographics', 'growtype-analytics');
    }

    public function get_menu_title()
    {
        return __('Demographics', 'growtype-analytics');
    }

    public function get_menu_slug()
    {
        return 'growtype-analytics-demographics';
    }

    public function render_page()
    {
        $this->render_page_header(__('Audience Demographics', 'growtype-analytics'));

        $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : date('Y-m-d', strtotime('-30 days'));
        $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : date('Y-m-d');
        $days = max(1, (int)((strtotime($date_to) - strtotime($date_from)) / 86400));

        $snapshot_settings = $this->controller->get_snapshot_settings();
        $objective = $snapshot_settings['growth_objective'] ?? '10x';
        $marketing_spend = $snapshot_settings['marketing_spend'] ?? 0;

        $this->controller->decision_renderer->render_dashboard_filters($date_from, $date_to, $objective, $marketing_spend, $this->get_menu_slug());

        $ph_service = $this->controller->posthog;

        if (!$ph_service->is_enabled()) {
            echo '<div class="notice notice-error"><p>' . __('PostHog is not enabled. Please configure your API key in settings.', 'growtype-analytics') . '</p></div>';
            $this->render_page_footer();
            return;
        }

        ?>
        <div class="analytics-scale-snapshot-grid" style="grid-template-columns: 1fr 1fr; align-items: start; margin-top: 20px;">
            <div class="analytics-section" style="margin-top: 0;">
                <h2><?php _e('Top Countries', 'growtype-analytics'); ?></h2>
                <p class="description"><?php printf(__('Active users by country in the last %d days.', 'growtype-analytics'), $days); ?></p>
                <div id="demo-countries">
                    <p><em><?php _e('Loading data from PostHog...', 'growtype-analytics'); ?></em></p>
                </div>
            </div>

            <div class="analytics-section" style="margin-top: 0;">
                <h2><?php _e('Gender Split', 'growtype-analytics'); ?></h2>
                <p class="description"><?php printf(__('Identified gender profile of active users in the last %d days.', 'growtype-analytics'), $days); ?></p>
                <div id="demo-genders">
                    <p><em><?php _e('Loading data from PostHog...', 'growtype-analytics'); ?></em></p>
                </div>
            </div>

            <div class="analytics-section" style="margin-top: 0;">
                <h2><?php _e('Age Distribution', 'growtype-analytics'); ?></h2>
                <p class="description"><?php printf(__('Age groups of active users in the last %d days.', 'growtype-analytics'), $days); ?></p>
                <div id="demo-ages">
                    <p><em><?php _e('Loading data from PostHog...', 'growtype-analytics'); ?></em></p>
                </div>
            </div>

            <div class="analytics-section" style="margin-top: 0;">
                <h2><?php _e('Device Types', 'growtype-analytics'); ?></h2>
                <p class="description"><?php printf(__('Most popular device types in the last %d days.', 'growtype-analytics'), $days); ?></p>
                <div id="demo-devices">
                    <p><em><?php _e('Loading data from PostHog...', 'growtype-analytics'); ?></em></p>
                </div>
            </div>

            <div class="analytics-section" style="margin-top: 0;">
                <h2><?php _e('Operating Systems', 'growtype-analytics'); ?></h2>
                <p class="description"><?php printf(__('Most popular OS platforms in the last %d days.', 'growtype-analytics'), $days); ?></p>
                <div id="demo-os">
                    <p><em><?php _e('Loading data from PostHog...', 'growtype-analytics'); ?></em></p>
                </div>
            </div>
        </div>

        <script>
            jQuery(document).ready(function ($) {
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'growtype_analytics_get_demographics',
                        nonce: '<?php echo wp_create_nonce('growtype-analytics'); ?>',
                        date_from: '<?php echo esc_js($date_from); ?>',
                        date_to: '<?php echo esc_js($date_to); ?>',
                        paged: '<?php echo isset($_GET['paged']) ? (int)$_GET['paged'] : 1; ?>'
                    },
                    success: function (response) {
                        if (response.success && response.data) {
                            $('#demo-countries').html(response.data.countries);
                            $('#demo-devices').html(response.data.devices);
                            $('#demo-os').html(response.data.os);

                            if (response.data.has_genders) {
                                $('#demo-genders').html(response.data.genders);
                            } else {
                                $('#demo-genders').html('<p><em><?php _e('No gender data tracked in PostHog for this period.', 'growtype-analytics'); ?></em></p>');
                            }

                            if (response.data.has_ages) {
                                $('#demo-ages').html(response.data.ages);
                            } else {
                                $('#demo-ages').html('<p><em><?php _e('No age data tracked in PostHog for this period.', 'growtype-analytics'); ?></em></p>');
                            }
                        } else {
                            var err = response.data && response.data.message ? response.data.message : '<?php _e('Failed to load data.', 'growtype-analytics'); ?>';
                            $('.analytics-section').find('div[id^="demo-"]').html('<p style="color:red;">' + err + '</p>');
                        }
                    },
                    error: function () {
                        $('.analytics-section').find('div[id^="demo-"]').html('<p style="color:red;"><?php _e('Network error. Failed to load PostHog data.', 'growtype-analytics'); ?></p>');
                    }
                });
            });
        </script>
        <?php

        $this->render_page_footer();
    }
}
