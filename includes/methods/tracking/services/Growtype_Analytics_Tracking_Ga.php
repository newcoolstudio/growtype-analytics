<?php

class Growtype_Analytics_Tracking_Ga
{
    public function __construct()
    {
        // WooCommerce Payment Complete Event
        add_action('woocommerce_payment_complete', [$this, 'track_payment_complete_event'], 10, 4);

        // WordPress User Registration Event
        add_action('user_register', [$this, 'track_user_registration_event'], 10, 1);

        // Inject GTM Scripts in Footer
        add_action('wp_footer', [$this, 'inject_gtm_scripts']);
    }

    /**
     * Tracks WooCommerce payment complete events and stores the data for GTM.
     */
    public function track_payment_complete_event($order_id, $transaction_id)
    {
        $order = wc_get_order($order_id);

        if ($order) {

            if (!in_array($order->get_status(), ['completed'])) {
                return;
            }

            $order_items = $order->get_items();
            $value = $order->get_total();
            $currency = get_woocommerce_currency();
            $items = growtype_wc_get_purchase_items_gtm($order_items);
            $user_id = apply_filters('growtype_analytics_default_user_id', get_current_user_id());
            $email = growtype_analytics_get_user_email();

            if (empty($transaction_id)) {
                $transaction_id = $order->get_order_number();
            }

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

            set_transient('growtype_analytics_tracking_gtm_purchase_event_details', $purchase_data, MINUTE_IN_SECONDS);
        }
    }

    /**
     * Tracks user registration events and stores the data for GTM.
     */
    public function track_user_registration_event($user_id)
    {
        $user = get_userdata($user_id);

        if ($user) {
            $registration_data = [
                'event' => 'user_registered',
                'user_id' => $user_id,
                'email' => $user->user_email,
                'role' => implode(', ', $user->roles),
            ];

            set_transient('growtype_analytics_tracking_user_registration_event_details', $registration_data, MINUTE_IN_SECONDS);
        }
    }

    /**
     * Injects GTM scripts into the footer to push dataLayer events.
     */
    public function inject_gtm_scripts()
    {
        $this->push_extra_gtm_data();
        $this->push_purchase_event_data();
        $this->push_user_registration_event_data();
        $this->push_checkout_and_payment_events();
    }

    /**
     * Pushes additional GTM data from transient.
     */
    private function push_extra_gtm_data()
    {
        $extra_data = get_transient('growtype_analytics_tracking_gtm_extra_details');
        delete_transient('growtype_analytics_tracking_gtm_extra_details');

        if ($extra_data) {
            echo $this->generate_data_layer_script($extra_data);
        }
    }

    /**
     * Pushes purchase event data from transient.
     */
    private function push_purchase_event_data()
    {
        $purchase_data = get_transient('growtype_analytics_tracking_gtm_purchase_event_details');
        delete_transient('growtype_analytics_tracking_gtm_purchase_event_details');

        if ($purchase_data && class_exists('WooCommerce') && growtype_wc_is_thankyou_page()) {
            echo $this->generate_data_layer_script($purchase_data, true);
        }
    }

    /**
     * Pushes user registration event data from transient.
     */
    private function push_user_registration_event_data()
    {
        $registration_data = get_transient('growtype_analytics_tracking_user_registration_event_details');
        delete_transient('growtype_analytics_tracking_user_registration_event_details');

        if ($registration_data) {
            echo $this->generate_data_layer_script($registration_data);
        }
    }

    /**
     * Pushes checkout and payment page events based on the current page.
     */
    private function push_checkout_and_payment_events()
    {
        $value = class_exists('WooCommerce') && isset(WC()->cart->total) ? WC()->cart->total : '';
        $currency = class_exists('WooCommerce') ? get_woocommerce_currency() : '';
        $items = class_exists('WooCommerce') ? json_encode(growtype_wc_get_cart_items_gtm()) : [];
        $user_id = apply_filters('growtype_analytics_default_user_id', get_current_user_id());
        $email = growtype_analytics_get_user_email();

        if (class_exists('WooCommerce') && growtype_wc_is_checkout_page()) {
            $checkout_event = [
                'event' => 'begin_checkout',
                'value' => $value,
                'currency' => $currency,
                'items' => $items,
                'user_id' => $user_id,
                'email' => $email,
            ];
            echo $this->generate_data_layer_script($checkout_event);
        }

        if (class_exists('WooCommerce') && growtype_wc_is_payment_page()) {
            $payment_event = [
                'event' => 'begin_payment',
                'value' => $value,
                'currency' => $currency,
                'items' => $items,
                'user_id' => $user_id,
                'email' => $email,
            ];
            echo $this->generate_data_layer_script($payment_event);
        }
    }

    /**
     * Generates a <script> block to push data to the dataLayer.
     *
     * @param array $data Data to be pushed to the dataLayer.
     * @param bool $clear_history Whether to clear browser history to prevent duplicate events.
     * @return string The script block.
     */
    private function generate_data_layer_script($data, $clear_history = false)
    {
        $script = "<script>
            if (window.dataLayer) {
                window.dataLayer.push(" . json_encode($data) . ");
        ";

        if ($clear_history) {
            $script .= "
                history.pushState(null, document.title, location.href);
                window.addEventListener('popstate', function () {
                    history.pushState(null, document.title, location.href);
                });
            ";
        }

        $script .= "}
        </script>";

        return $script;
    }
}
