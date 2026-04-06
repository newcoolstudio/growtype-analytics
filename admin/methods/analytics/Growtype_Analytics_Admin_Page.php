<?php

/**
 * Analytics Admin Page
 *
 * Handles the analytics dashboard page in WordPress admin
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/admin/methods/analytics
 */

require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/traits/MetricsTrait.php';
require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/traits/ReportsTrait.php';
require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/traits/ContributionTrait.php';
require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/traits/UtilitiesTrait.php';

class Growtype_Analytics_Admin_Page
{
    use Growtype_Analytics_Admin_Page_Metrics_Trait;
    use Growtype_Analytics_Admin_Page_Reports_Trait;
    use Growtype_Analytics_Admin_Page_Contribution_Trait;
    use Growtype_Analytics_Admin_Page_Utilities_Trait;

    public $hooks;
    public $decision_renderer;
    public $chart;
    public $registrations_chart;
    public $activation_chart;
    public $paywall_chart;
    public $retention_chart;
    public $funnel;
    public $metrics;
    public $utilities;
    public $contribution;
    public $reports;
    public $analytics_page;
    public $shared_report_page;
    public $posthog;
    public $menu;
    public $table_renderer;
    public $submenu_pages = array();


    public function __construct($register_hooks = true)
    {
        $this->hooks = $register_hooks;

        // Unset all lazy-loaded properties so that __get() is triggered upon first access.
        // This allows traits and external callers to trigger the load on-demand.
        unset(
            $this->decision_renderer,
            $this->chart,
            $this->registrations_chart,
            $this->activation_chart,
            $this->paywall_chart,
            $this->retention_chart,
            $this->funnel,
            $this->metrics,
            $this->utilities,
            $this->contribution,
            $this->reports,
            $this->analytics_page,
            $this->shared_report_page,
            $this->posthog,
            $this->menu,
            $this->table_renderer
        );

        if ($this->hooks) {
            $this->menu;
            $this->analytics_page;
            $this->metrics;
            $this->chart;
            $this->registrations_chart;
            $this->activation_chart;
            $this->paywall_chart;
            $this->retention_chart;
            $this->funnel;
            $this->utilities;
            $this->contribution;
            $this->reports;
            $this->decision_renderer;
            $this->table_renderer;
            $this->posthog;

            add_action('wp_ajax_growtype_analytics_toggle_pinned_kpi', array($this, 'ajax_toggle_pinned_kpi'));
            add_action('wp_ajax_growtype_analytics_save_pinned_kpi_order', array($this, 'ajax_save_pinned_kpi_order'));
            add_action('wp_ajax_growtype_analytics_load_section', array($this, 'ajax_load_section'));
            add_action('admin_post_growtype_analytics_update_quick_spend', array($this, 'handle_quick_spend_update'));
        }
    }

    public function __get($name)
    {
        switch ($name) {
            case 'decision_renderer':
                require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/partials/Growtype_Analytics_Admin_User_Filters.php';
                require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/partials/Growtype_Analytics_Admin_Decision_Renderer.php';
                $this->decision_renderer = new Growtype_Analytics_Admin_Decision_Renderer($this);
                return $this->decision_renderer;
            case 'table_renderer':
                require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/partials/Growtype_Analytics_Admin_Table_Renderer.php';
                $this->table_renderer = new Growtype_Analytics_Admin_Table_Renderer($this);
                return $this->table_renderer;
            case 'chart':
                require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/partials/Growtype_Analytics_Admin_Chart.php';
                $this->chart = new Growtype_Analytics_Admin_Chart($this);
                return $this->chart;
            case 'registrations_chart':
                require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/partials/Growtype_Analytics_Admin_Registrations_Chart.php';
                $this->registrations_chart = new Growtype_Analytics_Admin_Registrations_Chart($this);
                return $this->registrations_chart;
            case 'activation_chart':
                require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/partials/Growtype_Analytics_Admin_Activation_Chart.php';
                $this->activation_chart = new Growtype_Analytics_Admin_Activation_Chart($this);
                return $this->activation_chart;
            case 'paywall_chart':
                require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/partials/Growtype_Analytics_Admin_Paywall_Chart.php';
                $this->paywall_chart = new Growtype_Analytics_Admin_Paywall_Chart($this);
                return $this->paywall_chart;
            case 'retention_chart':
                require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/partials/Growtype_Analytics_Admin_Retention_Chart.php';
                $this->retention_chart = new Growtype_Analytics_Admin_Retention_Chart($this);
                return $this->retention_chart;
            case 'funnel':
                require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/partials/Growtype_Analytics_Admin_Funnel.php';
                $this->funnel = new Growtype_Analytics_Admin_Funnel($this);
                return $this->funnel;
            case 'metrics':
                if (!class_exists('Growtype_Analytics_Admin_User_Filters')) {
                    require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/partials/Growtype_Analytics_Admin_User_Filters.php';
                }
                require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/partials/Growtype_Analytics_Admin_Metrics.php';
                $this->metrics = new Growtype_Analytics_Admin_Metrics($this);
                return $this->metrics;
            case 'utilities':
                require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/partials/Growtype_Analytics_Admin_Utilities.php';
                $this->utilities = new Growtype_Analytics_Admin_Utilities($this);
                return $this->utilities;
            case 'contribution':
                require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/partials/Growtype_Analytics_Admin_Contribution.php';
                $this->contribution = new Growtype_Analytics_Admin_Contribution($this);
                return $this->contribution;
            case 'reports':
                require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/partials/Growtype_Analytics_Admin_Reports.php';
                $this->reports = new Growtype_Analytics_Admin_Reports($this);
                return $this->reports;
            case 'posthog':
                require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/services/Growtype_Analytics_Admin_Posthog_Service.php';
                $this->posthog = new Growtype_Analytics_Admin_Posthog_Service($this);
                return $this->posthog;
            case 'analytics_page':
                $this->ensure_pages_loaded();
                $this->analytics_page = new Growtype_Analytics_Admin_Page_Analytics($this, $this->decision_renderer, $this->hooks);
                return $this->analytics_page;
            case 'shared_report_page':
                $this->ensure_pages_loaded();
                $this->shared_report_page = new Growtype_Analytics_Admin_Page_Shared_Report($this, $this->decision_renderer);
                return $this->shared_report_page;
            case 'menu':
                require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/partials/Growtype_Analytics_Admin_Menu.php';
                $this->menu = new Growtype_Analytics_Admin_Menu($this);
                return $this->menu;
        }

        return null;
    }

    private function ensure_pages_loaded()
    {
        if (!class_exists('Growtype_Analytics_Admin_Base_Page')) {
            require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/pages/Growtype_Analytics_Admin_Base_Page.php';
            require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/pages/Growtype_Analytics_Admin_Page_Analytics.php';
            require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/pages/Growtype_Analytics_Admin_Page_Shared_Report.php';
            // Other pages are loaded on demand via get_page_by_class
        }
    }

    public function get_page_by_class($class_name)
    {
        if (!class_exists($class_name)) {
            $file_map = array(
                'Growtype_Analytics_Admin_Page_Source_Attribution' => 'Growtype_Analytics_Admin_Page_Source_Attribution.php',
                'Growtype_Analytics_Admin_Page_Contribution_Margin' => 'Growtype_Analytics_Admin_Page_Contribution_Margin.php',
            );
            
            if (isset($file_map[$class_name])) {
                require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/pages/' . $file_map[$class_name];
            } else {
                // Fallback for others
                $filename = GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/pages/' . $class_name . '.php';
                if (file_exists($filename)) {
                    require_once $filename;
                }
            }
        }

        if (class_exists($class_name)) {
            return new $class_name($this, $this->decision_renderer);
        }

        return null;
    }

    public function get_submenu_pages()
    {
        if (empty($this->submenu_pages)) {
            $this->load_submenu_pages();
            $this->submenu_pages = apply_filters('growtype_analytics_submenu_pages', $this->submenu_pages, $this);
        }
        return $this->submenu_pages;
    }

    private function load_submenu_pages()
    {
        $dir = GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/pages/';
        foreach (glob($dir . 'Growtype_Analytics_Admin_Page_*.php') as $filename) {
            $class_name = basename($filename, '.php');

            // Skip base class and special pages
            if (in_array($class_name, array('Growtype_Analytics_Admin_Base_Page', 'Growtype_Analytics_Admin_Page_Analytics', 'Growtype_Analytics_Admin_Page_Shared_Report'))) {
                continue;
            }

            require_once $filename;

            if (class_exists($class_name)) {
                $this->submenu_pages[] = new $class_name($this);
            }
        }
    }
    public function ajax_save_pinned_kpi_order()
    {
        check_ajax_referer('growtype_analytics_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $order = isset($_POST['order']) ? array_map('sanitize_text_field', (array)$_POST['order']) : array();

        if (!empty($order)) {
            update_option('growtype_analytics_pinned_kpis', $order);
            $this->metrics->bust_snapshot_cache();
            wp_send_json_success(array('order' => $order));
        }

        wp_send_json_error('Invalid order data');
    }

    public function handle_quick_spend_update()
    {
        check_admin_referer('growtype_analytics_quick_spend');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $spend = isset($_POST['marketing_spend']) ? (float)$_POST['marketing_spend'] : 0;
        $objective = isset($_POST['growth_objective']) ? sanitize_text_field($_POST['growth_objective']) : '10x';

        update_option('growtype_analytics_snapshot_marketing_spend_30d', $spend);
        update_option('growtype_analytics_growth_objective', $objective);
        
        $this->metrics->bust_snapshot_cache();

        wp_redirect(remove_query_arg(array('action', '_wpnonce'), wp_get_referer()));
        exit;
    }

    public function ajax_toggle_pinned_kpi()
    {
        check_ajax_referer('growtype_analytics_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $kpi_id = sanitize_text_field($_POST['kpi_id']);
        $pinned_kpis = get_option('growtype_analytics_pinned_kpis', array('registered_users_total', 'payment_success_rate', 'new_user_to_buyer_conversion', 'user_try_to_buy_rate', 'repurchase_rate_total'));

        if (in_array($kpi_id, $pinned_kpis)) {
            $pinned_kpis = array_diff($pinned_kpis, array($kpi_id));
        } else {
            $pinned_kpis[] = $kpi_id;
        }

        update_option('growtype_analytics_pinned_kpis', array_values($pinned_kpis));
        $this->metrics->bust_snapshot_cache();
        wp_send_json_success(array('id' => $kpi_id, 'is_pinned' => in_array($kpi_id, $pinned_kpis)));
    }

    public function ajax_load_section()
    {
        if (session_id()) {
            session_write_close();
        }

        check_ajax_referer('growtype_analytics_nonce', 'nonce');

        $section = sanitize_text_field($_POST['section'] ?? '');
        $date_from = sanitize_text_field($_POST['date_from'] ?? '');
        $date_to = sanitize_text_field($_POST['date_to'] ?? '');

        // Override GET parameters for the metrics logic
        $_GET['date_from'] = $date_from;
        $_GET['date_to'] = $date_to;

        ob_start();

        switch ($section) {
            case 'execution_kpis':
                ?>
                <div class="analytics-section">
                    <h2><?php _e('Execution KPIs', 'growtype-analytics'); ?></h2>
                    <p class="description"><?php _e('Core business metrics for tracking short-term growth and payment health.', 'growtype-analytics'); ?></p>
                    <?php
                    $this->decision_renderer->render_execution_kpis();
                    ?>
                </div>
                <?php
                break;
            case 'payment_failure':
                ?>
                <div class="analytics-section">
                    <h2><?php _e('Payment Failure Segmentation', 'growtype-analytics'); ?></h2>
                    <p class="description"><?php _e('Breakdown of unsuccessful payment attempts by device, country, and gateway.', 'growtype-analytics'); ?></p>
                    <?php
                    $this->decision_renderer->render_payment_failure_segmentation();
                    ?>
                </div>
                <?php
                break;
            case 'analytics_snapshot':
                ?>
                <div class="analytics-section">
                    <h2><?php _e('Analytics Overview', 'growtype-analytics'); ?></h2>
                    <p class="description"><?php _e('Core business snapshot across users, activation, conversion, revenue, and retention.', 'growtype-analytics'); ?></p>
                    <?php
                    $this->decision_renderer->render_analytics_snapshot();
                    ?>
                </div>
                <?php
                break;
            case 'custom_kpis':
                $this->decision_renderer->render_custom_kpi_section(
                    __('User Behavior & Limits (Custom)', 'growtype-analytics'),
                    __('Insights from plugins.', 'growtype-analytics'),
                    $date_from,
                    $date_to
                );
                break;
            case 'posthog_insights':
                $this->posthog->render_posthog_insights($date_from, $date_to);
                break;
            case 'extra_sections':
                do_action('growtype_analytics_render_extra_sections', $date_from, $date_to, $this->decision_renderer);
                break;
        }

        $html = ob_get_clean();

        wp_send_json_success(array('html' => $html));
    }
}