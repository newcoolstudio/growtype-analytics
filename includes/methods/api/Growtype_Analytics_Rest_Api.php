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
     * Initialize the class and set its properties.
     */
    public function __construct()
    {
        $this->load_partials();
    }

    /**
     * Load the API partials.
     */
    private function load_partials()
    {
        require_once GROWTYPE_ANALYTICS_PATH . 'includes/methods/api/partials/Growtype_Analytics_Rest_Api_User.php';
        $this->user_api = new Growtype_Analytics_Rest_Api_User();

        require_once GROWTYPE_ANALYTICS_PATH . 'includes/methods/api/partials/Growtype_Analytics_Rest_Api_Orders.php';
        $this->woocommerce_api = new Growtype_Analytics_Rest_Api_Orders();

        require_once GROWTYPE_ANALYTICS_PATH . 'includes/methods/api/partials/Growtype_Analytics_Rest_Api_Docs.php';
        $this->docs_api = new Growtype_Analytics_Rest_Api_Docs();

        require_once GROWTYPE_ANALYTICS_PATH . 'includes/methods/api/partials/Growtype_Analytics_Rest_Api_Chat.php';
        $this->chat_api = new Growtype_Analytics_Rest_Api_Chat();

        require_once GROWTYPE_ANALYTICS_PATH . 'includes/methods/api/partials/Growtype_Analytics_Rest_Api_Affiliate.php';
        $this->affiliate_api = new Growtype_Analytics_Rest_Api_Affiliate();

        require_once GROWTYPE_ANALYTICS_PATH . 'includes/methods/api/partials/Growtype_Analytics_Rest_Api_Quiz.php';
        $this->quiz_api = new Growtype_Analytics_Rest_Api_Quiz();

        require_once GROWTYPE_ANALYTICS_PATH . 'includes/methods/api/partials/Growtype_Analytics_Rest_Api_Payments.php';
        $this->payments_api = new Growtype_Analytics_Rest_Api_Payments();

        require_once GROWTYPE_ANALYTICS_PATH . 'includes/methods/api/partials/Growtype_Analytics_Rest_Api_Characters.php';
        $this->characters_api = new Growtype_Analytics_Rest_Api_Characters();
    }
}
