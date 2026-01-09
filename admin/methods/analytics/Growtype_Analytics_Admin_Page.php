<?php

/**
 * Analytics Admin Page
 *
 * Handles the analytics dashboard page in WordPress admin
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/admin/methods/analytics
 */

class Growtype_Analytics_Admin_Page
{
    private $chart;
    private $registrations_chart;
    private $activation_chart;
    private $paywall_chart;
    private $retention_chart;

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_analytics_menu_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_analytics_page_assets'));

        require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/partials/Growtype_Analytics_Admin_Page_Chart.php';
        $this->chart = new Growtype_Analytics_Admin_Page_Chart();

        require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/partials/Growtype_Analytics_Admin_Page_Registrations_Chart.php';
        $this->registrations_chart = new Growtype_Analytics_Admin_Page_Registrations_Chart();

        require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/partials/Growtype_Analytics_Admin_Page_Activation_Chart.php';
        $this->activation_chart = new Growtype_Analytics_Admin_Page_Activation_Chart();

        require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/partials/Growtype_Analytics_Admin_Page_Paywall_Chart.php';
        $this->paywall_chart = new Growtype_Analytics_Admin_Page_Paywall_Chart();

        require_once GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/partials/Growtype_Analytics_Admin_Page_Retention_Chart.php';
        $this->retention_chart = new Growtype_Analytics_Admin_Page_Retention_Chart();
    }

    /**
     * Add analytics page to WordPress admin menu
     */
    public function add_analytics_menu_page()
    {
        add_menu_page(
            __('Analytics', 'growtype-analytics'),           // Page title
            __('Analytics', 'growtype-analytics'),           // Menu title
            'manage_options',                                 // Capability required
            'growtype-analytics',                            // Menu slug
            array($this, 'render_analytics_page'),           // Callback function
            'dashicons-chart-line',                          // Icon (dashicons)
            30                                               // Position (after Comments)
        );

        add_submenu_page(
            'growtype-analytics',                            // Parent slug
            __('Users', 'growtype-analytics'),                // Page title
            __('Users', 'growtype-analytics'),                // Menu title
            'manage_options',                                 // Capability
            'growtype-analytics-users',                      // Menu slug
            array($this, 'render_users_page')                // Callback
        );

        add_submenu_page(
            'growtype-analytics',                            // Parent slug
            __('Chat', 'growtype-analytics'),                 // Page title
            __('Chat', 'growtype-analytics'),                 // Menu title
            'manage_options',                                 // Capability
            'growtype-analytics-chat',                       // Menu slug
            array($this, 'render_chat_page')                 // Callback
        );

        add_submenu_page(
            'growtype-analytics',                            // Parent slug
            __('Product', 'growtype-analytics'),              // Page title
            __('Product', 'growtype-analytics'),              // Menu title
            'manage_options',                                 // Capability
            'growtype-analytics-product',                    // Menu slug
            array($this, 'render_product_page')              // Callback
        );
    }

    /**
     * Enqueue styles and scripts for analytics page
     */
    public function enqueue_analytics_page_assets($hook)
    {
        // Only load on our analytics pages
        $pages = array(
            'toplevel_page_growtype-analytics',
            'analytics_page_growtype-analytics-users',
            'analytics_page_growtype-analytics-chat',
            'analytics_page_growtype-analytics-product'
        );

        if (!in_array($hook, $pages)) {
            return;
        }

        // Enqueue custom CSS for analytics page
        wp_enqueue_style(
            'growtype-analytics-page',
            GROWTYPE_ANALYTICS_URL . 'admin/css/growtype-analytics-page.css',
            array(),
            GROWTYPE_ANALYTICS_VERSION
        );

        // Enqueue Chart.js
        wp_enqueue_script(
            'chart-js',
            'https://cdn.jsdelivr.net/npm/chart.js',
            array(),
            '3.9.1',
            true
        );

        // Enqueue custom JS for analytics page
        wp_enqueue_script(
            'growtype-analytics-page',
            GROWTYPE_ANALYTICS_URL . 'admin/js/growtype-analytics-page.js',
            array('jquery', 'chart-js'),
            GROWTYPE_ANALYTICS_VERSION,
            true
        );

        // Pass data to JavaScript
        wp_localize_script('growtype-analytics-page', 'growtypeAnalytics', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('growtype_analytics_nonce'),
        ));
    }

    /**
     * Render the analytics page content
     */
    public function render_analytics_page()
    {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'growtype-analytics'));
        }

        ?>
        <div class="wrap growtype-analytics-page">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="growtype-analytics-dashboard">
                <!-- Analytics Overview -->
                <div class="analytics-section">
                    <h2><?php _e('Analytics Overview', 'growtype-analytics'); ?></h2>
                    
                    <div class="analytics-stats-grid">
                        <?php $this->render_stat_card('Platform Users', $this->get_platform_users()); ?>
                    </div>
                </div>

                <!-- Recent Events -->
                <div class="analytics-section">
                    <h2><?php _e('Recent Events', 'growtype-analytics'); ?></h2>
                    <div class="analytics-recent-events">
                        <?php $this->render_recent_events(); ?>
                    </div>
                </div>

                <!-- Configuration Status -->
                <div class="analytics-section">
                    <h2><?php _e('Configuration Status', 'growtype-analytics'); ?></h2>
                    <div class="analytics-config-status">
                        <?php $this->render_config_status(); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render the users analytics subpage content
     */
    public function render_users_page()
    {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'growtype-analytics'));
        }

        ?>
        <div class="wrap growtype-analytics-page">
            <h1><?php _e('Users Analytics', 'growtype-analytics'); ?></h1>
            
            <div class="growtype-analytics-dashboard">
                <!-- Daily Unique Users Chart -->
                <?php $this->chart->render(); ?>

                <!-- Daily Registrations Chart -->
                <?php $this->registrations_chart->render(); ?>

                <!-- User Retention Chart -->
                <?php $this->retention_chart->render(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render the chat analytics subpage content
     */
    public function render_chat_page()
    {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'growtype-analytics'));
        }

        ?>
        <div class="wrap growtype-analytics-page">
            <h1><?php _e('Chat Analytics', 'growtype-analytics'); ?></h1>
            
            <div class="growtype-analytics-dashboard">
                <!-- Activation Rate Chart -->
                <?php $this->activation_chart->render(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render the product analytics subpage content
     */
    public function render_product_page()
    {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'growtype-analytics'));
        }

        ?>
        <div class="wrap growtype-analytics-page">
            <h1><?php _e('Product Analytics', 'growtype-analytics'); ?></h1>
            
            <div class="growtype-analytics-dashboard">
                <!-- Paywall Views Chart -->
                <?php $this->paywall_chart->render(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render a stat card
     */
    private function render_stat_card($title, $value)
    {
        ?>
        <div class="stat-card">
            <div class="stat-title"><?php echo esc_html($title); ?></div>
            <div class="stat-value"><?php echo esc_html($value); ?></div>
        </div>
        <?php
    }

    /**
     * Render recent events table
     */
    private function render_recent_events()
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'growtype_analytics_events';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            echo '<p>' . __('No events table found. Events tracking may not be configured.', 'growtype-analytics') . '</p>';
            return;
        }

        $events = $wpdb->get_results(
            "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 10",
            ARRAY_A
        );

        if (empty($events)) {
            echo '<p>' . __('No events recorded yet.', 'growtype-analytics') . '</p>';
            return;
        }

        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Event Name', 'growtype-analytics'); ?></th>
                    <th><?php _e('User', 'growtype-analytics'); ?></th>
                    <th><?php _e('Date', 'growtype-analytics'); ?></th>
                    <th><?php _e('Details', 'growtype-analytics'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($events as $event): ?>
                    <tr>
                        <td><?php echo esc_html($event['event_name'] ?? 'N/A'); ?></td>
                        <td>
                            <?php 
                            if (!empty($event['user_id'])) {
                                $user = get_user_by('id', $event['user_id']);
                                echo $user ? esc_html($user->display_name) : 'User #' . esc_html($event['user_id']);
                            } else {
                                echo __('Guest', 'growtype-analytics');
                            }
                            ?>
                        </td>
                        <td><?php echo esc_html($event['created_at'] ?? 'N/A'); ?></td>
                        <td>
                            <?php 
                            if (!empty($event['event_data'])) {
                                echo '<code>' . esc_html(substr($event['event_data'], 0, 50)) . '...</code>';
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render configuration status
     */
    private function render_config_status()
    {
        $gtm_enabled = get_option('growtype_analytics_gtm_details_enabled');
        $gtm_id = get_option('growtype_analytics_gtm_details_gtm_id');
        $ga4_id = get_option('growtype_analytics_ga4_details_ga4_id');
        $posthog_api_key = get_option('growtype_analytics_posthog_details_api_key');

        ?>
        <table class="wp-list-table widefat fixed">
            <thead>
                <tr>
                    <th><?php _e('Service', 'growtype-analytics'); ?></th>
                    <th><?php _e('Status', 'growtype-analytics'); ?></th>
                    <th><?php _e('Configuration', 'growtype-analytics'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Google Tag Manager</strong></td>
                    <td>
                        <?php if ($gtm_enabled && !empty($gtm_id)): ?>
                            <span class="status-badge status-active">✓ Active</span>
                        <?php else: ?>
                            <span class="status-badge status-inactive">○ Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo !empty($gtm_id) ? esc_html($gtm_id) : __('Not configured', 'growtype-analytics'); ?></td>
                </tr>
                <tr>
                    <td><strong>Google Analytics 4</strong></td>
                    <td>
                        <?php if (!empty($ga4_id)): ?>
                            <span class="status-badge status-active">✓ Active</span>
                        <?php else: ?>
                            <span class="status-badge status-inactive">○ Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo !empty($ga4_id) ? esc_html($ga4_id) : __('Not configured', 'growtype-analytics'); ?></td>
                </tr>
                <tr>
                    <td><strong>PostHog</strong></td>
                    <td>
                        <?php if (!empty($posthog_api_key)): ?>
                            <span class="status-badge status-active">✓ Active</span>
                        <?php else: ?>
                            <span class="status-badge status-inactive">○ Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo !empty($posthog_api_key) ? __('Configured', 'growtype-analytics') : __('Not configured', 'growtype-analytics'); ?></td>
                </tr>
            </tbody>
        </table>
        
        <p>
            <a href="<?php echo admin_url('options-general.php?page=growtype-analytics-settings'); ?>" class="button button-primary">
                <?php _e('Configure Analytics Settings', 'growtype-analytics'); ?>
            </a>
        </p>
        <?php
    }

    /**
     * Get platform users count (customers and subscribers only)
     */
    private function get_platform_users()
    {
        $args = array(
            'role__in' => array('customer', 'subscriber'),
            'fields' => 'ID',
        );
        
        $user_query = new WP_User_Query($args);
        $users = $user_query->get_results();
        
        return number_format(count($users));
    }


}
