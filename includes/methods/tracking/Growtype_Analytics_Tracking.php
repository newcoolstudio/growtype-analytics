<?php

class Growtype_Analytics_Tracking
{
    public function __construct()
    {
        /**
         * Meta boxes
         */
        add_filter('growtype_form_lead_meta_boxes', array ($this, 'add_custom_meta_boxes'), 100, 1);

        /**
         * Growtype form
         */
        add_action('growtype_form_create_user', array ($this, 'growtype_form_create_user_extend'), 10, 4);

        /**
         * Scripts
         */
        add_action('wp_footer', array ($this, 'wp_footer_extend'));

        /**
         * Load methods
         */
        $this->load_methods();
    }

    function add_custom_meta_boxes($meta_boxes)
    {
        $meta_boxes[0]['fields'][] = [
            'title' => 'G.A. Marketing sources',
            'key' => 'growtype_analytics_marketing_sources',
            'type' => 'textarea'
        ];

        return $meta_boxes;
    }

    function growtype_form_create_user_extend($user_id)
    {
        if (isset($_COOKIE['growtype_analytics_marketing_sources']) && !empty($_COOKIE['growtype_analytics_marketing_sources'])) {
            $userdata = get_userdata($user_id);
            $lead = Growtype_Form_Admin_Lead::get_by_title($userdata->user_email);
            update_post_meta($lead->ID, 'growtype_analytics_marketing_sources', $_COOKIE['growtype_analytics_marketing_sources'] ?? []);
        }
    }

    public function load_methods()
    {
        include_once GROWTYPE_ANALYTICS_PATH . 'includes/methods/tracking/services/Growtype_Analytics_Tracking_Ga.php';
        new Growtype_Analytics_Tracking_Ga();

        include_once GROWTYPE_ANALYTICS_PATH . 'includes/methods/tracking/services/Growtype_Analytics_Tracking_Fb.php';
        new Growtype_Analytics_Tracking_Fb();
    }

    function wp_footer_extend()
    {
        ?>
        <script>
            function growtypeAnalyticsSetCookie(name, value, days) {
                var expires = "";
                if (days) {
                    var date = new Date();
                    date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                    expires = "; expires=" + date.toUTCString();
                }
                document.cookie = name + "=" + (value || "") + expires + "; path=/";
            }

            function growtypeAnalyticsGetCookie(name) {
                var nameEQ = name + "=";
                var cookiesArray = document.cookie.split(';');
                for (var i = 0; i < cookiesArray.length; i++) {
                    var cookie = cookiesArray[i];
                    while (cookie.charAt(0) === ' ') {
                        cookie = cookie.substring(1, cookie.length);
                    }
                    if (cookie.indexOf(nameEQ) === 0) {
                        return cookie.substring(nameEQ.length, cookie.length);
                    }
                }
                return null;
            }
        </script>
        <?php

        /**
         * Marketing sources
         */
        if (isset($_GET) && !empty($_GET) && !is_user_logged_in()) {
            $marketing_sources = apply_filters('growtype_analytics_marketing_sources', $_GET ?? []);
            ?>
            <script>
                function growtypeAnalyticsUpdateMarketingSources() {
                    // Get existing marketing sources cookie
                    let existingMarketingSources = growtypeAnalyticsGetCookie('growtype_analytics_marketing_sources');

                    // Initialize existingSourcesArray as an empty array or parse the existing sources
                    let existingSourcesArray = existingMarketingSources ? JSON.parse(existingMarketingSources) : [];

                    // Flag to keep track of whether any new marketing source was added
                    let anyNewSourceAdded = false;

                    // Get the URL query parameters
                    let searchParams = JSON.parse('<?php echo json_encode($marketing_sources) ?>');

                    // console.log(searchParams)

                    // Loop through each query parameter
                    Object.entries(searchParams).forEach(function (value, key) {

                        // Check if the parameter key is not already in the existing sources array
                        if (!existingSourcesArray.some(function (source) {
                            return source.key === value[0];
                        })) {
                            // Add the new parameter to the existing sources array
                            existingSourcesArray.push({key: value[0], value: value[1]});
                            anyNewSourceAdded = true;
                            // console.log('New parameter added:', value[0]);
                        } else {
                            // console.log('Parameter already exists:', value[0]);
                        }
                    });

                    // If any new parameter was added, update the marketing sources cookie
                    if (anyNewSourceAdded) {
                        growtypeAnalyticsSetCookie('growtype_analytics_marketing_sources', JSON.stringify(existingSourcesArray), 300);
                    }
                }

                growtypeAnalyticsUpdateMarketingSources();
            </script>
        <?php }
    }
}
