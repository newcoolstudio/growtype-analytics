<?php

/**
 * Tracks visits to specific pages defined via a filter.
 *
 * Child themes / plugins register pages via:
 *
 *     add_filter('growtype_analytics_tracked_pages', function ($pages) {
 *         $pages[] = ['path' => '/plans/',   'event_type' => 'page_plans_visit'];
 *         $pages[] = ['path' => '/credits/', 'event_type' => 'page_credits_visit'];
 *         return $pages;
 *     });
 *
 * Each entry:
 *   - path        (string, required) URL path to match, e.g. '/plans/'
 *   - event_type  (string, required) stored in the tracking table
 *   - object_id   (int,    optional) defaults to queried object ID (page post ID)
 *   - object_type (string, optional) defaults to 'page'
 *
 * Deduplication: Growtype_Analytics_Database::track() already prevents duplicate
 * events from the same user within 1 hour.
 */
class Growtype_Analytics_Tracking_System_Pages
{
    public function __construct()
    {
        add_action('template_redirect', [$this, 'track_page_visit']);
    }

    public function track_page_visit(): void
    {
        // Only track logged-in users
        if (!is_user_logged_in()) {
            return;
        }

        $pages = apply_filters('growtype_analytics_tracked_pages', []);
        if (empty($pages)) {
            return;
        }

        $current_path = trailingslashit(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));

        foreach ($pages as $page) {
            $path = trailingslashit($page['path'] ?? '');
            $event_type = sanitize_key($page['event_type'] ?? '');

            if (empty($path) || empty($event_type)) {
                continue;
            }

            if ($current_path !== $path) {
                continue;
            }

            $object_id = !empty($page['object_id'])
                ? absint($page['object_id'])
                : (get_queried_object_id() ?: 0);

            $object_type  = sanitize_key($page['object_type'] ?? 'page');

            // dedup_hours: how long before the same event can be recorded again for this user.
            // 0 = track every single visit (no deduplication).
            // Default: 1 hour (matches the global DB layer default).
            $dedup_hours = isset($page['dedup_hours']) ? max(0, (int)$page['dedup_hours']) : 1;

            Growtype_Analytics_Database::track($event_type, $object_id, $object_type, [], $dedup_hours);

            // Only match the first rule that fits this path
            break;
        }
    }
}
