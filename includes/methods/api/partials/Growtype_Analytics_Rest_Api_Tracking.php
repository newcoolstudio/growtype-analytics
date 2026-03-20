<?php

/**
 * REST API Tracking Partial
 *
 * Handles client-side tracking requests and stores them in the local database.
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/includes/methods/api/partials
 */

class Growtype_Analytics_Rest_Api_Tracking
{
    public function __construct()
    {
        if (did_action('rest_api_init')) {
            $this->register_routes();
        } else {
            add_action('rest_api_init', array($this, 'register_routes'));
        }
    }

    /**
     * Register the routes for the objects of the controller.
     */
    public function register_routes()
    {
        register_rest_route('growtype-analytics/v1', '/track', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'track_event'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Handle the tracking request (supports single and batch)
     */
    public function track_event($request)
    {
        $params = $request->get_params();
        
        // Check if it's a batch request (array of events)
        if (isset($params['events']) && is_array($params['events'])) {
            $results = [];
            foreach ($params['events'] as $event) {
                $results[] = $this->process_event($event);
            }
            return rest_ensure_response(array(
                'success' => true,
                'count'   => count(array_filter($results)),
            ));
        }

        // Handle single event request
        $result = $this->process_event($params);

        return rest_ensure_response(array(
            'success' => (bool)$result,
        ));
    }

    /**
     * Internal helper to process a single event
     */
    private function process_event($params)
    {
        $event_type  = isset($params['event_type']) ? sanitize_text_field($params['event_type']) : '';
        $object_id   = isset($params['object_id']) ? sanitize_text_field($params['object_id']) : '';
        $object_type = isset($params['object_type']) ? sanitize_text_field($params['object_type']) : '';
        $metadata    = isset($params['metadata']) ? (array)$params['metadata'] : array();

        if (empty($event_type)) {
            return false;
        }

        return Growtype_Analytics_Database::track($event_type, $object_id, $object_type, $metadata);
    }
}
