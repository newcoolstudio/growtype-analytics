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

    public $chart;
    public $registrations_chart;
    public $activation_chart;
    public $paywall_chart;
    public $retention_chart;
    public $funnel;
    public $scripts;
    public $metrics;
    public $decision_renderer;
    public $analytics_page;
    public $shared_report_page;
    public $utilities;
    public $contribution;
    public $reports;

    public function __construct($register_hooks = true)
    {
        $this->load_partial($register_hooks);
    }

    private function load_partial($register_hooks = true)
    {
        require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/partials/Growtype_Analytics_Admin_Decision_Renderer.php';
        $this->decision_renderer = new Growtype_Analytics_Admin_Decision_Renderer($this);

        require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/partials/Growtype_Analytics_Admin_Chart.php';
        $this->chart = new Growtype_Analytics_Admin_Chart($this);

        require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/partials/Growtype_Analytics_Admin_Registrations_Chart.php';
        $this->registrations_chart = new Growtype_Analytics_Admin_Registrations_Chart($this);

        require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/partials/Growtype_Analytics_Admin_Activation_Chart.php';
        $this->activation_chart = new Growtype_Analytics_Admin_Activation_Chart($this);

        require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/partials/Growtype_Analytics_Admin_Paywall_Chart.php';
        $this->paywall_chart = new Growtype_Analytics_Admin_Paywall_Chart($this);

        require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/partials/Growtype_Analytics_Admin_Retention_Chart.php';
        $this->retention_chart = new Growtype_Analytics_Admin_Retention_Chart($this);

        require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/partials/Growtype_Analytics_Admin_Funnel.php';
        $this->funnel = new Growtype_Analytics_Admin_Funnel($this);

        require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/partials/Growtype_Analytics_Admin_Scripts.php';
        $this->scripts = new Growtype_Analytics_Admin_Scripts($this);

        require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/partials/Growtype_Analytics_Admin_Metrics.php';
        $this->metrics = new Growtype_Analytics_Admin_Metrics($this);

        require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/partials/Growtype_Analytics_Admin_Utilities.php';
        $this->utilities = new Growtype_Analytics_Admin_Utilities($this);

        require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/partials/Growtype_Analytics_Admin_Contribution.php';
        $this->contribution = new Growtype_Analytics_Admin_Contribution($this);

        require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/partials/Growtype_Analytics_Admin_Reports.php';
        $this->reports = new Growtype_Analytics_Admin_Reports($this);

        // Load base page class
        require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/pages/Growtype_Analytics_Admin_Base_Page.php';

        // Include all pages in the folder
        foreach (glob(GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/pages/*.php') as $filename) {
            require_once $filename;
        }

        $this->analytics_page = new Growtype_Analytics_Admin_Page_Analytics($this, $this->decision_renderer, $register_hooks);
        $this->shared_report_page = new Growtype_Analytics_Admin_Page_Shared_Report($this, $this->decision_renderer);
    }

}