<?php

class Growtype_Analytics_Public
{
    const GROWTYPE_ANALYTICS_AJAX_ACTION = 'growtype_analytics';

    private $growtype_analytics;
    private $version;
    private $gtm_enabled;
    private $gtm_id;
    private $ga4_id;

    public function __construct($growtype_analytics, $version)
    {
        $this->growtype_analytics = $growtype_analytics;
        $this->version = $version;

        $this->gtm_enabled = get_option('growtype_analytics_gtm_details_enabled');

        $this->gtm_id = $this->gtm_enabled ? get_option('growtype_analytics_gtm_details_gtm_id') : null;

        $this->ga4_id = get_option('growtype_analytics_ga4_details_ga4_id');

        // Add scripts to <body> for better performance
        add_action('wp_body_open', array ($this, 'add_scripts_to_body_open'));
    }

    /**
     * Output GTM + GA4 scripts at wp_body_open
     */
    public function add_scripts_to_body_open()
    {
        // Google Tag Manager <script>
        if (!empty($this->gtm_id)) {
            echo "<!-- Google Tag Manager -->
<script data-cfasync=\"false\">
(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.defer=true;
j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;
f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','" . esc_js($this->gtm_id) . "');
</script>
<!-- End Google Tag Manager -->";
        }

        // Google Analytics 4 <script>
        if (!empty($this->ga4_id)) {
            echo "<!-- Google Analytics 4 -->
<script defer src=\"https://www.googletagmanager.com/gtag/js?id=" . esc_attr($this->ga4_id) . "\"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', '" . esc_js($this->ga4_id) . "');
</script>";
        }

        // GTM noscript fallback
        if (!empty($this->gtm_id)) {
            echo '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . esc_attr($this->gtm_id) . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>';
        }
    }

    /**
     * Enqueue styles
     */
    public function enqueue_styles()
    {
        wp_enqueue_style(
            $this->growtype_analytics,
            GROWTYPE_ANALYTICS_URL_PUBLIC . 'styles/growtype-analytics.css',
            array (),
            $this->version,
            'all'
        );
    }

    /**
     * Enqueue scripts
     */
    public function enqueue_scripts()
    {
        wp_enqueue_script(
            $this->growtype_analytics,
            GROWTYPE_ANALYTICS_URL_PUBLIC . 'scripts/growtype-analytics.js',
            array ('jquery'),
            $this->version,
            true
        );

        $ajax_url = admin_url('admin-ajax.php');

        if (class_exists('QTX_Translator')) {
            $ajax_url = admin_url('admin-ajax.php?lang=' . qtranxf_getLanguage());
        }

        wp_localize_script($this->growtype_analytics, 'growtype_analytics_ajax', array (
            'url' => $ajax_url,
            'nonce' => wp_create_nonce('ajax-nonce'),
            'action' => self::GROWTYPE_ANALYTICS_AJAX_ACTION,
            'user_id' => apply_filters('growtype_analytics_default_user_id', get_current_user_id()),
        ));
    }
}
