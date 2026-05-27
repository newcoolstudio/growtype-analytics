<?php

class Growtype_Analytics_Admin_Page_Users extends Growtype_Analytics_Admin_Base_Page
{
    public function get_page_title()
    {
        return __('Users', 'growtype-analytics');
    }

    public function get_menu_title()
    {
        return __('Users', 'growtype-analytics');
    }

    public function get_menu_slug()
    {
        return 'growtype-analytics-users';
    }

    public function render_page()
    {
        if (isset($_GET['refresh']) && $_GET['refresh'] == '1') {
            $this->controller->metrics->bust_snapshot_cache();
            echo '<div class="notice notice-success is-dismissible" style="margin-top: 20px;"><p>' . __('Analytics data has been refreshed successfully.', 'growtype-analytics') . '</p></div>';
        }

        $this->render_page_header(__('Users Analytics', 'growtype-analytics'));

        $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : date('Y-m-d', strtotime('-30 days'));
        $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : date('Y-m-d');
        $marketing_spend = (float)get_option('growtype_analytics_snapshot_marketing_spend_30d', 0);
        $objective = get_option('growtype_analytics_growth_objective', '10x');

        $this->controller->decision_renderer->render_dashboard_filters($date_from, $date_to, $objective, $marketing_spend, 'growtype-analytics-users');
        
        $this->controller->decision_renderer->render_filter_pills($date_from, $date_to, 'growtype-analytics-users');

        // ── Registered Users table (AJAX-loaded) ─────────────────────────────
        ?>
        <div id="ga-users-table-container">
            <div id="ga-users-table-loading" style="padding:30px 0; text-align:center; color:#646970; font-size:14px;">
                <span class="spinner is-active" style="float:none; margin:0 8px 0 0; vertical-align:middle;"></span>
                <?php _e('Loading users…', 'growtype-analytics'); ?>
            </div>
        </div>
        <script>
        (function($) {
            var urlParams = new URLSearchParams(window.location.search);

            // Browser encodes name="user_filters[]" as user_filters[0], user_filters[1]...
            // so getAll('user_filters[]') returns empty — iterate all params instead
            var activeFilters = [];
            urlParams.forEach(function(value, key) {
                if (/^user_filters\[/.test(key)) {
                    activeFilters.push(value);
                }
            });

            $.post(ajaxurl, {
                action        : 'growtype_analytics_load_section',
                nonce         : '<?php echo wp_create_nonce('growtype_analytics_nonce'); ?>',
                section       : 'registered_users',
                date_from     : urlParams.get('date_from')   || '<?php echo esc_js($date_from); ?>',
                date_to       : urlParams.get('date_to')     || '<?php echo esc_js($date_to); ?>',
                orderby       : urlParams.get('orderby')     || 'registered',
                order         : urlParams.get('order')       || 'DESC',
                paged         : urlParams.get('paged')       || 1,
                user_search   : urlParams.get('user_search') || '',
                user_filters  : activeFilters.join(','),  // send as CSV string — no jQuery array serialization issues
            }, function(response) {
                if (response.success) {
                    $('#ga-users-table-container').html(response.data.html);
                } else {
                    $('#ga-users-table-container').html('<div class="notice notice-error"><p><?php echo esc_js(__('Failed to load users table.', 'growtype-analytics')); ?></p></div>');
                }
            }).fail(function() {
                $('#ga-users-table-container').html('<div class="notice notice-error"><p><?php echo esc_js(__('Request failed. Please refresh the page.', 'growtype-analytics')); ?></p></div>');
            });
        })(jQuery);
        </script>
        <?php


        $this->controller->chart->render();
        $this->controller->registrations_chart->render();
        $this->controller->retention_chart->render();

        $this->render_page_footer();
    }
}
