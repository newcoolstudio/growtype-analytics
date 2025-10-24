<?php

class Growtype_Analytics_Tracking_Ga
{
    public function __construct()
    {
        add_action('woocommerce_payment_complete', [$this, 'track_payment_complete_event'], 10, 4);
        add_action('user_register', [$this, 'track_user_registration_event'], 10, 1);
        add_action('wp_login', [$this, 'track_user_login_event'], 10, 2);
        add_action('wp_footer', [$this, 'inject_gtm_scripts']);
    }

    private function add_event_to_transient($event_data)
    {
        $user_id = get_current_user_id() ?: 'guest';
        $key = "gtm_events_{$user_id}";
        $events = get_transient($key) ?: [];
        $events[] = $event_data;
        set_transient($key, $events, MINUTE_IN_SECONDS);
    }

    public function track_payment_complete_event($order_id, $transaction_id)
    {
        $order = wc_get_order($order_id);
        if (!$order || $order->get_status() !== 'completed') return;

        $items = growtype_wc_get_purchase_items_gtm($order->get_items());
        $value = $order->get_total();
        $currency = get_woocommerce_currency();
        $user_id = apply_filters('growtype_analytics_default_user_id', get_current_user_id());
        $email = growtype_analytics_get_user_email();
        $transaction_id = $transaction_id ?: $order->get_order_number();

        $purchase_data = [
            'event' => 'purchase',
            'user_id' => $user_id,
            'email' => $email,
            'ecommerce' => [
                'transaction_id' => $transaction_id,
                'value' => $value,
                'currency' => $currency,
                'tax' => '',
                'shipping' => '',
                'coupon' => '',
                'items' => $items,
            ],
        ];

        $this->add_event_to_transient($purchase_data);
    }

    public function track_user_registration_event($user_id)
    {
        $user = get_userdata($user_id);
        if (!$user) return;

        $registration_data = [
            'event' => 'user_registered',
            'user_id' => $user_id,
            'email' => $user->user_email,
            'role' => implode(', ', $user->roles),
        ];

        $this->add_event_to_transient($registration_data);
    }

    public function track_user_login_event($user_login, $user)
    {
        $login_data = [
            'event' => 'user_login',
            'user_id' => $user->ID,
            'email' => $user->user_email,
            'role' => implode(', ', $user->roles),
        ];

        $this->add_event_to_transient($login_data);
    }

    public function inject_gtm_scripts()
    {
        $user_id = get_current_user_id() ?: 'guest';
        $key = "gtm_events_{$user_id}";
        $events = get_transient($key);
        delete_transient($key);

        if (!empty($events)) {
            foreach ($events as $event) {
                $clear_history = isset($event['event']) && $event['event'] === 'purchase';
                echo $this->generate_data_layer_script($event, $clear_history);
            }
        }

        $this->push_checkout_and_payment_events();
    }

    private function push_checkout_and_payment_events()
    {
        $is_wc = class_exists('WooCommerce');
        $is_growtype_wc = class_exists('growtype_wc');
        $is_checkout = $is_growtype_wc && growtype_wc_is_checkout_page();
        $is_payment = $is_growtype_wc && growtype_wc_is_payment_page();

        if (!$is_wc || !$is_growtype_wc || (!$is_checkout && !$is_payment)) return;

        $value = WC()->cart->total ?? '';
        $currency = get_woocommerce_currency();
        $items = json_encode(growtype_wc_get_cart_items_gtm());
        $user_id = apply_filters('growtype_analytics_default_user_id', get_current_user_id());
        $email = growtype_analytics_get_user_email();

        $event_type = $is_checkout ? 'begin_checkout' : 'begin_payment';
        $event_data = [
            'event' => $event_type,
            'value' => $value,
            'currency' => $currency,
            'items' => $items,
            'user_id' => $user_id,
            'email' => $email,
        ];

        echo $this->generate_data_layer_script($event_data);
    }

    private function generate_data_layer_script($data, $clear_history = false)
    {
        $json_data = json_encode($data);
        $script = "<script>
            if (window.dataLayer) {
                window.dataLayer.push({$json_data});";

        if ($clear_history) {
            $script .= "
                history.pushState(null, document.title, location.href);
                window.addEventListener('popstate', function () {
                    history.pushState(null, document.title, location.href);
                });";
        }

        $script .= "}
        </script>";

        return $script;
    }
}
