<?php

/**
 * PostHog Analytics Handler
 * Handles all PostHog API interactions and data processing
 */
class Growtype_Analytics_User_PostHog
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->register_hooks();
    }

    /**
     * Register WordPress hooks
     */
    private function register_hooks()
    {
        // Register AJAX handler
        add_action('wp_ajax_get_user_posthog_data', array ($this, 'ajax_get_user_data'));

        // Register analytics section renderer
        add_action('growtype_analytics_user_analytics_sections', array ($this, 'render_analytics_section'), 10);
    }

    /**
     * Fetch data from PostHog API for a specific user
     */
    public function fetch_user_data($user_email)
    {
        // Get PostHog API credentials from settings
        $api_key = get_option('growtype_analytics_posthog_details_api_key', '');
        $project_id = get_option('growtype_analytics_posthog_details_project_id', '');
        $host = get_option('growtype_analytics_posthog_details_host', 'https://app.posthog.com');

        if (empty($api_key) || empty($project_id)) {
            return new WP_Error('missing_credentials', __('PostHog API credentials are not configured. Please configure them in Settings > Growtype - Analytics.', 'growtype-analytics'));
        }

        // Query PostHog API for events related to this user email
        $api_url = trailingslashit($host) . 'api/projects/' . $project_id . '/events/';

        $response = wp_remote_get($api_url, array (
            'headers' => array (
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'timeout' => 30,
            'body' => array (
                'properties' => json_encode(array (
                    array (
                        'key' => 'email',
                        'value' => $user_email,
                        'operator' => 'exact'
                    )
                )),
                'limit' => 50
            )
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (wp_remote_retrieve_response_code($response) !== 200) {
            return new WP_Error('api_error', __('PostHog API returned an error: ', 'growtype-analytics') . ($data['detail'] ?? 'Unknown error'));
        }

        // Fetch person data
        $person_url = trailingslashit($host) . 'api/projects/' . $project_id . '/persons/';

        $person_response = wp_remote_get($person_url, array (
            'headers' => array (
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'timeout' => 30,
            'body' => array (
                'email' => $user_email
            )
        ));

        $person_data = array ();
        if (!is_wp_error($person_response) && wp_remote_retrieve_response_code($person_response) === 200) {
            $person_body = wp_remote_retrieve_body($person_response);
            $person_data = json_decode($person_body, true);
        }

        // Analyze drop-off points
        $dropoff = $this->analyze_dropoff($data['results'] ?? array ());

        // Build user journey
        $journey = $this->build_journey($data['results'] ?? array ());

        // Build conversion funnel
        $funnel = $this->build_funnel($data['results'] ?? array (), $user_email);

        return array (
            'events' => $data['results'] ?? array (),
            'properties' => $person_data['results'][0]['properties'] ?? array (),
            'sessions' => array (
                'total_events' => count($data['results'] ?? array ()),
                'last_seen' => $person_data['results'][0]['created_at'] ?? null
            ),
            'dropoff' => $dropoff,
            'journey' => $journey,
            'funnel' => $funnel
        );
    }

    /**
     * Build conversion funnel from events
     *
     * @param array $events PostHog events
     * @param string $user_email User email to check WooCommerce orders
     */
    private function build_funnel($events, $user_email = '')
    {
        if (empty($events)) {
            return array ();
        }

        // Define default funnel steps (can be extended by other plugins)
        $funnel_steps = array (
            array (
                'id' => 'landing',
                'name' => 'Landing Page',
                'icon' => '🚀',
                'completed' => false,
                'url' => '',
                'priority' => 10
            ),
            array (
                'id' => 'registration',
                'name' => 'Registration',
                'icon' => '📝',
                'completed' => false,
                'url' => '',
                'priority' => 20
            ),
            array (
                'id' => 'checkout',
                'name' => 'Checkout',
                'icon' => '🛒',
                'completed' => false,
                'url' => '',
                'priority' => 30
            ),
            array (
                'id' => 'purchase',
                'name' => 'Purchase',
                'icon' => '💰',
                'completed' => false,
                'url' => '',
                'priority' => 40
            )
        );

        /**
         * Filter to allow other plugins to add custom funnel steps
         *
         * @param array $funnel_steps Array of funnel step definitions
         * @param array $events Array of PostHog events
         *
         * Example usage in another plugin:
         * add_filter('growtype_analytics_funnel_steps', function($steps, $events) {
         *     $steps[] = array(
         *         'id' => 'first_chat',
         *         'name' => 'First Chat',
         *         'icon' => '💬',
         *         'completed' => false,
         *         'url' => '',
         *         'priority' => 25
         *     );
         *     return $steps;
         * }, 10, 2);
         */
        $funnel_steps = apply_filters('growtype_analytics_funnel_steps', $funnel_steps, $events);

        // Sort steps by priority
        usort($funnel_steps, function ($a, $b) {
            return ($a['priority'] ?? 0) - ($b['priority'] ?? 0);
        });

        // Check which steps were completed
        foreach ($events as $event) {
            $event_name = $event['event'] ?? '';
            $props = $event['properties'] ?? array ();

            // Check each funnel step
            foreach ($funnel_steps as &$step) {
                if ($step['completed']) {
                    continue; // Skip if already completed
                }

                /**
                 * Filter to determine if a funnel step is completed
                 *
                 * @param bool $completed Whether the step is completed
                 * @param array $step The funnel step definition
                 * @param array $event The current event being processed
                 *
                 * Example usage:
                 * add_filter('growtype_analytics_funnel_step_completed', function($completed, $step, $event) {
                 *     if ($step['id'] === 'first_chat' && strpos($event['properties']['$pathname'] ?? '', '/chat/') !== false) {
                 *         return true;
                 *     }
                 *     return $completed;
                 * }, 10, 3);
                 */
                $is_completed = apply_filters('growtype_analytics_funnel_step_completed', false, $step, $event);

                if ($is_completed) {
                    $step['completed'] = true;
                    $step['url'] = $props['$current_url'] ?? '';
                    continue;
                }

                // Default completion logic for built-in steps
                switch ($step['id']) {
                    case 'landing':
                        if (!empty($props['$session_entry_pathname'])) {
                            $step['completed'] = true;
                            $step['url'] = $props['$session_entry_url'] ?? '';
                        }
                        break;

                    case 'registration':
                        if ($event_name === 'growtype_analytics_wp_user_registered' ||
                            $event_name === 'growtype_analytics_wp_user_login') {
                            $step['completed'] = true;
                            $step['url'] = $props['$current_url'] ?? '';
                        }
                        break;

                    case 'checkout':
                        if (strpos($props['$pathname'] ?? '', '/checkout') !== false ||
                            $event_name === 'woocommerce_checkout_started' ||
                            $event_name === 'growtype_analytics_growtype_wc_begin_payment') {
                            $step['completed'] = true;
                            $step['url'] = $props['$current_url'] ?? '';
                        }
                        break;

                    case 'purchase':
                        // Check PostHog events for purchase indicators
                        $has_purchase_event = strpos($props['$pathname'] ?? '', '/order-received') !== false ||
                            strpos($props['$pathname'] ?? '', '/thank-you') !== false ||
                            $event_name === 'woocommerce_order_completed';

                        // Check WooCommerce for actual purchases
                        $has_woocommerce_purchase = false;
                        $purchased_products = array ();

                        if (class_exists('WooCommerce') && !empty($user_email)) {
                            // Get user by email
                            $user = get_user_by('email', $user_email);

                            // Check if user has any completed orders
                            if ($user) {
                                $customer_orders = wc_get_orders(array (
                                    'customer_id' => $user->ID,
                                    'status' => array ('wc-completed', 'wc-processing'),
                                    'limit' => -1, // Get all orders
                                ));

                                if (!empty($customer_orders)) {
                                    $has_woocommerce_purchase = true;

                                    // Collect product details from all orders
                                    foreach ($customer_orders as $order) {
                                        foreach ($order->get_items() as $item_id => $item) {
                                            $product = $item->get_product();
                                            $purchased_products[] = array (
                                                'order_id' => $order->get_id(),
                                                'order_date' => $order->get_date_created()->date('Y-m-d H:i:s'),
                                                'order_status' => $order->get_status(),
                                                'product_id' => $item->get_product_id(),
                                                'product_name' => $item->get_name(),
                                                'quantity' => $item->get_quantity(),
                                                'total' => $item->get_total(),
                                                'currency' => $order->get_currency(),
                                            );
                                        }
                                    }
                                }
                            }
                        }

                        if ($has_purchase_event || $has_woocommerce_purchase) {
                            $step['completed'] = true;
                            $step['url'] = $props['$current_url'] ?? '';

                            // Add purchased products to step data
                            if (!empty($purchased_products)) {
                                $step['purchased_products'] = $purchased_products;
                                $step['total_orders'] = count($customer_orders);
                                $step['total_products'] = count($purchased_products);
                            }
                        }
                        break;
                }
            }
        }

        return array (
            'steps' => $funnel_steps,
            'total_steps' => count($funnel_steps),
            'completed_steps' => count(array_filter($funnel_steps, function ($step) {
                return $step['completed'];
            }))
        );
    }

    /**
     * Build user journey from events
     */
    private function build_journey($events)
    {
        if (empty($events)) {
            return array ();
        }

        $journey = array ();

        foreach ($events as $event) {
            $props = $event['properties'] ?? array ();

            $journey_step = array (
                'event' => $event['event'] ?? 'Unknown Event',
                'timestamp' => $event['timestamp'] ?? '',
                'url' => $props['$current_url'] ?? '',
                'pathname' => $props['$pathname'] ?? '',
                'referrer' => $props['$referrer'] ?? '',
                'is_landing' => !empty($props['$session_entry_pathname']),
                // Device & Browser info
                'device' => $props['$device'] ?? '',
                'browser' => $props['$browser'] ?? '',
                'os' => $props['$os'] ?? '',
                // Location info
                'city' => $props['$geoip_city_name'] ?? '',
                'country' => $props['$geoip_country_name'] ?? '',
                // UTM parameters
                'utm_source' => $props['utm_source'] ?? '',
                'utm_medium' => $props['utm_medium'] ?? '',
                'utm_campaign' => $props['utm_campaign'] ?? '',
            );

            $journey[] = $journey_step;
        }

        return $journey;
    }

    /**
     * Analyze drop-off points from events
     */
    private function analyze_dropoff($events)
    {
        if (empty($events)) {
            return array (
                'message' => 'No events to analyze',
                'severity' => 'inactive'
            );
        }

        // Get event names
        $event_names = array_map(function ($event) {
            return $event['event'] ?? '';
        }, $events);

        // Check for common drop-off patterns
        $has_registration = in_array('growtype_analytics_wp_user_registered', $event_names);
        $has_pageview = in_array('$pageview', $event_names);
        $event_count = count($events);

        // Determine drop-off status
        if ($has_registration && $event_count > 5) {
            return array (
                'message' => 'User completed registration and is actively engaged (' . $event_count . ' events)',
                'severity' => 'success'
            );
        } elseif ($has_registration && $event_count <= 5) {
            return array (
                'message' => 'User registered but has low engagement (' . $event_count . ' events). Consider sending engagement emails.',
                'severity' => 'warning'
            );
        } elseif ($has_pageview && !$has_registration) {
            return array (
                'message' => 'User visited the site but did not complete registration. This is a drop-off point.',
                'severity' => 'high'
            );
        } else {
            return array (
                'message' => 'Limited activity detected (' . $event_count . ' events)',
                'severity' => 'medium'
            );
        }
    }

    /**
     * Get PostHog project ID from settings
     */
    public function get_project_id()
    {
        return get_option('growtype_analytics_posthog_details_project_id', '');
    }

    /**
     * Get PostHog host URL from settings
     */
    public function get_host()
    {
        return get_option('growtype_analytics_posthog_details_host', 'https://app.posthog.com');
    }

    /**
     * AJAX handler to fetch PostHog data for a user
     */
    public function ajax_get_user_data()
    {
        check_ajax_referer('user_analytics_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array ('message' => 'Insufficient permissions.'));
        }

        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

        if (!$user_id) {
            wp_send_json_error(array ('message' => 'Invalid user ID.'));
        }

        $user = get_userdata($user_id);

        if (!$user) {
            wp_send_json_error(array ('message' => 'User not found.'));
        }

        // Get PostHog data
        $posthog_data = $this->fetch_user_data($user->user_email);

        if (is_wp_error($posthog_data)) {
            wp_send_json_error(array ('message' => $posthog_data->get_error_message()));
        }

        wp_send_json_success($posthog_data);
    }

    /**
     * Render the complete PostHog analytics section
     */
    public function render_analytics_section($user_id)
    {
        // Check if current user has admin capabilities
        if (!current_user_can('manage_options')) {
            return;
        }

        // Enqueue PostHog assets
        $this->enqueue_assets();

        ?>
        <div class="analytics-section">
            <h2><?php _e('PostHog Analytics', 'growtype-analytics'); ?></h2>

            <div id="posthog-loading" class="notice notice-info">
                <p><?php _e('Loading analytics data...', 'growtype-analytics'); ?></p>
            </div>

            <div id="posthog-error" class="notice notice-error" style="display: none;">
                <p></p>
            </div>

            <div id="posthog-data" style="display: none;">
                <!-- Summary Overview -->
                <div id="posthog-summary"></div>

                <div class="analytics-grid">
                    <!-- Session Recordings Section -->
                    <div class="analytics-card recordings">
                        <h3><?php _e('Session Recordings', 'growtype-analytics'); ?></h3>
                        <div id="posthog-recordings"></div>
                    </div>

                    <!-- Conversion Insights Section -->
                    <div class="analytics-card conversion-insights">
                        <h3><?php _e('Conversion Insights', 'growtype-analytics'); ?></h3>
                        <div id="posthog-conversion-insights"></div>
                    </div>

                    <!-- Properties Section -->
                    <div class="analytics-card properties">
                        <h3><?php _e('User Properties', 'growtype-analytics'); ?></h3>
                        <div id="posthog-properties"></div>
                    </div>

                    <!-- Drop-off Analysis -->
                    <div class="analytics-card dropoff">
                        <h3><?php _e('Drop-off Points', 'growtype-analytics'); ?></h3>
                        <div id="posthog-dropoff"></div>
                    </div>

                    <!-- User Journey Section -->
                    <div class="analytics-card journey" style="grid-column: 1 / -1;">
                        <h3><?php _e('User Journey & Page Visits', 'growtype-analytics'); ?></h3>
                        <div id="posthog-journey"></div>
                    </div>

                    <!-- Conversion Funnel -->
                    <div class="analytics-card funnel" style="grid-column: 1 / -1;">
                        <h3><?php _e('Conversion Funnel', 'growtype-analytics'); ?></h3>
                        <div id="posthog-funnel"></div>
                    </div>

                    <!-- Events Section -->
                    <div class="analytics-card events" style="grid-column: 1 / -1;">
                        <h3><?php _e('Recent Events', 'growtype-analytics'); ?></h3>
                        <div id="posthog-events"></div>
                    </div>
                </div>
            </div>
        </div>

        <script type="text/javascript">
            jQuery(document).ready(function ($) {
                // Initialize PostHog data loading
                if (window.GrowtypeAnalytics && window.GrowtypeAnalytics.loadPostHogData) {
                    window.GrowtypeAnalytics.loadPostHogData(
                        <?php echo intval($user_id); ?>,
                        '<?php echo wp_create_nonce('user_analytics_nonce'); ?>'
                    );
                }
            });
        </script>
        <?php
    }

    /**
     * Enqueue PostHog assets
     */
    private function enqueue_assets()
    {
        // Get the correct path - we're in partials/posthog/, assets are in partials/posthog/assets/
        $assets_dir = plugin_dir_path(__FILE__) . 'assets/';
        $assets_url = plugin_dir_url(__FILE__) . 'assets/';

        // Enqueue PostHog JavaScript
        wp_enqueue_script(
            'growtype-analytics-posthog',
            $assets_url . 'posthog-analytics.js',
            array ('jquery'),
            filemtime($assets_dir . 'posthog-analytics.js'),
            true
        );
    }
}
