<?php

/**
 * Top-level Analytics admin page
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/admin/methods/analytics/pages
 */

class Growtype_Analytics_Admin_Page_Analytics extends Growtype_Analytics_Admin_Base_Page
{
    /**
     * @var Growtype_Analytics_Admin_Decision_Renderer
     */
    private $decision_renderer;

    public function __construct($controller, $decision_renderer, $register_hooks = true)
    {
        parent::__construct($controller);
        $this->decision_renderer = $decision_renderer;
    }

    /**
     * @return Growtype_Analytics_Admin_Decision_Renderer
     */
    public function get_decision_renderer()
    {
        return $this->decision_renderer;
    }

    public function render_page()
    {
        if (isset($_GET['refresh']) && $_GET['refresh'] == '1') {
            $this->controller->metrics->bust_snapshot_cache();

            // Render a notice that data was refreshed
            echo '<div class="notice notice-success is-dismissible" style="margin-top: 20px;"><p>' . __('Analytics data has been refreshed successfully.', 'growtype-analytics') . '</p></div>';
        }

        $this->render_page_header(__('Analytics', 'growtype-analytics'));

        $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : date('Y-m-d', strtotime('-30 days'));
        $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : date('Y-m-d');
        $marketing_spend = (float)get_option('growtype_analytics_snapshot_marketing_spend_30d', 0);
        $objective = get_option('growtype_analytics_growth_objective', '10x');

        $this->decision_renderer->render_dashboard_filters($date_from, $date_to, $objective, $marketing_spend);
        ?>
        <div class="analytics-ajax-section" data-section="execution_kpis" data-date-from="<?php echo esc_attr($date_from); ?>" data-date-to="<?php echo esc_attr($date_to); ?>">
            <div class="ajax-loader-wrapper">
                <div class="ajax-spinner"></div>
                <p><?php _e('Loading Execution KPIs...', 'growtype-analytics'); ?></p>
            </div>
        </div>

        <div class="analytics-ajax-section" data-section="payment_failure" data-date-from="<?php echo esc_attr($date_from); ?>" data-date-to="<?php echo esc_attr($date_to); ?>">
            <div class="ajax-loader-wrapper">
                <div class="ajax-spinner"></div>
                <p><?php _e('Loading Payment Failure Data...', 'growtype-analytics'); ?></p>
            </div>
        </div>

        <div class="analytics-ajax-section" data-section="analytics_snapshot" data-date-from="<?php echo esc_attr($date_from); ?>" data-date-to="<?php echo esc_attr($date_to); ?>">
            <div class="ajax-loader-wrapper">
                <div class="ajax-spinner"></div>
                <p><?php _e('Loading Analytics Snapshot...', 'growtype-analytics'); ?></p>
            </div>
        </div>

        <div class="analytics-ajax-section" data-section="custom_kpis" data-date-from="<?php echo esc_attr($date_from); ?>" data-date-to="<?php echo esc_attr($date_to); ?>">
            <div class="ajax-loader-wrapper">
                <div class="ajax-spinner"></div>
                <p><?php _e('Loading Custom KPIs...', 'growtype-analytics'); ?></p>
            </div>
        </div>

        <div class="analytics-ajax-section" data-section="posthog_insights" data-date-from="<?php echo esc_attr($date_from); ?>" data-date-to="<?php echo esc_attr($date_to); ?>">
            <div class="ajax-loader-wrapper">
                <div class="ajax-spinner"></div>
                <p><?php _e('Loading PostHog Insights...', 'growtype-analytics'); ?></p>
            </div>
        </div>


        <div class="analytics-ajax-section" data-section="extra_sections" data-date-from="<?php echo esc_attr($date_from); ?>" data-date-to="<?php echo esc_attr($date_to); ?>">
            <div class="ajax-loader-wrapper">
                <div class="ajax-spinner"></div>
                <p><?php _e('Loading Extra Sections...', 'growtype-analytics'); ?></p>
            </div>
        </div>

        <?php
        $this->render_page_footer();

        
    }

}
