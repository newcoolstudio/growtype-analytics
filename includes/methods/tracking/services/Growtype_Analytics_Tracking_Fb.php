<?php

class Growtype_Analytics_Tracking_Fb
{
    public function __construct()
    {
        /**
         * Set FB cookie params
         */
        add_action('wp_loaded', array ($this, 'set_fb_cookie_params'));

        /**
         * Woocommerce
         */
        add_action('woocommerce_payment_complete', array ($this, 'woocommerce_payment_complete_extend'), 10, 4);
        add_action('woocommerce_order_status_processing', array ($this, 'woocommerce_payment_complete_extend'), 10, 1);
        add_action('woocommerce_order_status_completed', array ($this, 'woocommerce_payment_complete_extend'), 10, 1);
    }

    function set_fb_cookie_params()
    {
        if (isset($_GET['fbclid'])) {
            setcookie('_fbc', 'fb.1.' . time() . '.' . $_GET['fbclid'], time() + 365 * 24 * 60 * 60, '/');
        }
    }

    function woocommerce_payment_complete_extend($order_id, $transaction_id = null)
    {
        $order = wc_get_order($order_id);

        if (!empty($order)) {
            if ($order->get_meta('_growtype_analytics_fb_purchase_tracked')) {
                return;
            }

            if (!in_array($order->get_status(), ['processing', 'completed'])) {
                return;
            }

            // Mark as tracked before proceeding
            $order->update_meta_data('_growtype_analytics_fb_purchase_tracked', 'yes');
            $order->save();

            $order_items = $order->get_items();
            $value = $order->get_total();
            $currency = get_woocommerce_currency();
            $email = growtype_analytics_get_user_email();

            /**
             * Init facebook purchase event
             */
            $product_data = [];
            foreach ($order_items as $order_item) {
                $product = $order_item->get_product();

                $product_data[] = [
                    'id' => $product->get_id(),
                    'quantity' => $order_item->get_quantity(),
                    'title' => $order_item->get_name(),
                ];
            }

            $phone = $order->get_billing_phone();

            $fb_data = [
                [
                    'event_name' => 'Purchase',
                    'event_time' => time(),
                    'action_source' => 'website',
                    'event_source_url' => growtype_analytics_get_current_url(),
                    'user_data' => [
                        'client_ip_address' => growtype_analytics_get_client_ip(),
                        'client_user_agent' => growtype_analytics_get_client_user_agent(),
                        'em' => hash('sha256', $email),
                        'ph' => isset($phone) ? hash('sha256', preg_replace('/\D/', '', $phone)) : '',
                        'fbc' => isset($_COOKIE['_fbc']) ? $_COOKIE['_fbc'] : '',
                        'country' => isset($order) ? strtoupper($order->get_billing_country()) : ''
                    ],
                    'custom_data' => [
                        'currency' => $currency,
                        'value' => $value,
                        'contents' => $product_data,
                    ]
                ],
            ];

            $this->init_facebook_event($fb_data);
        }
    }

    public function init_facebook_event($data)
    {
        $pixel_id = apply_filters('growtype_analytics_facebook_pixel_id', getenv('GROWTYPE_ANALYTICS_FACEBOOK_PIXEL_ID'));
        $access_token = apply_filters('growtype_analytics_facebook_access_token', getenv('GROWTYPE_ANALYTICS_FACEBOOK_ACCESS_TOKEN'));

        if (empty($pixel_id) || empty($access_token)) {
            return;
        }

        error_log(sprintf('Growtype Analytics: Facebook event FIRED: %s', json_encode($data)));

        $url = "https://graph.facebook.com/v18.0/{$pixel_id}/events";

        $query_params = [
            'access_token' => $access_token,
            'data' => json_encode($data)
        ];

        if (!empty(getenv('GROWTYPE_ANALYTICS_FACEBOOK_TEST_EVENT_CODE'))) {
            $query_params['test_event_code'] = getenv('GROWTYPE_ANALYTICS_FACEBOOK_TEST_EVENT_CODE');

            error_log(sprintf('Growtype Analytics: Facebook event TEST EVENT CODE ENABLED: %s', getenv('GROWTYPE_ANALYTICS_FACEBOOK_TEST_EVENT_CODE')));
        }

        $payload_encoded = http_build_query($query_params);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload_encoded);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        error_log(sprintf('Growtype Analytics: Facebook event RESPONSE: %s', $response));

        if ($response === false) {
            error_log(curl_error($ch));
        }

        curl_close($ch);

        return $response;
    }
}
