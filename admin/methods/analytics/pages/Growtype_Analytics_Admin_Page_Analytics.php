<?php

/**
 * Top-level Analytics admin page
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/admin/methods/analytics/pages
 */

class Growtype_Analytics_Admin_Page_Analytics extends Growtype_Analytics_Admin_Base_Page
{
    /**
     * @var Growtype_Analytics_Admin_Decision_Renderer
     */
    private $decision_renderer;
    private $submenu_pages = array();

    public function __construct($controller, $decision_renderer, $register_hooks = true)
    {
        parent::__construct($controller);
        $this->decision_renderer = $decision_renderer;
        $this->load_submenu_pages();

        if ($register_hooks) {
            add_action('admin_menu', array($this, 'register_menu_pages'));
        }
    }

    public function register_menu_pages()
    {
        add_menu_page(
            __('Analytics', 'growtype-analytics'),
            __('Analytics', 'growtype-analytics'),
            'manage_options',
            'growtype-analytics',
            array($this, 'render_page'),
            'dashicons-chart-line',
            30
        );

        foreach ($this->get_submenu_pages() as $page) {
            add_submenu_page(
                'growtype-analytics',
                $page->get_page_title(),
                $page->get_menu_title(),
                'manage_options',
                $page->get_menu_slug(),
                array($page, 'render_page')
            );
        }
    }

    public function get_page_by_class($class_name)
    {
        foreach ($this->submenu_pages as $page) {
            if (get_class($page) === $class_name) {
                return $page;
            }
        }

        return null;
    }

    public function get_admin_page_hooks()
    {
        $hooks = array('toplevel_page_growtype-analytics');

        foreach ($this->get_submenu_pages() as $page) {
            $hooks[] = 'analytics_page_' . $page->get_menu_slug();
        }

        return $hooks;
    }

    public function render_page()
    {
        $this->render_page_header(__('Analytics', 'growtype-analytics'));
        ?>
            <div class="analytics-section">
                <h2><?php _e('Execution KPIs', 'growtype-analytics'); ?></h2>
                <p class="description"><?php _e('Payment success, new-user-to-buyer conversion, repurchase rate, and payment-failure segmentation.', 'growtype-analytics'); ?></p>
                <?php $this->decision_renderer->render_execution_kpis(); ?>
            </div>

            <div class="analytics-section">
                <h2><?php _e('Analytics Overview', 'growtype-analytics'); ?></h2>
                <p class="description"><?php _e('Core business snapshot across users, activation, conversion, revenue, and retention.', 'growtype-analytics'); ?></p>
                <?php $this->decision_renderer->render_analytics_snapshot(); ?>
            </div>

            <div class="analytics-section">
                <h2><?php _e('Recent Events', 'growtype-analytics'); ?></h2>
                <div class="analytics-recent-events">
                    <?php $this->render_recent_events(); ?>
                </div>
            </div>

            <div class="analytics-section">
                <h2><?php _e('Configuration Status', 'growtype-analytics'); ?></h2>
                <div class="analytics-config-status" style="margin-top: 1rem;">
                    <?php $this->render_config_status(); ?>
                </div>
            </div>
        <?php
        $this->render_page_footer();
    }

    private function render_recent_events()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'growtype_analytics_events';

        if (!$this->controller->table_exists($table_name)) {
            echo '<p>' . __('No events table found. Events tracking may not be configured.', 'growtype-analytics') . '</p>';
            return;
        }

        $events = $wpdb->get_results(
            "SELECT * FROM `{$table_name}` ORDER BY created_at DESC LIMIT 10",
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
            <a href="<?php echo esc_url(admin_url('options-general.php?page=growtype-analytics-settings')); ?>" class="button button-primary">
                <?php _e('Configure Analytics Settings', 'growtype-analytics'); ?>
            </a>
        </p>
        <?php
    }

    private function get_submenu_pages()
    {
        return $this->submenu_pages;
    }

    private function load_submenu_pages()
    {
        $dir = GROWTYPE_ANALYTICS_PATH . 'admin/methods/analytics/pages/';
        foreach (glob($dir . 'Growtype_Analytics_Admin_Page_*.php') as $filename) {
            $class_name = basename($filename, '.php');

            // Skip base class and special pages
            if (in_array($class_name, array('Growtype_Analytics_Admin_Base_Page', 'Growtype_Analytics_Admin_Page_Analytics', 'Growtype_Analytics_Admin_Page_Shared_Report'))) {
                continue;
            }

            if (class_exists($class_name)) {
                $this->submenu_pages[] = new $class_name($this->controller);
            }
        }
    }
}
