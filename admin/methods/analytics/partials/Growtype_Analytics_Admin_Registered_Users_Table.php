<?php

/**
 * Registered Users Table Renderer
 *
 * Renders the paginated registered-users admin table, including the bulk-action
 * bar, the "Offer Shown" popover, and all related inline JavaScript.
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/admin/methods/analytics/partials
 */

class Growtype_Analytics_Admin_Registered_Users_Table
{
    /**
     * @var Growtype_Analytics_Admin_Page
     */
    private $page;

    public function __construct($page)
    {
        $this->page = $page;
    }

    /**
     * Register the AJAX action for the offer-shown popover.
     * Called once by Growtype_Analytics_Admin_Page during bootstrap.
     */
    public static function register_hooks()
    {
        add_action('wp_ajax_growtype_analytics_user_offers', [self::class, 'ajax_user_offers']);
    }

    /**
     * AJAX handler: return offer_shown breakdown for a user.
     * Returns [{object_id, title, edit_url, cnt}, ...] sorted DESC by count.
     */
    public static function ajax_user_offers()
    {
        check_ajax_referer('growtype_analytics_bulk_actions', 'nonce');

        if (!current_user_can('list_users')) {
            wp_send_json_error('Forbidden', 403);
        }

        $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
        if (!$user_id) {
            wp_send_json_error('Invalid user ID');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'growtype_analytics_tracking';

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT object_id, metadata, COUNT(*) as cnt
             FROM `{$table}`
             WHERE user_id = %d AND event_type = 'offer_shown'
             GROUP BY object_id, metadata
             ORDER BY cnt DESC",
            $user_id
        ), ARRAY_A);

        // Aggregate by object_id — pick the most common title per product
        $offers = [];
        foreach ($rows as $row) {
            $oid = $row['object_id'];
            if (!isset($offers[$oid])) {
                $meta  = json_decode($row['metadata'] ?? '', true);
                $title = !empty($meta['name']) ? $meta['name'] : '';

                if (empty($title)) {
                    $post  = get_post((int)$oid);
                    $title = $post ? $post->post_title : $oid;
                }

                $offers[$oid] = [
                    'object_id' => $oid,
                    'title'     => $title,
                    'edit_url'  => admin_url('post.php?post=' . absint($oid) . '&action=edit'),
                    'cnt'       => 0,
                ];
            }
            $offers[$oid]['cnt'] += (int)$row['cnt'];
        }

        usort($offers, fn($a, $b) => $b['cnt'] - $a['cnt']);

        wp_send_json_success(array_values($offers));
    }

    public function render($date_from, $date_to)
    {
        $days           = $this->page->metrics->get_period_days_count($date_from . ' - ' . $date_to);
        $paged          = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page       = 20;
        $active_filters = Growtype_Analytics_Admin_User_Filters::active_from_request();

        $results     = $this->page->get_registered_users_list_data($days, $paged, $per_page, $active_filters);
        $users       = $results['items'];
        $total_items = $results['total_items'];

        $bulk_nonce = wp_create_nonce('growtype_analytics_bulk_actions');
        ?>
        <div class="analytics-section" style="margin-top:24px;">
            <h2><?php _e('Registered Users', 'growtype-analytics'); ?></h2>
            <p class="description">
                <?php printf(__('Total registered users found for this period: %s', 'growtype-analytics'), number_format_i18n($total_items)); ?>
                <?php
                $registry = Growtype_Analytics_Admin_User_Filters::registry();
                foreach ($active_filters as $f) {
                    $label = isset($registry[$f]) ? ($registry[$f]['icon'] . ' ' . $registry[$f]['label']) : ucwords(str_replace('_', ' ', $f));
                    echo '<span style="display:inline-block; background:#fff3cd; color:#856404; border-radius:4px; padding:1px 8px; font-size:0.85em; font-weight:600; margin-left:6px;">' . esc_html($label) . ' Active</span>';
                }
                ?>
            </p>

            <?php /* ── Bulk action bar ── */ ?>
            <div id="ga-bulk-bar" style="
                display:none;
                align-items:center; gap:10px; flex-wrap:wrap;
                background:#f0f6fc; border:1px solid #c8d7e8;
                border-radius:8px; padding:10px 16px; margin-bottom:12px;
            ">
                <span id="ga-bulk-count" style="font-weight:600; font-size:13px; color:#2271b1;"></span>

                <select id="ga-bulk-action-select" style="height:30px; line-height:1; padding:0 10px; border-radius:4px;">
                    <option value="none"><?php _e('Bulk Actions', 'growtype-analytics'); ?></option>
                    <option value="export_conversations"><?php _e('Export Conversations', 'growtype-analytics'); ?></option>
                </select>

                <button type="button" id="ga-bulk-submit-btn" class="button button-secondary">
                    <?php _e('Apply', 'growtype-analytics'); ?>
                </button>

                <span id="ga-bulk-status" style="font-size:12px; color:#646970;"></span>
            </div>

            <?php /* ── Table ── */ ?>
            <div class="analytics-recent-events">
                <table class="wp-list-table widefat striped">
                    <thead>
                    <tr>
                        <th style="width:32px;">
                            <input type="checkbox" id="ga-select-all" title="<?php esc_attr_e('Select all on this page', 'growtype-analytics'); ?>">
                        </th>
                        <th><?php _e('ID', 'growtype-analytics'); ?></th>
                        <th><?php _e('Email', 'growtype-analytics'); ?></th>
                        <th><?php _e('Registered', 'growtype-analytics'); ?></th>
                        <th><?php _e('Paid Orders', 'growtype-analytics'); ?></th>
                        <th><?php _e('Messages', 'growtype-analytics'); ?></th>
                        <th><?php _e('Regular Chat Visits', 'growtype-analytics'); ?></th>
                        <th><?php _e('Roleplay Chat Visits', 'growtype-analytics'); ?></th>
                        <th><?php _e('Roleplays Created', 'growtype-analytics'); ?></th>
                        <th><?php _e('Quiz Solved', 'growtype-analytics'); ?></th>
                        <th><?php _e('Offer Shown', 'growtype-analytics'); ?></th>
                        <th><?php _e('Checkout Page', 'growtype-analytics'); ?></th>
                        <th><?php _e('Credits Page', 'growtype-analytics'); ?></th>
                        <th><?php _e('Subscription Modal Shown', 'growtype-analytics'); ?></th>
                        <th><?php _e('Character Profile Visits', 'growtype-analytics'); ?></th>
                        <th><?php _e('Roleplay Profile Visits', 'growtype-analytics'); ?></th>
                        <th><?php _e('Chat Credits', 'growtype-analytics'); ?></th>
                        <th><?php _e('Actions', 'growtype-analytics'); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="18"><?php _e('No data available for this view yet.', 'growtype-analytics'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user):
                            $analytics_url = add_query_arg(
                                ['page' => 'user-analytics', 'user_id' => $user['ID']],
                                admin_url('users.php')
                            );
                            $profile_url = add_query_arg(
                                ['user_id' => $user['ID']],
                                admin_url('user-edit.php')
                            );
                            $lead_post = get_posts([
                                'post_type'      => 'gf_lead',
                                'title'          => $user['user_email'],
                                'posts_per_page' => 1,
                                'fields'         => 'ids',
                                'no_found_rows'  => true,
                            ]);
                            $lead_profile_url = !empty($lead_post)
                                ? admin_url('post.php?post=' . $lead_post[0] . '&action=edit')
                                : '';
                        ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="ga-user-checkbox" value="<?php echo esc_attr($user['ID']); ?>">
                                </td>
                                <td><?php echo esc_html($user['ID']); ?></td>
                                <td><?php echo esc_html($user['user_email']); ?></td>
                                <td><?php echo esc_html(wp_date(get_option('date_format') . ' H:i', strtotime($user['user_registered']))); ?></td>
                                <td><?php echo esc_html($user['paid_orders']); ?></td>
                                <td><?php echo esc_html($user['message_count']); ?></td>
                                <td><?php echo (int)($user['regular_chat_visits'] ?? 0); ?></td>
                                <td><?php echo (int)($user['roleplay_chat_visits'] ?? 0); ?></td>
                                <td><?php echo (int)($user['roleplay_visited'] ?? 0); ?></td>
                                <td><?php echo (int)($user['quizzes_solved'] ?? 0); ?></td>
                                <td>
                                    <?php $offer_count = (int)($user['payment_form_shown'] ?? 0); ?>
                                    <?php if ($offer_count > 0): ?>
                                        <span class="ga-offer-shown-trigger"
                                              data-user-id="<?php echo esc_attr($user['ID']); ?>"
                                              style="cursor:pointer; color:#2271b1; font-weight:600; text-decoration:underline; white-space:nowrap;">
                                            <?php echo $offer_count; ?>
                                        </span>
                                    <?php else: ?>
                                        0
                                    <?php endif; ?>
                                </td>
                                <td><?php echo (int)($user['checkout_visited'] ?? 0); ?></td>
                                <td><?php echo (int)($user['credits_page_visited'] ?? 0); ?></td>
                                <td><?php echo (int)($user['subscription_modal_shown'] ?? 0); ?></td>
                                <td><?php echo (int)($user['character_profile_visits'] ?? 0); ?></td>
                                <td><?php echo (int)($user['roleplay_profile_visits'] ?? 0); ?></td>
                                <td><?php echo (int)($user['chat_credits_amount'] ?? 0); ?></td>
                                <td>
                                    <a href="<?php echo esc_url($analytics_url); ?>" class="button button-small" target="_blank"><?php _e('Analytics', 'growtype-analytics'); ?></a>
                                    <a href="<?php echo esc_url($profile_url); ?>" class="button button-small" target="_blank"><?php _e('User', 'growtype-analytics'); ?></a>
                                    <?php if (!empty($lead_profile_url)): ?>
                                    <a href="<?php echo esc_url($lead_profile_url); ?>" class="button button-small" target="_blank"><?php _e('Lead', 'growtype-analytics'); ?></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>

                <?php /* Pagination */ ?>
                <?php
                $total_pages = ceil($total_items / $per_page);
                if ($total_pages > 1):
                    $base_url = $_SERVER['REQUEST_URI'] ?? admin_url('admin.php');
                    $base = add_query_arg('paged', '%#%', remove_query_arg(['paged', 'refresh'], $base_url));
                ?>
                    <div class="tablenav bottom">
                        <div class="tablenav-pages">
                            <span class="displaying-num"><?php printf(_n('%s item', '%s items', $total_items, 'growtype-analytics'), number_format_i18n($total_items)); ?></span>
                            <span class="pagination-links">
                                <?php echo paginate_links([
                                    'base'      => $base,
                                    'format'    => '',
                                    'prev_text' => __('&laquo;'),
                                    'next_text' => __('&raquo;'),
                                    'total'     => $total_pages,
                                    'current'   => $paged,
                                ]); ?>
                            </span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php /* ── Offer Shown popover ── */ ?>
        <div id="ga-offer-popover" style="
            display:none; position:fixed; z-index:99999;
            background:#fff; border:1px solid #c8d7e8; border-radius:8px;
            box-shadow:0 4px 20px rgba(0,0,0,.15); padding:14px 18px;
            min-width:220px; max-width:320px; font-size:13px;
        ">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                <strong style="color:#1d2327;"><?php _e('Offers Shown', 'growtype-analytics'); ?></strong>
                <span id="ga-offer-popover-close" style="cursor:pointer; color:#646970; font-size:16px; line-height:1;">&times;</span>
            </div>
            <div id="ga-offer-popover-body" style="color:#3c434a;"></div>
        </div>

        <script>
        (function($) {
            const nonce   = '<?php echo esc_js($bulk_nonce); ?>';
            const ajaxUrl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';

            // ── Offer Shown popover ──────────────────────────────────────────
            const $popover = $('#ga-offer-popover');

            $(document).on('click', '.ga-offer-shown-trigger', function(e) {
                e.stopPropagation();
                const userId = $(this).data('user-id');

                $popover.find('#ga-offer-popover-body').html('<em style="color:#646970"><?php echo esc_js(__('Loading…', 'growtype-analytics')); ?></em>');

                const rect = this.getBoundingClientRect();
                $popover.css({
                    top:  Math.min(rect.bottom + window.scrollY + 6, document.documentElement.scrollHeight - 260) + 'px',
                    left: Math.min(rect.left  + window.scrollX,     document.documentElement.scrollWidth  - 340) + 'px',
                }).show();

                $.post(ajaxUrl, {
                    action : 'growtype_analytics_user_offers',
                    nonce  : nonce,
                    user_id: userId,
                }, function(response) {
                    if (!response.success || !response.data.length) {
                        $popover.find('#ga-offer-popover-body').html('<em style="color:#646970"><?php echo esc_js(__('No offer data found.', 'growtype-analytics')); ?></em>');
                        return;
                    }
                    let html = '<table style="width:100%; border-collapse:collapse;">';
                    html += '<tr style="border-bottom:1px solid #f0f0f1;"><th style="text-align:left; padding:4px 6px; color:#646970; font-size:11px; text-transform:uppercase;"><?php echo esc_js(__('Offer', 'growtype-analytics')); ?></th><th style="text-align:right; padding:4px 6px; color:#646970; font-size:11px; text-transform:uppercase;"><?php echo esc_js(__('Shown', 'growtype-analytics')); ?></th></tr>';
                    $.each(response.data, function(i, row) {
                        const label     = row.title    || row.object_id || '<?php echo esc_js(__('(unknown)', 'growtype-analytics')); ?>';
                        const editUrl   = row.edit_url || '#';
                        const safeLabel = $('<span>').text(label).html();
                        html += '<tr style="border-bottom:1px solid #f9f9f9;">'
                              + '<td style="padding:5px 6px;"><a href="' + editUrl + '" target="_blank" style="color:#2271b1; text-decoration:none;" onmouseover="this.style.textDecoration=\'underline\'" onmouseout="this.style.textDecoration=\'none\'">' + safeLabel + '</a></td>'
                              + '<td style="padding:5px 6px; text-align:right; font-weight:600; color:#1d2327;">' + row.cnt + '</td>'
                              + '</tr>';
                    });
                    html += '</table>';
                    $popover.find('#ga-offer-popover-body').html(html);
                }).fail(function() {
                    $popover.find('#ga-offer-popover-body').html('<em style="color:#c00;"><?php echo esc_js(__('Request failed.', 'growtype-analytics')); ?></em>');
                });
            });

            $('#ga-offer-popover-close').on('click', function() { $popover.hide(); });
            $(document).on('click', function(e) {
                if (!$(e.target).closest('#ga-offer-popover, .ga-offer-shown-trigger').length) {
                    $popover.hide();
                }
            });

            // ── Checkbox logic ──────────────────────────────────────────────
            function getChecked() {
                return $('.ga-user-checkbox:checked').map(function() { return $(this).val(); }).get();
            }

            function updateBulkBar() {
                const ids = getChecked();
                if (ids.length > 0) {
                    $('#ga-bulk-bar').css('display', 'flex');
                    $('#ga-bulk-count').text(ids.length + ' user' + (ids.length > 1 ? 's' : '') + ' selected');
                } else {
                    $('#ga-bulk-bar').hide();
                }
            }

            $('#ga-select-all').on('change', function() {
                $('.ga-user-checkbox').prop('checked', this.checked);
                updateBulkBar();
            });

            $(document).on('change', '.ga-user-checkbox', function() {
                if (!this.checked) { $('#ga-select-all').prop('checked', false); }
                updateBulkBar();
            });

            // ── Bulk Actions ────────────────────────────────────────────────
            $('#ga-bulk-submit-btn').on('click', function() {
                const action = $('#ga-bulk-action-select').val();
                const ids = getChecked();

                if (action === 'none') {
                    alert('Please select an action.');
                    return;
                }

                if (!ids.length) {
                    alert('Please select at least one user.');
                    return;
                }

                if (action === 'export_conversations') {
                    executeExportConversations(ids);
                }
            });

            function executeExportConversations(ids) {
                const $btn    = $('#ga-bulk-submit-btn');
                const $status = $('#ga-bulk-status');

                $btn.prop('disabled', true).text('Working…');
                $status.text('Fetching data from server…');

                $.post(ajaxUrl, {
                    action   : 'growtype_analytics_bulk_export_conversations',
                    nonce    : nonce,
                    user_ids : ids,
                }, function(response) {
                    $btn.prop('disabled', false).text('Apply');

                    if (!response.success) {
                        $status.text('Error: ' + (response.data || 'Unknown error'));
                        return;
                    }

                    const data   = response.data;
                    const blob   = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
                    const url    = URL.createObjectURL(blob);
                    const anchor = document.createElement('a');
                    const ts     = new Date().toISOString().slice(0, 10);

                    anchor.href     = url;
                    anchor.download = 'conversations-export-' + ts + '.json';
                    document.body.appendChild(anchor);
                    anchor.click();
                    document.body.removeChild(anchor);
                    URL.revokeObjectURL(url);

                    $status.text(
                        '✓ Exported ' + data.user_count + ' user(s) · ' +
                        data.session_count + ' session(s) · ' + data.exported_at
                    );
                }).fail(function() {
                    $btn.prop('disabled', false).text('Apply');
                    $status.text('Request failed. Check permissions or try again.');
                });
            }
        })(jQuery);
        </script>
        <?php
    }
}
