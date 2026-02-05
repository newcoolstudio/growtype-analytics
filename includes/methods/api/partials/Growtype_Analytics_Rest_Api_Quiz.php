<?php

/**
 * Growtype Analytics REST API Quiz Partial
 *
 * Handles REST API routes for quiz-related data.
 * NOTE: This partial requires the 'growtype-quiz' plugin to be active.
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/includes/methods/api/partials
 */

class Growtype_Analytics_Rest_Api_Quiz
{
    /**
     * Initialize the class and set its properties.
     */
    public function __construct()
    {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Register the routes for the quiz.
     */
    public function register_routes()
    {
        register_rest_route('growtype-analytics/v1', '/quiz/results', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_quiz_results'),
                'permission_callback' => array($this, 'get_quiz_permissions_check'),
                'args'                => array(
                    'quiz_id' => array(
                        'description' => __('Filter by specific quiz ID.', 'growtype-analytics'),
                        'type'        => 'integer',
                        'required'    => false,
                    ),
                    'quiz_slug' => array(
                        'description' => __('Filter by specific quiz slug.', 'growtype-analytics'),
                        'type'        => 'string',
                        'required'    => false,
                    ),
                    'limit' => array(
                        'description' => __('Limit the number of results.', 'growtype-analytics'),
                        'type'        => 'integer',
                        'default'     => 20,
                        'required'    => false,
                    ),
                ),
            ),
        ));
    }

    /**
     * Check if a given request has access to get quiz data.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return bool
     */
    public function get_quiz_permissions_check($request)
    {
        return current_user_can('manage_options');
    }

    /**
     * Get quiz results based on quiz_id or quiz_slug.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function get_quiz_results($request)
    {
        global $wpdb;

        if (!class_exists('Growtype_Quiz_Result_Crud')) {
            return new WP_Error('growtype_quiz_not_found', __('Growtype Quiz plugin is not active.', 'growtype-analytics'), array('status' => 404));
        }

        $quiz_id = $request->get_param('quiz_id');
        $quiz_slug = $request->get_param('quiz_slug');
        $limit = $request->get_param('limit') ?: 20;

        $table_name = Growtype_Quiz_Result_Crud::table_name();

        $where = [];
        if (!empty($quiz_id)) {
            $where[] = $wpdb->prepare("quiz_id = %d", $quiz_id);
        }
        if (!empty($quiz_slug)) {
            $where[] = $wpdb->prepare("quiz_slug = %s", $quiz_slug);
        }

        $where_clause = !empty($where) ? " WHERE " . implode(' AND ', $where) : "";

        $results = $wpdb->get_results(
            "SELECT * FROM $table_name $where_clause ORDER BY created_at DESC LIMIT $limit",
            ARRAY_A
        );

        return new WP_REST_Response(array(
            'results' => $results,
            'quiz_id' => $quiz_id,
            'quiz_slug' => $quiz_slug,
            'limit' => $limit
        ), 200);
    }
}
