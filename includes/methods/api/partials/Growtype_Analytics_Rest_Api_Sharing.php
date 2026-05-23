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
            // Parse ONLY the path — never the query string — so a crafted ?foo=/report/ can't spoof a match.
            $raw  = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            $uri  = parse_url($raw, PHP_URL_PATH) ?: '';
            $route = isset($_GET['rest_route']) ? (string) wp_unslash($_GET['rest_route']) : '';

            $public_routes = array(
                '/growtype-analytics/v1/report/',
                '/growtype-analytics/v1/track',
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
        // Metrics report
        register_rest_route('growtype-analytics/v1', '/report/metrics/(?P<token>[A-Za-z0-9]+)', array(
            'methods'  => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_shared_report'),
            'permission_callback' => '__return_true',
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

        register_rest_route('growtype-analytics/v1', '/report/metrics/(?P<token>[A-Za-z0-9]+)/fragment/(?P<fragment>[A-Za-z0-9_]+)', array(
            'methods'  => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_shared_report_fragment'),
            'permission_callback' => '__return_true',
            'args' => array(
                'clear_cache' => array(
                    'default' => false,
                    'sanitize_callback' => array($this, 'sanitize_clear_cache'),
                ),
            ),
        ));

        // Strategy report (shared read-only, token-based)
        register_rest_route('growtype-analytics/v1', '/report/strategy/(?P<token>[A-Za-z0-9]+)', array(
            'methods'  => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_strategy_report'),
            'permission_callback' => '__return_true',
        ));

        // Strategy sync (secret-authenticated, bidirectional)
        register_rest_route('growtype-analytics/v1', '/strategy/sync', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_strategy_sync'),
                'permission_callback' => '__return_true',
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'receive_strategy_sync'),
                'permission_callback' => '__return_true',
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

    public function get_strategy_report(WP_REST_Request $request)
    {
        $token       = sanitize_text_field($request->get_param('token'));
        $matched_link = $this->find_share_link($token);

        if (empty($matched_link)) {
            return new WP_Error('growtype_analytics_invalid_share_link', __('Invalid shared analytics access URL.', 'growtype-analytics'), array('status' => 404));
        }

        if (($matched_link['report_type'] ?? 'metrics') !== 'strategy') {
            return new WP_Error('growtype_analytics_wrong_report_type', __('This URL is not a strategy report.', 'growtype-analytics'), array('status' => 400));
        }

        $steps = get_option('growtype_analytics_strategy_steps', []);

        $content_format = sanitize_key($request->get_param('content_format') ?? 'json');

        if ($content_format === 'html') {
            status_header(200);
            nocache_headers();
            header('Content-Type: text/html; charset=' . get_bloginfo('charset'));
            header('Access-Control-Allow-Origin: *');
            header('X-Robots-Tag: noindex, nofollow');

            if (session_id()) {
                session_write_close();
            }

            echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Strategy Report</title>';
            echo '<style>body{font-family:system-ui,sans-serif;max-width:860px;margin:40px auto;padding:0 20px;color:#1a1a1a;}';
            echo 'h1{font-size:24px;margin-bottom:4px;}';
            echo '.step{background:#fff;border:1px solid #ddd;border-left:4px solid #2271b1;padding:16px 20px;margin-bottom:16px;border-radius:2px;}';
            echo '.step-title{font-size:15px;font-weight:700;margin:0 0 8px;}';
            echo '.step-desc{font-size:12px;font-family:monospace;white-space:pre-wrap;background:#f6f7f7;border:1px solid #ddd;padding:8px 10px;margin:0 0 8px;border-radius:2px;color:#555;}';
            echo '.step-value{font-size:13px;white-space:pre-wrap;line-height:1.6;}';
            echo '.empty{color:#aaa;font-style:italic;}</style></head><body>';
            echo '<h1>Strategy Report</h1>';
            echo '<p style="color:#666;margin-bottom:24px;">Read-only shared strategy context.</p>';

            foreach ($steps as $step) {
                $title = esc_html($step['title'] ?? '');
                $desc  = esc_html($step['description'] ?? '');
                $value = esc_html($step['value'] ?? '');

                echo '<div class="step">';
                echo '<div class="step-title">' . $title . '</div>';
                if ($desc) echo '<div class="step-desc">' . $desc . '</div>';
                if ($value) {
                    echo '<div class="step-value">' . $value . '</div>';
                } else {
                    echo '<div class="step-value empty">Not answered yet.</div>';
                }
                echo '</div>';
            }

            echo '</body></html>';
            exit;
        }

        $data = [
            'report_type' => 'strategy',
            'steps'       => array_map(function ($step) {
                return [
                    'title'       => $step['title'] ?? '',
                    'description' => $step['description'] ?? '',
                    'value'       => $step['value'] ?? '',
                ];
            }, $steps),
        ];

        $response = rest_ensure_response($data);
        $response->header('Access-Control-Allow-Origin', '*');
        $response->header('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }

    // ── Strategy sync endpoints ────────────────────────────────────────────

    /**
     * The ONE and ONLY wp_options key the sync endpoint is permitted to read/write.
     * Nothing outside this key can ever be touched by the sync token.
     */
    const SYNC_STRATEGY_OPTION = 'growtype_analytics_strategy_steps';

    /** Maximum number of steps accepted in a push payload. */
    const SYNC_MAX_STEPS = 50;

    /** Per-field length limits (characters). */
    const SYNC_MAX_TITLE_LEN = 200;
    const SYNC_MAX_FIELD_LEN = 20000;

    /** The only keys permitted inside each step object. Any other keys are silently dropped. */
    const SYNC_ALLOWED_STEP_KEYS = ['title', 'description', 'value'];

    public function get_strategy_sync(WP_REST_Request $request)
    {
        $provided = sanitize_text_field($request->get_header('X-Sync-Secret') ?? '');
        $stored   = $this->get_sync_secret();

        if ($this->is_sync_rate_limited()) {
            return new WP_Error('growtype_analytics_sync_rate_limited', __('Too many failed attempts. Try again in 60 seconds.', 'growtype-analytics'), array('status' => 429));
        }

        if (empty($stored) || !hash_equals($stored, $provided)) {
            $this->record_sync_failure();
            return new WP_Error('growtype_analytics_sync_forbidden', __('Invalid sync secret.', 'growtype-analytics'), array('status' => 403));
        }

        $this->clear_sync_failures();
        $steps    = get_option(self::SYNC_STRATEGY_OPTION, []);
        $response = rest_ensure_response(['steps' => $steps]);

        return $response;
    }

    public function receive_strategy_sync(WP_REST_Request $request)
    {
        $provided = sanitize_text_field($request->get_header('X-Sync-Secret') ?? '');
        $stored   = $this->get_sync_secret();

        if ($this->is_sync_rate_limited()) {
            return new WP_Error('growtype_analytics_sync_rate_limited', __('Too many failed attempts. Try again in 60 seconds.', 'growtype-analytics'), array('status' => 429));
        }

        if (empty($stored) || !hash_equals($stored, $provided)) {
            $this->record_sync_failure();
            return new WP_Error('growtype_analytics_sync_forbidden', __('Invalid sync secret.', 'growtype-analytics'), array('status' => 403));
        }

        $body  = $request->get_json_params();
        $steps = $body['steps'] ?? null;

        if (!is_array($steps)) {
            return new WP_Error('growtype_analytics_sync_invalid', __('Invalid steps payload.', 'growtype-analytics'), array('status' => 400));
        }

        // ── Hard limits: cap step count before any processing ──────────────
        if (count($steps) > self::SYNC_MAX_STEPS) {
            return new WP_Error(
                'growtype_analytics_sync_too_large',
                sprintf(__('Payload too large: maximum %d steps allowed.', 'growtype-analytics'), self::SYNC_MAX_STEPS),
                array('status' => 400)
            );
        }

        // ── Strict key whitelist + sanitize + length cap ───────────────────
        $allowed_keys = array_fill_keys(self::SYNC_ALLOWED_STEP_KEYS, '');

        $clean = [];
        foreach ($steps as $s) {
            if (!is_array($s)) {
                continue;
            }

            // Strip every key that isn't in the whitelist — no extra data reaches the DB.
            $s = array_intersect_key($s, $allowed_keys);

            $title       = substr(sanitize_text_field($s['title'] ?? ''),       0, self::SYNC_MAX_TITLE_LEN);
            $description = substr(sanitize_textarea_field($s['description'] ?? ''), 0, self::SYNC_MAX_FIELD_LEN);
            $value       = substr(sanitize_textarea_field($s['value'] ?? ''),       0, self::SYNC_MAX_FIELD_LEN);

            $clean[] = [
                'title'       => $title,
                'description' => $description,
                'value'       => $value,
            ];
        }

        // ── Write exclusively to the declared option key ───────────────────
        $this->clear_sync_failures();
        update_option(self::SYNC_STRATEGY_OPTION, $clean, false);

        return rest_ensure_response(['message' => __('Strategy updated.', 'growtype-analytics'), 'count' => count($clean)]);
    }

    private function get_sync_secret()
    {
        $sync = get_option('growtype_analytics_strategy_sync', []);

        return $sync['secret'] ?? '';
    }

    private function sync_rate_limit_key()
    {
        $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));
        return 'growtype_analytics_sync_fail_' . md5($ip);
    }

    private function is_sync_rate_limited()
    {
        return (int) get_transient($this->sync_rate_limit_key()) >= 5;
    }

    private function record_sync_failure()
    {
        $key   = $this->sync_rate_limit_key();
        $count = (int) get_transient($key);
        set_transient($key, $count + 1, 60);
    }

    private function clear_sync_failures()
    {
        delete_transient($this->sync_rate_limit_key());
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
