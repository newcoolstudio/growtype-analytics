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

        /**
         * Growtype form email page submit (backend CAPI CompleteRegistration)
         */
        add_action('growtype_form_email_page_submitted', array ($this, 'track_complete_registration'), 10, 2);

        /**
         * Growtype quiz complete (backend CAPI QuizComplete)
         */
        add_action('growtype_quiz_after_save_data', array ($this, 'track_quiz_complete_after_save'), 10, 2);
        add_action('growtype_quiz_after_update_data', array ($this, 'track_quiz_complete_after_update'), 10, 2);
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

        $testing_enabled = filter_var(getenv('GROWTYPE_ANALYTICS_FACEBOOK_EVENTS_TESTING_ENABLED'), FILTER_VALIDATE_BOOLEAN);
        $test_event_code = getenv('GROWTYPE_ANALYTICS_FACEBOOK_TEST_EVENT_CODE');

        if ($testing_enabled && !empty($test_event_code)) {
            $query_params['test_event_code'] = $test_event_code;

            error_log(sprintf('Growtype Analytics: Facebook event TEST EVENT CODE ENABLED: %s', $test_event_code));
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

    public function track_complete_registration($email, $context = [])
    {
        $email = is_string($email) ? trim($email) : '';

        if (empty($email) || !is_email($email)) {
            return;
        }

        $event_source_url = '';
        if (is_array($context) && !empty($context['event_source_url'])) {
            $event_source_url = $context['event_source_url'];
        }
        if (empty($event_source_url)) {
            $event_source_url = function_exists('home_url') ? home_url('/') : '';
        }

        $fb_data = [
            [
                'event_name' => 'CompleteRegistration',
                'event_time' => time(),
                'action_source' => 'website',
                'event_source_url' => $event_source_url,
                'user_data' => [
                    'client_ip_address' => growtype_analytics_get_client_ip(),
                    'client_user_agent' => growtype_analytics_get_client_user_agent(),
                    'em' => hash('sha256', strtolower($email)),
                    'fbc' => isset($_COOKIE['_fbc']) ? $_COOKIE['_fbc'] : '',
                ],
            ],
        ];

        $this->init_facebook_event($fb_data);
    }

    public function track_quiz_complete_after_save($quiz_id, $submitted_quiz_data = [])
    {
        $quiz_id = (int) $quiz_id;
        $this->track_quiz_complete($submitted_quiz_data, $quiz_id);
    }

    public function track_quiz_complete_after_update($existing_quiz_data = [], $submitted_quiz_data = [])
    {
        $quiz_id = isset($existing_quiz_data['quiz_id']) ? (int) $existing_quiz_data['quiz_id'] : 0;
        $this->track_quiz_complete($submitted_quiz_data, $quiz_id);
    }

    private function track_quiz_complete($submitted_quiz_data = [], $quiz_id = 0)
    {
        if (!is_array($submitted_quiz_data) || empty($submitted_quiz_data['answers'])) {
            return;
        }

        $extra_details = isset($submitted_quiz_data['extra_details']) && is_array($submitted_quiz_data['extra_details'])
            ? $submitted_quiz_data['extra_details']
            : [];

        $email = '';
        if (!empty($extra_details['email'])) {
            $candidate_email = sanitize_email($extra_details['email']);
            if (is_email($candidate_email)) {
                $email = strtolower(trim($candidate_email));
            }
        }

        $event_source_url = !empty($extra_details['http_referer'])
            ? esc_url_raw($extra_details['http_referer'])
            : (function_exists('home_url') ? home_url('/') : '');

        $fb_event = [
            'event_name' => 'QuizComplete',
            'event_time' => time(),
            'action_source' => 'website',
            'event_source_url' => $event_source_url,
            'user_data' => [
                'client_ip_address' => growtype_analytics_get_client_ip(),
                'client_user_agent' => growtype_analytics_get_client_user_agent(),
                'fbc' => isset($_COOKIE['_fbc']) ? $_COOKIE['_fbc'] : '',
            ],
            'custom_data' => [
                'quiz_id' => $quiz_id,
            ],
        ];

        if (!empty($email)) {
            $fb_event['user_data']['em'] = hash('sha256', $email);
        }

        $this->init_facebook_event([$fb_event]);
    }
}
