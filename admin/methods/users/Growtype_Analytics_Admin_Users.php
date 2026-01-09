<?php

class Growtype_Analytics_Admin_Users
{
    private $posthog;
    private $chat_analytics;

    public function __construct()
    {
        // Load helper classes on init to ensure other plugins are ready
        add_action('init', array($this, 'load_dependencies'));

        // Add custom action link to users list
        add_filter('user_row_actions', array($this, 'add_analytics_action_link'), 10, 2);
        
        // Register admin page for user analytics
        add_action('admin_menu', array($this, 'register_analytics_page'));
        
        // Hide the menu item from sidebar
        add_action('admin_head', array($this, 'hide_analytics_menu_item'));
    }

    /**
     * Load required dependencies
     */
    public function load_dependencies()
    {
        // Load PostHog handler if credentials are configured
        if ($this->is_posthog_configured()) {
            require_once plugin_dir_path(__FILE__) . 'partials/posthog/class-growtype-analytics-user-posthog.php';
            $this->posthog = new Growtype_Analytics_User_PostHog();
        }

        // Load Chat Analytics handler if growtype-chat plugin is active
        if ($this->is_chat_available()) {
            require_once plugin_dir_path(__FILE__) . 'partials/growtype-chat/class-growtype-analytics-user-chat.php';
            $this->chat_analytics = new Growtype_Analytics_User_Chat();
        }
    }

    /**
     * Check if PostHog is configured
     */
    private function is_posthog_configured()
    {
        // Only allow admin users to access PostHog analytics
        if (!current_user_can('manage_options')) {
            return false;
        }

        $api_key = get_option('growtype_analytics_posthog_details_api_key', '');
        $project_id = get_option('growtype_analytics_posthog_details_project_id', '');
        
        return !empty($api_key) && !empty($project_id);
    }

    /**
     * Check if Growtype Chat is available
     */
    private function is_chat_available()
    {
        // Only allow admin users to access Chat analytics
        if (!current_user_can('manage_options')) {
            return false;
        }

        return class_exists('Growtype_Chat');
    }

    /**
     * Add "Analytics" action link to user row actions
     */
    public function add_analytics_action_link($actions, $user)
    {
        // Only show for users with email
        if (!empty($user->user_email)) {
            $analytics_url = add_query_arg(
                array(
                    'page' => 'user-analytics',
                    'user_id' => $user->ID
                ),
                admin_url('users.php')
            );
            
            $actions['analytics'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url($analytics_url),
                __('Analytics', 'growtype-analytics')
            );
        }
        
        return $actions;
    }

    /**
     * Register the user analytics admin page
     */
    public function register_analytics_page()
    {
        // Register the page under users.php
        add_submenu_page(
            'users.php', // Parent slug
            __('User Analytics', 'growtype-analytics'), // Page title
            __('User Analytics', 'growtype-analytics'), // Menu title
            'list_users', // Capability
            'user-analytics', // Menu slug
            array($this, 'render_analytics_page') // Callback
        );
    }

    /**
     * Hide the analytics menu item from sidebar using CSS
     */
    public function hide_analytics_menu_item()
    {
        echo '<style>
            #adminmenu a[href="users.php?page=user-analytics"] {
                display: none !important;
            }
        </style>';
    }

    /**
     * Render the user analytics page
     */
    public function render_analytics_page()
    {
        // Validate permissions and get user
        $user = $this->validate_and_get_user();
        
        // Enqueue styles and scripts
        $this->enqueue_analytics_assets();

        ?>
        <div class="wrap growtype-analytics-user-page">
            <h1><?php echo esc_html(sprintf(__('Analytics for %s', 'growtype-analytics'), $user->display_name)); ?></h1>
            
            <?php 
            $this->render_user_info_card($user);
            $this->render_module_notices();
            
            /**
             * Action hook to render analytics sections
             * 
             * @param int $user_id The user ID being analyzed
             * 
             * @since 1.0.0
             */
            do_action('growtype_analytics_user_analytics_sections', $user->ID);
            
            $this->render_back_button();
            ?>
        </div>
        <?php
    }

    /**
     * Validate permissions and get user data
     * 
     * @return WP_User The user object
     */
    private function validate_and_get_user()
    {
        if (!current_user_can('list_users')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
        
        if (!$user_id) {
            wp_die(__('Invalid user ID.', 'growtype-analytics'));
        }

        $user = get_userdata($user_id);
        
        if (!$user) {
            wp_die(__('User not found.', 'growtype-analytics'));
        }

        return $user;
    }

    /**
     * Render user information card
     * 
     * @param WP_User $user The user object
     */
    private function render_user_info_card($user)
    {
        ?>
        <div class="user-info-card">
            <h2><?php _e('User Information', 'growtype-analytics'); ?></h2>
            <table class="widefat">
                <tbody>
                    <tr>
                        <th><?php _e('Name', 'growtype-analytics'); ?></th>
                        <td><?php echo esc_html($user->display_name); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Email', 'growtype-analytics'); ?></th>
                        <td><?php echo esc_html($user->user_email); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Username', 'growtype-analytics'); ?></th>
                        <td><?php echo esc_html($user->user_login); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Registered', 'growtype-analytics'); ?></th>
                        <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($user->user_registered))); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render module availability notices
     */
    private function render_module_notices()
    {
        if (!$this->posthog && !$this->chat_analytics) {
            $this->render_no_modules_notice();
        } else {
            $this->render_partial_module_notices();
        }
    }

    /**
     * Render notice when no modules are available
     */
    private function render_no_modules_notice()
    {
        ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php _e('No analytics modules are currently available.', 'growtype-analytics'); ?></strong>
            </p>
            <ul style="list-style: disc; margin-left: 20px;">
                <?php if (!$this->is_posthog_configured()): ?>
                    <li>
                        <?php 
                        printf(
                            __('PostHog: Please configure your API credentials in %s.', 'growtype-analytics'), 
                            '<a href="' . admin_url('admin.php?page=growtype-analytics') . '">' . __('Settings', 'growtype-analytics') . '</a>'
                        ); 
                        ?>
                    </li>
                <?php endif; ?>
                <?php if (!$this->is_chat_available()): ?>
                    <li><?php _e('Chat Analytics: The Growtype Chat plugin is not active.', 'growtype-analytics'); ?></li>
                <?php endif; ?>
            </ul>
        </div>
        <?php
    }

    /**
     * Render notices for partially available modules
     */
    private function render_partial_module_notices()
    {
        if (!$this->posthog && $this->chat_analytics) {
            ?>
            <div class="notice notice-info">
                <p>
                    <?php 
                    printf(
                        __('PostHog analytics is not configured. %s to enable PostHog tracking.', 'growtype-analytics'), 
                        '<a href="' . admin_url('admin.php?page=growtype-analytics') . '">' . __('Configure settings', 'growtype-analytics') . '</a>'
                    ); 
                    ?>
                </p>
            </div>
            <?php
        }
        
        if ($this->posthog && !$this->chat_analytics) {
            ?>
            <div class="notice notice-info">
                <p><?php _e('Chat analytics is not available. Activate the Growtype Chat plugin to enable chat session tracking.', 'growtype-analytics'); ?></p>
            </div>
            <?php
        }
    }

    /**
     * Render back to users button
     */
    private function render_back_button()
    {
        ?>
        <p>
            <a href="<?php echo esc_url(admin_url('users.php')); ?>" class="button">
                <?php _e('← Back to Users', 'growtype-analytics'); ?>
            </a>
        </p>
        <?php
    }
    
    /**
     * Enqueue styles and scripts for analytics page
     */
    private function enqueue_analytics_assets()
    {
        // Enqueue user analytics CSS
        wp_enqueue_style(
            'growtype-analytics-user-page',
            plugin_dir_url(__FILE__) . 'assets/user-analytics.css',
            array(),
            filemtime(plugin_dir_path(__FILE__) . 'assets/user-analytics.css')
        );
    }
}
