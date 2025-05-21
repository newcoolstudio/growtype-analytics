<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/public
 * @author     Your Name <email@example.com>
 */
class Growtype_Analytics_Public
{

    const GROWTYPE_ANALYTICS_AJAX_ACTION = 'growtype_analytics';

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $growtype_analytics The ID of this plugin.
     */
    private $growtype_analytics;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $version The current version of this plugin.
     */
    private $version;

    private $gtm_id;
    private $ga4_id;

    /**
     * Initialize the class and set its properties.
     *
     * @param string $growtype_analytics The name of the plugin.
     * @param string $version The version of this plugin.
     * @since    1.0.0
     */
    public function __construct($growtype_analytics, $version)
    {
        $this->growtype_analytics = $growtype_analytics;
        $this->version = $version;

        add_action('wp_head', array ($this, 'add_scripts_to_header'));
        add_action('wp_body_open', array ($this, 'add_scripts_to_body'));

        $this->gtm_id = get_option('growtype_analytics_gtm_details_gtm_id');
        $this->ga4_id = get_option('growtype_analytics_ga4_details_ga4_id');
    }

    function add_scripts_to_body()
    {
        if (!empty($this->gtm_id)) { ?>
            <!-- Google Tag Manager (noscript) -->
            <noscript>
                <iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo $this->gtm_id ?>"
                        height="0" width="0" style="display:none;visibility:hidden"></iframe>
            </noscript>
            <!-- End Google Tag Manager (noscript) -->
        <?php }
    }

    /***
     *
     */
    function add_scripts_to_header()
    {
        if (!empty($this->gtm_id)) { ?>
            <!-- Google Tag Manager -->
            <script data-cfasync="false">
                (function (w, d, s, l, i) {
                    w[l] = w[l] || [];
                    w[l].push({
                        'gtm.start':
                            new Date().getTime(), event: 'gtm.js'
                    });
                    var f = d.getElementsByTagName(s)[0],
                        j = d.createElement(s), dl = l != 'dataLayer' ? '&l=' + l : '';
                    j.async = true;
                    j.src =
                        '//www.googletagmanager.com/gtm.js?id=' + i + dl;
                    f.parentNode.insertBefore(j, f);
                })(window, document, 'script', 'dataLayer', '<?php echo $this->gtm_id ?>');
            </script>
            <!-- End Google Tag Manager -->
        <?php }

        if (!empty($this->ga4_id)) { ?>
            <!-- Google tag (gtag.js) -->
            <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo $this->ga4_id ?>"></script>
            <script>
                window.dataLayer = window.dataLayer || [];

                function gtag() {
                    dataLayer.push(arguments);
                }

                gtag('js', new Date());

                gtag('config', '<?php echo $this->ga4_id ?>');
            </script>
        <?php }
    }

    /**
     * Register the stylesheets for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public
    function enqueue_styles()
    {
        wp_enqueue_style($this->growtype_analytics, GROWTYPE_ANALYTICS_URL_PUBLIC . 'styles/growtype-analytics.css', array (), $this->version, 'all');
    }

    /**
     * Register the JavaScript for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts()
    {
        wp_enqueue_script($this->growtype_analytics, GROWTYPE_ANALYTICS_URL_PUBLIC . 'scripts/growtype-analytics.js', array ('jquery'), $this->version, true);

        $ajax_url = admin_url('admin-ajax.php');

        if (class_exists('QTX_Translator')) {
            $ajax_url = admin_url('admin-ajax.php' . '?lang=' . qtranxf_getLanguage());
        }

        wp_localize_script($this->growtype_analytics, 'growtype_analytics_ajax', array (
            'url' => $ajax_url,
            'nonce' => wp_create_nonce('ajax-nonce'),
            'action' => self::GROWTYPE_ANALYTICS_AJAX_ACTION,
            'user_id' => apply_filters('growtype_analytics_default_user_id', get_current_user_id()),
        ));
    }

}
