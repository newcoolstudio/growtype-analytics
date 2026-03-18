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
    public $scripts;
    public $metrics;
    public $utilities;
    public $contribution;
    public $reports;
    public $analytics_page;
    public $shared_report_page;

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
            $this->scripts,
            $this->metrics,
            $this->utilities,
            $this->contribution,
            $this->reports,
            $this->analytics_page,
            $this->shared_report_page
        );

        if ($this->hooks) {
            $this->analytics_page;
            $this->scripts;
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
        }
    }

    public function __get($name)
    {
        switch ($name) {
            case 'decision_renderer':
                require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/partials/Growtype_Analytics_Admin_Decision_Renderer.php';
                $this->decision_renderer = new Growtype_Analytics_Admin_Decision_Renderer($this);
                return $this->decision_renderer;
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
            case 'scripts':
                require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/partials/Growtype_Analytics_Admin_Scripts.php';
                $this->scripts = new Growtype_Analytics_Admin_Scripts($this);
                return $this->scripts;
            case 'metrics':
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
            case 'analytics_page':
                $this->ensure_pages_loaded();
                $this->analytics_page = new Growtype_Analytics_Admin_Page_Analytics($this, $this->decision_renderer, $this->hooks);
                return $this->analytics_page;
            case 'shared_report_page':
                $this->ensure_pages_loaded();
                $this->shared_report_page = new Growtype_Analytics_Admin_Page_Shared_Report($this, $this->decision_renderer);
                return $this->shared_report_page;
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

}