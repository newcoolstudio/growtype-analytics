<?php

class Growtype_Analytics_Tracking_Posthog
{
    public function __construct()
    {
        add_filter('growtype_analytics_data_layer_script_additions', [$this, 'append_identity_script'], 10, 3);
    }

    /**
     * Map identity to PostHog directly alongside the GA event script
     */
    public function append_identity_script($script, $event_name, $data)
    {
        if (in_array($event_name, ['growtype_analytics_wp_user_registered', 'growtype_analytics_wp_user_login'])) {
            $user_id_js = isset($data['user_id']) ? $this->json_encode_safe((string) $data['user_id']) : "''";
            $email_js = isset($data['email']) ? $this->json_encode_safe($data['email']) : "''";
            
            $script .= "if (typeof posthog !== 'undefined' && $user_id_js && $email_js) {\n";
            $script .= "  posthog.identify($user_id_js, { email: $email_js });\n";
            $script .= "}\n";
        }

        return $script;
    }

    /**
     * Safe json encode
     */
    private function json_encode_safe($data)
    {
        if (function_exists('wp_json_encode')) {
            return wp_json_encode($data);
        }

        return json_encode($data);
    }
}
