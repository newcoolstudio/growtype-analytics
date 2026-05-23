<?php

/**
 * Growtype Analytics – Frontend Documentation Site
 *
 * Pure constants + bootstrap. All logic lives in dedicated components.
 *
 *   /growtype-analytics/           → Metrics documentation (default)
 *   /growtype-analytics/metrics/   → Metrics documentation
 *   /growtype-analytics/strategy/  → Strategy documentation
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/includes/methods/documentation
 */
class Growtype_Analytics_Frontend_Page
{
    const SLUG        = 'growtype-analytics';
    const QUERY_VAR   = 'growtype_analytics_frontend';
    const PAGE_VAR    = 'ga_doc_page';
    const TOKEN_PARAM = 'ga_token';

    const PAGES = ['metrics', 'strategy'];

    public function __construct()
    {
        self::load_helpers();
        self::load_components();
        self::load_pages();

        $renderer = new Growtype_Analytics_Doc_Renderer();

        new Growtype_Analytics_Doc_Router();
        new Growtype_Analytics_Doc_Request_Handler($renderer);
    }

    private static function load_helpers(): void
    {
        require_once GROWTYPE_ANALYTICS_PATH . 'includes/methods/share/Growtype_Analytics_Share_Links_Helper.php';
    }

    private static function load_components(): void
    {
        require_once __DIR__ . '/components/Growtype_Analytics_Doc_Shared_Links.php';
        require_once __DIR__ . '/components/Growtype_Analytics_Doc_Layout.php';
        require_once __DIR__ . '/components/Growtype_Analytics_Doc_Renderer.php';
        require_once __DIR__ . '/router/Growtype_Analytics_Doc_Router.php';
        require_once __DIR__ . '/router/Growtype_Analytics_Doc_Request_Handler.php';
    }

    private static function load_pages(): void
    {
        require_once __DIR__ . '/pages/Growtype_Analytics_Frontend_Page_Metrics.php';
        require_once __DIR__ . '/pages/Growtype_Analytics_Frontend_Page_Strategy.php';
    }
}
