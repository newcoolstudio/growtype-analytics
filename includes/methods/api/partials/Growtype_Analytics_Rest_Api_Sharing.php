<?php

/**
 * Shared analytics report REST API partial
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/includes/methods/api/partials
 */

class Growtype_Analytics_Rest_Api_Sharing
{
    public function __construct()
    {
        add_action('rest_api_init', array($this, 'register_routes'));

        // Bypass global REST locks (like Wordfence or "Force Login" plugins) for specific endpoints
        add_filter('rest_authentication_errors', function($result) {
            $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            $route = isset($_GET['rest_route']) ? $_GET['rest_route'] : '';
            
            $public_routes = array(
                '/growtype-analytics/v1/shared-report/',
                '/growtype-analytics/v1/track'
            );

            foreach ($public_routes as $public_route) {
                if (strpos($uri, $public_route) !== false || strpos($route, $public_route) !== false) {
                    return true; // Overrides 401/403 errors and allows the request
                }
            }

            return $result;
        }, 100);
    }

    public function register_routes()
    {
        register_rest_route('growtype-analytics/v1', '/shared-report/(?P<token>[A-Za-z0-9]+)', array(
            'methods'  => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_shared_report'),
            'permission_callback' => '__return_true', // Standard public access
            'args' => array(
                'content_format' => array(
                    'default' => 'json',
                    'sanitize_callback' => array($this, 'sanitize_content_format'),
                ),
                'clear_cache' => array(
                    'default' => false,
                    'sanitize_callback' => array($this, 'sanitize_clear_cache'),
                ),
            ),
        ));
        
        register_rest_route('growtype-analytics/v1', '/shared-report/(?P<token>[A-Za-z0-9]+)/fragment/(?P<fragment>[A-Za-z0-9_]+)', array(
            'methods'  => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_shared_report_fragment'),
            'permission_callback' => '__return_true', // Standard public access
            'args' => array(
                'clear_cache' => array(
                    'default' => false,
                    'sanitize_callback' => array($this, 'sanitize_clear_cache'),
                ),
            ),
        ));
    }

    public function sanitize_content_format($value)
    {
        $value = sanitize_key($value);

        return in_array($value, array('json', 'html'), true) ? $value : 'json';
    }

    public function sanitize_clear_cache($value)
    {
        if (is_bool($value)) {
            return $value;
        }

        $value = strtolower((string)$value);
        return in_array($value, array('1', 'true', 'yes', 'y', 'on'), true);
    }

    public function get_shared_report(WP_REST_Request $request)
    {
        $token = sanitize_text_field($request->get_param('token'));
        $content_format = $this->sanitize_content_format($request->get_param('content_format'));
        $clear_cache = $this->sanitize_clear_cache($request->get_param('clear_cache'));
        $matched_link = $this->find_share_link($token);

        if (empty($matched_link)) {
            return new WP_Error('growtype_analytics_invalid_share_link', __('Invalid shared analytics access URL.', 'growtype-analytics'), array('status' => 404));
        }

        if ($clear_cache) {
            $this->clear_analytics_transients();
        }

        if (!class_exists('Growtype_Analytics_Admin_Page')) {
            require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/Growtype_Analytics_Admin_Page.php';
        }

        $report = new Growtype_Analytics_Admin_Page(false);
        $shared_report_page = $report->get_shared_report_page();

        if ($content_format === 'html') {
            status_header(200);
            nocache_headers();
            header('Content-Type: text/html; charset=' . get_bloginfo('charset'));
            header('Access-Control-Allow-Origin: *'); // Allow from anywhere
            header('X-Robots-Tag: noindex, nofollow');

            // Release session lock to prevent blocking other requests from the same user
            if (session_id()) {
                session_write_close();
            }

            // Increase time limit for large reports
            @set_time_limit(300);

            $shared_report_page->render_page($matched_link, 'html');
            exit;
        }

        // Release session lock for REST requests too
        if (session_id()) {
            session_write_close();
        }

        @set_time_limit(300);

        $data = $shared_report_page->render_page($matched_link, 'json');
        $response = rest_ensure_response($data);
        $response->header('Access-Control-Allow-Origin', '*');
        $response->header('X-Robots-Tag', 'noindex, nofollow');
        
        return $response;
    }

    public function get_shared_report_fragment(WP_REST_Request $request)
    {
        $token = sanitize_text_field($request->get_param('token'));
        $fragment = sanitize_key($request->get_param('fragment'));
        $content_format = $this->sanitize_content_format($request->get_param('content_format'));
        $clear_cache = $this->sanitize_clear_cache($request->get_param('clear_cache'));
        $matched_link = $this->find_share_link($token);

        if (empty($matched_link)) {
            return new WP_Error('growtype_analytics_invalid_share_link', __('Invalid shared analytics access URL.', 'growtype-analytics'), array('status' => 404));
        }

        if (!class_exists('Growtype_Analytics_Admin_Page')) {
            require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/Growtype_Analytics_Admin_Page.php';
        }

        $report = new Growtype_Analytics_Admin_Page(false);
        $shared_report_page = $report->get_shared_report_page();

        $data = $shared_report_page->get_fragment($fragment, $matched_link, $clear_cache);

        if ($content_format === 'html') {
            $data = array(
                'html' => $shared_report_page->render_fragment_html($fragment, $data)
            );
        }
        
        $response = rest_ensure_response($data);
        $response->header('Access-Control-Allow-Origin', '*');
        $response->header('X-Robots-Tag', 'noindex, nofollow');
        
        return $response;
    }

    private function clear_analytics_transients()
    {
        global $wpdb;

        $like_patterns = array(
            '_transient_growtype_analytics_%',
            '_transient_timeout_growtype_analytics_%',
            '_site_transient_growtype_analytics_%',
            '_site_transient_timeout_growtype_analytics_%',
        );

        foreach ($like_patterns as $pattern) {
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM `{$wpdb->options}` WHERE option_name LIKE %s",
                    $pattern
                )
            );

            if (is_multisite()) {
                $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM `{$wpdb->sitemeta}` WHERE meta_key LIKE %s",
                        $pattern
                    )
                );
            }
        }
    }

    private function find_share_link($token)
    {
        $links = get_option('growtype_analytics_share_access_links', array());

        if (!is_array($links)) {
            $links = array();
        }

        foreach ($links as $index => $link) {
            if (!empty($link['token']) && hash_equals((string) $link['token'], (string) $token)) {
                $last_used = !empty($link['last_used_at']) ? strtotime($link['last_used_at']) : 0;
                
                // Only update database if last used was more than 5 minutes ago to save IO
                if (time() - $last_used > 300) {
                    $links[$index]['last_used_at'] = current_time('mysql');
                    update_option('growtype_analytics_share_access_links', $links, false);
                }
                
                return $links[$index];
            }
        }

        return null;
    }
}
