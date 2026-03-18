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

        // Bypass global REST locks (like Wordfence or "Force Login" plugins) for this specific endpoint
        add_filter('rest_authentication_errors', function($result) {
            $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            $route = isset($_GET['rest_route']) ? $_GET['rest_route'] : '';
            
            if (strpos($uri, '/growtype-analytics/v1/shared-report/') !== false || strpos($route, '/growtype-analytics/v1/shared-report/') !== false) {
                return true; // Overrides 401/403 errors and allows the request
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
            ),
        ));
    }

    public function sanitize_content_format($value)
    {
        $value = sanitize_key($value);

        return in_array($value, array('json', 'html'), true) ? $value : 'json';
    }

    public function get_shared_report(WP_REST_Request $request)
    {
        $token = sanitize_text_field($request->get_param('token'));
        $content_format = $this->sanitize_content_format($request->get_param('content_format'));
        $matched_link = $this->find_share_link($token);

        if (empty($matched_link)) {
            return new WP_Error('growtype_analytics_invalid_share_link', __('Invalid shared analytics access URL.', 'growtype-analytics'), array('status' => 404));
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
            $shared_report_page->render_page($matched_link, 'html');
            exit;
        }

        $data = $shared_report_page->render_page($matched_link, 'json');
        $response = rest_ensure_response($data);
        $response->header('Access-Control-Allow-Origin', '*');
        $response->header('X-Robots-Tag', 'noindex, nofollow');
        
        return $response;
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
