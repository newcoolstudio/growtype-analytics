<?php

class Growtype_Analytics_Tracking
{
    public function __construct()
    {
        /**
         * Load methods
         */
        $this->load_methods();
    }

    public function load_methods()
    {
        include_once GROWTYPE_ANALYTICS_PATH . 'includes/methods/tracking/user/Growtype_Analytics_Tracking_User.php';
        new Growtype_Analytics_Tracking_User();

        include_once GROWTYPE_ANALYTICS_PATH . 'includes/methods/tracking/scripts/Growtype_Analytics_Tracking_Scripts.php';
        new Growtype_Analytics_Tracking_Scripts();

        include_once GROWTYPE_ANALYTICS_PATH . 'includes/methods/tracking/services/Growtype_Analytics_Tracking_Ga.php';
        new Growtype_Analytics_Tracking_Ga();

        include_once GROWTYPE_ANALYTICS_PATH . 'includes/methods/tracking/services/Growtype_Analytics_Tracking_Fb.php';
        new Growtype_Analytics_Tracking_Fb();

        include_once GROWTYPE_ANALYTICS_PATH . 'includes/methods/tracking/services/Growtype_Analytics_Tracking_Posthog.php';
        new Growtype_Analytics_Tracking_Posthog();

        include_once GROWTYPE_ANALYTICS_PATH . 'includes/methods/tracking/services/Growtype_Analytics_Tracking_Wc.php';
        new Growtype_Analytics_Tracking_Wc();

        include_once GROWTYPE_ANALYTICS_PATH . 'includes/methods/tracking/system/Growtype_Analytics_Tracking_Pages.php';
        new Growtype_Analytics_Tracking_System_Pages();
    }
}
