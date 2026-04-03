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
            'source_type', // source_type

            'garef', // growtype-affiliate ref

            // UTM Parameters (Standard)
            'utm_source', // Referrer (e.g., google, facebook)
            'utm_medium', // Marketing medium (e.g., cpc, email)
            'utm_campaign', // Campaign name or identifier
            'utm_term', // Paid search keyword
            'utm_content', // Differentiator for A/B testing
            'utm_marketing_tactic', // Differentiator for A/B testing

            // Facebook Ads Parameters
            'fbclid', // Facebook Click ID (used for tracking Facebook campaigns)

            // Google Ads Parameters
            'gclid', // Google Ads Click ID
            'dclid', // Display Ads Click ID (used in Display campaigns)

            // Microsoft Ads Parameters
            'msclkid', // Microsoft Click ID (used for Bing Ads)

            // Twitter Ads Parameters
            'twclid', // Twitter Click ID (used for Twitter campaigns)

            // TikTok Ads Parameters
            'ttclid', // TikTok Click ID (used for TikTok campaigns)

            // LinkedIn Ads Parameters
            'li_fat_id', // LinkedIn Ad tracking ID

            // Pinterest Ads Parameters
            'pinclick', // Pinterest Click ID

            // Custom Parameters
            'ref', // Custom referral source
            'aff_id', // Affiliate ID
            'campaign_id', // Custom campaign identifier
            'promo_code', // Promotional code for discounts or offers
        ];

        return apply_filters('growtype_analytics_tracking_wc_keys', $keys);
    }

    public function setup_tracking()
    {
        add_action('woocommerce_init', [__CLASS__, 'set_tracking_params']);

        add_action('woocommerce_before_shop_loop_item', [$this, 'track_product_shown']);
        add_action('woocommerce_shop_loop_item_title', [$this, 'track_product_shown']);
        add_action('woocommerce_before_single_product', [$this, 'track_product_shown']);

        /**
         * Manual tracking for custom renderers
         */
        add_filter('growtype_wc_render_products_after', [$this, 'track_shortcode_products'], 10, 3);

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

        add_action('wp_footer', [$this, 'track_subscription_modal']);
    }

    /**
     * Output JS to track subscription/paywall modal impressions.
     */
    public function track_subscription_modal()
    {
        ?>
        <script id="growtype-analytics-wc-modal-tracker">
            (function () {
                var modalEl = document.getElementById('growtypeWcSubscriptionModal');
                if (!modalEl) return;

                var REST_URL = '<?php echo esc_url_raw(rest_url('growtype-analytics/v1/track')); ?>';
                var modalTracked = false;

                modalEl.addEventListener('show.bs.modal', function () {
                    if (modalTracked) return;
                    modalTracked = true;
                    setTimeout(function () { modalTracked = false; }, 3600000);

                    var title = (modalEl.querySelector('.modal-title') || {}).innerText || '';
                    if (typeof fetch === 'function') {
                        fetch(REST_URL, {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({
                                event_type: 'subscription_modal_shown',
                                object_id: 'subscription_modal',
                                object_type: 'modal',
                                metadata: {title: title}
                            })
                        }).catch(function () {});
                    }
                });
            })();
        </script>
        <?php
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

    /**
     * Manual tracking for products in shortcodes
     */
    public function track_shortcode_products($render, $query_args, $params)
    {
        $product_ids = [];

        if (isset($query_args['post__in']) && !empty($query_args['post__in'])) {
            $product_ids = is_array($query_args['post__in']) ? $query_args['post__in'] : explode(',', $query_args['post__in']);
        }

        if (empty($product_ids) && isset($params['ids']) && !empty($params['ids'])) {
            $product_ids = is_array($params['ids']) ? $params['ids'] : explode(',', $params['ids']);
        }

        if (!empty($product_ids)) {
            foreach ($product_ids as $product_id) {
                $product = wc_get_product($product_id);
                if ($product) {
                    $render .= sprintf(
                        '<span class="growtype-analytics-track" data-event-type="%s" data-object-id="%s" data-object-type="%s" data-metadata=\'%s\' style="display:none;"></span>',
                        esc_attr('offer_shown'),
                        esc_attr($product->get_id()),
                        esc_attr('product'),
                        esc_attr(wp_json_encode(array ('name' => $product->get_name())))
                    );
                }
            }
        }

        return $render;
    }

    /**
     * Track when a product is shown
     */
    public function track_product_shown()
    {
        global $product;

        /**
         * Fallback for custom loops where global $product might not be set yet
         */
        if (!$product && function_exists('wc_get_product')) {
            $product = wc_get_product(get_the_ID());
        }

        if (!$product || !is_a($product, 'WC_Product')) {
            return;
        }

        $event_data = array (
            'event_type' => 'offer_shown',
            'object_id' => (string)$product->get_id(),
            'object_type' => 'product',
            'metadata' => array ('name' => $product->get_name())
        );

        printf(
            '<span class="growtype-analytics-track" data-event-type="%s" data-object-id="%s" data-object-type="%s" data-metadata=\'%s\' style="display:none;"></span>',
            esc_attr($event_data['event_type']),
            esc_attr($event_data['object_id']),
            esc_attr($event_data['object_type']),
            esc_attr(wp_json_encode($event_data['metadata']))
        );
    }
}
