<?php

/**
 * Growtype Analytics REST API
 *
 * Handles registration of REST API routes for the plugin by delegating to partials.
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/includes/methods/api
 */

class Growtype_Analytics_Rest_Api
{
    /**
     * @var Growtype_Analytics_Rest_Api_User
     */
    private $user_api;

    /**
     * @var Growtype_Analytics_Rest_Api_Orders
     */
    private $woocommerce_api;

    /**
     * @var Growtype_Analytics_Rest_Api_Docs
     */
    private $docs_api;

    /**
     * @var Growtype_Analytics_Rest_Api_Chat
     */
    private $chat_api;

    /**
     * @var Growtype_Analytics_Rest_Api_Affiliate
     */
    private $affiliate_api;

    /**
     * @var Growtype_Analytics_Rest_Api_Quiz
     */
    private $quiz_api;

    /**
     * @var Growtype_Analytics_Rest_Api_Payments
     */
    private $payments_api;

    /**
     * @var Growtype_Analytics_Rest_Api_Characters
     */
    private $characters_api;

    /**
     * @var Growtype_Analytics_Rest_Api_Retention
     */
    private $retention_api;

    /**
     * @var Growtype_Analytics_Rest_Api_Economy
     */
    private $economy_api;

    /**
     * @var Growtype_Analytics_Rest_Api_Sharing
     */
    private $sharing_api;

    /**
     * @var Growtype_Analytics_Rest_Api_Tracking
     */
    private $tracking_api;

    /**
     * Initialize the class and set its properties.
     */
    public function __construct()
    {
        // Force load sharing_api because it contains a global filter for 'rest_authentication_errors'
        // needed to bypass sitewide locks on the shared report endpoint.
        $this->get_sharing_api();

        // Force load tracking_api early so the /track route is always available
        $this->get_tracking_api();

        // Register other routes on demand
        add_action('rest_api_init', array($this, 'register_remaining_routes'));
    }

    public function register_remaining_routes()
    {
        $this->get_user_api();
        $this->get_woocommerce_api();
        $this->get_docs_api();
        $this->get_chat_api();
        $this->get_affiliate_api();
        $this->get_quiz_api();
        $this->get_payments_api();
        $this->get_characters_api();
        $this->get_retention_api();
        $this->get_economy_api();
    }

    public function get_user_api() {
        if (!$this->user_api) {
            require_once GROWTYPE_ANALYTICS_PATH . 'includes/methods/api/partials/Growtype_Analytics_Rest_Api_User.php';
            $this->user_api = new Growtype_Analytics_Rest_Api_User();
        }
        return $this->user_api;
    }

    public function get_woocommerce_api() {
        if (!$this->woocommerce_api) {
            require_once GROWTYPE_ANALYTICS_PATH . 'includes/methods/api/partials/Growtype_Analytics_Rest_Api_Orders.php';
            $this->woocommerce_api = new Growtype_Analytics_Rest_Api_Orders();
        }
        return $this->woocommerce_api;
    }

    public function get_docs_api() {
        if (!$this->docs_api) {
            require_once GROWTYPE_ANALYTICS_PATH . 'includes/methods/api/partials/Growtype_Analytics_Rest_Api_Docs.php';
            $this->docs_api = new Growtype_Analytics_Rest_Api_Docs();
        }
        return $this->docs_api;
    }

    public function get_chat_api() {
        if (!$this->chat_api) {
            require_once GROWTYPE_ANALYTICS_PATH . 'includes/methods/api/partials/Growtype_Analytics_Rest_Api_Chat.php';
            $this->chat_api = new Growtype_Analytics_Rest_Api_Chat();
        }
        return $this->chat_api;
    }

    public function get_affiliate_api() {
        if (!$this->affiliate_api) {
            require_once GROWTYPE_ANALYTICS_PATH . 'includes/methods/api/partials/Growtype_Analytics_Rest_Api_Affiliate.php';
            $this->affiliate_api = new Growtype_Analytics_Rest_Api_Affiliate();
        }
        return $this->affiliate_api;
    }

    public function get_quiz_api() {
        if (!$this->quiz_api) {
            require_once GROWTYPE_ANALYTICS_PATH . 'includes/methods/api/partials/Growtype_Analytics_Rest_Api_Quiz.php';
            $this->quiz_api = new Growtype_Analytics_Rest_Api_Quiz();
        }
        return $this->quiz_api;
    }

    public function get_payments_api() {
        if (!$this->payments_api) {
            require_once GROWTYPE_ANALYTICS_PATH . 'includes/methods/api/partials/Growtype_Analytics_Rest_Api_Payments.php';
            $this->payments_api = new Growtype_Analytics_Rest_Api_Payments();
        }
        return $this->payments_api;
    }

    public function get_characters_api() {
        if (!$this->characters_api) {
            require_once GROWTYPE_ANALYTICS_PATH . 'includes/methods/api/partials/Growtype_Analytics_Rest_Api_Characters.php';
            $this->characters_api = new Growtype_Analytics_Rest_Api_Characters();
        }
        return $this->characters_api;
    }

    public function get_retention_api() {
        if (!$this->retention_api) {
            require_once GROWTYPE_ANALYTICS_PATH . 'includes/methods/api/partials/Growtype_Analytics_Rest_Api_Retention.php';
            $this->retention_api = new Growtype_Analytics_Rest_Api_Retention();
        }
        return $this->retention_api;
    }

    public function get_economy_api() {
        if (!$this->economy_api) {
            require_once GROWTYPE_ANALYTICS_PATH . 'includes/methods/api/partials/Growtype_Analytics_Rest_Api_Economy.php';
            $this->economy_api = new Growtype_Analytics_Rest_Api_Economy();
        }
        return $this->economy_api;
    }

    public function get_sharing_api() {
        if (!$this->sharing_api) {
            require_once GROWTYPE_ANALYTICS_PATH . 'includes/methods/api/partials/Growtype_Analytics_Rest_Api_Sharing.php';
            $this->sharing_api = new Growtype_Analytics_Rest_Api_Sharing();
        }
        return $this->sharing_api;
    }

    public function get_tracking_api() {
        if (!$this->tracking_api) {
            require_once GROWTYPE_ANALYTICS_PATH . 'includes/methods/api/partials/Growtype_Analytics_Rest_Api_Tracking.php';
            $this->tracking_api = new Growtype_Analytics_Rest_Api_Tracking();
        }
        return $this->tracking_api;
    }
}
