<?php

class Growtype_Analytics_Database
{
    const TABLE_NAME = 'growtype_analytics_tracking';

    public function __construct()
    {
        add_action('growtype_analytics_cleanup_cron', array(__CLASS__, 'cleanup_old_data'));
        add_action('growtype_analytics_process_track_event', array(__CLASS__, 'process_track_event'), 10, 1);
        add_action('growtype_analytics_flush_redis_queue', array(__CLASS__, 'flush_redis_queue'));
    }

    /**
     * Initialization for activation
     */
    public static function init()
    {
        // No need to create an instance here, maybe_create_table is now static
        self::maybe_create_table();

        if (!wp_next_scheduled('growtype_analytics_cleanup_cron')) {
            wp_schedule_event(time(), 'daily', 'growtype_analytics_cleanup_cron');
        }

        if (!wp_next_scheduled('growtype_analytics_flush_redis_queue')) {
            wp_schedule_event(time(), 'hourly', 'growtype_analytics_flush_redis_queue');
        }
    }

    /**
     * Check if table needs to be created or updated
     */
    public static function maybe_create_table()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;

        $version = get_option('growtype_analytics_db_version', '0');
        $current_version = '1.3'; // Increment this when schema changes

        if (!$table_exists || $version !== $current_version) {
            self::create_tracking_table();
            update_option('growtype_analytics_db_version', $current_version);
        }
    }

    /**
     * Create the universal tracking table
     */
    public static function create_tracking_table()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) DEFAULT 0 NOT NULL,
            event_type varchar(100) NOT NULL,
            object_id varchar(100) DEFAULT '' NOT NULL,
            object_type varchar(100) DEFAULT '' NOT NULL,
            metadata longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY event_type (event_type),
            KEY created_at (created_at),
            KEY object_id (object_id),
            KEY type_date_object (event_type, created_at, object_id),
            KEY user_event_date (user_id, event_type, created_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Flush asynchronously queued Redis analytics events
     */
    public static function flush_redis_queue()
    {
        global $wp_object_cache;
        
        // Ensure Redis is available
        if (!isset($wp_object_cache->redis) || !is_object($wp_object_cache->redis) || !method_exists($wp_object_cache->redis, 'lPop')) {
            return;
        }

        $processed = 0;
        $max_batch_size = 500; // Process chunks of 500 to avoid timeouts

        while ($processed < $max_batch_size) {
            $item = $wp_object_cache->redis->lPop('growtype_analytics_async_queue');
            if (!$item) {
                break;
            }

            $payload = json_decode($item, true);
            if ($payload && is_array($payload)) {
                self::process_track_event($payload);
            }
            $processed++;
        }
    }

    /**
     * Check if the current request is coming from a known bot or scraper.
     */
    public static function is_bot_request()
    {
        $user_agent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
        if (empty($user_agent) || preg_match('/bot|crawl|spider|slurp|facebook|twitter|whatsapp|telegram|discord|linkedin|snippet/i', $user_agent)) {
            return true;
        }
        return false;
    }

    /**
     * Insert tracking record into database
     */
    public static function track($event_type, $object_id = '', $object_type = '', $metadata = [])
    {
        $user_id = get_current_user_id() ?: 0;

        // Prevent tracking from known bots and scrapers globally
        if (self::is_bot_request()) {
            return false;
        }

        $payload = array(
            'event_type'  => $event_type,
            'object_id'   => (string)$object_id,
            'object_type' => (string)$object_type,
            'metadata'    => $metadata,
            'user_id'     => $user_id
        );

        global $wp_object_cache;

        // Try Redis first (Fastest, in-memory queue)
        if (isset($wp_object_cache->redis) && is_object($wp_object_cache->redis) && method_exists($wp_object_cache->redis, 'rPush')) {
            $wp_object_cache->redis->rPush('growtype_analytics_async_queue', json_encode($payload));
            return true;
        }

        // Try WordPress Action Scheduler (WooCommerce standard queue)
        if (function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action('growtype_analytics_process_track_event', array($payload));
            return true;
        }

        // Fallback: Synchronous direct database insert
        return self::process_track_event($payload);
    }

    /**
     * Actually processes the event Payload into the database
     */
    public static function process_track_event($payload)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $user_id = isset($payload['user_id']) ? (int)$payload['user_id'] : 0;
        $event_type = isset($payload['event_type']) ? $payload['event_type'] : '';
        $object_id = isset($payload['object_id']) ? $payload['object_id'] : '';
        $object_type = isset($payload['object_type']) ? $payload['object_type'] : '';
        $metadata = isset($payload['metadata']) ? $payload['metadata'] : [];

        // Basic deduplication/throttling: No duplicate hits from same user within 1 hour
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name 
             WHERE user_id = %d AND event_type = %s AND object_id = %s 
             AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) 
             LIMIT 1",
            $user_id,
            $event_type,
            (string)$object_id
        ));

        if ($exists) {
            return false;
        }

        $result = $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'event_type' => $event_type,
                'object_id' => (string)$object_id,
                'object_type' => (string)$object_type,
                'metadata' => !empty($metadata) ? json_encode($metadata) : null,
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );

        if ($result) {
            delete_transient('growtype_analytics_counts_' . $event_type);
        }

        return $result;
    }

    /**
     * Cleanup old analytics data (older than 90 days)
     */
    public static function cleanup_old_data($retention_days = 90)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        return $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            (int)$retention_days
        ));
    }

    /**
     * Get aggregate interaction stats for all characters (unique users)
     */
    public static function get_all_interaction_stats($days = 30)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT object_id, 
                    COUNT(DISTINCT CASE WHEN event_type = 'character_profile' THEN user_id ELSE NULL END) as profiles,
                    COUNT(DISTINCT CASE WHEN event_type = 'character_chat' THEN user_id ELSE NULL END) as chats
             FROM $table_name 
             WHERE object_type = 'character'
             AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY) 
             GROUP BY object_id",
            $days
        ), ARRAY_A);

        $stats = array();
        foreach ($results as $row) {
            $stats[$row['object_id']] = array(
                'profiles' => (int)$row['profiles'],
                'chats' => (int)$row['chats']
            );
        }

        return $stats;
    }

    /**
     * Get event counts grouped by object_id
     */
    public static function get_event_counts($event_type, $days = 30)
    {
        $cache_key = 'growtype_analytics_counts_' . $event_type . '_' . $days;
        $refresh = isset($_GET['refresh']) && $_GET['refresh'];

        $counts = $refresh ? false : get_transient($cache_key);

        if (false !== $counts) {
            return $counts;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT object_id, COUNT(*) as count 
             FROM $table_name 
             WHERE event_type = %s 
             AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY) 
             GROUP BY object_id",
            $event_type,
            (int)$days
        ), ARRAY_A);

        $counts = array();
        foreach ($results as $row) {
            $counts[$row['object_id']] = (int)$row['count'];
        }

        set_transient($cache_key, $counts, HOUR_IN_SECONDS);

        return $counts;
    }

    /**
     * Get unique event counts (distinct users) grouped by object_id
     */
    public static function get_unique_event_counts($event_type, $days = 30)
    {
        $cache_key = 'growtype_analytics_unique_counts_' . $event_type . '_' . $days;
        $refresh = isset($_GET['refresh']) && $_GET['refresh'];

        $counts = $refresh ? false : get_transient($cache_key);

        if (false !== $counts) {
            return $counts;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT object_id, COUNT(DISTINCT user_id) as count 
             FROM $table_name 
             WHERE event_type = %s 
             AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY) 
             GROUP BY object_id",
            $event_type,
            (int)$days
        ), ARRAY_A);

        $counts = array();
        foreach ($results as $row) {
            $counts[$row['object_id']] = (int)$row['count'];
        }

        set_transient($cache_key, $counts, HOUR_IN_SECONDS);

        return $counts;
    }

    /**
     * Get total unique users for a specific event type, across all objects
     */
    public static function get_total_unique_users_for_event($event_type, $days = 30)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) !== $table_name) {
            return 0;
        }

        return (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) 
             FROM $table_name 
             WHERE event_type = %s 
             AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $event_type,
            (int)$days
        ));
    }

    /**
     * Get paginated events for a specific date range
     */
    public static function get_paginated_events($date_from, $date_to, $per_page, $offset)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $total_events = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE created_at >= %s AND created_at <= %s",
            $date_from . ' 00:00:00',
            $date_to . ' 23:59:59'
        ));

        $events = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE created_at >= %s AND created_at <= %s
             ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $date_from . ' 00:00:00',
            $date_to . ' 23:59:59',
            $per_page,
            $offset
        ), ARRAY_A);

        return array(
            'total' => $total_events,
            'events' => $events
        );
    }
}
