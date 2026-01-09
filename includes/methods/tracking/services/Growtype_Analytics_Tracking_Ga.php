<?php

class Growtype_Analytics_Tracking_Ga
{
    // transient TTLs (seconds)
    private const TRANSIENT_EVENTS_TTL = 300;            // 5 minutes - give time for redirects/caching
    private const TRANSIENT_REGISTRATION_FIRED_TTL = 10; // small lock to dedupe immediate duplicates
    private const TRANSIENT_RECENT_REGISTRATION_TTL = 30;// detect first-login-after-registration

    public function __construct()
    {
        // Core hooks (always)
        add_action('user_register', [$this, 'track_user_registration_event'], 99, 1);
        add_action('wp_login', [$this, 'track_user_login_event'], 10, 2);
        add_action('wp_footer', [$this, 'inject_gtm_scripts'], 100);

        // mark recent registration session flag (used by wp_login fallback)
        add_action('user_register', [$this, 'mark_registration_in_session'], 100, 1);

        add_action('woocommerce_payment_complete', [$this, 'track_payment_complete_event'], 10, 1);
        add_action('woocommerce_order_status_processing', [$this, 'track_payment_complete_event'], 10, 1);
        add_action('woocommerce_order_status_completed', [$this, 'track_payment_complete_event'], 10, 1);
    }

    /* -------------------------
     * Helpers: safe json encode
     * ------------------------- */
    private function json_encode_safe($data)
    {
        if (function_exists('wp_json_encode')) {
            return wp_json_encode($data);
        }

        return json_encode($data);
    }

    /* -----------------------------------------
     * Transient helpers: events + registration flags
     * ----------------------------------------- */

    private function events_transient_key($user_id)
    {
        if ($user_id === 'guest' || empty($user_id)) {
            if (class_exists('WooCommerce') && WC()->session && method_exists(WC()->session, 'get_customer_id')) {
                $session_id = WC()->session->get_customer_id();
                if ($session_id) {
                    return "gtm_events_guest_{$session_id}";
                }
            }
        }
        return "gtm_events_{$user_id}";
    }

    private function registration_fired_key($user_id)
    {
        return "gtm_registration_fired_{$user_id}";
    }

    private function recent_registration_key($user_id)
    {
        return "gtm_recent_registration_{$user_id}";
    }

    private function add_event_to_user_transient($user_id, $event_data)
    {
        $user_id = $user_id ?: 'guest';
        $key = $this->events_transient_key($user_id);
        $events = get_transient($key);
        if (!is_array($events)) {
            $events = [];
        }
        $events[] = $event_data;
        // store for longer to handle cached pages / redirects
        set_transient($key, $events, self::TRANSIENT_EVENTS_TTL);
    }

    private function mark_registration_event_fired($user_id)
    {
        set_transient($this->registration_fired_key($user_id), true, self::TRANSIENT_REGISTRATION_FIRED_TTL);
    }

    private function has_registration_event_fired($user_id)
    {
        return (bool) get_transient($this->registration_fired_key($user_id));
    }

    /* ------------------------------------------------
     * Mark recent registration (detect first-login fallback)
     * ------------------------------------------------ */
    public function mark_registration_in_session($user_id)
    {
        set_transient($this->recent_registration_key($user_id), true, self::TRANSIENT_RECENT_REGISTRATION_TTL);
    }

    /* ------------------------
     * Purchase (Woo) handling
     * ------------------------ */
    public function track_payment_complete_event($order_id)
    {
        
        // Safety: only run when WooCommerce present
        if (!class_exists('WooCommerce')) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        if ($order->get_meta('_growtype_analytics_purchase_tracked')) {
            return;
        }

        // Consider 'processing' or 'completed' as paid.
        if (!in_array($order->get_status(), ['processing', 'completed'], true)) {
            return;
        }

        // Mark as tracked before proceeding
        $order->update_meta_data('_growtype_analytics_purchase_tracked', 'yes');
        $order->save();

        $items = function_exists('growtype_wc_get_purchase_items_gtm') ? growtype_wc_get_purchase_items_gtm($order->get_items()) : [];
        $value = $order->get_total();
        $currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : '';
        $user_id = apply_filters('growtype_analytics_default_user_id', $order->get_user_id() ?: get_current_user_id());
        $email = function_exists('growtype_analytics_get_user_email') ? growtype_analytics_get_user_email() : '';

        $transaction_id = $order->get_order_number() ?: $order_id;

        $purchase_data = [
            'event' => 'growtype_analytics_growtype_wc_purchase',
            'user_id' => $user_id,
            'email'   => $email,
            'ecommerce' => [
                'transaction_id' => $transaction_id,
                'value'          => $value,
                'currency'       => $currency,
                'tax'            => '',
                'shipping'       => '',
                'coupon'         => '',
                'items'          => $items,
            ],
        ];

        $this->add_event_to_user_transient($user_id, $purchase_data);
    }

    /* ------------------------
     * Registration handling
     * ------------------------ */

    public function track_user_registration_event($user_id)
    {
        // dedupe immediate duplicate firing
        if ($this->has_registration_event_fired($user_id)) {
            return;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }

        $registration_data = [
            'event' => 'growtype_analytics_wp_user_registered',
            'user_id' => $user_id,
            'email'   => $user->user_email,
            'role'    => implode(', ', (array) $user->roles),
        ];

        $this->add_event_to_user_transient($user_id, $registration_data);
        $this->mark_registration_event_fired($user_id);
    }

    /* ------------------------
     * Login handling (with registration fallback)
     * ------------------------ */

    public function track_user_login_event($user_login, $user)
    {
        // normalise WP_User
        if (is_int($user)) {
            $user = get_userdata($user);
            if (!$user) {
                return;
            }
        }

        $user_id = (int) $user->ID;

        // if we detect a very recent registration, use fallback
        $recent_flag = get_transient($this->recent_registration_key($user_id));

        if ($recent_flag) {

            // If registration handler already fired: nothing more to do.
            if (!$this->has_registration_event_fired($user_id)) {
                // Post-register fallback: fire registration event now (roles may be final)
                $registration_data = [
                    'event' => 'growtype_analytics_wp_user_registered',
                    'user_id' => $user_id,
                    'email'   => $user->user_email,
                    'role'    => implode(', ', (array) $user->roles),
                ];

                $this->add_event_to_user_transient($user_id, $registration_data);
                $this->mark_registration_event_fired($user_id);
            }

            // clear recent registration marker and do not track login (this login was auto-login after register)
            delete_transient($this->recent_registration_key($user_id));
            return;
        }

        // Normal login event
        $login_data = [
            'event' => 'growtype_analytics_wp_user_login',
            'user_id' => $user_id,
            'email'   => $user->user_email,
            'role'    => implode(', ', (array) $user->roles),
        ];

        $this->add_event_to_user_transient($user_id, $login_data);
    }

    /* ------------------------
     * Footer injection (print JS dataLayer)
     * ------------------------ */

    public function inject_gtm_scripts()
    {
        $user_id = get_current_user_id() ?: 'guest';
        $key = $this->events_transient_key($user_id);
        $events = get_transient($key);
        // delete early to avoid duplicates between AJAX/footer combos
        delete_transient($key);

        if (!empty($events) && is_array($events)) {
            foreach ($events as $event) {
                $clear_history = isset($event['event']) && $event['event'] === 'growtype_analytics_growtype_wc_purchase';
                echo $this->generate_data_layer_script($event, $clear_history);
            }
        }

        // push checkout/begin-payment events only when Woo exists
        $this->push_checkout_and_payment_events();
    }

    /* ------------------------
     * Checkout / begin payment events (Woo)
     * ------------------------ */

    private function push_checkout_and_payment_events()
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        $is_checkout = function_exists('growtype_wc_is_checkout_page') ? growtype_wc_is_checkout_page() : is_checkout();
        $is_payment = function_exists('growtype_wc_is_payment_page') ? growtype_wc_is_payment_page() : is_wc_endpoint_url('order-pay');

        if (!$is_checkout && !$is_payment) {
            return;
        }

        $value = (WC()->cart->total ?? '');

        $currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : '';
        $items = function_exists('growtype_wc_get_cart_items_gtm') ? growtype_wc_get_cart_items_gtm() : [];
        $user_id = apply_filters('growtype_analytics_default_user_id', get_current_user_id());
        $email = function_exists('growtype_analytics_get_user_email') ? growtype_analytics_get_user_email() : '';

        $event_type = $is_checkout ? 'growtype_analytics_growtype_wc_begin_checkout' : 'growtype_analytics_growtype_wc_begin_payment';
        $event_data = [
            'event'    => $event_type,
            'value'    => $value,
            'currency' => $currency,
            'items'    => $items,
            'user_id'  => $user_id,
            'email'    => $email,
        ];

        echo $this->generate_data_layer_script($event_data);
    }

    /* ------------------------
     * Generate JS snippet
     * ------------------------ */

    private function generate_data_layer_script($data, $clear_history = false)
    {
        $json_data = $this->json_encode_safe($data);
        $event_name = isset($data['event']) ? $data['event'] : 'growtype_analytics_event';
        $event_name_js = $this->json_encode_safe($event_name);

        // ensure safe output (we already json-encoded)
        $script = "<script>\n";
        $script .= "if (typeof window.growtypeAnalyticsCapture === 'function') {\n";
        $script .= "  window.growtypeAnalyticsCapture($event_name_js, $json_data);\n";
        $script .= "} else {\n";
        // fallback: push to dataLayer if gtm is present
        $script .= "  window.dataLayer = window.dataLayer || [];\n";
        $script .= "  window.dataLayer.push({ event: $event_name_js, data: $json_data });\n";
        $script .= "}\n";

        if ($clear_history) {
            $script .= <<<JS
history.pushState(null, document.title, location.href);
window.addEventListener('popstate', function () {
    history.pushState(null, document.title, location.href);
});
JS;
        }

        $script .= "\n</script>\n";
        return $script;
    }
}
