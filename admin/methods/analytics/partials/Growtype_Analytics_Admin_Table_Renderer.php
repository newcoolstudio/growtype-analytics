<?php

/**
 * Analytics paginated table renderer
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/admin/methods/analytics/partials
 */

class Growtype_Analytics_Admin_Table_Renderer
{
    const DEFAULT_PER_PAGE = 50;

    /**
     * @var Growtype_Analytics_Admin_Page
     */
    private $page;

    public function __construct($page)
    {
        $this->page = $page;
    }

    /**
     * Render a paginated table using WordPress standard styles
     *
     * @param array $headers Column labels
     * @param array $rows Data rows
     * @param int|null $total_items Total items in the entire dataset
     * @param int $per_page Items per page
     * @param int $current_page Current page number
     * @param string $base_url Optional base URL for pagination links
     */
    public function render($headers, $rows, $total_items = null, $per_page = self::DEFAULT_PER_PAGE, $current_page = 1, $base_url = '')
    {
        if ($total_items === null) {
            $total_items = count($rows);
        }
        
        $total_pages = ceil($total_items / $per_page);
        ?>
        <div class="analytics-recent-events">
            <table class="wp-list-table widefat striped">
                <thead>
                <tr>
                    <?php foreach ($headers as $header): ?>
                        <th><?php echo wp_kses_post($header); ?></th>
                    <?php endforeach; ?>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="<?php echo esc_attr(count($headers)); ?>"><?php _e('No data available for this view yet.', 'growtype-analytics'); ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php foreach ($row as $key => $cell): ?>
                                <?php if ($key === 'total_items_count') continue; ?>
                                <td><?php echo wp_kses_post($cell); ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <span class="displaying-num"><?php printf(_n('%s item', '%s items', $total_items, 'growtype-analytics'), number_format_i18n($total_items)); ?></span>
                        <span class="pagination-links">
                            <?php
                            if (empty($base_url)) {
                                if (wp_doing_ajax() && !empty($_SERVER['HTTP_REFERER'])) {
                                    $base_url = $_SERVER['HTTP_REFERER'];
                                } else {
                                    $base_url = $_SERVER['REQUEST_URI'] ?? admin_url('admin.php');
                                }
                            }

                            // Ensure 'paged' and 'refresh' are handled correctly in the base URL
                            $base = add_query_arg('paged', '%#%', remove_query_arg(array('paged', 'refresh'), $base_url));

                            echo paginate_links(array(
                                'base' => $base,
                                'format' => '',
                                'prev_text' => __('&laquo;'),
                                'next_text' => __('&raquo;'),
                                'total' => $total_pages,
                                'current' => $current_page,
                            ));
                            ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
