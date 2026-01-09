<?php

class Growtype_Analytics_Tracking_Wc
{
    public function __construct()
    {
        $this->setup_tracking();
    }

    public static function get_tracking_keys()
    {
        $keys = [
            'source_type',       // source_type

            'garef',       // growtype-affiliate ref

            // UTM Parameters (Standard)
            'utm_source',       // Referrer (e.g., google, facebook)
            'utm_medium',       // Marketing medium (e.g., cpc, email)
            'utm_campaign',     // Campaign name or identifier
            'utm_term',         // Paid search keyword
            'utm_content',      // Differentiator for A/B testing
            'utm_marketing_tactic', // Differentiator for A/B testing

            // Facebook Ads Parameters
            'fbclid',           // Facebook Click ID (used for tracking Facebook campaigns)

            // Google Ads Parameters
            'gclid',            // Google Ads Click ID
            'dclid',            // Display Ads Click ID (used in Display campaigns)

            // Microsoft Ads Parameters
            'msclkid',          // Microsoft Click ID (used for Bing Ads)

            // Twitter Ads Parameters
            'twclid',           // Twitter Click ID (used for Twitter campaigns)

            // TikTok Ads Parameters
            'ttclid',           // TikTok Click ID (used for TikTok campaigns)

            // LinkedIn Ads Parameters
            'li_fat_id',        // LinkedIn Ad tracking ID

            // Pinterest Ads Parameters
            'pinclick',         // Pinterest Click ID

            // Custom Parameters
            'ref',              // Custom referral source
            'aff_id',           // Affiliate ID
            'campaign_id',      // Custom campaign identifier
            'promo_code',       // Promotional code for discounts or offers
        ];

        return apply_filters('growtype_analytics_tracking_wc_keys', $keys);
    }

    public function setup_tracking()
    {
        add_action('woocommerce_init', [__CLASS__, 'set_tracking_params']);

        add_action('woocommerce_checkout_create_order', function ($order) {
            $tracking_params = self::get_tracking_params();

            foreach ($tracking_params as $tracking_param_key => $tracking_param_value) {
                if (!empty($tracking_param_value)) {
                    if (empty($tracking_params['utm_source'])) {

                        if ($tracking_param_key === 'garef') {
                            $tracking_param_key = 'utm';
                            $tracking_param_value = 'garef=' . $tracking_param_value;
                        }

                        $order->update_meta_data('_wc_order_attribution_source_type', $tracking_param_key);
                        $order->update_meta_data('_wc_order_attribution_utm_source', $tracking_param_value);
                    } else {
                        $order->update_meta_data('_wc_order_attribution_' . $tracking_param_key, $tracking_param_value);
                    }
                }
            }
        }, 10, 1);
    }

    public static function set_tracking_params()
    {
        $tracking_params = self::get_tracking_keys();

        foreach ($tracking_params as $tracking_param_key) {
            if (isset($_GET[$tracking_param_key])) {
                $param_value = sanitize_text_field($_GET[$tracking_param_key]);

                if (WC()->session) {
                    WC()->session->set($tracking_param_key, $param_value);
                }

                if (!isset($_COOKIE[$tracking_param_key]) || $_COOKIE[$tracking_param_key] !== $param_value) {
                    setcookie($tracking_param_key, $param_value, time() + DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
                }

                /**
                 * Set default source_type cookie if not set
                 */
                if (!isset($_GET['source_type']) && strpos($tracking_param_key, 'utm_') !== false) {
                    setcookie('source_type', 'utm', time() + DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
                }
            }
        }
    }

    public static function get_tracking_params()
    {
        $tracking_keys = self::get_tracking_keys();
        $tracking_values = [];

        foreach ($tracking_keys as $tracking_key) {
            $track_value = '';

            if (WC()->session) {
                $track_value = WC()->session->get($tracking_key);
            }

            if (!$track_value && isset($_COOKIE[$tracking_key])) {
                $track_value = sanitize_text_field($_COOKIE[$tracking_key]);
            }

            $tracking_values[$tracking_key] = $track_value;
        }

        return $tracking_values;
    }
}
