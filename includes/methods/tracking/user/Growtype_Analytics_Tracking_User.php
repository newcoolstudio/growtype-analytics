<?php

class Growtype_Analytics_Tracking_User
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

            if (!empty($lead)) {
                $sources = $this->get_marketing_sources_for_lead();
                update_post_meta($lead->ID, 'growtype_analytics_marketing_sources', wp_json_encode($sources));
            }
        }
    }

    private function get_marketing_sources_for_lead(): array
    {
        // SECURITY: Sanitize cookie data before storing
        $cookie_data = sanitize_text_field($_COOKIE['growtype_analytics_marketing_sources']);
        $sources = json_decode($cookie_data, true);
        if (!is_array($sources)) {
            $sources = [];
        }

        // Inject the correct redirect_after from the form submission.
        // $_POST['growtype_form_redirect_after'] or $_POST['redirect_after'] holds
        // the actual originating URL (e.g. /chat/lola-riot), which is more reliable
        // than what the page-footer tracker captured from $_GET.
        $redirect_after = '';
        if (!empty($_POST['growtype_form_redirect_after'])) {
            $redirect_after = esc_url_raw(wp_unslash($_POST['growtype_form_redirect_after']));
        } elseif (!empty($_POST['redirect_after'])) {
            $redirect_after = esc_url_raw(wp_unslash($_POST['redirect_after']));
        } elseif (!empty($_COOKIE['growtype_form_redirect_after'])) {
            $redirect_after = esc_url_raw(wp_unslash($_COOKIE['growtype_form_redirect_after']));
        }

        if (!empty($redirect_after)) {
            // Remove any stale redirect_after already in the array, then add correct one.
            $sources = array_values(array_filter($sources, fn($s) => ($s['key'] ?? '') !== 'redirect_after'));
            $sources[] = ['key' => 'redirect_after', 'value' => $redirect_after];
        }

        return $sources;
    }
}
