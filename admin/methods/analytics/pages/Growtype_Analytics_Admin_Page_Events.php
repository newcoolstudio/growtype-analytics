<?php

class Growtype_Analytics_Admin_Page_Events extends Growtype_Analytics_Admin_Base_Page
{
    public function get_page_title()
    {
        return __('Live Events', 'growtype-analytics');
    }

    public function get_menu_title()
    {
        return __('Live Events', 'growtype-analytics');
    }

    public function get_menu_slug()
    {
        return 'growtype-analytics-events';
    }

    public function render_page()
    {
        $this->render_page_header(__('Live Tracking Events', 'growtype-analytics'));

        // Validate date format — strip anything that isn't a valid YYYY-MM-DD date
        $date_from_raw = isset($_GET['date_from']) ? sanitize_text_field(wp_unslash($_GET['date_from'])) : '';
        $date_to_raw   = isset($_GET['date_to'])   ? sanitize_text_field(wp_unslash($_GET['date_to']))   : '';
        $date_from = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from_raw) && strtotime($date_from_raw)) ? $date_from_raw : date('Y-m-d', strtotime('-30 days'));
        $date_to   = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to_raw)   && strtotime($date_to_raw))   ? $date_to_raw   : date('Y-m-d');

        $snapshot_settings = $this->controller->get_snapshot_settings();
        $objective = $snapshot_settings['growth_objective'] ?? '10x';
        $marketing_spend = $snapshot_settings['marketing_spend'] ?? 0;

        $this->controller->decision_renderer->render_dashboard_filters($date_from, $date_to, $objective, $marketing_spend, $this->get_menu_slug());

        // User email filter — resolved to user_id for the DB query
        // If the email is supplied but no WP user exists, use -1 so the query returns 0 rows
        // (user_id = 0 means "no filter" in get_paginated_events, do not fall back to that)
        $filter_email = isset($_GET['user_email']) ? sanitize_email(wp_unslash($_GET['user_email'])) : '';
        $filter_user_id = 0;  // 0 = no filter
        $filter_user = null;
        if (!empty($filter_email)) {
            $filter_user = get_user_by('email', $filter_email);
            $filter_user_id = $filter_user ? (int)$filter_user->ID : -1; // -1 = email given but unknown
        }

        $paged = isset($_GET['paged']) ? max(1, (int)$_GET['paged']) : 1;
        $per_page = Growtype_Analytics_Admin_Table_Renderer::DEFAULT_PER_PAGE;
        $offset = ($paged - 1) * $per_page;

        $active_user_filters = isset($_GET['user_filters']) && is_array($_GET['user_filters']) ? $_GET['user_filters'] : [];
        $group_by_user = in_array('group_by_user', $active_user_filters, true);
        $search_query = isset($_GET['search_events']) ? sanitize_text_field(wp_unslash($_GET['search_events'])) : '';

        $paginated_data = $this->get_paginated_events($date_from, $date_to, $per_page, $offset, $filter_user_id, $group_by_user, $active_user_filters, $search_query);
        
        $total_events = $paginated_data['total'];
        $events = $paginated_data['events'];
        ?>

        <?php if (!empty($filter_email)): ?>
        <div class="notice notice-info" style="margin:10px 0; display:flex; align-items:center; justify-content:space-between;">
            <p style="margin:0;">
                <?php if ($filter_user): ?>
                    <?php printf(__('Showing events for: <strong>%s</strong> (User #%d)', 'growtype-analytics'), esc_html($filter_user->user_email), $filter_user_id); ?>
                <?php else: ?>
                    <?php printf(__('No user found with email: <strong>%s</strong>', 'growtype-analytics'), esc_html($filter_email)); ?>
                <?php endif; ?>
            </p>
            <a href="<?php echo esc_url(remove_query_arg('user_email')); ?>" class="button button-small" style="margin-left:16px; flex-shrink:0;"><?php _e('Clear filter', 'growtype-analytics'); ?></a>
        </div>
        <?php endif; ?>

        <?php
        $extra_filters = [
            'group_by_user' => [
                'label' => __('Group By User', 'growtype-analytics'),
                'icon'  => '👥',
                'color' => '#8a2424'
            ]
        ];

        // If filtering by email, we need to pass it into the search/url somehow. 
        // Currently render_filter_pills doesn't pass 'user_email'. We can inject it using a hidden field.
        // Wait, render_filter_pills renders its own form. If we need user_email to be persisted, 
        // we should either modify render_filter_pills to include it, or not worry about it.
        // Actually, render_filter_pills doesn't pass custom GET parameters. 
        // Let's modify $_GET briefly so it gets caught? No, render_filter_pills hardcodes date_from, date_to, page, refresh.
        // I will just call it as is for now, if user loses filter_email when toggling, it's fine or I can just echo the hidden input manually via JS or extending the function.
        // Actually, $this->controller->decision_renderer->render_filter_pills DOES NOT pass user_email.
        
        $this->controller->decision_renderer->render_filter_pills($date_from, $date_to, $this->get_menu_slug(), $extra_filters);
        ?>

        <div class="analytics-section">
            <?php
            $desc = sprintf(__('Showing %d events out of %s total in selected period.', 'growtype-analytics'), count($events), '<strong>' . number_format($total_events) . '</strong>');
            $this->controller->decision_renderer->render_section_header(
                __('Recent Tracking Events', 'growtype-analytics'),
                $desc,
                [],
                'search_events',
                $search_query,
                __('Search event type, metadata, or email...', 'growtype-analytics')
            );
            ?>

            <?php
            $rows = array();
            foreach ($events as $event) {
                // Object cell
                $object_html = $event['object_type'] . ': ' . $event['object_id'];
                if ($event['object_type'] === 'product' && function_exists('wc_get_product')) {
                    $product = wc_get_product($event['object_id']);
                    if ($product) {
                        $object_html = '<strong>' . esc_html($product->get_name()) . '</strong> <br>';
                        $object_html .= '<small style="opacity: 0.6;">(ID: ' . esc_html($event['object_id']) . ')</small>';
                    }
                }

                // User cell
                $user_html = '<span style="opacity: 0.5;">' . __('Guest', 'growtype-analytics') . '</span>';
                if ($event['user_id'] > 0) {
                    $user = get_user_by('id', $event['user_id']);
                    if ($user) {
                        $user_html = '<a href="' . get_edit_user_link($user->ID) . '"><strong>' . esc_html($user->user_email) . '</strong></a>';
                    } else {
                        $user_html = 'User #' . esc_html($event['user_id']);
                    }
                }

                // Metadata cell
                $metadata_html = '-';
                if (!empty($event['metadata'])) {
                    $meta = json_decode($event['metadata'], true);
                    if ($meta) {
                        $metadata_html = '<pre style="margin: 0; font-size: 10px; line-height: 1.2; background: #f9f9f9; padding: 5px; border-radius: 4px; border: 1px solid #eee;">' . esc_html(json_encode($meta, JSON_PRETTY_PRINT)) . '</pre>';
                    } else {
                        $metadata_html = esc_html($event['metadata']);
                    }
                }

                $rows[] = array(
                    'timestamp'  => esc_html($event['created_at']),
                    'event_type' => '<span class="status-badge" style="background: #f0f0f1; padding: 4px 8px; border-radius: 4px; font-weight: 600; font-size: 11px; text-transform: uppercase;">' . esc_html($event['event_type']) . '</span>',
                    'object'     => $object_html,
                    'user'       => $user_html,
                    'metadata'   => $metadata_html
                );
            }

            $this->controller->table_renderer->render(
                array(__('Timestamp', 'growtype-analytics'), __('Event Type', 'growtype-analytics'), __('Object', 'growtype-analytics'), __('User', 'growtype-analytics'), __('Metadata', 'growtype-analytics')),
                $rows,
                (int)$total_events,
                $per_page,
                $paged
            );
            ?>
        </div>
        <?php
        $this->render_page_footer();
    }

    public function get_paginated_events($date_from, $date_to, $per_page, $offset, $user_id = 0, $group_by_user = false, $active_user_filters = [], $search_query = '')
    {
        global $wpdb;
        $table_name = $wpdb->prefix . Growtype_Analytics_Database::TABLE_NAME;

        $where_clauses = ["created_at >= %s", "created_at <= %s"];
        $where_args    = [$date_from . ' 00:00:00', $date_to . ' 23:59:59'];

        if ($user_id !== 0) {
            $where_clauses[] = "user_id = %d";
            $where_args[] = $user_id;
        }

        // Apply active user filters using EXISTS subqueries on usermeta
        if (!empty($active_user_filters) && is_array($active_user_filters)) {
            // Require user_id > 0 to have user meta
            $where_clauses[] = "user_id > 0";

            if (in_array('paid_orders_only', $active_user_filters, true)) {
                $where_clauses[] = "EXISTS (SELECT 1 FROM {$wpdb->usermeta} WHERE user_id = {$table_name}.user_id AND meta_key = 'growtype_analytics_paid_orders' AND meta_value > 0)";
            }
            if (in_array('zero_credits', $active_user_filters, true)) {
                $where_clauses[] = "EXISTS (SELECT 1 FROM {$wpdb->usermeta} WHERE user_id = {$table_name}.user_id AND meta_key = 'growtype_chat_credits' AND (meta_value = '0' OR meta_value = ''))";
            }
            if (in_array('has_characters', $active_user_filters, true)) {
                // Check if they authored at least one post of type 'character'
                $where_clauses[] = "EXISTS (SELECT 1 FROM {$wpdb->posts} WHERE post_author = {$table_name}.user_id AND post_type = 'character' AND post_status IN ('publish', 'draft', 'private'))";
            }
        }

        if (!empty($search_query)) {
            $search_like = '%' . $wpdb->esc_like($search_query) . '%';
            $where_clauses[] = "({$table_name}.metadata LIKE %s OR {$table_name}.event_type LIKE %s OR EXISTS (SELECT 1 FROM {$wpdb->users} u WHERE u.ID = {$table_name}.user_id AND u.user_email LIKE %s))";
            $where_args[] = $search_like;
            $where_args[] = $search_like;
            $where_args[] = $search_like;
        }

        $where_sql = implode(' AND ', $where_clauses);

        $total_events = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE $where_sql",
            $where_args
        ));

        $order_by = $group_by_user ? "user_id DESC, created_at DESC" : "created_at DESC";

        $events = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE $where_sql
             ORDER BY $order_by LIMIT %d OFFSET %d",
            array_merge($where_args, [$per_page, $offset])
        ), ARRAY_A);

        return array(
            'total' => $total_events,
            'events' => $events
        );
    }
}
